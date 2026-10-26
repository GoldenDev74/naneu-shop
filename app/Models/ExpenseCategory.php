<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    protected $fillable = ['name', 'slug', 'icon', 'color', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Catégorie « Transport », créée par la migration. */
    public static function transport(): ?self
    {
        return static::where('slug', 'transport')->first();
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @param  Builder<ExpenseCategory>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
