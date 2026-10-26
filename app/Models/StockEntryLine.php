<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockEntryLine extends Model
{
    protected $fillable = [
        'stock_entry_id', 'product_id', 'stock_batch_id', 'quantity',
        'purchase_price', 'line_total', 'expiry_date', 'batch_number',
    ];

    protected function casts(): array
    {
        return [
            'quantity'       => 'decimal:3',
            'purchase_price' => 'integer',
            'line_total'     => 'integer',
            'expiry_date'    => 'date',
        ];
    }

    /** @return BelongsTo<StockEntry, $this> */
    public function stockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class);
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
}
