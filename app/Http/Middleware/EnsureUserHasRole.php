<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôle hiérarchique : `role:gerant` laisse passer les gérants ET les
 * administrateurs. Les niveaux sont définis une seule fois, dans l'enum.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();
        $required = UserRole::tryFrom($role);

        abort_if($required === null, 500, "Rôle inconnu dans la définition de route : {$role}");
        abort_unless($user && $user->hasAtLeast($required), 403, "Vous n'avez pas accès à cette section.");

        return $next($request);
    }
}
