<?php

namespace App\Exceptions;

use App\Models\Product;
use App\Support\Money;
use RuntimeException;

/**
 * Pas assez de stock utilisable pour une sortie. Le message précise ce qui
 * manque réellement : un stock périmé (bloqué) ou une marchandise reçue
 * après la date de la sortie ne compte pas comme « disponible ».
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly Product $product,
        public readonly float $requested,
        public readonly float $available,
        public readonly float $expired = 0.0,
        public readonly float $notYetReceived = 0.0,
    ) {
        $qte = fn (float $q) => rtrim(rtrim(Money::number($q, 3), '0'), ',.');

        $message = __('Stock insuffisant pour « :nom » : :demande demandé, :dispo disponible.', [
            'nom'     => $product->name,
            'demande' => $qte($requested),
            'dispo'   => $qte($available),
        ]);

        if ($expired > 1e-6) {
            $message .= ' '.__(':qte périmé(s) en stock : vente bloquée, retirez-les du rayon.', ['qte' => $qte($expired)]);
        }

        if ($notYetReceived > 1e-6) {
            $message .= ' '.__(':qte reçu(s) après la date de la sortie.', ['qte' => $qte($notYetReceived)]);
        }

        parent::__construct($message);
    }
}
