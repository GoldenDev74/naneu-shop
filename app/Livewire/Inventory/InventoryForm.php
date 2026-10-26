<?php

namespace App\Livewire\Inventory;

use App\Enums\DocumentStatus;
use App\Enums\MovementReason;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Livewire\Concerns\WithColumnFilters;
use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Services\StockService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class InventoryForm extends Component
{
    use InteractsWithToasts, WithPagination, WithColumnFilters;

    public InventoryCount $count;

    public string $recherche = '';

    /** true : n'afficher que les lignes pas encore comptées. */
    public bool $seulementRestantes = false;

    /** Rayon affiché (0 = produits sans catégorie, null = tous). */
    public ?int $categorie = null;

    /**
     * Quantités saisies, indexées par identifiant de ligne.
     *
     * @var array<int, string>
     */
    public array $saisies = [];

    public bool $confirmationValidation = false;

    public function mount(InventoryCount $count): void
    {
        abort_unless(auth()->user()->canAccess('inventaires'), 403);

        $this->count = $count->load('category');
    }

    public function updatedRecherche(): void
    {
        $this->resetPage();
    }

    public function choisirCategorie(?int $id): void
    {
        $this->categorie = $id;
        $this->resetPage();
    }

    /**
     * Enregistre une quantité comptée. L'écriture est immédiate, ligne par
     * ligne : un inventaire se fait debout dans le rayon, souvent sur
     * téléphone, et une saisie perdue parce que la page s'est rechargée
     * serait insupportable.
     */
    public function compter(int $ligneId, StockService $stock): void
    {
        abort_unless(auth()->user()->canWrite('inventaires'), 403);

        if (! $this->count->isDraft()) {
            return;
        }

        $valeur = $this->saisies[$ligneId] ?? null;

        $ligne = InventoryCountLine::where('inventory_count_id', $this->count->id)
            ->whereKey($ligneId)
            ->firstOrFail();

        if ($valeur === null || $valeur === '') {
            $ligne->forceFill([
                'counted_quantity' => null,
                'variance'         => 0,
                'variance_value'   => 0,
            ])->save();

            return;
        }

        $compte = (float) str_replace(',', '.', (string) $valeur);

        if ($compte < 0) {
            $this->addError("saisies.{$ligneId}", 'Quantité négative impossible.');

            return;
        }

        // Le stock théorique est relu à l'instant du comptage, et non figé au
        // lancement : une vente passée entre-temps fausserait sinon l'écart.
        $produit = $ligne->product()->first();
        $theorique = (float) ($produit?->quantity_on_hand ?? $ligne->expected_quantity);
        $ecart = round($compte - $theorique, 3);

        $ligne->forceFill([
            'expected_quantity' => $theorique,
            'counted_quantity'  => $compte,
            'variance'          => $ecart,
            'variance_value'    => $produit ? $stock->varianceValue($produit, $ecart) : 0,
        ])->save();
    }

    /**
     * Applique les écarts au stock. Chaque produit compté avec un écart
     * génère un mouvement d'ajustement ; les lignes non comptées sont
     * ignorées, jamais traitées comme un stock nul.
     *
     * L'écart constaté au comptage est appliqué au stock du moment : les
     * ventes et réceptions passées entre le comptage et la validation
     * restent acquises au lieu d'être écrasées par la quantité comptée.
     */
    public function valider(StockService $stock): void
    {
        abort_unless(auth()->user()->hasAtLeast(UserRole::Gerant), 403);

        if (! $this->count->isDraft()) {
            $this->toastErreur('Cet inventaire est déjà validé.');

            return;
        }

        $nonComptees = $this->count->lines()->whereNull('counted_quantity')->count();

        $total = 0;
        $ajustes = 0;

        DB::transaction(function () use ($stock, &$total, &$ajustes) {
            $this->count->lines()
                ->whereNotNull('counted_quantity')
                ->with('product')
                ->chunkById(100, function ($lignes) use ($stock, &$total, &$ajustes) {
                    foreach ($lignes as $ligne) {
                        if (! $ligne->product || ! $ligne->hasVariance()) {
                            continue;
                        }

                        $produit = $ligne->product->fresh();
                        $ecart = (float) $ligne->variance;

                        // Valorisé juste avant l'écriture, sur les lots
                        // que l'ajustement va réellement toucher.
                        $ligne->forceFill(['variance_value' => $stock->varianceValue($produit, $ecart)])->save();

                        $stock->adjustTo(
                            product: $produit,
                            countedQuantity: max(0, round((float) $produit->quantity_on_hand + $ecart, 3)),
                            user: auth()->user(),
                            reason: MovementReason::Inventaire,
                            source: $this->count,
                        );

                        $total += $ligne->variance_value;
                        $ajustes++;
                    }
                });

            $this->count->forceFill([
                'status'               => DocumentStatus::Valide,
                'total_variance_value' => $total,
                'validated_at'         => now(),
            ])->save();
        });

        $this->count->refresh();
        $this->confirmationValidation = false;

        $this->toastSucces($nonComptees > 0
            ? __('Inventaire validé : :n produit(s) ajusté(s), :ignorees ligne(s) non comptée(s) ignorée(s).', [
                'n'        => $ajustes,
                'ignorees' => $nonComptees,
            ])
            : __('Inventaire validé : :n produit(s) ajusté(s).', ['n' => $ajustes]));
    }

    /** Filtres par colonne (entonnoir des en-têtes). */
    protected function filtresColonnes(): array
    {
        return [
            'produit' => self::filtreTexte('products.name', 'products.sku'),
            'theorique' => self::filtreNombre('inventory_count_lines.expected_quantity'),
            'compte' => self::filtreNombre('inventory_count_lines.counted_quantity'),
            'ecart' => self::filtreNombre('inventory_count_lines.variance'),
            'valeur' => self::filtreNombre('inventory_count_lines.variance_value', montant: true),
        ];
    }

    public function render(): View
    {
        $lignes = $this->count->lines()
            ->with(['product:id,name,sku,unit_id,last_purchase_price', 'product.unit:id,symbol,allows_decimal'])
            ->when($this->recherche, fn ($q) => $q->whereHas(
                'product',
                fn ($p) => $p->search($this->recherche)
            ))
            ->when($this->seulementRestantes, fn ($q) => $q->whereNull('counted_quantity'))
            ->join('products', 'products.id', '=', 'inventory_count_lines.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->when($this->categorie === 0, fn ($q) => $q->whereNull('products.category_id'))
            ->when($this->categorie, fn ($q) => $q->where('products.category_id', $this->categorie))
            ->tap(fn ($q) => $this->appliquerFiltres($q))
            // Un rayon après l'autre : on compte comme on marche dans la boutique.
            ->orderByRaw('categories.name IS NULL, categories.name')
            ->orderBy('products.name')
            ->select('inventory_count_lines.*', 'categories.name as categorie_nom', 'categories.color as categorie_couleur', 'products.category_id as categorie_id')
            ->paginate(60);

        // Préremplit le tableau de saisie avec ce qui est déjà en base,
        // sans écraser ce que l'opérateur est en train de taper.
        foreach ($lignes as $ligne) {
            if (! array_key_exists($ligne->id, $this->saisies)) {
                $this->saisies[$ligne->id] = $ligne->counted_quantity !== null
                    ? rtrim(rtrim((string) $ligne->counted_quantity, '0'), '.')
                    : '';
            }
        }

        return view('livewire.inventory.inventory-form', [
            'lignes'       => $lignes,
            'nbTotal'      => $this->count->lines()->count(),
            'nbComptees'   => $this->count->lines()->whereNotNull('counted_quantity')->count(),
            'nbEcarts'     => $this->count->lines()->whereNotNull('counted_quantity')
                ->whereRaw('ABS(variance) > 0.0001')->count(),
            'peutValider'  => auth()->user()->hasAtLeast(UserRole::Gerant),
            'peutCompter'  => auth()->user()->canWrite('inventaires'),
            'rayons'       => $this->count->lines()
                ->join('products', 'products.id', '=', 'inventory_count_lines.product_id')
                ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                ->groupBy('categories.id', 'categories.name', 'categories.color')
                ->orderByRaw('categories.name IS NULL, categories.name')
                ->selectRaw('categories.id, categories.name, categories.color, COUNT(*) AS total,
                    SUM(CASE WHEN counted_quantity IS NOT NULL THEN 1 ELSE 0 END) AS comptes,
                    SUM(CASE WHEN counted_quantity IS NOT NULL AND ABS(variance) > 0.0001 THEN 1 ELSE 0 END) AS ecarts')
                ->toBase()
                ->get(),
        ]);
    }
}
