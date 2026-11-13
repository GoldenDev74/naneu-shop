<?php

namespace App\Http\Middleware;

use App\Models\IpRule;
use App\Services\GeoIp;
use App\Support\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Premier filtre, avant la session et la base de données :
 *  1. adresse bloquée à la main  -> refus ;
 *  2. adresse autorisée à la main -> admise ;
 *  3. pays hors de la liste       -> refus (si le filtre est activé).
 * Les adresses privées (Tailscale, Docker) et les chemins techniques
 * (renouvellement HTTPS, /up) ne sont jamais filtrés par pays.
 */
class FilterTraffic
{
    public function __construct(private readonly GeoIp $geo) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ip = (string) $request->ip();

        $regle = $this->regle($ip);

        if ($regle === IpRule::BLOQUEE) {
            SecurityLog::evenement('acces.ip_bloquee', [], $request);

            return $this->refus(__('L\'accès depuis votre adresse a été bloqué. Si c\'est une erreur, contactez la gérance de la boutique.'));
        }

        if ($regle === IpRule::AUTORISEE || ! $this->filtrePaysActif() || $request->is(config('naneu.security.geo.except', []))) {
            return $next($request);
        }

        $pays = $this->geo->pays($ip);

        // Pays inconnu (adresse privée, base absente) : on laisse passer.
        if ($pays !== null && ! in_array($pays, config('naneu.security.geo.countries', []), true)) {
            SecurityLog::evenement('acces.pays_refuse', ['pays' => $pays], $request);

            return $this->refus(__('Naneu Shop est accessible uniquement depuis le Togo. En déplacement ? Demandez à la gérance d\'autoriser votre adresse.'));
        }

        return $next($request);
    }

    private function regle(string $ip): ?string
    {
        try {
            return IpRule::pour($ip);
        } catch (\Throwable $e) {
            // Base indisponible : on ne bloque pas toute la boutique pour autant.
            report($e);

            return null;
        }
    }

    private function filtrePaysActif(): bool
    {
        return (bool) config('naneu.security.geo.enabled') && config('naneu.security.geo.countries');
    }

    private function refus(string $texte): Response
    {
        return response()->view('errors.page', [
            'code'  => 403,
            'titre' => __('Accès restreint'),
            'texte' => $texte,
            'icone' => 'shield-ban',
            'ton'   => 'rose',
        ], 403);
    }
}
