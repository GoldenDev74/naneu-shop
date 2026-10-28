<?php

namespace App\Services;

use App\Data\EntryLineData;
use App\Data\ExitLineData;
use App\Enums\AlertType;
use App\Enums\DocumentStatus;
use App\Enums\ExitType;
use App\Enums\MovementDirection;
use App\Enums\MovementReason;
use App\Events\StockEntryRecorded;
use App\Events\StockExitRecorded;
use App\Events\StockLevelChanged;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seul point d'entrée autorisé pour modifier le stock.
 *
 * Règle de conception : aucun autre code — contrôleur, composant Livewire,
 * commande — n'écrit directement dans stock_batches, stock_movements ou
 * products.quantity_on_hand. Tout passe ici, dans une transaction, pour
 * que le grand livre reste cohérent avec les lots en toutes circonstances.
 */
class StockService
{
    public function __construct(
        private readonly AlertService $alerts,
    ) {
    }

    // =================================================================
    // ENTRÉES
    // =================================================================

    /**
     * Enregistre une réception marchandise. Chaque ligne devient un lot
     * distinct : c'est ce qui rend le FIFO possible et la marge exacte.
     *
     * @param  array<int, EntryLineData>  $lines
     * @param  array<string, mixed>  $attributes
     */
    public function receive(User $user, array $lines, array $attributes = []): StockEntry
    {
        if ($lines === []) {
            throw new \InvalidArgumentException('Un bon d\'entrée doit comporter au moins une ligne.');
        }

        return DB::transaction(function () use ($user, $lines, $attributes) {
            $entry = StockEntry::create([
                'reference'      => $attributes['reference'] ?? StockEntry::nextReference(),
                'supplier_id'    => $attributes['supplier_id'] ?? null,
                'user_id'        => $user->id,
                'entry_date'     => $attributes['entry_date'] ?? now()->toDateString(),
                'invoice_number' => $attributes['invoice_number'] ?? null,
                'document_path'  => $attributes['document_path'] ?? null,
                'note'           => $attributes['note'] ?? null,
                'status'         => DocumentStatus::Valide,
                'validated_at'   => now(),
                'total_cost'     => 0,
            ]);

            $total = 0;

            foreach ($lines as $line) {
                $product = Product::lockForUpdate()->findOrFail($line->productId);

                $this->guardQuantity($line->quantity);

                if ($line->purchasePrice <= 0) {
                    throw new \InvalidArgumentException("Le prix d'achat doit être strictement positif ({$product->name}).");
                }

                $batch = $this->createBatch($product, $entry, $line);

                $entry->lines()->create([
                    'product_id'     => $product->id,
                    'stock_batch_id' => $batch->id,
                    'quantity'       => $line->quantity,
                    'purchase_price' => $line->purchasePrice,
                    'line_total'     => $line->lineTotal(),
                    'expiry_date'    => $batch->expiry_date?->toDateString(),
                    'batch_number'   => $batch->batch_number,
                ]);

                $before = (float) $product->quantity_on_hand;
                $after  = $before + $line->quantity;

                $this->writeMovement(
                    product: $product,
                    batch: $batch,
                    direction: MovementDirection::In,
                    reason: MovementReason::Achat,
                    quantity: $line->quantity,
                    unitCost: $line->purchasePrice,
                    before: $before,
                    after: $after,
                    source: $entry,
                    user: $user,
                    occurredAt: Carbon::parse($entry->entry_date),
                );

                $product->forceFill([
                    'quantity_on_hand'    => $after,
                    'last_purchase_price' => $line->purchasePrice,
                ])->save();

                $total += $line->lineTotal();

                // Une réception peut refermer une alerte de stock bas ou de
                // rupture : on réévalue le produit tout de suite plutôt que
                // d'attendre le passage du planificateur le lendemain matin.
                $this->alerts->refreshForProduct($product->fresh());

                // Même logique pour la péremption : un lot reçu déjà proche
                // de sa date doit être signalé dès maintenant.
                $this->alerts->checkBatchExpiry($batch->setRelation('product', $product));

                event(new StockLevelChanged($product->fresh(), $before, $after));
            }

            $entry->forceFill(['total_cost' => $total])->save();

            event(new StockEntryRecorded($entry->fresh(['lines', 'supplier', 'user'])));

            return $entry;
        });
    }

