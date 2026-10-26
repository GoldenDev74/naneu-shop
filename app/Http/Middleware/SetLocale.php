<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applique la langue d'interface : celle du compte connecté, sinon celle
 * choisie dans la session (page de connexion), sinon la langue par défaut.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $disponibles = array_keys(config('naneu.locales'));

        // getAttributes() : un modèle pas encore relu en base (fabrique,
        // tests) n'a pas la colonne, et le mode strict lèverait une erreur.
        $locale = collect([
            $request->user()?->getAttributes()['locale'] ?? null,
            $request->session()->get('locale'),
        ])->first(fn ($l) => in_array($l, $disponibles, true)) ?? config('app.locale');

        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
