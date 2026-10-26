<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAlert extends Model
{
    protected $fillable = [
        'type', 'severity', 'product_id', 'stock_batch_id',
        'threshold_value', 'current_value', 'expiry_date', 'days_remaining',
        'message', 'triggered_at', 'notified_at',
        'acknowledged_at', 'acknowledged_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type'            => AlertType::class,
            'severity'        => AlertSeverity::class,
            'threshold_value' => 'decimal:3',
            'current_value'   => 'decimal:3',
            'expiry_date'     => 'date',
            'days_remaining'  => 'integer',
            'triggered_at'    => 'datetime',
            'notified_at'     => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at'     => 'datetime',
        ];
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
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    public function acknowledge(User $user): void
    {
        $this->forceFill([
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
        ])->save();
    }

    public function resolve(): void
    {
        $this->forceFill(['resolved_at' => now()])->save();
    }

    /** @param  Builder<StockAlert>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }

    /** @param  Builder<StockAlert>  $query */
    public function scopeUnacknowledged(Builder $query): void
    {
        $query->whereNull('resolved_at')->whereNull('acknowledged_at');
    }

    /** @param  Builder<StockAlert>  $query */
    public function scopeCritical(Builder $query): void
    {
        $query->where('severity', AlertSeverity::Critique->value);
    }

    /** @param  Builder<StockAlert>  $query */
    public function scopeOfType(Builder $query, AlertType $type): void
    {
        $query->where('type', $type->value);
    }

    /**
     * Tri d'affichage : le plus grave d'abord, puis le plus récent.
     * SQLite n'a pas de FIELD(), d'où le CASE explicite.
     *
     * @param  Builder<StockAlert>  $query
     */
    public function scopeBySeverity(Builder $query): void
    {
        $query->orderByRaw(
            "CASE severity WHEN 'critique' THEN 1 WHEN 'avertissement' THEN 2 ELSE 3 END"
        )->orderByDesc('triggered_at');
    }
}
