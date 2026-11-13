<?php

namespace App\Support;

use App\Services\GeoIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Évènements de sécurité au format JSON (storage/logs/securite-*.log),
 * collectés par Grafana Alloy. N'échoue jamais : un journal indisponible ne
 * doit pas empêcher une connexion ou une vente.
 */
class SecurityLog
{
    /** @param  array<string, mixed>  $contexte */
    public static function evenement(string $evenement, array $contexte = [], ?Request $request = null): void
    {
        try {
            $request ??= request();
            $ip = $request?->ip();

            Log::channel('securite')->info($evenement, array_merge([
                'evenement' => $evenement,
                'ip'        => $ip,
                'pays'      => app(GeoIp::class)->pays($ip),
                'methode'   => $request?->method(),
                'chemin'    => $request ? '/'.ltrim($request->path(), '/') : null,
                'compte'    => $request?->user()?->username,
                'agent'     => str($request?->userAgent() ?? '')->limit(250)->value(),
            ], $contexte));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
