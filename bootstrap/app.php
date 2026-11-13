<?php

use App\Http\Middleware\EnsurePasswordIsCurrent;
use App\Http\Middleware\EnsureUserCanAccessSection;
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
            'section'              => EnsureUserCanAccessSection::class,
            'mot-de-passe.actuel'  => EnsurePasswordIsCurrent::class,
        ]);

        // Derrière un tunnel ou un proxy HTTPS, on se fie à X-Forwarded-Proto
        // pour générer des URL https (sinon « mixed content »).
        $middleware->trustProxies(at: '*');

        // Avant tout le reste : refus des adresses bloquées et des pays non
        // autorisés, puis une ligne de journal par requête (pour Grafana).
        $middleware->append([
            \App\Http\Middleware\FilterTraffic::class,
            \App\Http\Middleware\LogRequest::class,
        ]);

        // Les requêtes de l'application sont toutes internes : on peut
        // durcir les en-têtes sans risquer de casser un client tiers.
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\LogoutIdleSession::class,
            // Limite de requêtes par minute (AppServiceProvider::configureRateLimits),
            // y compris les clics Livewire (/livewire/update passe par « web »).
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':naneu',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
