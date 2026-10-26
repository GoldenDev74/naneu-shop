<?php

namespace App\Livewire\Audit;

use App\Livewire\Concerns\WithColumnFilters;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Journal de sécurité')]
class AuditTrail extends Component
{
    use WithPagination, WithColumnFilters;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    #[Url(except: '')]
    public string $evenement = '';

    #[Url(except: '')]
    public string $depuis = '';

    public function mount(): void
    {
        $this->authorize('voir-journal-audit');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /** Filtres par colonne (entonnoir des en-têtes). */
    protected function filtresColonnes(): array
    {
        return [
            'date' => self::filtreDate('created_at'),
            'evenement' => self::filtreEgal('event'),
            'compte' => self::filtreTexte('user_email'),
            'origine' => self::filtreTexte('ip_address', 'user_agent'),
        ];
    }

    public function render(): View
    {
        $entrees = AuditLog::query()
            ->with('user:id,name,email')
            ->when($this->recherche, fn (Builder $q) => $q->where(function (Builder $sub) {
                $sub->whereLike('user_email', "%{$this->recherche}%")
                    ->orWhereLike('event', "%{$this->recherche}%")
                    ->orWhereLike('ip_address', "%{$this->recherche}%");
            }))
            ->when($this->evenement, fn (Builder $q) => $q->where('event', $this->evenement))
            ->when($this->depuis, fn (Builder $q) => $q->whereDate('created_at', '>=', $this->depuis))
            ->tap(fn ($q) => $this->appliquerFiltres($q))
            ->latest('created_at')
            ->paginate(40);

        return view('livewire.audit.audit-trail', [
            'entrees'    => $entrees,
            'evenements' => AuditLog::query()
                ->distinct()
                ->orderBy('event')
                ->pluck('event'),
        ]);
    }
}
