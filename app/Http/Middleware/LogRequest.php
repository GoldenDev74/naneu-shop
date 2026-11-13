<?php

namespace App\Http\Middleware;

use App\Services\GeoIp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Une ligne JSON par requête dans storage/logs/requetes-*.log, écrite après
 * l'envoi de la réponse (le visiteur n'attend pas). Le chemin est noté sans
 * la chaîne de requête : elle peut contenir un jeton (lien signé, reset).
 */
class LogRequest
{
    public function __construct(private readonly GeoIp $geo) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->is('up')) {
            return;
        }

        try {
            $ip = $request->ip();
            $debut = defined('LARAVEL_START') ? LARAVEL_START : $request->server('REQUEST_TIME_FLOAT');

            Log::channel('requetes')->info('requete', [
                'ip'       => $ip,
                'pays'     => $this->geo->pays($ip),
                'methode'  => $request->method(),
                'chemin'   => '/'.ltrim($request->path(), '/'),
                'route'    => $request->route()?->getName(),
                'statut'   => $response->getStatusCode(),
                'duree_ms' => $debut ? (int) round((microtime(true) - $debut) * 1000) : null,
                'compte'   => $request->user()?->username,
                'agent'    => str($request->userAgent() ?? '')->limit(250)->value(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
