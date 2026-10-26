<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `section:produits` : le menu doit être ouvert à l'utilisateur
 * (User::accessLevel). `section:produits,ecriture` exige en plus l'accès
 * complet : pages de création et de modification.
 *
 * Le tableau de bord, page d'arrivée par défaut, renvoie vers la page
 * d'accueil de l'utilisateur au lieu d'un refus : une vendeuse qui se
 * connecte arrive directement à la caisse.
 */
class EnsureUserCanAccessSection
{
    public function handle(Request $request, Closure $next, string $section, ?string $mode = null): Response
    {
        $user = $request->user();

        if ($user && ! $user->canAccess($section) && $section === 'tableau-de-bord') {
            return redirect()->route($user->homeRoute());
        }

        abort_unless($user && $user->canAccess($section), 403, "Vous n'avez pas accès à cette section.");

        abort_if($mode === 'ecriture' && ! $user->canWrite($section), 403, 'Accès en lecture seule à cette section.');

        return $next($request);
    }
}
