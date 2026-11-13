<?php

namespace App\Livewire\Audit;

use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\AuditLog;
use App\Models\IpRule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panneau du Journal de sécurité : adresses IP bloquées ou autorisées
 * (gérant en déplacement hors du Togo). Réservé à la gérance.
 */
class IpRules extends Component
{
    use InteractsWithToasts;

    public string $ip = '';

    public string $type = IpRule::BLOQUEE;

    public string $note = '';

    /** Durée en jours ; vide = sans limite. */
    public string $duree = '';

    public bool $formulaireOuvert = false;

    public function mount(): void
    {
        Gate::authorize('gerer-adresses-ip');
    }

    /** Raccourci « Bloquer » depuis une ligne du journal. */
    #[On('ip-a-regler')]
    public function preparer(string $ip, string $type = IpRule::BLOQUEE): void
    {
        $this->ip = $ip;
        $this->type = $type === IpRule::AUTORISEE ? IpRule::AUTORISEE : IpRule::BLOQUEE;
        $this->formulaireOuvert = true;
    }

    public function enregistrer(): void
    {
        Gate::authorize('gerer-adresses-ip');

        $this->validate([
            'ip'    => ['required', 'string', 'max:64', function ($attribut, $valeur, $echec) {
                if (! IpRule::adresseValide($valeur)) {
                    $echec(__('Adresse IP ou plage (ex. 41.207.160.0/24) invalide.'));
                }
            }],
            'type'  => ['required', 'in:'.IpRule::BLOQUEE.','.IpRule::AUTORISEE],
            'note'  => ['nullable', 'string', 'max:255'],
            'duree' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $ip = trim($this->ip);

        // Se bloquer soi-même fermerait la boutique à la gérance connectée.
        if ($this->type === IpRule::BLOQUEE && \Symfony\Component\HttpFoundation\IpUtils::checkIp((string) request()->ip(), $ip)) {
            $this->addError('ip', __('Cette règle bloquerait votre propre adresse.'));

            return;
        }

        $regle = IpRule::updateOrCreate(
            ['ip' => $ip, 'type' => $this->type],
            [
                'note'       => $this->note ?: null,
                'expires_at' => $this->duree ? now()->addDays((int) $this->duree) : null,
                'created_by' => auth()->id(),
            ],
        );

        AuditLog::record('ip.'.$this->type, auth()->user(), $regle, [
            'adresse' => $ip,
            'note'    => $this->note ?: null,
            'jusqua'  => $regle->expires_at?->toDateTimeString(),
        ]);

        $this->reset('ip', 'note', 'duree', 'formulaireOuvert');
        $this->type = IpRule::BLOQUEE;
        $this->toastSucces('Règle enregistrée. Elle s\'applique dans la minute.');
    }

    public function supprimer(int $id): void
    {
        Gate::authorize('gerer-adresses-ip');

        $regle = IpRule::findOrFail($id);
        $regle->delete();

        AuditLog::record('ip.regle_retiree', auth()->user(), $regle, [
            'adresse' => $regle->ip,
            'type'    => $regle->type,
        ]);

        $this->toastSucces('Règle retirée.');
    }

    public function render(): View
    {
        return view('livewire.audit.ip-rules', [
            'regles' => IpRule::query()->actives()->with('auteur:id,name')->latest()->get(),
            'votreIp' => request()->ip(),
        ]);
    }
}
