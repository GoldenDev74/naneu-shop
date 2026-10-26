<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AlertService;
use Illuminate\Console\Command;

class CheckLowStock extends Command
{
    protected $signature = 'naneu:verifier-stock';

    protected $description = 'Contrôle les niveaux de stock et ouvre les alertes de réapprovisionnement';

    public function handle(AlertService $alerts): int
    {
        $this->components->info('Contrôle des niveaux de stock…');

        $opened = $alerts->scanStockLevels();

        $out = Product::query()->active()->outOfStock()->count();
        $low = Product::query()->active()->lowStock()->count();

        $this->components->twoColumnDetail('Produits en rupture', (string) $out);
        $this->components->twoColumnDetail('Produits sous le seuil', (string) $low);
        $this->components->twoColumnDetail('Nouvelles alertes ouvertes', (string) $opened->count());

        return self::SUCCESS;
    }
}