    /**
     * Applique la durée de conservation d'un produit à ses lots encore en
     * stock qui n'ont pas de date : la gérante la renseigne une fois, les
     * oignons déjà en rayon entrent aussitôt dans le suivi des péremptions.
     *
     * @return int nombre de lots datés
     */
    public function applyShelfLife(Product $product): int
    {
        if (! $product->is_perishable || ! $product->shelf_life_days) {
            return 0;
        }

        $lots = StockBatch::query()
            ->where('product_id', $product->id)
            ->available()
            ->where(fn (Builder $q) => $q->whereNull('expiry_date')->orWhere('expiry_estimated', true))
            ->get();

        foreach ($lots as $lot) {
            $lot->forceFill([
                'expiry_date'      => $product->estimatedExpiryFor($lot->received_at)->toDateString(),
                'expiry_estimated' => true,
            ])->save();

            $this->alerts->checkBatchExpiry($lot->setRelation('product', $product));
        }

        if ($lots->isNotEmpty()) {
            $this->alerts->refreshForProduct($product->fresh());
        }

        return $lots->count();
    }

    private function createBatch(Product $product, StockEntry $entry, EntryLineData $line): StockBatch
    {
        $batchNumber = $line->batchNumber ?: StockBatch::nextBatchNumber();

        // Un même bon peut contenir deux fois le même produit avec le même
        // numéro de lot saisi à la main ; la contrainte UNIQUE
        // (product_id, batch_number) l'interdit, donc on suffixe.
        $candidate = $batchNumber;
        $suffix = 2;

        while (StockBatch::where('product_id', $product->id)
            ->where('batch_number', $candidate)
            ->exists()) {
            $candidate = $batchNumber.'-'.$suffix++;
        }

        // Pas de date imprimée sur un produit frais : on l'estime à partir
        // de sa durée de conservation, pour que FEFO, alertes et blocage
        // de la vente des périmés s'appliquent aussi aux oignons.
        $expiry = $line->expiryDate ?? $product->estimatedExpiryFor(Carbon::parse($entry->entry_date));

        return StockBatch::create([
            'batch_number'       => $candidate,
            'product_id'         => $product->id,
            'stock_entry_id'     => $entry->id,
            'supplier_id'        => $entry->supplier_id,
            'purchase_price'     => $line->purchasePrice,
            'quantity_received'  => $line->quantity,
            'quantity_remaining' => $line->quantity,
            'received_at'        => $entry->entry_date,
            'expiry_date'        => $expiry?->toDateString(),
            'expiry_estimated'   => $line->expiryDate === null && $expiry !== null,
        ]);
    }

    // =================================================================
    // SORTIES
    // =================================================================

    /**
     * Enregistre une sortie de stock, quel qu'en soit le motif.
     *
     * @param  array<int, ExitLineData>  $lines
     * @param  array<string, mixed>  $attributes
     *
     * @throws InsufficientStockException
     */
    public function issue(User $user, ExitType $type, array $lines, array $attributes = []): StockExit
    {
        if ($lines === []) {
            throw new \InvalidArgumentException('Un bon de sortie doit comporter au moins une ligne.');
        }

        try {
            return $this->recordExit($user, $type, $lines, $attributes);
        } catch (InsufficientStockException $e) {
            // Signalé hors transaction : la transaction annulée aurait
            // emporté l'alerte avec elle.
            if ($e->expired > 1e-6) {
                $this->signalBlockedExpiredSale($e->product, $user);
            }

            throw $e;
        }
    }

