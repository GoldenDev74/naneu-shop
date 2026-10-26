<?php

namespace App\Console\Commands;

use App\Models\StockBatch;
use App\Services\AlertService;
use Illuminate\Console\Command;

class CheckExpiries extends Command
{
    protected $signature = 'naneu:verifier-peremptions';

    protected $description = 'Balaie les lots en stock et ouvre les alertes de péremption';

    public function handle(AlertService $alerts): int
    {
        $this->components->info('Analyse des dates de péremption…');

        $opened = $alerts->scanExpiries();

        $expired = StockBatch::query()->available()->expired()->count();
        $soon = StockBatch::query()
            ->available()
            ->expiringWithin((int) config('naneu.stock.expiry_alert_days', 30))
            ->count();

        $this->components->twoColumnDetail('Lots périmés en stock', (string) $expired);
        $this->components->twoColumnDetail('Lots proches de la péremption', (string) $soon);
        $this->components->twoColumnDetail('Nouvelles alertes ouvertes', (string) $opened->count());

        if ($expired > 0) {
            $this->components->warn(
                "{$expired} lot(s) périmé(s) sont encore comptés dans le stock. ".
                'Lancez « naneu:sortir-perimes » pour les retirer.'
            );
        }

        return self::SUCCESS;
    }
}
