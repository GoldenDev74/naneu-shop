<?php

namespace App\Livewire\Stock;

use App\Livewire\Concerns\WithColumnFilters;
use App\Models\StockEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Entrées de stock')]
class EntryList extends Component
{
    use WithPagination, WithColumnFilters;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(except: '')]
    public string $fournisseur = '';

    #[Url(except: '')]
    public string $depuis = '';

    #[Url(except: '')]
    public string $jusqua = '';

    /** Bon affiché dans la fenêtre de consultation (œil). */
    public ?int $apercu = null;

    public function mount(): void
    {
        $this->authorize('viewAny', StockEntry::class);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function voir(int $id): void
    {
        $this->apercu = $id;
    }

    public function fermerApercu(): void
    {
        $this->apercu = null;
    }

    #[On('entree-enregistree')]
    public function rafraichir(): void
    {
    }

    /** Filtres par colonne (entonnoir des en-têtes). */
    protected function filtresColonnes(): array
    {
        return [
            'reference' => self::filtreTexte('reference', 'invoice_number'),
            'date' => self::filtreDate('entry_date'),
            'fournisseur' => self::filtreEgal('supplier_id'),
            'total' => self::filtreNombre('total_cost', montant: true),
            'auteur' => self::filtreRelation('user', 'name'),
            'statut' => self::filtreEgal('status'),
        ];
    }

    public function render(): View
    {
        $entrees = StockEntry::query()
            ->with(['supplier:id,name', 'user:id,name'])
            ->withCount('lines')
            ->when($this->recherche, fn (Builder $q) => $q->where(function (Builder $sub) {
                $sub->whereLike('reference', "%{$this->recherche}%")
                    ->orWhereLike('invoice_number', "%{$this->recherche}%");
            }))
            ->when($this->fournisseur, fn (Builder $q) => $q->where('supplier_id', $this->fournisseur))
            ->when($this->depuis, fn (Builder $q) => $q->whereDate('entry_date', '>=', $this->depuis))
            ->when($this->jusqua, fn (Builder $q) => $q->whereDate('entry_date', '<=', $this->jusqua))
            ->tap(fn ($q) => $this->appliquerFiltres($q))
            ->latest('entry_date')
            ->latest('id')
            ->paginate(20);

        return view('livewire.stock.entry-list', [
            'entrees'      => $entrees,
            'fournisseurs' => \App\Models\Supplier::query()->orderBy('name')->get(['id', 'name']),
            'detail'       => $this->apercu
                ? tap(StockEntry::with(['lines.product.unit', 'supplier', 'user'])->find($this->apercu), function (?StockEntry $entree) {
                    if ($entree) {
                        $this->authorize('view', $entree);
                    }
                })
                : null,
        ]);
    }
}
