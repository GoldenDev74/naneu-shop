<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Transforme la liste des lots en décisions : ce qui doit quitter le rayon,
 * ce qui ne se vendra pas à temps au rythme actuel (et combien cela coûtera),
 * ce qui partira tout seul, et les produits frais qu'on ne sait pas suivre
 * faute de date.
 *
 * Le rythme de vente est la moyenne journalière des ventes validées sur les
 * `WINDOW` derniers jours. La simulation suit l'ordre de consommation : les
 * lots qui expirent d'abord absorbent d'abord les ventes.
 */
class ExpiryAdvisor
{
    public const WINDOW = 28;

    /**
     * @return array{
     *   perimes: Collection, a_risque: Collection, a_temps: Collection,
     *   sans_date: Collection, pertes: Collection, horizon: int
     * }
     */
    public function analyse(): array
    {
        $horizon = (int) config('naneu.stock.expiry_alert_days', 30);
        $today = today();

        $lots = StockBatch::query()
            ->available()
            ->with(['product:id,name,sku,unit_id,image_path,shelf_life_days,is_perishable,sale_price', 'product.unit:id,symbol,allows_decimal', 'supplier:id,name'])
            ->whereHas('product', fn ($q) => $q->where('is_perishable', true))
            ->orderByRaw('expiry_date IS NULL, expiry_date')
            ->orderBy('received_at')
            ->get();

        $rythmes = $this->dailySales($lots->pluck('product_id')->unique()->all());

        $perimes = collect();
        $analyses = collect();
        $sansDate = collect();

        foreach ($lots->groupBy('product_id') as $productId => $lotsProduit) {
            $rythme = $rythmes[$productId] ?? 0.0;
            $vendu = 0.0; // ventes déjà « attribuées » aux lots précédents

            foreach ($lotsProduit as $lot) {
                if ($lot->expiry_date === null) {
                    $sansDate->push([
                        'lot'      => $lot,
                        'age'      => (int) $lot->received_at->diffInDays($today),
                        'rythme'   => $rythme,
                    ]);

                    continue;
                }

                $jours = $lot->daysUntilExpiry();
                $quantite = (float) $lot->quantity_remaining;

                if ($jours < 0) {
                    $perimes->push(['lot' => $lot, 'jours' => $jours, 'valeur' => $lot->remainingValue()]);

                    continue;
                }

                if ($jours > $horizon) {
                    continue;
                }

                // Ventes possibles d'ici la veille de la date limite (jour J inclus).
                $capacite = $rythme * ($jours + 1);
                $ecoule = min($quantite, max(0.0, $capacite - $vendu));
                $vendu += $ecoule;
                $reste = max(0.0, $quantite - $ecoule);

                $analyses->push([
                    'lot'          => $lot,
                    'jours'        => $jours,
                    'rythme'       => $rythme,
                    'invendu'      => round($reste, 3),
                    'valeur_risque' => (int) round($reste * $lot->purchase_price),
                    'jours_pour_ecouler' => $rythme > 0 ? (int) ceil(($vendu) / $rythme) : null,
                ]);
            }
        }

        return [
            'perimes'   => $perimes,
            'a_risque'  => $analyses->filter(fn ($a) => $a['invendu'] > 1e-6)->sortBy([['jours', 'asc'], ['valeur_risque', 'desc']])->values(),
            'a_temps'   => $analyses->filter(fn ($a) => $a['invendu'] <= 1e-6)->values(),
            'sans_date' => $sansDate->sortByDesc('age')->values(),
            'pertes'    => $this->recentLosses(),
            'horizon'   => $horizon,
        ];
    }

    /**
     * Ventes moyennes par jour, par produit, sur la fenêtre glissante.
     *
     * @param  array<int, int>  $productIds
     * @return array<int, float>
     */
    public function dailySales(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return DB::table('stock_exit_lines as l')
            ->join('stock_exits as e', 'e.id', '=', 'l.stock_exit_id')
            ->where('e.type', 'vente')
            ->where('e.status', 'valide')
            ->whereDate('e.exit_date', '>', today()->subDays(self::WINDOW))
            ->whereIn('l.product_id', $productIds)
            ->groupBy('l.product_id')
            ->selectRaw('l.product_id, SUM(l.quantity) AS total')
            ->pluck('total', 'product_id')
            ->map(fn ($total) => (float) $total / self::WINDOW)
            ->all();
    }

    /**
     * Produits jetés pour péremption sur la même fenêtre : ce sont eux
     * qu'il faut commander en plus petite quantité.
     *
     * @return Collection<int, object>
     */
    public function recentLosses(int $limit = 6): Collection
    {
        return DB::table('stock_exit_lines as l')
            ->join('stock_exits as e', 'e.id', '=', 'l.stock_exit_id')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->where('e.type', 'peremption')
            ->where('e.status', 'valide')
            ->whereDate('e.exit_date', '>', today()->subDays(self::WINDOW))
            ->groupBy('p.id', 'p.name', 'u.symbol')
            ->selectRaw('p.id, p.name, u.symbol AS unite, SUM(l.quantity) AS quantite, SUM(l.cost_total) AS valeur, COUNT(DISTINCT e.id) AS fois')
            ->orderByDesc('valeur')
            ->limit($limit)
            ->get();
    }
}
