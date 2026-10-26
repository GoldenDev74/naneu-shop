<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Une session restée sans activité (poste de caisse laissé ouvert) est
 * fermée au bout du délai réglé dans les Paramètres.
 *
 * Seules les actions de l'utilisateur comptent comme activité : le
 * rafraîchissement automatique de la cloche (wire:poll) ne doit pas
 * maintenir une session ouverte indéfiniment. Le navigateur déconnecte de
 * lui-même à l'échéance (layouts.app) ; ce contrôle serveur est le filet.
 */
class LogoutIdleSession
{
    public const CLE = 'naneu.derniere_activite';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $delai = (int) config('naneu.security.idle_minutes');

        if (! $user || $delai <= 0) {
            return $next($request);
        }

        $derniere = (int) $request->session()->get(self::CLE, 0);

        if ($derniere > 0 && now()->timestamp - $derniere > $delai * 60) {
            AuditLog::record('deconnexion.inactivite', $user, $user, ['minutes' => $delai]);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->flash('status', __('Session fermée après :n minutes d\'inactivité. Reconnectez-vous.', ['n' => $delai]));

            // Requête Livewire : la page expirée invite à recharger, ce qui
            // mène à l'écran de connexion.
            abort_if($this->estLivewire($request), 419);

            return redirect()->route('login');
        }

        if (! $this->estRafraichissementAutomatique($request)) {
            $request->session()->put(self::CLE, now()->timestamp);
        }

        return $next($request);
    }

    private function estLivewire(Request $request): bool
    {
        return $request->hasHeader('X-Livewire');
    }

    /** Requête Livewire qui ne fait que se rafraîchir ($refresh, wire:poll), sans saisie. */
    private function estRafraichissementAutomatique(Request $request): bool
    {
        if (! $this->estLivewire($request)) {
            return false;
        }

        foreach ((array) $request->input('components', []) as $composant) {
            if (! empty($composant['updates'])) {
                return false;
            }

            foreach ((array) ($composant['calls'] ?? []) as $appel) {
                if (($appel['method'] ?? null) !== '$refresh') {
                    return false;
                }
            }
        }

        return true;
    }
}
