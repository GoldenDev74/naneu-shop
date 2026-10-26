<?php

namespace App\Data;

use App\Support\Money;

/**
 * Une ligne de bon de sortie. Le prix de vente est facultatif : pour une
 * perte ou une casse il n'y a pas de chiffre d'affaires, seulement un coût.
 */
final readonly class ExitLineData
{
    public function __construct(
        public int $productId,
        public float $quantity,
        public ?int $salePrice = null,
    ) {
    }

    /** @param  array<string, mixed>  $row */
    public static function fromArray(array $row): self
    {
        return new self(
            productId: (int) $row['product_id'],
            quantity: (float) str_replace(',', '.', (string) $row['quantity']),
            salePrice: isset($row['sale_price']) ? Money::parse($row['sale_price']) : null,
        );
    }
}
