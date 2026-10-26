<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un lot = une réception d'un produit donné, à un prix donné, avec sa
 * propre date de péremption. C'est l'unité atomique du FIFO : les sorties
 * vident les lots un par un, du plus ancien au plus récent.
 */
class StockBatch extends Model
{
    protected $fillable = [
        'batch_number', 'product_id', 'stock_entry_id', 'supplier_id',
        'purchase_price', 'quantity_received', 'quantity_remaining',
        'received_at', 'expiry_date', 'expiry_estimated', 'depleted_at',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price'     => 'integer',
            'quantity_received'  => 'decimal:3',
            'quantity_remaining' => 'decimal:3',
            'received_at'        => 'date',
            'expiry_date'        => 'date',
            'expiry_estimated'   => 'boolean',
            'depleted_at'        => 'datetime',
        ];
    }

    /** Génère un numéro de lot lisible : LOT-2026-000123. */
    public static function nextBatchNumber(): string
    {
        $year = now()->year;
        $sequence = static::whereYear('created_at', $year)->count() + 1;

        return sprintf('LOT-%d-%06d', $year, $sequence);
    }

    // -----------------------------------------------------------------
    // Relations
    // -----------------------------------------------------------------

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<StockEntry, $this> */
    public function stockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // -----------------------------------------------------------------
    // État
    // -----------------------------------------------------------------

    public function isDepleted(): bool
    {
        return (float) $this->quantity_remaining <= 0;
    }

    public function quantityConsumed(): float
    {
        return (float) $this->quantity_received - (float) $this->quantity_remaining;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    /** Jours restants avant péremption. Négatif si déjà périmé. */
    public function daysUntilExpiry(): ?int
    {
        if ($this->expiry_date === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false);
    }

    public function isExpiringSoon(?int $withinDays = null): bool
    {
        $days = $this->daysUntilExpiry();

        if ($days === null || $days < 0) {
            return false;
        }

        $threshold = $withinDays ?? $this->product?->expiryAlertDays()
            ?? (int) config('naneu.stock.expiry_alert_days', 30);

        return $days <= $threshold;
    }

    /** Valeur d'achat de ce qu'il reste dans le lot. */
    public function remainingValue(): int
    {
        return (int) round((float) $this->quantity_remaining * $this->purchase_price);
    }

    public function formattedPurchasePrice(): string
    {
        return Money::format($this->purchase_price);
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    /** @param  Builder<StockBatch>  $query */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('quantity_remaining', '>', 0);
    }

    /** @param  Builder<StockBatch>  $query */
    public function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', now());
    }

    /**
     * Lots déjà reçus à une date donnée : une sortie du 3 octobre ne peut
     * pas puiser dans une livraison du 6.
     *
     * @param  Builder<StockBatch>  $query
     */
    public function scopeReceivedBy(Builder $query, \DateTimeInterface $date): void
    {
        $query->whereDate('received_at', '<=', $date);
    }

    /**
     * Lots encore bons à une date donnée. La date de péremption elle-même
     * est incluse : le produit se vend jusqu'à ce jour-là.
     *
     * @param  Builder<StockBatch>  $query
     */
    public function scopeNotExpiredOn(Builder $query, \DateTimeInterface $date): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', $date));
    }

    /** @param  Builder<StockBatch>  $query */
    public function scopeExpiredOn(Builder $query, \DateTimeInterface $date): void
    {
        $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', $date);
    }

    /** @param  Builder<StockBatch>  $query */
    public function scopeExpiringWithin(Builder $query, int $days): void
    {
        $query->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', now())
            ->whereDate('expiry_date', '<=', now()->addDays($days));
    }

    /**
     * Ordre de consommation. C'est ici, et nulle part ailleurs, que la
     * stratégie FIFO ou FEFO se matérialise.
     *
     * FIFO : premier reçu, premier sorti — ordre comptable strict.
     * FEFO : premier périmé, premier sorti — minimise les pertes.
     *
     * L'identifiant sert de départage pour rendre l'ordre déterministe
     * quand deux lots partagent la même date.
     *
     * @param  Builder<StockBatch>  $query
     */
    public function scopeInConsumptionOrder(Builder $query, ?string $strategy = null): void
    {
        $strategy = $strategy ?? config('naneu.stock.strategy', 'fifo');

        if ($strategy === 'fefo') {
            // Les lots sans date de péremption passent en dernier : ils ne
            // risquent rien, autant écouler d'abord ce qui peut se perdre.
            $query->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expiry_date')
                ->orderBy('received_at')
                ->orderBy('id');

            return;
        }

        $query->orderBy('received_at')->orderBy('id');
    }
}
