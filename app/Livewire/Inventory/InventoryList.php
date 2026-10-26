<?php

namespace App\Livewire\Inventory;

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Livewire\Concerns\WithColumnFilters;
use App\Models\Category;
use App\Models\InventoryCount;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Inventaires')]
class InventoryList extends Component
{
    use InteractsWithToasts, WithPagination, WithColumnFilters;

    public bool $panneau = false;
    public string $scope = 'complet';
    public ?int $category_id = null;
    public string $note = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->canAccess('inventaires'), 403);
    }

    /**
     * Crée une feuille de comptage et fige le stock théorique de chaque
     * produit au moment du lancement. Figer maintenant, et non à la
     * validation, est ce qui rend l'écart interprétable : il mesure la
     * réalité du rayon face au stock tel qu'il était au début du comptage.
     */
    public function lancer()
    {
        abort_unless(auth()->user()->canWrite('inventaires'), 403);

        $this->validate([
            'scope'       => ['required', 'in:complet,partiel'],
            'category_id' => ['nullable', 'required_if:scope,partiel', 'exists:categories,id'],
            'note'        => ['nullable', 'string', 'max:500'],
        ], [
            'category_id.required_if' => 'Choisissez une catégorie pour un inventaire partiel.',
        ]);

        $inventaire = DB::transaction(function () {
            $inventaire = InventoryCount::create([
                'reference'   => InventoryCount::nextReference(),
                'user_id'     => auth()->id(),
                'count_date'  => now()->toDateString(),
                'scope'       => $this->scope,
                'category_id' => $this->scope === 'partiel' ? $this->category_id : null,
                'status'      => DocumentStatus::Brouillon,
                'note'        => $this->note ?: null,
            ]);

            Product::query()
                ->active()
                ->when($this->scope === 'partiel', fn ($q) => $q->whereIn('category_id', Category::findOrFail($this->category_id)->selfAndDescendantIds()))
                ->orderBy('name')
                ->chunk(200, function ($produits) use ($inventaire) {
                    $lignes = $produits->map(fn (Product $produit) => [
                        'inventory_count_id' => $inventaire->id,
                        'product_id'         => $produit->id,
                        'expected_quantity'  => $produit->quantity_on_hand,
                        'counted_quantity'   => null,
                        'variance'           => 0,
                        'variance_value'     => 0,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ])->all();

                    DB::table('inventory_count_lines')->insert($lignes);
                });

            return $inventaire;
        });

        $this->panneau = false;

        return $this->redirectRoute('inventaires.show', ['count' => $inventaire], navigate: true);
    }

    /** Filtres par colonne (entonnoir des en-têtes). */
    protected function filtresColonnes(): array
    {
        return [
            'reference' => self::filtreTexte('reference'),
            'date' => self::filtreDate('count_date'),
            'portee' => self::filtreEgal('scope'),
            'ecart' => self::filtreNombre('total_variance_value', montant: true),
            'auteur' => self::filtreRelation('user', 'name'),
            'statut' => self::filtreEgal('status'),
        ];
    }

    public function render(): View
    {
        return view('livewire.inventory.inventory-list', [
            'peutLancer'  => auth()->user()->canWrite('inventaires'),
            'inventaires' => InventoryCount::query()
                ->with(['user:id,name', 'category:id,name'])
                ->withCount('lines')
                ->tap(fn ($q) => $this->appliquerFiltres($q))
                ->latest('count_date')
                ->latest('id')
                ->paginate(20),
            'categories' => Category::enArbre(Category::query()->active()->get(['id', 'name', 'parent_id'])),
        ]);
    }
}
