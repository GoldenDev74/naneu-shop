<?php

use App\Http\Middleware\EnsurePasswordIsCurrent;
use App\Http\Middleware\EnsureUserCanAccessSection;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'actif'                => EnsureUserIsActive::class,
            'role'                 => EnsureUserHasRole::class,
            'section'              => EnsureUserCanAccessSection::class,
            'mot-de-passe.actuel'  => EnsurePasswordIsCurrent::class,
        ]);

        // Derrière un tunnel ou un proxy HTTPS, on se fie à X-Forwarded-Proto
        // pour générer des URL https (sinon « mixed content »).
        $middleware->trustProxies(at: '*');

        // Les requêtes de l'application sont toutes internes : on peut
        // durcir les en-têtes sans risquer de casser un client tiers.
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\LogoutIdleSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
