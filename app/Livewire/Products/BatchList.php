<?php

namespace App\Livewire\Products;

use App\Livewire\Concerns\WithColumnFilters;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\StockBatch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Sert deux écrans : la liste globale des lots (/lots) et les lots d'un
 * produit précis (/produits/{product}/lots). La seule différence est le
 * filtre initial, d'où le composant unique.
 */
#[Layout('layouts.app')]
#[Title('Lots')]
class BatchList extends Component
{
    use WithPagination, WithColumnFilters;

    public ?Product $product = null;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** tous | disponibles | epuises | perimes | bientot */
    #[Url(except: 'disponibles')]
    public string $filtre = 'disponibles';

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->authorize('view', $product);

            $this->product = $product->load('unit');
        }
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    #[On('stock-modifie')]
    public function rafraichir(): void
    {
    }

    /** Filtres par colonne (entonnoir des en-têtes). */
    protected function filtresColonnes(): array
    {
        return [
            'lot' => self::filtreTexte('batch_number'),
            'produit' => self::filtreRelation('product', 'name', 'sku'),
            'recu_le' => self::filtreDate('received_at'),
            'peremption' => self::filtreDate('expiry_date'),
            'recu' => self::filtreNombre('quantity_received'),
            'restant' => self::filtreNombre('quantity_remaining'),
            'prix' => self::filtreNombre('purchase_price', montant: true),
            'fournisseur' => self::filtreRelation('supplier', 'name'),
        ];
    }

    public function render(): View
    {
        $lots = StockBatch::query()
            ->with(['product:id,name,sku,unit_id', 'product.unit:id,symbol,allows_decimal', 'supplier:id,name'])
            ->when($this->product, fn (Builder $q) => $q->where('product_id', $this->product->id))
            ->when($this->recherche, fn (Builder $q) => $q->where(function (Builder $sub) {
                $sub->whereLike('batch_number', "%{$this->recherche}%")
                    ->orWhereHas('product', fn (Builder $p) => $p->search($this->recherche));
            }))
            ->when($this->filtre === 'disponibles', fn (Builder $q) => $q->available())
            ->when($this->filtre === 'epuises', fn (Builder $q) => $q->where('quantity_remaining', '<=', 0))
            ->when($this->filtre === 'perimes', fn (Builder $q) => $q->available()->expired())
            ->when($this->filtre === 'bientot', fn (Builder $q) => $q->available()
                ->expiringWithin((int) config('naneu.stock.expiry_alert_days', 30)))
            ->tap(fn ($q) => $this->appliquerFiltres($q))
            ->inConsumptionOrder()
            ->paginate(30);

        return view('livewire.products.batch-list', [
            'lots'     => $lots,
            'voitPrix' => auth()->user()->hasAtLeast(UserRole::Gerant),
            'strategie' => config('naneu.stock.strategy', 'fifo'),
        ]);
    }
}
