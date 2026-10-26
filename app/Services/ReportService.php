<?php

namespace App\Services;

use App\Enums\ExitType;
use App\Models\Product;
use App\Models\StockAlert;
use App\Models\StockBatch;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\StockExitLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agrégats de lecture seule. Aucun de ces calculs n'écrit en base : ils
 * alimentent le tableau de bord, le résumé quotidien et les exports.
 */
class ReportService
{
    /**
     * Chiffres clés du tableau de bord.
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        return [
            'valeur_stock'        => $this->stockValue(),
            'produits_actifs'     => Product::query()->active()->count(),
            'ruptures'            => Product::query()->active()->outOfStock()->count(),
            'stock_bas'           => Product::query()->active()->lowStock()->count(),
            'lots_perimes'        => StockBatch::query()->available()->expired()->count(),
            'lots_bientot_perimes' => StockBatch::query()
                ->available()
                ->expiringWithin((int) config('naneu.stock.expiry_alert_days', 30))
                ->count(),
            'alertes_ouvertes'    => StockAlert::query()->open()->count(),
            'alertes_critiques'   => StockAlert::query()->open()->critical()->count(),
            'ventes_jour'         => $this->salesTotal($today, $today),
            'ventes_hier'         => $this->salesTotal(now()->subDay()->toDateString(), now()->subDay()->toDateString()),
            'marge_jour'          => $this->marginTotal($today, $today),
            'tva_jour'            => $this->vatTotal($today, $today),
            'ventes_mois'         => $this->salesTotal($monthStart, $today),
            'marge_mois'          => $this->marginTotal($monthStart, $today),
            'tva_mois'            => $this->vatTotal($monthStart, $today),
            'pertes_mois'         => $this->shrinkageTotal($monthStart, $today),
            'entrees_mois'        => (int) StockEntry::query()
                ->validated()
                ->between($monthStart, $today)
                ->sum('total_cost'),
        ];
    }

    /**
     * Valorisation du stock au coût d'achat réel, lot par lot.
     * Une seule requête agrégée : la boucle PHP serait intenable sur
     * plusieurs milliers de lots.
     */
    public function stockValue(): int
    {
        return (int) round(
            (float) StockBatch::query()
                ->where('quantity_remaining', '>', 0)
                ->sum(DB::raw('quantity_remaining * purchase_price'))
        );
    }

    public function salesTotal(string $from, string $to): int
    {
        return (int) StockExit::query()->validated()->sales()->between($from, $to)->sum('total_sale');
    }

    /** TVA collectée : contenue dans les prix TTC encaissés. */
    public function vatTotal(string $from, string $to): int
    {
        return (int) StockExit::query()->validated()->sales()->between($from, $to)->sum('total_vat');
    }

    public function marginTotal(string $from, string $to): int
    {
        return (int) StockExit::query()->validated()->sales()->between($from, $to)->sum('total_margin');
    }

    public function shrinkageTotal(string $from, string $to): int
    {
        return (int) StockExit::query()->validated()->shrinkage()->between($from, $to)->sum('total_cost');
    }

    /**
     * Série journalière pour le graphique du tableau de bord.
     *
     * @return Collection<int, array{date: string, libelle: string, ventes: int, marge: int}>
     */
    public function salesSeries(int $days = 14): Collection
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = StockExit::query()
            ->validated()
            ->sales()
            ->where('exit_date', '>=', $from->toDateString())
            ->selectRaw('exit_date, SUM(total_sale) as ventes')
            ->groupBy('exit_date')
            ->pluck('ventes', 'exit_date');

        $margins = StockExit::query()
            ->validated()
            ->sales()
            ->where('exit_date', '>=', $from->toDateString())
            ->selectRaw('exit_date, SUM(total_margin) as marge')
            ->groupBy('exit_date')
            ->pluck('marge', 'exit_date');

        // Détail par produit pour l'infobulle : ce qui a fait la journée.
        $produits = $this->productsByDay($from->toDateString(), now()->toDateString());

