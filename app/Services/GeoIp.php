<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use MaxMind\Db\Reader;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Pays d'une adresse IP, lu dans une base .mmdb locale (DB-IP Lite ou
 * GeoLite2 : même format). Aucun appel réseau : la réponse est instantanée
 * et l'adresse des visiteurs ne quitte pas le serveur.
 */
class GeoIp
{
    private ?Reader $reader = null;

    private bool $ouvert = false;

    /** @var array<string, ?string> */
    private array $cache = [];

    public function __construct(private readonly string $chemin) {}

    /**
     * Code pays ISO (« TG ») ou null : adresse privée ou locale (Tailscale,
     * Docker, poste de dev), inconnue de la base, ou base absente.
     */
    public function pays(?string $ip): ?string
    {
        if (! $ip || $this->estPrivee($ip)) {
            return null;
        }

        if (array_key_exists($ip, $this->cache)) {
            return $this->cache[$ip];
        }

        $code = null;

        try {
            $donnees = $this->lecteur()?->get($ip);
            $code = $donnees['country']['iso_code'] ?? null;
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->cache[$ip] = $code ? strtoupper($code) : null;
    }

    public function disponible(): bool
    {
        return $this->lecteur() !== null;
    }

    /** Plages privées, locales et CGNAT (100.64/10 = Tailscale). */
    public function estPrivee(string $ip): bool
    {
        return IpUtils::checkIp($ip, [
            '127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16',
            '100.64.0.0/10', '169.254.0.0/16', '::1/128', 'fc00::/7', 'fe80::/10',
        ]);
    }

    private function lecteur(): ?Reader
    {
        if ($this->ouvert) {
            return $this->reader;
        }

        $this->ouvert = true;

        if (! is_readable($this->chemin)) {
            Log::warning('Base de géolocalisation introuvable : filtrage par pays inactif.', ['chemin' => $this->chemin]);

            return null;
        }

        try {
            return $this->reader = new Reader($this->chemin);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
