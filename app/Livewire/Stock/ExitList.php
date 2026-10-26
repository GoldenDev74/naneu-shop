<?php

namespace App\Livewire\Stock;

use App\Livewire\Concerns\WithColumnFilters;
use App\Enums\ExitType;
use App\Enums\UserRole;
use App\Models\StockExit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Sorties de stock')]
class ExitList extends Component
{
    use WithPagination, WithColumnFilters;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(except: '')]
    public string $type = '';

    /** '' = toutes, 'caisse' = ventes encaissées au comptoir, 'stock' = bons saisis. */
    #[Url(except: '')]
    public string $origine = '';

    #[Url(except: '')]
    public string $depuis = '';

    #[Url(except: '')]
    public string $jusqua = '';

    /** Bon affiché dans la fenêtre de consultation (œil). */
    public ?int $apercu = null;

    public function mount(): void
    {
        $this->authorize('viewAny', StockExit::class);
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

    #[On('sortie-enregistree')]
    public function rafraichir(): void
    {
    }

    /** Filtres par colonne (entonnoir des en-têtes). */
    protected function filtresColonnes(): array
    {
        return [
            'reference' => self::filtreTexte('reference'),
            'date' => self::filtreDate('exit_date'),
            'motif' => self::filtreEgal('type'),
            'cout' => self::filtreNombre('total_cost', montant: true),
            'vente' => self::filtreNombre('total_sale', montant: true),
            'marge' => self::filtreNombre('total_margin', montant: true),
            'auteur' => self::filtreRelation('user', 'name'),
            'statut' => self::filtreEgal('status'),
        ];
    }

    public function render(): View
    {
        $sorties = StockExit::query()
            ->with('user:id,name')
            ->withCount('lines')
            ->when($this->recherche, fn (Builder $q) => $q->whereLike('reference', "%{$this->recherche}%"))
            ->when($this->type, fn (Builder $q) => $q->where('type', $this->type))
            ->when($this->origine === 'caisse', fn (Builder $q) => $q->whereNotNull('payment_method'))
            ->when($this->origine === 'stock', fn (Builder $q) => $q->whereNull('payment_method'))
            ->when($this->depuis, fn (Builder $q) => $q->whereDate('exit_date', '>=', $this->depuis))
            ->when($this->jusqua, fn (Builder $q) => $q->whereDate('exit_date', '<=', $this->jusqua))
            ->tap(fn ($q) => $this->appliquerFiltres($q))
            ->latest('exit_date')
            ->latest('id')
            ->paginate(20);

        return view('livewire.stock.exit-list', [
            'sorties'   => $sorties,
            'types'     => ExitType::options(),
            'voitPrix'  => auth()->user()->hasAtLeast(UserRole::Gerant),
            'detail'    => $this->apercu
                ? tap(StockExit::with(['lines.product.unit', 'user'])->find($this->apercu), function (?StockExit $sortie) {
                    if ($sortie) {
                        $this->authorize('view', $sortie);
                    }
                })
                : null,
        ]);
    }
}