    /**
     * @param  array<int, ExitLineData>  $lines
     * @param  array<string, mixed>  $attributes
     */
    private function recordExit(User $user, ExitType $type, array $lines, array $attributes): StockExit
    {
        return DB::transaction(function () use ($user, $type, $lines, $attributes) {
            $exit = StockExit::create([
                'reference'    => $attributes['reference'] ?? StockExit::nextReference(),
                'type'         => $type,
                'user_id'      => $user->id,
                'exit_date'    => $attributes['exit_date'] ?? now()->toDateString(),
                'note'         => $attributes['note'] ?? null,
                'status'       => DocumentStatus::Valide,
                'payment_method' => $attributes['payment_method'] ?? null,
                'total_cost'   => 0,
                'total_sale'   => 0,
                'total_vat'    => 0,
                'total_margin' => 0,
            ]);

            $totalCost = 0;
            $totalSale = 0;
            $totalVat  = 0;

            foreach ($lines as $line) {
                $product = Product::lockForUpdate()->findOrFail($line->productId);

                $this->guardQuantity($line->quantity);

                $before = (float) $product->quantity_on_hand;

                // Consommation FIFO : la ligne peut mordre sur plusieurs lots.
                $allocations = $this->consume(
                    product: $product,
                    quantity: $line->quantity,
                    reason: $type->toReason(),
                    source: $exit,
                    user: $user,
                    occurredAt: Carbon::parse($exit->exit_date),
                );

                $lineCost = array_sum(array_map(
                    fn (array $a) => $a['cost'],
                    $allocations
                ));

                // Prix TTC : c'est ce que paie le client. La TVA en est
                // extraite ; la marge se calcule sur le hors-taxe.
                $unitSalePrice = $type->generatesRevenue()
                    ? ($line->salePrice ?? $product->sale_price)
                    : 0;

                $lineSale = (int) round($line->quantity * $unitSalePrice);
                $lineVat  = Money::vatFromInclusive($lineSale, $product->effectiveVatRate());

                $exit->lines()->create([
                    'product_id' => $product->id,
                    'quantity'   => $line->quantity,
                    'sale_price' => $unitSalePrice,
                    'line_total' => $lineSale,
                    'vat_amount' => $lineVat,
                    'cost_total' => $lineCost,
                    'margin'     => $lineSale - $lineVat - $lineCost,
                ]);

                $after = $before - $line->quantity;

                $product->forceFill(['quantity_on_hand' => $after])->save();

                $totalCost += $lineCost;
                $totalSale += $lineSale;
                $totalVat  += $lineVat;

                $fresh = $product->fresh();
                $this->alerts->refreshForProduct($fresh);

                event(new StockLevelChanged($fresh, $before, $after));
            }

            $exit->forceFill([
                'total_cost'   => $totalCost,
                'total_sale'   => $totalSale,
                'total_vat'    => $totalVat,
                'total_margin' => $totalSale - $totalVat - $totalCost,
            ])->save();

            event(new StockExitRecorded($exit->fresh(['lines', 'user'])));

            return $exit;
        });
    }

