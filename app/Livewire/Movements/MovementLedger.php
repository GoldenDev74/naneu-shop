<?php

namespace App\Livewire\Movements;

use App\Livewire\Concerns\WithColumnFilters;
use App\Enums\MovementReason;
use App\Enums\UserRole;
use App\Models\StockMovement;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Grand livre de stock : la vue brute de tout ce qui est entré et sorti.
 * Lecture seule par construction — les mouvements sont immuables.
 */
#[Layout('layouts.app')]
#[Title('Mouvements')]
class MovementLedger extends Component
{
    use WithPagination, WithColumnFilters;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(except: '')]
    public string $sens = '';

    #[Url(except: '')]
    public string $motif = '';

    #[Url(except: '')]
    public string $depuis = '';

    #[Url(except: '')]
    public string $jusqua = '';

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
            'date' => self::filtreDate('occurred_at'),
            'sens' => self::filtreEgal('direction'),
            'produit' => self::filtreRelation('product', 'name', 'sku'),
            'lot' => self::filtreRelation('batch', 'batch_number'),
            'motif' => self::filtreEgal('reason'),
            'quantite' => self::filtreNombre('quantity'),
            'stock_apres' => self::filtreNombre('quantity_after'),
            'cout' => self::filtreNombre('total_cost', montant: true),
            'auteur' => self::filtreRelation('user', 'name'),
        ];
    }

    public function render(): View
    {
        $mouvements = StockMovement::query()
            ->with([
                'product:id,name,sku,unit_id',
                'product.unit:id,symbol,allows_decimal',
                'batch:id,batch_number',
                'user:id,name',
            ])
            ->when($this->recherche, fn (Builder $q) => $q->whereHas(
                'product',
                fn (Builder $p) => $p->search($this->recherche)
            ))
            ->when($this->sens, fn (Builder $q) => $q->where('direction', $this->sens))
            ->when($this->motif, fn (Builder $q) => $q->where('reason', $this->motif))
            ->when($this->depuis, fn (Builder $q) => $q->whereDate('occurred_at', '>=', $this->depuis))
            ->when($this->jusqua, fn (Builder $q) => $q->whereDate('occurred_at', '<=', $this->jusqua))
            ->tap(fn ($q) => $this->appliquerFiltres($q))
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(40);

        return view('livewire.movements.movement-ledger', [
            'mouvements' => $mouvements,
            'motifs'     => collect(MovementReason::cases())
                ->mapWithKeys(fn (MovementReason $r) => [$r->value => $r->label()])
                ->all(),
            'voitPrix'   => auth()->user()->hasAtLeast(UserRole::Gerant),
        ]);
    }
}
