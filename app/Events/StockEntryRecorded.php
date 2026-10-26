<?php

namespace App\Events;

use App\Models\StockEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockEntryRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public StockEntry $entry)
    {
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('naneu.stock')];
    }

    public function broadcastAs(): string
    {
        return 'entree.enregistree';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id'          => $this->entry->id,
            'reference'   => $this->entry->reference,
            'lignes'      => $this->entry->lines->count(),
            'total'       => $this->entry->total_cost,
            'fournisseur' => $this->entry->supplier?->name,
            'par'         => $this->entry->user?->name,
        ];
    }
}