    /**
     * Cœur du FIFO. Vide les lots dans l'ordre de consommation jusqu'à
     * couvrir la quantité demandée, en écrivant un mouvement par lot
     * touché — c'est ce détail qui permet de retrouver plus tard à quel
     * prix d'achat exact chaque unité vendue avait été payée.
     *
     * @return array<int, array{batch: StockBatch, quantity: float, cost: int}>
     *
     * @throws InsufficientStockException
     */
    public function consume(
        Product $product,
        float $quantity,
        MovementReason $reason,
        ?object $source = null,
        ?User $user = null,
        ?Carbon $occurredAt = null,
    ): array {
        $this->guardQuantity($quantity);

        $occurredAt ??= now();

        $batches = $this->consumptionQueue($product, $reason, $occurredAt)
            ->lockForUpdate()
            ->get();

        $available = (float) $batches->sum(fn (StockBatch $b) => (float) $b->quantity_remaining);

        if (! config('naneu.stock.allow_negative') && $available + 1e-6 < $quantity) {
            $detail = $this->availability($product, $reason, $occurredAt);

            throw new InsufficientStockException(
                $product, $quantity, $available, $detail['perime'], $detail['pas_encore_recu'],
            );
        }

        $remaining   = $quantity;
        $allocations = [];
        $runningQty  = (float) $product->quantity_on_hand;

        foreach ($batches as $batch) {
            if ($remaining <= 1e-6) {
                break;
            }

            $taken = min((float) $batch->quantity_remaining, $remaining);
            $cost  = (int) round($taken * $batch->purchase_price);

            $batch->forceFill([
                'quantity_remaining' => (float) $batch->quantity_remaining - $taken,
            ]);

            if ((float) $batch->quantity_remaining <= 1e-6) {
                $batch->quantity_remaining = 0;
                $batch->depleted_at = now();
            }

            $batch->save();

            $this->writeMovement(
                product: $product,
                batch: $batch,
                direction: MovementDirection::Out,
                reason: $reason,
                quantity: $taken,
                unitCost: $batch->purchase_price,
                before: $runningQty,
                after: $runningQty - $taken,
                source: $source,
                user: $user,
                occurredAt: $occurredAt,
            );

            $runningQty -= $taken;
            $remaining  -= $taken;

            $allocations[] = ['batch' => $batch, 'quantity' => $taken, 'cost' => $cost];
        }

        // Stock négatif autorisé et lots épuisés : on impute le reliquat au
        // dernier prix d'achat connu pour ne pas perdre la valorisation.
        if ($remaining > 1e-6) {
            $unitCost = $product->last_purchase_price;
            $cost = (int) round($remaining * $unitCost);

            $this->writeMovement(
                product: $product,
                batch: null,
                direction: MovementDirection::Out,
                reason: $reason,
                quantity: $remaining,
                unitCost: $unitCost,
                before: $runningQty,
                after: $runningQty - $remaining,
                source: $source,
                user: $user,
                occurredAt: $occurredAt,
            );

            $allocations[] = ['batch' => null, 'quantity' => $remaining, 'cost' => $cost];
        }

        return $allocations;
    }

    /**
     * Lots dans lesquels une sortie peut puiser, dans l'ordre où elle les
     * videra. Deux règles s'ajoutent au FIFO / FEFO :
     *
     * - chronologie : seuls les lots reçus au plus tard à la date de la
     *   sortie comptent ;
     * - péremption : une vente ou une consommation interne ignore les lots
     *   périmés à cette date ; les autres sorties (perte, péremption,
     *   retour…) les prennent en premier.
     *
     * L'aperçu du formulaire de sortie utilise la même file, pour annoncer
     * exactement ce que l'enregistrement fera.
     *
     * @return Builder<StockBatch>
     */
    public function consumptionQueue(Product $product, MovementReason $reason, \DateTimeInterface $asOf): Builder
    {
        $query = StockBatch::query()
            ->where('product_id', $product->id)
            ->available()
            ->receivedBy($asOf);

        if ($reason->usesExpiredStock()) {
            $query->orderByRaw(
                'CASE WHEN expiry_date IS NOT NULL AND expiry_date < ? THEN 0 ELSE 1 END',
                [Carbon::parse($asOf)->toDateString()],
            );
        } else {
            $query->notExpiredOn($asOf);
        }

        return $query->inConsumptionOrder();
    }

    /**
     * Répartition du stock d'un produit pour une sortie donnée.
     *
     * @return array{utilisable: float, perime: float, pas_encore_recu: float}
     */
    public function availability(Product $product, MovementReason $reason, \DateTimeInterface $asOf): array
    {
        $somme = fn ($q) => (float) $q->sum('quantity_remaining');
        $base = fn () => StockBatch::query()->where('product_id', $product->id)->available();

        return [
            'utilisable'      => $somme($this->consumptionQueue($product, $reason, $asOf)->reorder()),
            'perime'          => $reason->usesExpiredStock() ? 0.0 : $somme($base()->receivedBy($asOf)->expiredOn($asOf)),
            'pas_encore_recu' => $somme($base()->whereDate('received_at', '>', $asOf)),
        ];
    }

