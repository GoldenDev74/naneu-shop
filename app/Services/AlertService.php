<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Events\StockAlertRaised;
use App\Events\StockAlertResolved;
use App\Models\Product;
use App\Models\StockAlert;
use App\Models\StockBatch;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Décide quand une alerte s'ouvre et quand elle se referme.
 *
 * Principe : une alerte est le reflet d'un état, pas d'un évènement. Tant
 * que la condition dure, l'alerte reste ouverte sans être renotifiée ;
 * dès que la condition disparaît, elle se referme seule. C'est ce qui
 * évite de noyer le gérant sous des notifications répétées chaque matin
 * pour le même produit.
 */
class AlertService
{
    // =================================================================
    // NIVEAU DE STOCK
    // =================================================================

    /**
     * Réévalue les alertes de niveau d'un produit. Appelée après chaque
     * mouvement, donc conçue pour être bon marché : deux requêtes au plus
     * quand rien ne change.
     *
     * @return Collection<int, StockAlert> alertes nouvellement ouvertes
     */
    public function refreshForProduct(Product $product): Collection
    {
        /** @var Collection<int, StockAlert> $opened */
        $opened = collect();

        // Rupture et stock bas s'excluent mutuellement : ouvrir l'une
        // referme toujours l'autre, sinon le badge afficherait deux
        // alertes contradictoires pour le même produit.
        switch ($product->stockStatus()) {
            case 'rupture':
                $this->resolveOpen($product, AlertType::StockBas);

                $alert = $this->open(
                    $product,
                    AlertType::Rupture,
                    sprintf('%s est en rupture de stock.', $product->name),
                    currentValue: 0,
                    thresholdValue: (float) $product->alert_quantity,
                );
                break;

            case 'bas':
                $this->resolveOpen($product, AlertType::Rupture);

                $alert = $this->open(
                    $product,
                    AlertType::StockBas,
                    sprintf(
                        'Stock bas : %s — il reste %s (seuil %s).',
                        $product->name,
                        $this->formatQuantity($product, (float) $product->quantity_on_hand),
                        $this->formatQuantity($product, (float) $product->alert_quantity),
                    ),
                    currentValue: (float) $product->quantity_on_hand,
                    thresholdValue: (float) $product->alert_quantity,
                );
                break;

            default:
                $this->resolveOpen($product, AlertType::Rupture);
                $this->resolveOpen($product, AlertType::StockBas);
                $alert = null;
        }

        return $alert ? $opened->push($alert) : $opened;
    }

    // =================================================================
    // PÉREMPTION
    // =================================================================

    /**
     * Balaie tous les lots encore en stock et ouvre les alertes de
     * péremption. Idempotent : peut tourner plusieurs fois par jour.
     *
     * @return Collection<int, StockAlert>
     */
    public function scanExpiries(): Collection
    {
        $opened = collect();

        $batches = StockBatch::query()
            ->available()
            ->whereNotNull('expiry_date')
            ->with(['product' => fn ($q) => $q->withTrashed()])
            ->get();

        foreach ($batches as $batch) {
            $product = $batch->product;

            if (! $product || ! $product->is_active) {
                continue;
            }

            $days = $batch->daysUntilExpiry();

            if ($days === null) {
                continue;
            }

            if ($days < 0) {
                // Périmé : l'alerte « proche » n'a plus de sens.
                $this->resolveOpen($product, AlertType::PeremptionProche, $batch);

                $opened = $this->collectOpened($opened, $this->open(
                    $product,
                    AlertType::Perime,
                    sprintf(
                        'PÉRIMÉ depuis %d jour%s : %s — lot %s, %s, valeur %s.',
                        abs($days),
                        abs($days) > 1 ? 's' : '',
                        $product->name,
                        $batch->batch_number,
                        $this->formatQuantity($product, (float) $batch->quantity_remaining),
                        Money::format($batch->remainingValue()),
                    ),
                    batch: $batch,
                    currentValue: (float) $batch->quantity_remaining,
                    daysRemaining: $days,
                ));

                continue;
            }

            $threshold = $product->expiryAlertDays();

            if ($days <= $threshold) {
                $critical = $days <= (int) config('naneu.stock.expiry_critical_days', 7);

                $opened = $this->collectOpened($opened, $this->open(
                    $product,
                    AlertType::PeremptionProche,
                    sprintf(
                        '%s expire dans %d jour%s (%s) — lot %s, %s.',
                        $product->name,
                        $days,
                        $days > 1 ? 's' : '',
                        $batch->expiry_date->format('d/m/Y'),
                        $batch->batch_number,
                        $this->formatQuantity($product, (float) $batch->quantity_remaining),
                    ),
                    batch: $batch,
                    currentValue: (float) $batch->quantity_remaining,
                    daysRemaining: $days,
                    severity: $critical ? AlertSeverity::Critique : AlertSeverity::Avertissement,
                ));
            }
        }

        $this->closeStaleBatchAlerts();

        return $opened->filter();
    }

