<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un mot de passe provisoire n'a de sens que s'il est réellement provisoire.
 *
 * Tant que le drapeau `must_change_password` est levé, l'utilisateur est
 * ramené à l'écran de changement, quelle que soit l'adresse demandée.
 * Sans ce contrôle, l'option « exiger un changement » ne serait qu'un
 * libellé décoratif, et un mot de passe transmis par courriel resterait
 * valable indéfiniment.
 */
class EnsurePasswordIsCurrent
{
    /** Routes accessibles malgré le drapeau, sous peine de boucle. */
    private const ROUTES_AUTORISEES = [
        'mot-de-passe.changer',
        'deconnexion',
        'login',
        'logout',
        'password.confirm',
        'verification.notice',
        'verification.verify',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // La liste blanche est évaluée en premier : se déconnecter ou
        // atteindre l'écran de changement doit rester possible sans même
        // consulter l'état du compte.
        if ($request->routeIs(self::ROUTES_AUTORISEES) || $request->routeIs('livewire.*')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        return redirect()->route('mot-de-passe.changer');
    }
}