    /**
     * Une vente vient d'être refusée parce que le stock restant est périmé :
     * une alerte critique par lot prévient le gérant (cloche, temps réel et
     * courriel). Une seule par lot tant qu'elle reste ouverte.
     */
    public function signalBlockedExpiredSale(Product $product, ?User $user = null): void
    {
        StockBatch::query()
            ->where('product_id', $product->id)
            ->available()
            ->expiredOn(now())
            ->get()
            ->each(fn (StockBatch $batch) => $this->alerts->open(
                $product,
                AlertType::VenteBloquee,
                sprintf(
                    'Vente bloquée : %s — lot %s périmé depuis le %s (%s en stock). Retirez-le du rayon.%s',
                    $product->name,
                    $batch->batch_number,
                    $batch->expiry_date->format('d/m/Y'),
                    rtrim(rtrim(number_format((float) $batch->quantity_remaining, 3, ',', ' '), '0'), ','),
                    $user ? ' Tentative de '.$user->name.'.' : '',
                ),
                batch: $batch,
                currentValue: (float) $batch->quantity_remaining,
                daysRemaining: $batch->daysUntilExpiry(),
            ));
    }

    // =================================================================
    // AJUSTEMENTS ET INVENTAIRE
    // =================================================================

    /**
     * Aligne le stock théorique sur une quantité comptée. Un écart positif
     * crée un lot d'ajustement au coût moyen des lots en stock ; un écart négatif
     * consomme les lots en FIFO.
     */
    public function adjustTo(
        Product $product,
        float $countedQuantity,
        User $user,
        MovementReason $reason = MovementReason::Inventaire,
        ?string $note = null,
        ?object $source = null,
    ): float {
        return DB::transaction(function () use ($product, $countedQuantity, $user, $reason, $note, $source) {
            $product = Product::lockForUpdate()->findOrFail($product->id);

            $before   = (float) $product->quantity_on_hand;
            $variance = round($countedQuantity - $before, 3);

            if (abs($variance) < 0.0001) {
                return 0.0;
            }

            if ($variance > 0) {
                $unitCost = $this->gainUnitCost($product);

                $batch = StockBatch::create([
                    'batch_number'       => StockBatch::nextBatchNumber(),
                    'product_id'         => $product->id,
                    'purchase_price'     => $unitCost,
                    'quantity_received'  => $variance,
                    'quantity_remaining' => $variance,
                    'received_at'        => now()->toDateString(),
                    'expiry_date'        => null,
                ]);

                $this->writeMovement(
                    product: $product,
                    batch: $batch,
                    direction: MovementDirection::In,
                    reason: $reason,
                    quantity: $variance,
                    unitCost: $unitCost,
                    before: $before,
                    after: $countedQuantity,
                    source: $source,
                    user: $user,
                    occurredAt: now(),
                );
            } else {
                $this->consume(
                    product: $product,
                    quantity: abs($variance),
                    reason: $reason,
                    source: $source,
                    user: $user,
                );
            }

            $product->forceFill(['quantity_on_hand' => $countedQuantity])->save();

            $fresh = $product->fresh();
            $this->alerts->refreshForProduct($fresh);

            event(new StockLevelChanged($fresh, $before, $countedQuantity));

            return $variance;
        });
    }

    /**
     * Valeur d'un écart d'inventaire au coût réel des lots, celle-là même
     * que l'ajustement passera en écriture : un manque est valorisé au
     * coût des lots que le FIFO videra, un surplus au coût moyen des lots
     * encore en stock.
     */
    public function varianceValue(Product $product, float $variance): int
    {
        if (abs($variance) < 0.0001) {
            return 0;
        }

        if ($variance > 0) {
            return (int) round($variance * $this->gainUnitCost($product));
        }

        $restant = abs($variance);
        $valeur = 0.0;

        foreach ($this->consumptionQueue($product, MovementReason::Inventaire, now())->get() as $lot) {
            if ($restant <= 1e-6) {
                break;
            }

            $pris = min((float) $lot->quantity_remaining, $restant);
            $valeur += $pris * $lot->purchase_price;
            $restant -= $pris;
        }

        // Plus de lots que le stock théorique : reliquat au dernier prix connu,
        // comme le fait consume().
        $valeur += max($restant, 0) * $product->last_purchase_price;

        return -(int) round($valeur);
    }

