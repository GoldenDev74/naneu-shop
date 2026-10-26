<?php

namespace Tests\Feature;

use App\Data\EntryLineData;
use App\Data\ExitLineData;
use App\Enums\ExitType;
use App\Enums\MovementDirection;
use App\Enums\UserRole;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StockFifoTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;
    private User $utilisateur;
    private Product $produit;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            \App\Events\StockEntryRecorded::class,
            \App\Events\StockExitRecorded::class,
            \App\Events\StockLevelChanged::class,
            \App\Events\StockAlertRaised::class,
            \App\Events\StockAlertResolved::class,
        ]);

        $this->stock = app(StockService::class);

        $this->utilisateur = User::create([
            'name'      => 'Testeur',
            'email'     => 'test@naneushop.tg',
            'password'  => 'motdepasse',
            'role'      => UserRole::Admin,
            'is_active' => true,
        ]);

        $unite = Unit::create(['name' => 'Pièce', 'symbol' => 'pcs', 'allows_decimal' => false]);

        $this->produit = Product::create([
            'sku'            => 'NAN-0001',
            'name'           => 'Riz parfumé 5 kg',
            'unit_id'        => $unite->id,
            'sale_price'     => 5500,
            'alert_quantity' => 5,
            'is_perishable'  => true,
            'is_active'      => true,
        ]);
    }

    /** Trois réceptions à trois prix différents : trois lots distincts. */
    private function recevoirTroisLots(): void
    {
        $lots = [
            ['prix' => 4000, 'quantite' => 10, 'date' => '-10 days', 'peremption' => '+60 days'],
            ['prix' => 4200, 'quantite' => 10, 'date' => '-5 days',  'peremption' => '+30 days'],
            ['prix' => 4500, 'quantite' => 10, 'date' => '-1 day',   'peremption' => '+90 days'],
        ];

        foreach ($lots as $lot) {
            $this->stock->receive(
                $this->utilisateur,
                [new EntryLineData(
                    productId: $this->produit->id,
                    quantity: $lot['quantite'],
                    purchasePrice: $lot['prix'],
                    expiryDate: Carbon::parse($lot['peremption']),
                )],
                ['entry_date' => now()->modify($lot['date'])->toDateString()],
            );
        }
    }

    public function test_une_reception_cree_un_lot_et_met_le_stock_a_jour(): void
    {
        $entree = $this->stock->receive(
            $this->utilisateur,
            [new EntryLineData($this->produit->id, 12, 4200, Carbon::parse('+45 days'))],
        );

        $this->assertSame(12 * 4200, $entree->total_cost);
        $this->assertSame(1, StockBatch::count());

        $lot = StockBatch::first();
        $this->assertEquals(12, (float) $lot->quantity_received);
        $this->assertEquals(12, (float) $lot->quantity_remaining);
        $this->assertSame(4200, $lot->purchase_price);

        $this->produit->refresh();
        $this->assertEquals(12, (float) $this->produit->quantity_on_hand);
        $this->assertSame(4200, $this->produit->last_purchase_price);
    }

    public function test_la_sortie_consomme_le_lot_le_plus_ancien_en_premier(): void
    {
        // Cas d'une boutique assujettie : la TVA est extraite du prix TTC.
        config(['naneu.vat.enabled' => true]);

        $this->recevoirTroisLots();

        // 8 unités : entièrement dans le premier lot, reçu il y a 10 jours.
        $sortie = $this->stock->issue(
            $this->utilisateur,
            ExitType::Vente,
            [new ExitLineData($this->produit->id, 8, 5500)],
        );

        $lots = StockBatch::orderBy('received_at')->get();

        $this->assertEquals(2, (float) $lots[0]->quantity_remaining, 'Le lot le plus ancien doit être entamé.');
        $this->assertEquals(10, (float) $lots[1]->quantity_remaining, 'Le deuxième lot ne doit pas être touché.');
        $this->assertEquals(10, (float) $lots[2]->quantity_remaining);

        // Coût FIFO : 8 unités au prix du premier lot, pas au prix moyen.
        $this->assertSame(8 * 4000, $sortie->total_cost);
        // Prix TTC : la TVA (18 %) est contenue dans les 44 000 F encaissés,
        // la marge se calcule sur le hors-taxe.
        $tva = 44000 - (int) round(44000 / 1.18);
        $this->assertSame(8 * 5500, $sortie->total_sale);
        $this->assertSame($tva, $sortie->total_vat);
        $this->assertSame(8 * 5500 - $tva - 8 * 4000, $sortie->total_margin);
    }

    public function test_une_sortie_peut_mordre_sur_plusieurs_lots(): void
    {
        $this->recevoirTroisLots();

        // 25 unités : 10 (à 4000) + 10 (à 4200) + 5 (à 4500).
        $sortie = $this->stock->issue(
            $this->utilisateur,
            ExitType::Vente,
            [new ExitLineData($this->produit->id, 25, 5500)],
        );

        $coutAttendu = (10 * 4000) + (10 * 4200) + (5 * 4500);

        $this->assertSame($coutAttendu, $sortie->total_cost);

        $lots = StockBatch::orderBy('received_at')->get();
        $this->assertEquals(0, (float) $lots[0]->quantity_remaining);
        $this->assertEquals(0, (float) $lots[1]->quantity_remaining);
        $this->assertEquals(5, (float) $lots[2]->quantity_remaining);

        $this->assertNotNull($lots[0]->depleted_at, 'Un lot vidé doit être daté.');
        $this->assertNotNull($lots[1]->depleted_at);
        $this->assertNull($lots[2]->depleted_at);

        $this->produit->refresh();
        $this->assertEquals(5, (float) $this->produit->quantity_on_hand);
    }

    public function test_chaque_lot_touche_laisse_son_propre_mouvement(): void
    {
        $this->recevoirTroisLots();

        $this->stock->issue(
            $this->utilisateur,
            ExitType::Vente,
            [new ExitLineData($this->produit->id, 25, 5500)],
        );

        $sorties = StockMovement::where('direction', MovementDirection::Out->value)->get();

        $this->assertCount(3, $sorties, 'Une écriture par lot consommé.');

        $this->assertSame(4000, $sorties[0]->unit_cost);
        $this->assertSame(4200, $sorties[1]->unit_cost);
        $this->assertSame(4500, $sorties[2]->unit_cost);

        // Le stock décroît de façon continue d'une écriture à l'autre.
        $this->assertEquals(30, (float) $sorties[0]->quantity_before);
        $this->assertEquals(20, (float) $sorties[0]->quantity_after);
        $this->assertEquals(20, (float) $sorties[1]->quantity_before);
        $this->assertEquals(5, (float) $sorties[2]->quantity_after);
    }

    public function test_la_strategie_fefo_sort_le_lot_le_plus_proche_de_la_peremption(): void
    {
        config(['naneu.stock.strategy' => 'fefo']);

        $this->recevoirTroisLots();

        // Le deuxième lot périme dans 30 jours : c'est lui que FEFO doit vider,
        // alors que FIFO aurait commencé par le premier.
        $sortie = $this->stock->issue(
            $this->utilisateur,
            ExitType::Vente,
            [new ExitLineData($this->produit->id, 6, 5500)],
        );

        $lots = StockBatch::orderBy('received_at')->get();

        $this->assertEquals(10, (float) $lots[0]->quantity_remaining);
        $this->assertEquals(4, (float) $lots[1]->quantity_remaining);
        $this->assertSame(6 * 4200, $sortie->total_cost);
    }

    public function test_une_sortie_superieure_au_stock_est_refusee(): void
    {
        $this->recevoirTroisLots();

        $this->expectException(InsufficientStockException::class);

        $this->stock->issue(
            $this->utilisateur,
            ExitType::Vente,
            [new ExitLineData($this->produit->id, 31, 5500)],
        );
    }

    public function test_un_echec_de_sortie_ne_laisse_aucune_trace(): void
    {
        $this->recevoirTroisLots();

        $mouvementsAvant = StockMovement::count();

        try {
            $this->stock->issue(
                $this->utilisateur,
                ExitType::Vente,
                [
                    new ExitLineData($this->produit->id, 5, 5500),   // passe
                    new ExitLineData($this->produit->id, 999, 5500), // échoue
                ],
            );
        } catch (InsufficientStockException) {
            // attendu
        }

        // La transaction doit avoir tout annulé, y compris la première ligne.
        $this->assertSame($mouvementsAvant, StockMovement::count());
        $this->assertSame(0, \App\Models\StockExit::count());

        $this->produit->refresh();
        $this->assertEquals(30, (float) $this->produit->quantity_on_hand);
    }

    public function test_une_perte_na_pas_de_chiffre_daffaires_mais_un_cout(): void
    {
        $this->recevoirTroisLots();

        $sortie = $this->stock->issue(
            $this->utilisateur,
            ExitType::Peremption,
            [new ExitLineData($this->produit->id, 4)],
        );

        $this->assertSame(4 * 4000, $sortie->total_cost);
        $this->assertSame(0, $sortie->total_sale);
        $this->assertSame(-4 * 4000, $sortie->total_margin);
    }

    public function test_annuler_une_sortie_remet_les_quantites_dans_leurs_lots(): void
    {
        $this->recevoirTroisLots();

        $sortie = $this->stock->issue(
            $this->utilisateur,
            ExitType::Vente,
            [new ExitLineData($this->produit->id, 15, 5500)],
        );

        $this->stock->cancelExit($sortie, $this->utilisateur, 'Erreur de saisie en caisse');

        $lots = StockBatch::orderBy('received_at')->get();

        $this->assertEquals(10, (float) $lots[0]->quantity_remaining);
        $this->assertEquals(10, (float) $lots[1]->quantity_remaining);
        $this->assertEquals(10, (float) $lots[2]->quantity_remaining);

        $this->produit->refresh();
        $this->assertEquals(30, (float) $this->produit->quantity_on_hand);

        $this->assertSame(\App\Enums\DocumentStatus::Annule, $sortie->fresh()->status);
    }

    public function test_annuler_une_entree_deja_entamee_est_refuse(): void
    {
        $entree = $this->stock->receive(
            $this->utilisateur,
            [new EntryLineData($this->produit->id, 10, 4000)],
        );

        $this->stock->issue(
            $this->utilisateur,
            ExitType::Vente,
            [new ExitLineData($this->produit->id, 1, 5500)],
        );

        $this->expectException(\LogicException::class);

        $this->stock->cancelEntry($entree, $this->utilisateur, 'Livraison refusée');
    }

    public function test_un_mouvement_ne_peut_etre_ni_modifie_ni_supprime(): void
    {
        $this->recevoirTroisLots();

        $mouvement = StockMovement::first();

        try {
            $mouvement->update(['quantity' => 999]);
            $this->fail('La modification aurait dû être refusée.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('immuables', $e->getMessage());
        }

        try {
            $mouvement->delete();
            $this->fail('La suppression aurait dû être refusée.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('supprimés', $e->getMessage());
        }
    }

    public function test_un_ajustement_positif_cree_un_lot_et_un_negatif_consomme_en_fifo(): void
    {
        $this->recevoirTroisLots();

        // Comptage à 33 : 3 unités apparues, au dernier prix connu.
        $ecart = $this->stock->adjustTo($this->produit, 33, $this->utilisateur);

        $this->assertEquals(3, $ecart);
        $this->assertSame(4, StockBatch::count());

        $this->produit->refresh();
        $this->assertEquals(33, (float) $this->produit->quantity_on_hand);

        // Comptage à 28 : 5 unités manquantes, prélevées sur le lot le plus ancien.
        $ecart = $this->stock->adjustTo($this->produit, 28, $this->utilisateur);

        $this->assertEquals(-5, $ecart);

        $lotAncien = StockBatch::orderBy('received_at')->first();
        $this->assertEquals(5, (float) $lotAncien->quantity_remaining);

        $this->produit->refresh();
        $this->assertEquals(28, (float) $this->produit->quantity_on_hand);
    }

    public function test_le_cache_de_quantite_est_recalculable_depuis_les_lots(): void
    {
        $this->recevoirTroisLots();

        // Simule une désynchronisation de la colonne dénormalisée.
        $this->produit->forceFill(['quantity_on_hand' => 999])->save();

        $reel = $this->stock->recomputeOnHand($this->produit->fresh());

        $this->assertEquals(30, $reel);
        $this->assertEquals(30, (float) $this->produit->fresh()->quantity_on_hand);
    }
}
