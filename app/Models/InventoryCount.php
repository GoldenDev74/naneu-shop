<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\HasSequentialReference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCount extends Model
{
    use HasSequentialReference;

    protected $fillable = [
        'reference', 'user_id', 'count_date', 'scope', 'category_id',
        'status', 'total_variance_value', 'note', 'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'count_date'           => 'date',
            'status'               => DocumentStatus::class,
            'total_variance_value' => 'integer',
            'validated_at'         => 'datetime',
        ];
    }

    /** INV-2026-0001 — numéro attribué à l'enregistrement. */
    protected static function referencePrefix(): string
    {
        return 'INV';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<InventoryCountLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCountLine::class);
    }

    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Brouillon;
    }

    /** @param  Builder<InventoryCount>  $query */
    public function scopeDraft(Builder $query): void
    {
        $query->where('status', DocumentStatus::Brouillon->value);
    }
}