    /**
     * Coût unitaire d'une marchandise retrouvée à l'inventaire : coût moyen
     * pondéré des lots en stock, à défaut le dernier prix d'achat.
     */
    private function gainUnitCost(Product $product): int
    {
        $lots = StockBatch::query()->where('product_id', $product->id)->available();
        $quantite = (float) (clone $lots)->sum('quantity_remaining');

        if ($quantite <= 1e-6) {
            return (int) $product->last_purchase_price;
        }

        return (int) round((float) (clone $lots)->sum(DB::raw('quantity_remaining * purchase_price')) / $quantite);
    }

    /**
     * Sort d'un coup tous les lots périmés d'un produit, ou de toute la
     * boutique si aucun produit n'est précisé. Appelé depuis l'écran
     * « Péremptions » et depuis la tâche planifiée.
     *
     * @return array{lots: int, quantite: float, valeur: int}
     */
    public function writeOffExpired(User $user, ?Product $product = null, ?string $note = null): array
    {
        $batches = StockBatch::query()
            ->available()
            ->expired()
            ->when($product, fn ($q) => $q->where('product_id', $product->id))
            ->with('product')
            ->get();

        if ($batches->isEmpty()) {
            return ['lots' => 0, 'quantite' => 0.0, 'valeur' => 0];
        }

        $lines = $batches
            ->groupBy('product_id')
            ->map(fn ($group, $productId) => new ExitLineData(
                productId: (int) $productId,
                quantity: (float) $group->sum(fn (StockBatch $b) => (float) $b->quantity_remaining),
            ))
            ->values()
            ->all();

        // Le motif « péremption » prend les lots périmés en premier : la
        // quantité demandée correspond exactement à ces lots.
        $exit = $this->issue($user, ExitType::Peremption, $lines, [
            'note' => $note ?? 'Sortie automatique des lots périmés au '.now()->format('d/m/Y'),
        ]);

        return [
            'lots'     => $batches->count(),
            'quantite' => (float) $batches->sum(fn (StockBatch $b) => (float) $b->quantity_remaining),
            'valeur'   => $exit->total_cost,
        ];
    }

    // =================================================================
    // ANNULATIONS
    // =================================================================

    /**
     * Annule un bon d'entrée. Refusé si la marchandise reçue a déjà
     * commencé à être vendue : la piste d'audit deviendrait fausse.
     */
    public function cancelEntry(StockEntry $entry, User $user, string $reason): StockEntry
    {
        return DB::transaction(function () use ($entry, $user, $reason) {
            if ($entry->status === DocumentStatus::Annule) {
                throw new \LogicException('Ce bon est déjà annulé.');
            }

            $entry->load('batches');

            foreach ($entry->batches as $batch) {
                if ($batch->quantityConsumed() > 1e-6) {
                    throw new \LogicException(__(
                        'Annulation impossible : le lot :lot a déjà été entamé. Passez par une sortie pour ajustement.',
                        ['lot' => $batch->batch_number]
                    ));
                }
            }

            foreach ($entry->batches as $batch) {
                $product = Product::lockForUpdate()->findOrFail($batch->product_id);
                $before  = (float) $product->quantity_on_hand;
                $after   = $before - (float) $batch->quantity_remaining;

                $this->writeMovement(
                    product: $product,
                    batch: $batch,
                    direction: MovementDirection::Out,
                    reason: MovementReason::Ajustement,
                    quantity: (float) $batch->quantity_remaining,
                    unitCost: $batch->purchase_price,
                    before: $before,
                    after: $after,
                    source: $entry,
                    user: $user,
                    occurredAt: now(),
                );

                $batch->forceFill([
                    'quantity_remaining' => 0,
                    'depleted_at'        => now(),
                ])->save();

                $product->forceFill(['quantity_on_hand' => $after])->save();

                $this->alerts->refreshForProduct($product->fresh());
            }

            $entry->forceFill([
                'status' => DocumentStatus::Annule,
                'note'   => trim(($entry->note ?? '')."\nAnnulé le ".now()->format('d/m/Y H:i').' : '.$reason),
            ])->save();

            return $entry;
        });
    }

