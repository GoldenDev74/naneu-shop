<?php

namespace App\Events;

use App\Models\StockAlert;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** La condition qui avait déclenché l'alerte a disparu. */
class StockAlertResolved implements ShouldBroadcast
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
        return 'alerte.resolue';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id'         => $this->alert->id,
            'produit_id' => $this->alert->product_id,
            'type'       => $this->alert->type->value,
        ];
    }
}
