<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Filet de rattrapage : recalcule products.quantity_on_hand depuis les
 * lots. Cette colonne n'est qu'un cache de lecture ; si un incident l'a
 * désynchronisée, c'est ici qu'on la répare, sans jamais toucher au
 * grand livre.
 */
class RecomputeStock extends Command
{
    protected $signature = 'naneu:recalculer-stock {--fix : Écrire les corrections au lieu de seulement les lister}';

    protected $description = 'Vérifie et répare la quantité en stock calculée depuis les lots';

    public function handle(StockService $stock): int
    {
        $ecarts = [];

        Product::query()->chunkById(200, function (Collection $products) use ($stock, &$ecarts) {
            foreach ($products as $product) {
                $cache = (float) $product->quantity_on_hand;
                $reel  = (float) $product->batches()->sum('quantity_remaining');

                if (abs($cache - $reel) < 0.0001) {
                    continue;
                }

                $ecarts[] = [
                    $product->sku,
                    $product->name,
                    number_format($cache, 3, ',', ' '),
                    number_format($reel, 3, ',', ' '),
                ];

                if ($this->option('fix')) {
                    $stock->recomputeOnHand($product);
                }
            }
        });

        if ($ecarts === []) {
            $this->components->info('Aucun écart : le stock calculé correspond aux lots.');

            return self::SUCCESS;
        }

        $this->table(['SKU', 'Produit', 'Stock affiché', 'Stock réel (lots)'], $ecarts);

        if ($this->option('fix')) {
            $this->components->info(count($ecarts).' produit(s) corrigé(s).');
        } else {
            $this->components->warn(
                count($ecarts).' écart(s) détecté(s). Relancez avec --fix pour corriger.'
            );
        }

        return self::SUCCESS;
    }
}
