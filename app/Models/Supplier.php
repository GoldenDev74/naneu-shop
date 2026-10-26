<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'contact_name', 'phone', 'email', 'address',
        'city', 'is_local', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_local'  => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<StockEntry, $this> */
    public function stockEntries(): HasMany
    {
        return $this->hasMany(StockEntry::class);
    }

    /** @return HasMany<StockBatch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'default_supplier_id');
    }

    /** @param  Builder<Supplier>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<Supplier>  $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $q->whereLikeLoose('name', $term)
                ->orWhereLikeLoose('contact_name', $term)
                ->orWhereLike('phone', "%{$term}%");
        });
    }
}
