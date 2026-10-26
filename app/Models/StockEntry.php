<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\HasSequentialReference;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockEntry extends Model
{
    use HasSequentialReference;

    protected $fillable = [
        'reference', 'supplier_id', 'user_id', 'entry_date', 'invoice_number',
        'document_path', 'total_cost', 'note', 'status', 'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_date'   => 'date',
            'total_cost'   => 'integer',
            'status'       => DocumentStatus::class,
            'validated_at' => 'datetime',
        ];
    }

    /** ENT-2026-0001 — numéro attribué à l'enregistrement. */
    protected static function referencePrefix(): string
    {
        return 'ENT';
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<StockEntryLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockEntryLine::class);
    }

    /**
     * Frais rattachés au bon (transport de la marchandise…).
     *
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return HasMany<StockBatch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'source_id')
            ->where('source_type', static::class);
    }

    public function formattedTotal(): string
    {
        return Money::format($this->total_cost);
    }

    /** @param  Builder<StockEntry>  $query */
    public function scopeValidated(Builder $query): void
    {
        $query->where('status', DocumentStatus::Valide->value);
    }

    /** @param  Builder<StockEntry>  $query */
    public function scopeBetween(Builder $query, string $from, string $to): void
    {
        $query->whereBetween('entry_date', [$from, $to]);
    }
}
