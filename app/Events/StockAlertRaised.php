<?php

namespace App\Events;

use App\Models\StockAlert;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Une alerte vient de s'ouvrir. C'est l'évènement qui déclenche à la fois
 * la notification persistée en base et le push temps réel vers la cloche.
 */
class StockAlertRaised implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public StockAlert $alert)
    {
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('naneu.alertes')];
    }

    public function broadcastAs(): string
    {
        return 'alerte.ouverte';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $this->alert->loadMissing('product');

        return [
            'id'         => $this->alert->id,
            'type'       => $this->alert->type->value,
            'type_label' => $this->alert->type->label(),
            'gravite'    => $this->alert->severity->value,
            'message'    => $this->alert->message,
            'produit'    => $this->alert->product?->name,
            'produit_id' => $this->alert->product_id,
            'declenchee' => $this->alert->triggered_at?->toIso8601String(),
        ];
    }
}
