<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockExitLine extends Model
{
    protected $fillable = [
        'stock_exit_id', 'product_id', 'quantity',
        'sale_price', 'line_total', 'vat_amount', 'cost_total', 'margin',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'decimal:3',
            'sale_price' => 'integer',
            'line_total' => 'integer',
            'vat_amount' => 'integer',
            'cost_total' => 'integer',
            'margin'     => 'integer',
        ];
    }

    /** @return BelongsTo<StockExit, $this> */
    public function stockExit(): BelongsTo
    {
        return $this->belongsTo(StockExit::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
