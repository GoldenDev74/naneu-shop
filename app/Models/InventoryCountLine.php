<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountLine extends Model
{
    protected $fillable = [
        'inventory_count_id', 'product_id', 'expected_quantity',
        'counted_quantity', 'variance', 'variance_value', 'variance_reason', 'note',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:3',
            'counted_quantity'  => 'decimal:3',
            'variance'          => 'decimal:3',
            'variance_value'    => 'integer',
        ];
    }

    /** @return BelongsTo<InventoryCount, $this> */
    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isCounted(): bool
    {
        return $this->counted_quantity !== null;
    }

    public function hasVariance(): bool
    {
        return $this->isCounted() && abs((float) $this->variance) > 0.0001;
    }
}
