<?php

namespace App\Listeners;

use App\Events\StockEntryRecorded;
use App\Events\StockExitRecorded;
use App\Models\AuditLog;
use App\Services\StaffActivityNotifier;

class RecordStockMovementAudit
{
    public function handleEntry(StockEntryRecorded $event): void
    {
        AuditLog::record('stock.entree', $event->entry->user, $event->entry, [
            'reference'   => $event->entry->reference,
            'lignes'      => $event->entry->lines->count(),
            'total'       => $event->entry->total_cost,
            'fournisseur' => $event->entry->supplier?->name,
        ]);

        app(StaffActivityNotifier::class)->entree($event->entry);
    }

    public function handleExit(StockExitRecorded $event): void
    {
        AuditLog::record('stock.sortie', $event->exit->user, $event->exit, [
            'reference' => $event->exit->reference,
            'type'      => $event->exit->type->value,
            'lignes'    => $event->exit->lines->count(),
            'cout'      => $event->exit->total_cost,
            'vente'     => $event->exit->total_sale,
        ]);

        app(StaffActivityNotifier::class)->sortie($event->exit);
    }

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            StockEntryRecorded::class => 'handleEntry',
            StockExitRecorded::class  => 'handleExit',
        ];
    }
}
