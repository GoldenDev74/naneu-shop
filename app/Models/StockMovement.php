<?php

namespace App\Models;

use App\Enums\MovementDirection;
use App\Enums\MovementReason;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Ligne du grand livre de stock. Immuable par convention : l'application
 * n'y fait que des insertions. Une erreur se corrige par un mouvement
 * inverse, jamais par une modification — c'est ce qui rend la piste
 * d'audit opposable.
 */
class StockMovement extends Model
{
    protected $fillable = [
        'product_id', 'stock_batch_id', 'direction', 'reason', 'quantity',
        'unit_cost', 'total_cost', 'quantity_before', 'quantity_after',
        'source_type', 'source_id', 'user_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'direction'       => MovementDirection::class,
            'reason'          => MovementReason::class,
            'quantity'        => 'decimal:3',
            'unit_cost'       => 'integer',
            'total_cost'      => 'integer',
            'quantity_before' => 'decimal:3',
            'quantity_after'  => 'decimal:3',
            'occurred_at'     => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException(
                'Les mouvements de stock sont immuables. Enregistrez un mouvement inverse.'
            );
        });

        static::deleting(function () {
            throw new \LogicException(
                'Les mouvements de stock ne peuvent pas être supprimés.'
            );
        });
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<StockBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function signedQuantity(): float
    {
        return (float) $this->quantity * $this->direction->sign();
    }

    public function formattedCost(): string
    {
        return Money::format($this->total_cost);
    }

    /** @param  Builder<StockMovement>  $query */
    public function scopeIncoming(Builder $query): void
    {
        $query->where('direction', MovementDirection::In->value);
    }

    /** @param  Builder<StockMovement>  $query */
    public function scopeOutgoing(Builder $query): void
    {
        $query->where('direction', MovementDirection::Out->value);
    }

    /** @param  Builder<StockMovement>  $query */
    public function scopeBetween(Builder $query, string $from, string $to): void
    {
        $query->whereBetween('occurred_at', [$from.' 00:00:00', $to.' 23:59:59']);
    }
}