    /**
     * Annule un bon de sortie en remettant les quantités dans leurs lots
     * d'origine, au prix d'achat d'origine.
     *
     * Si un lot d'origine est périmé, la marchandise rendue ne doit pas
     * redevenir vendable : elle est aussitôt sortie en « Péremption ». Le
     * grand livre montre les deux écritures (retour, puis perte).
     */
    public function cancelExit(StockExit $exit, User $user, string $reason): StockExit
    {
        $produitsAPerimer = [];

        $exit = DB::transaction(function () use ($exit, $user, $reason, &$produitsAPerimer) {
            if ($exit->status === DocumentStatus::Annule) {
                throw new \LogicException('Ce bon est déjà annulé.');
            }

            $movements = StockMovement::query()
                ->where('source_type', StockExit::class)
                ->where('source_id', $exit->id)
                ->outgoing()
                ->get();

            foreach ($movements as $movement) {
                $product = Product::lockForUpdate()->findOrFail($movement->product_id);
                $before  = (float) $product->quantity_on_hand;
                $after   = $before + (float) $movement->quantity;

                $batch = null;

                if ($movement->stock_batch_id) {
                    $batch = StockBatch::lockForUpdate()->find($movement->stock_batch_id);

                    $batch?->forceFill([
                        'quantity_remaining' => (float) $batch->quantity_remaining + (float) $movement->quantity,
                        'depleted_at'        => null,
                    ])->save();

                    if ($batch?->expiry_date?->lt(today())) {
                        $produitsAPerimer[$product->id] = $product;
                    }
                }

                $this->writeMovement(
                    product: $product,
                    batch: $batch,
                    direction: MovementDirection::In,
                    reason: MovementReason::Ajustement,
                    quantity: (float) $movement->quantity,
                    unitCost: $movement->unit_cost,
                    before: $before,
                    after: $after,
                    source: $exit,
                    user: $user,
                    occurredAt: now(),
                );

                $product->forceFill(['quantity_on_hand' => $after])->save();

                $this->alerts->refreshForProduct($product->fresh());
            }

            $exit->forceFill([
                'status' => DocumentStatus::Annule,
                'note'   => trim(($exit->note ?? '')."\nAnnulé le ".now()->format('d/m/Y H:i').' : '.$reason),
            ])->save();

            return $exit;
        });

        foreach ($produitsAPerimer as $product) {
            $this->writeOffExpired(
                $user,
                $product,
                'Retour sur un lot périmé (annulation de '.$exit->reference.') : marchandise sortie en perte.',
            );
        }

        return $exit;
    }

    // =================================================================
    // OUTILS
    // =================================================================

    /**
     * Recalcule quantity_on_hand depuis les lots. La colonne n'est qu'un
     * cache de lecture ; cette méthode est le filet de rattrapage en cas
     * d'incident (restauration partielle, import manuel).
     */
    public function recomputeOnHand(Product $product): float
    {
        $quantity = (float) StockBatch::where('product_id', $product->id)->sum('quantity_remaining');

        $product->forceFill(['quantity_on_hand' => $quantity])->save();

        return $quantity;
    }

    private function writeMovement(
        Product $product,
        ?StockBatch $batch,
        MovementDirection $direction,
        MovementReason $reason,
        float $quantity,
        int $unitCost,
        float $before,
        float $after,
        ?object $source,
        ?User $user,
        Carbon $occurredAt,
    ): StockMovement {
        return StockMovement::create([
            'product_id'      => $product->id,
            'stock_batch_id'  => $batch?->id,
            'direction'       => $direction,
            'reason'          => $reason,
            'quantity'        => $quantity,
            'unit_cost'       => $unitCost,
            'total_cost'      => (int) round($quantity * $unitCost),
            'quantity_before' => $before,
            'quantity_after'  => $after,
            'source_type'     => $source ? $source::class : null,
            'source_id'       => $source?->getKey(),
            'user_id'         => $user?->id,
            'occurred_at'     => $occurredAt,
        ]);
    }

    private function guardQuantity(float $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité doit être strictement positive.');
        }
    }
}
