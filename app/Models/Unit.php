<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $fillable = ['name', 'symbol', 'allows_decimal'];

    protected function casts(): array
    {
        return ['allows_decimal' => 'boolean'];
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Formate une quantité selon l'unité : « 3 pcs » pour les unités
     * indivisibles, « 2,500 kg » pour celles qui acceptent les décimales.
     */
    public function formatQuantity(float $quantity): string
    {
        $decimals = $this->allows_decimal ? 3 : 0;

        return \App\Support\Money::number($quantity, $decimals).' '.$this->symbol;
    }
}
