<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un compte suspendu doit perdre l'accès immédiatement, y compris si sa
 * session était déjà ouverte au moment de la suspension. Sans ce contrôle,
 * un employé renvoyé garderait la main sur le stock jusqu'à l'expiration
 * naturelle de son cookie.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'form.login' => 'Ce compte a été désactivé. Contactez un administrateur.',
                ]);
        }

        return $next($request);
    }
}
