<?php

namespace App\Events;

use App\Models\StockExit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockExitRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public StockExit $exit)
    {
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('naneu.stock')];
    }

    public function broadcastAs(): string
    {
        return 'sortie.enregistree';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id'        => $this->exit->id,
            'reference' => $this->exit->reference,
            'type'      => $this->exit->type->value,
            'lignes'    => $this->exit->lines->count(),
            'total'     => $this->exit->total_sale,
            'cout'      => $this->exit->total_cost,
            'par'       => $this->exit->user?->name,
        ];
    }
}
