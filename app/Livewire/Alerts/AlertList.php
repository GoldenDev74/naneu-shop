<?php

namespace App\Livewire\Alerts;

use App\Enums\AlertType;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\StockAlert;
use App\Services\AlertService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Alertes')]
class AlertList extends Component
{
    use InteractsWithToasts, WithPagination;

    /** ouvertes | non_traitees | resolues | toutes */
    #[Url(except: 'ouvertes')]
    public string $etat = 'ouvertes';

    #[Url(except: '')]
    public string $type = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[On('alerte-recue')]
    #[On('alerte-resolue')]
    public function rafraichir(): void
    {
    }

    public function acquitter(int $id): void
    {
        abort_unless(auth()->user()->canWrite('alertes'), 403);

        $alerte = StockAlert::findOrFail($id);

        $alerte->acknowledge(auth()->user());

        $this->toastSucces('Alerte marquée comme traitée.');
    }

    public function acquitterTout(): void
    {
        abort_unless(auth()->user()->canWrite('alertes'), 403);

        StockAlert::query()
            ->unacknowledged()
            ->get()
            ->each(fn (StockAlert $alerte) => $alerte->acknowledge(auth()->user()));

        $this->toastSucces('Toutes les alertes ouvertes ont été marquées comme traitées.');
    }

    /**
     * Relance un contrôle complet. Utile après une correction manuelle :
     * évite d'attendre le passage du planificateur pour voir l'alerte
     * se refermer.
     */
    public function reanalyser(AlertService $alerts): void
    {
        $niveaux = $alerts->scanStockLevels();
        $peremptions = $alerts->scanExpiries();

        $total = $niveaux->count() + $peremptions->count();

        $this->toastSucces(
            $total > 0
                ? __(':n nouvelle(s) alerte(s) détectée(s).', ['n' => $total])
                : 'Analyse terminée : aucune nouvelle alerte.'
        );
    }

    public function render(): View
    {
        $alertes = StockAlert::query()
            ->with(['product:id,name,sku,quantity_on_hand,alert_quantity', 'batch:id,batch_number,expiry_date', 'acknowledgedBy:id,name'])
            ->when($this->etat === 'ouvertes', fn (Builder $q) => $q->open())
            ->when($this->etat === 'non_traitees', fn (Builder $q) => $q->unacknowledged())
            ->when($this->etat === 'resolues', fn (Builder $q) => $q->whereNotNull('resolved_at'))
            ->when($this->type, fn (Builder $q) => $q->where('type', $this->type))
            ->bySeverity()
            ->paginate(25);

        return view('livewire.alerts.alert-list', [
            'alertes'      => $alertes,
            'peutTraiter'  => auth()->user()->canWrite('alertes'),
            'types'        => AlertType::options(),
            'nbOuvertes'   => StockAlert::query()->open()->count(),
            'nbCritiques'  => StockAlert::query()->open()->critical()->count(),
            'nbNonTraitees' => StockAlert::query()->unacknowledged()->count(),
        ]);
    }
}
