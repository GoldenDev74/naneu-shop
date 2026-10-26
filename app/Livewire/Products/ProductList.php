<?php

namespace App\Livewire\Products;

use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Livewire\Concerns\WithColumnFilters;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Produits')]
class ProductList extends Component
{
    use InteractsWithToasts, WithPagination, WithColumnFilters;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(except: '')]
    public string $categorie = '';

    /** tous | rupture | bas | reappro | perissable | inactif */
    #[Url(except: 'tous')]
    public string $statut = 'tous';

    #[Url(except: 'name')]
    public string $tri = 'name';

    #[Url(except: 'asc')]
    public string $sens = 'asc';

    public int $parPage = 25;

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'categorie', 'statut'], true)) {
            $this->resetPage();
        }
    }

    public function trierPar(string $colonne): void
    {
        if ($this->tri === $colonne) {
            $this->sens = $this->sens === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->tri = $colonne;
        $this->sens = 'asc';
    }

    public function reinitialiser(): void
    {
        $this->reset(['recherche', 'categorie', 'statut']);
        $this->resetPage();
    }

    /**
     * Le champ de recherche reçoit aussi bien la frappe au clavier que le
     * scan d'une douchette. Quand la saisie correspond exactement à un
     * code-barres, on ouvre directement la fiche : c'est le comportement
     * attendu en rayon, douchette en main.
     */
    public function scanner(): void
    {
        $terme = trim($this->recherche);

        if ($terme === '') {
            return;
        }

        $produit = Product::query()
            ->where('barcode', $terme)
            ->orWhere('internal_code', $terme)
            ->first();

        if ($produit) {
            $this->redirectRoute('produits.edit', ['product' => $produit], navigate: true);
        }
    }

    #[On('stock-modifie')]
    public function rafraichir(): void
    {
    }

    public function basculerActif(int $id): void
    {
        $produit = Product::findOrFail($id);

        $this->authorize('update', $produit);

        $produit->forceFill(['is_active' => ! $produit->is_active])->save();

        $this->toastSucces(
            $produit->is_active
                ? __('« :nom » est de nouveau actif.', ['nom' => $produit->name])
                : __('« :nom » a été désactivé.', ['nom' => $produit->name])
        );
    }

    /** Filtres par colonne (entonnoir des en-têtes). */
    protected function filtresColonnes(): array
    {
        return [
            'produit' => self::filtreTexte('name', 'sku', 'barcode'),
            'categorie' => fn ($q, $v) => $q->whereIn('category_id', Category::find($v)?->selfAndDescendantIds() ?? [0]),
            'stock' => self::filtreNombre('quantity_on_hand'),
            'prix' => self::filtreNombre('sale_price', montant: true),
            'perissable' => self::filtreEgal('is_perishable'),
        ];
    }

    public function render(): View
    {
        $produits = Product::query()
            ->with(['category:id,name,color', 'unit:id,symbol,allows_decimal'])
            ->when($this->recherche, fn (Builder $q) => $q->search($this->recherche))
            ->when($this->categorie, fn (Builder $q) => $q->whereIn('category_id', Category::find($this->categorie)?->selfAndDescendantIds() ?? [0]))
            ->when($this->statut !== 'inactif', fn (Builder $q) => $q->where('is_active', true))
            ->when($this->statut === 'inactif', fn (Builder $q) => $q->where('is_active', false))
            ->when($this->statut === 'rupture', fn (Builder $q) => $q->outOfStock())
            ->when($this->statut === 'bas', fn (Builder $q) => $q->lowStock())
            ->when($this->statut === 'reappro', fn (Builder $q) => $q->needsReorder())
            ->when($this->statut === 'perissable', fn (Builder $q) => $q->where('is_perishable', true))
            ->tap(fn ($q) => $this->appliquerFiltres($q))
            ->orderBy($this->tri, $this->sens)
            ->paginate($this->parPage);

        return view('livewire.products.product-list', [
            'produits'   => $produits,
            'categories' => Category::enArbre(Category::query()->active()->get(['id', 'name', 'parent_id'])),

            // Liste vide alors que des produits existent : ce sont les filtres
            // (ou le masquage des inactifs) qui les écartent, pas un catalogue vide.
            'filtresActifs' => $produits->isEmpty() && Product::query()->exists(),

            // Les colonnes de prix ne s'affichent pas pour un magasinier :
            // il gère des quantités, pas la politique tarifaire.
            'voitPrix'   => auth()->user()->hasAtLeast(UserRole::Gerant),
            'peutAgir'   => auth()->user()->canWrite('produits') || auth()->user()->canAccess('lots'),
        ]);
    }
}
