<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'user_email', 'event', 'auditable_type', 'auditable_id',
        'properties', 'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Enregistre un évènement. N'échoue jamais bruyamment : un journal
     * indisponible ne doit pas empêcher une vente de se conclure.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        string $event,
        ?User $user = null,
        ?Model $auditable = null,
        array $properties = [],
        ?Request $request = null,
    ): void {
        try {
            $request ??= request();

            static::create([
                'user_id'        => $user?->id,
                'user_email'     => $user?->email,
                'event'          => $event,
                'auditable_type' => $auditable ? $auditable::class : null,
                'auditable_id'   => $auditable?->getKey(),
                'properties'     => static::scrub($properties),
                'ip_address'     => $request?->ip(),
                'user_agent'     => str($request?->userAgent() ?? '')->limit(250)->value(),
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Filet de sécurité : aucun secret ne doit atterrir dans le journal,
     * même si un appelant passe un tableau de requête complet.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    protected static function scrub(array $properties): array
    {
        $forbidden = [
            'password', 'password_confirmation', 'current_password',
            'remember_token', 'token', 'secret', 'api_key',
        ];

        return collect($properties)
            ->except($forbidden)
            ->map(fn ($value) => is_string($value) ? str($value)->limit(500)->value() : $value)
            ->all();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @param  Builder<AuditLog>  $query */
    public function scopeEvent(Builder $query, string $event): void
    {
        $query->where('event', $event);
    }
}