        return collect(range(0, $days - 1))->map(function (int $offset) use ($from, $rows, $margins, $produits) {
            $date = $from->copy()->addDays($offset);
            $key  = $date->toDateString();
            $jour = $produits->get($key, collect());

            return [
                'date'        => $key,
                'libelle'     => $date->translatedFormat('d/m'),
                'jour'        => $date->translatedFormat('l j F'),
                'ventes'      => (int) ($rows[$key] ?? 0),
                'marge'       => (int) ($margins[$key] ?? 0),
                'produits'    => $jour->take(5)->values()->all(),
                'nb_produits' => $jour->count(),
            ];
        });
    }

    /**
     * Produits vendus chaque jour, du plus gros chiffre au plus petit.
     *
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function productsByDay(string $from, string $to): Collection
    {
        return StockExitLine::query()
            ->join('stock_exits', 'stock_exits.id', '=', 'stock_exit_lines.stock_exit_id')
            ->join('products', 'products.id', '=', 'stock_exit_lines.product_id')
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->where('stock_exits.type', ExitType::Vente->value)
            ->where('stock_exits.status', 'valide')
            ->whereBetween('stock_exits.exit_date', [$from, $to])
            ->groupBy('stock_exits.exit_date', 'products.id', 'products.name', 'units.symbol')
            ->orderByDesc(DB::raw('SUM(stock_exit_lines.line_total)'))
            ->get([
                'stock_exits.exit_date',
                'products.name',
                'units.symbol',
                DB::raw('SUM(stock_exit_lines.quantity) as quantite'),
                DB::raw('SUM(stock_exit_lines.line_total) as chiffre'),
            ])
            ->groupBy(fn ($row) => \Illuminate\Support\Carbon::parse($row->exit_date)->toDateString())
            ->map(fn ($rows) => $rows->map(fn ($row) => [
                'nom'      => $row->name,
                'quantite' => rtrim(rtrim(\App\Support\Money::number((float) $row->quantite, 3), '0'), ',.').($row->symbol ? ' '.$row->symbol : ''),
                'chiffre'  => (int) $row->chiffre,
            ]));
    }

    /**
     * Meilleures ventes sur une période, par quantité écoulée.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topProducts(string $from, string $to, int $limit = 10, string $tri = 'quantite'): Collection
    {
        return StockExitLine::query()
            ->join('stock_exits', 'stock_exits.id', '=', 'stock_exit_lines.stock_exit_id')
            ->join('products', 'products.id', '=', 'stock_exit_lines.product_id')
            ->where('stock_exits.type', ExitType::Vente->value)
            ->where('stock_exits.status', 'valide')
            ->whereBetween('stock_exits.exit_date', [$from, $to])
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc(DB::raw($tri === 'chiffre' ? 'SUM(stock_exit_lines.line_total)' : 'SUM(stock_exit_lines.quantity)'))
            ->limit($limit)
            ->get([
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(stock_exit_lines.quantity) as quantite'),
                DB::raw('SUM(stock_exit_lines.line_total) as chiffre'),
                DB::raw('SUM(stock_exit_lines.margin) as marge'),
            ])
            ->map(fn ($row) => [
                'id'       => $row->id,
                'nom'      => $row->name,
                'sku'      => $row->sku,
                'quantite' => (float) $row->quantite,
                'chiffre'  => (int) $row->chiffre,
                'marge'    => (int) $row->marge,
            ]);
    }

    /**
     * Données du résumé quotidien.
     *
     * @return array<string, mixed>
     */
    public function dailyDigest(string $date): array
    {
        $day = Carbon::parse($date)->toDateString();

        return [
            'ventes'          => $this->salesTotal($day, $day),
            'marge'           => $this->marginTotal($day, $day),
            'nombre_ventes'   => StockExit::query()->validated()->sales()->between($day, $day)->count(),
            'entrees'         => (int) StockEntry::query()->validated()->between($day, $day)->sum('total_cost'),
            'pertes'          => $this->shrinkageTotal($day, $day),
            'valeur_stock'    => $this->stockValue(),
            'ruptures'        => Product::query()->active()->outOfStock()->count(),
            'stock_bas'       => Product::query()->active()->lowStock()->count(),
            'lots_perimes'    => StockBatch::query()->available()->expired()->count(),
            'alertes'         => StockAlert::query()->open()->count(),
            'top_produits'    => $this->topProducts($day, $day, 5)->all(),
        ];
    }

    /**
     * Produits à recommander, triés par urgence.
     *
     * @return Collection<int, Product>
     */
    public function reorderList(): Collection
    {
        return Product::query()
            ->active()
            ->needsReorder()
            ->with(['category', 'unit', 'defaultSupplier'])
            ->orderBy('quantity_on_hand')
            ->get();
    }
}
