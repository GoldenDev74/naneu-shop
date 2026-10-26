<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Émis à chaque variation du stock d'un produit. Diffusé pour que les
 * écrans ouverts (tableau de bord, liste produits) se mettent à jour
 * sans que personne n'ait à rafraîchir la page.
 */
class StockLevelChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Product $product,
        public float $previousQuantity,
        public float $newQuantity,
    ) {
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('naneu.stock')];
    }

    public function broadcastAs(): string
    {
        return 'stock.modifie';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'produit_id' => $this->product->id,
            'nom'        => $this->product->name,
            'sku'        => $this->product->sku,
            'avant'      => round($this->previousQuantity, 3),
            'apres'      => round($this->newQuantity, 3),
            'statut'     => $this->product->stockStatus(),
        ];
    }
}
