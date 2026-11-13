<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Règle manuelle sur une adresse IP ou une plage CIDR :
 *  - bloquee  : refusée sur tout le site ;
 *  - autorisee : admise même hors des pays autorisés (gérant en déplacement).
 */
class IpRule extends Model
{
    use MassPrunable;

    public const BLOQUEE = 'bloquee';

    public const AUTORISEE = 'autorisee';

    private const CLE_CACHE = 'naneu.ip-rules';

    protected $fillable = ['ip', 'type', 'note', 'expires_at', 'created_by'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CLE_CACHE));
        static::deleted(fn () => Cache::forget(self::CLE_CACHE));
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActives(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** Les règles expirées sont supprimées par `model:prune` (chaque jour). */
    public function prunable(): Builder
    {
        return static::query()->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    /** Type de règle qui s'applique à cette adresse, ou null. Le blocage prime. */
    public static function pour(string $ip): ?string
    {
        $regles = Cache::remember(self::CLE_CACHE, 60, fn () => static::query()->actives()
            ->get(['ip', 'type', 'expires_at'])
            ->groupBy('type')
            ->map(fn ($groupe) => $groupe->pluck('ip')->all())
            ->all());

        foreach ([self::BLOQUEE, self::AUTORISEE] as $type) {
            if (! empty($regles[$type]) && IpUtils::checkIp($ip, $regles[$type])) {
                return $type;
            }
        }

        return null;
    }

    /** Adresse IPv4/IPv6 ou plage CIDR valide. */
    public static function adresseValide(string $valeur): bool
    {
        [$ip, $masque] = array_pad(explode('/', trim($valeur), 2), 2, null);

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if ($masque === null) {
            return true;
        }

        $max = str_contains($ip, ':') ? 128 : 32;

        return ctype_digit($masque) && (int) $masque >= 8 && (int) $masque <= $max;
    }
}
