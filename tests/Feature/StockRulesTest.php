<?php

namespace Tests\Feature;

use App\Data\EntryLineData;
use App\Data\ExitLineData;
use App\Enums\AlertType;
use App\Enums\DocumentStatus;
use App\Enums\ExitType;
use App\Enums\UserRole;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockAlert;
use App\Models\StockBatch;
use App\Models\StockExit;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Règles décidées avec la gérante :
 * - un lot périmé ne se vend pas, et la tentative est signalée ;
 * - une sortie ne puise que dans des lots déjà reçus à sa date ;
 * - un retour sur un lot périmé part aussitôt en perte ;
 * - les prix de vente sont TTC ;
 * - les Paramètres s'appliquent réellement.
 */
class StockRulesTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;
    private User $gerante;
    private Product $yaourt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = app(StockService::class);

        $this->gerante = User::create([
            'name'      => 'Gérante',
            'email'     => 'gerante@naneushop.tg',
            'password'  => 'motdepasse',
            'role'      => UserRole::Gerant,
            'is_active' => true,
        ]);

        $unite = Unit::create(['name' => 'Pièce', 'symbol' => 'pcs', 'allows_decimal' => false]);

        $this->yaourt = Product::create([
            'sku'            => 'NAN-0001',
            'name'           => 'Yaourt nature',
            'unit_id'        => $unite->id,
            'sale_price'     => 354,
            'vat_rate'       => 18,
            'alert_quantity' => 2,
            'is_perishable'  => true,
            'is_active'      => true,
        ]);
    }

    /** Reçoit un lot puis, si demandé, le fait vieillir jusqu'à la péremption. */
    private function recevoir(int $quantite, string $dateReception, ?string $peremptionForcee = null): StockBatch
    {
        $this->stock->receive(
            $this->gerante,
            [new EntryLineData($this->yaourt->id, $quantite, 240, Carbon::parse('+20 days'))],
            ['entry_date' => Carbon::parse($dateReception)->toDateString()],
        );

        $lot = StockBatch::latest('id')->first();

        if ($peremptionForcee) {
            // Le temps a passé : la date limite est désormais derrière nous.
            $lot->forceFill(['expiry_date' => Carbon::parse($peremptionForcee)->toDateString()])->save();
        }

        return $lot;
    }

    private function vendre(float $quantite, ?string $date = null): StockExit
    {
        return $this->stock->issue($this->gerante, ExitType::Vente, [
            new ExitLineData($this->yaourt->id, $quantite),
        ], ['exit_date' => $date ?? now()->toDateString()]);
    }

    public function test_un_lot_perime_ne_se_vend_pas_et_la_gerante_est_prevenue(): void
    {
        Notification::fake();

        $this->recevoir(5, '-30 days', '-2 days');

        try {
            $this->vendre(1);
            $this->fail('La vente d\'un produit périmé aurait dû être refusée.');
        } catch (InsufficientStockException $e) {
            $this->assertEquals(5, $e->expired);
            $this->assertStringContainsString('périmé', $e->getMessage());
        }

        $this->assertSame(0, StockExit::count());
        $this->assertSame(1, StockAlert::open()->where('type', AlertType::VenteBloquee->value)->count());
        Notification::assertSentTo($this->gerante, StockAlertNotification::class,
            fn ($n, array $canaux) => in_array('mail', $canaux) && in_array('database', $canaux));
    }

    public function test_la_vente_ignore_le_lot_perime_et_prend_le_suivant(): void
    {
        $perime = $this->recevoir(5, '-30 days', '-2 days');
        $frais = $this->recevoir(5, '-1 day');

        $this->vendre(2);

        $this->assertEquals(5, (float) $perime->fresh()->quantity_remaining);
        $this->assertEquals(3, (float) $frais->fresh()->quantity_remaining);
    }

    public function test_une_perte_ecoule_d_abord_le_lot_perime(): void
    {
        $perime = $this->recevoir(5, '-30 days', '-2 days');
        $frais = $this->recevoir(5, '-1 day');

        $this->stock->issue($this->gerante, ExitType::Casse, [new ExitLineData($this->yaourt->id, 2)]);

        $this->assertEquals(3, (float) $perime->fresh()->quantity_remaining);
        $this->assertEquals(5, (float) $frais->fresh()->quantity_remaining);
    }

    public function test_une_vente_ne_peut_pas_preceder_la_reception(): void
    {
        $this->recevoir(5, 'today');

        try {
            $this->vendre(1, now()->subDays(10)->toDateString());
            $this->fail('Une vente antérieure à la réception aurait dû être refusée.');
        } catch (InsufficientStockException $e) {
            $this->assertEquals(5, $e->notYetReceived);
        }

        $this->vendre(1);
        $this->assertSame(1, StockExit::count());
    }

    public function test_annuler_une_vente_sur_un_lot_devenu_perime_le_sort_en_perte(): void
    {
        $lot = $this->recevoir(5, '-10 days');
        $vente = $this->vendre(1);

        $lot->forceFill(['expiry_date' => now()->subDay()->toDateString()])->save();
        $this->stock->writeOffExpired($this->gerante);
        $this->assertEquals(0, (float) $lot->fresh()->quantity_remaining);

        $this->stock->cancelExit($vente, $this->gerante, 'Retour client');

        $this->assertSame(DocumentStatus::Annule, $vente->fresh()->status);
        $this->assertEquals(0, (float) $lot->fresh()->quantity_remaining, 'Le stock périmé ne doit pas redevenir vendable.');
        $this->assertSame(2, StockExit::where('type', ExitType::Peremption->value)->count());
    }

    public function test_le_prix_de_vente_est_ttc(): void
    {
        // Cas d'une boutique assujettie : la TVA est extraite du prix TTC.
        config(['naneu.vat.enabled' => true]);

        $this->recevoir(5, '-1 day');

        $vente = $this->vendre(1);

        $this->assertSame(354, $vente->total_sale);
        $this->assertSame(54, $vente->total_vat);          // 354 TTC = 300 HT + 54 de TVA
        $this->assertSame(300 - 240, $vente->total_margin);
    }

    public function test_tva_desactivee_dans_les_parametres(): void
    {
        Setting::put('vat_enabled', false);
        $this->recevoir(5, '-1 day');

        $vente = $this->vendre(1);

        $this->assertSame(0, $vente->total_vat);
        $this->assertSame(354 - 240, $vente->total_margin);
    }

    public function test_les_parametres_remplacent_la_configuration(): void
    {
        Setting::put('stock_strategy', 'fefo');
        Setting::put('expiry_alert_days', 12);
        Setting::put('shop_name', 'Naneu Shop Bè');

        $this->assertSame('fefo', config('naneu.stock.strategy'));
        $this->assertSame(12, config('naneu.stock.expiry_alert_days'));
        $this->assertSame('Naneu Shop Bè', config('naneu.shop.name'));
    }
}