    /**
     * Referme les alertes de lot dont le lot est désormais vide : la
     * marchandise a été vendue ou sortie, il n'y a plus rien à surveiller.
     */
    protected function closeStaleBatchAlerts(): void
    {
        StockAlert::query()
            ->open()
            ->whereNotNull('stock_batch_id')
            ->whereIn('type', collect(AlertType::cases())->filter->isBatchScoped()->map->value->all())
            ->whereHas('batch', fn ($q) => $q->where('quantity_remaining', '<=', 0))
            ->get()
            ->each(function (StockAlert $alert) {
                $alert->resolve();
                event(new StockAlertResolved($alert));
            });
    }

    // =================================================================
    // PRIMITIVES
    // =================================================================

    /**
     * Ouvre une alerte si elle n'existe pas déjà. Retourne null si une
     * alerte identique est déjà ouverte — c'est le garde anti-spam.
     */
    public function open(
        Product $product,
        AlertType $type,
        string $message,
        ?StockBatch $batch = null,
        ?float $currentValue = null,
        ?float $thresholdValue = null,
        ?int $daysRemaining = null,
        ?AlertSeverity $severity = null,
    ): ?StockAlert {
        $existing = StockAlert::query()
            ->open()
            ->where('product_id', $product->id)
            ->where('type', $type->value)
            ->when($batch, fn ($q) => $q->where('stock_batch_id', $batch->id))
            ->when(! $batch, fn ($q) => $q->whereNull('stock_batch_id'))
            ->first();

        if ($existing) {
            // Le message peut avoir bougé (jours restants qui diminuent)
            // sans que l'alerte doive être rouverte ni renotifiée.
            $existing->forceFill([
                'message'        => $message,
                'current_value'  => $currentValue,
                'days_remaining' => $daysRemaining,
            ])->save();

            return null;
        }

        try {
            $alert = StockAlert::create([
                'type'            => $type,
                'severity'        => $severity ?? $type->defaultSeverity(),
                'product_id'      => $product->id,
                'stock_batch_id'  => $batch?->id,
                'threshold_value' => $thresholdValue,
                'current_value'   => $currentValue,
                'expiry_date'     => $batch?->expiry_date,
                'days_remaining'  => $daysRemaining,
                'message'         => $message,
                'triggered_at'    => now(),
            ]);
        } catch (QueryException $e) {
            // Course entre deux processus (file d'attente + planificateur) :
            // l'index partiel a tranché, l'autre a gagné. Rien à signaler.
            if (str_contains($e->getMessage(), 'alerts_open_unique')) {
                return null;
            }

            throw $e;
        }

        event(new StockAlertRaised($alert));

        return $alert;
    }

    /** Referme l'alerte ouverte correspondante, si elle existe. */
    public function resolveOpen(Product $product, AlertType $type, ?StockBatch $batch = null): void
    {
        StockAlert::query()
            ->open()
            ->where('product_id', $product->id)
            ->where('type', $type->value)
            ->when($batch, fn ($q) => $q->where('stock_batch_id', $batch->id))
            ->when(! $batch, fn ($q) => $q->whereNull('stock_batch_id'))
            ->get()
            ->each(function (StockAlert $alert) {
                $alert->resolve();
                event(new StockAlertResolved($alert));
            });
    }

    /**
     * Passe en revue tout le catalogue. Utilisé par la tâche planifiée du
     * matin et par la commande de diagnostic.
     *
     * @return Collection<int, StockAlert>
     */
    public function scanStockLevels(): Collection
    {
        $opened = collect();

        Product::query()
            ->active()
            ->chunkById(200, function (Collection $products) use (&$opened) {
                foreach ($products as $product) {
                    $opened = $opened->merge($this->refreshForProduct($product));
                }
            });

        return $opened->filter();
    }

    /**
     * open() renvoie null quand l'alerte existait déjà : on ne pousse
     * que les ouvertures réelles, pour que le compteur retourné par les
     * commandes reflète ce qui a vraiment été notifié.
     *
     * @param  Collection<int, StockAlert>  $opened
     * @return Collection<int, StockAlert>
     */
    private function collectOpened(Collection $opened, ?StockAlert $alert): Collection
    {
        return $alert ? $opened->push($alert) : $opened;
    }

    private function formatQuantity(Product $product, float $quantity): string
    {
        $product->loadMissing('unit');

        return $product->unit
            ? $product->unit->formatQuantity($quantity)
            : rtrim(rtrim(number_format($quantity, 3, ',', ' '), '0'), ',');
    }
}
