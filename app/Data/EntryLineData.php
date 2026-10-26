<?php

namespace App\Data;

use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Une ligne de bon d'entrée, telle qu'elle sort du formulaire Livewire
 * et telle qu'elle entre dans le StockService.
 */
final readonly class EntryLineData
{
    public function __construct(
        public int $productId,
        public float $quantity,
        public int $purchasePrice,
        public ?Carbon $expiryDate = null,
        public ?string $batchNumber = null,
    ) {
    }

    /** @param  array<string, mixed>  $row */
    public static function fromArray(array $row): self
    {
        return new self(
            productId: (int) $row['product_id'],
            quantity: (float) str_replace(',', '.', (string) $row['quantity']),
            purchasePrice: Money::parse($row['purchase_price'] ?? 0),
            expiryDate: filled($row['expiry_date'] ?? null)
                ? Carbon::parse($row['expiry_date'])
                : null,
            batchNumber: filled($row['batch_number'] ?? null)
                ? trim((string) $row['batch_number'])
                : null,
        );
    }

    public function lineTotal(): int
    {
        return (int) round($this->quantity * $this->purchasePrice);
    }
}
