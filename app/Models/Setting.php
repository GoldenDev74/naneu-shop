<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key', 'value', 'type', 'group', 'label', 'description', 'is_public',
    ];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Le cache vidé, la configuration est réappliquée tout de suite : la
        // suite de la requête voit déjà le nouveau réglage.
        static::saved(function () {
            Cache::forget('naneu.settings');
            \App\Support\ShopSettings::apply();
        });
        static::deleted(function () {
            Cache::forget('naneu.settings');
            \App\Support\ShopSettings::apply();
        });
    }

    /**
     * Les paramètres sont lus à presque chaque requête : on les charge
     * une fois par cycle et on invalide le cache à l'écriture.
     *
     * @return array<string, mixed>
     */
    public static function cached(): array
    {
        return Cache::rememberForever('naneu.settings', function () {
            return static::query()
                ->get()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $s->castValue()])
                ->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::cached()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $setting = static::firstOrNew(['key' => $key]);

        $setting->fill([
            'value' => is_array($value) ? json_encode($value) : (string) $value,
            'type'  => $setting->type ?: match (true) {
                is_bool($value)  => 'bool',
                is_int($value)   => 'int',
                is_array($value) => 'json',
                default          => 'string',
            },
            'label' => $setting->label ?: $key,
        ])->save();
    }

    public function castValue(): mixed
    {
        return match ($this->type) {
            'int'  => (int) $this->value,
            'bool' => filter_var($this->value, FILTER_VALIDATE_BOOL),
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }
}
