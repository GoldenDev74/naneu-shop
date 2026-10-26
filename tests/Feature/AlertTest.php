<?php

namespace Tests\Feature;

use App\Data\EntryLineData;
use App\Data\ExitLineData;
use App\Enums\AlertType;
use App\Enums\ExitType;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\StockAlert;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use App\Services\AlertService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AlertTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;
    private AlertService $alerts;
    private User $admin;
    private Product $produit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = app(StockService::class);
        $this->alerts = app(AlertService::class);

        $this->admin = User::create([
            'name'      => 'Admin',
            'email'     => 'admin@naneushop.tg',
            'password'  => 'motdepasse',
            'role'      => UserRole::Admin,
            'is_active' => true,
        ]);

        $unite = Unit::create(['name' => 'Pièce', 'symbol' => 'pcs', 'allows_decimal' => false]);

        $this->produit = Product::create([
            'sku'            => 'NAN-0001',
            'name'           => 'Lait en poudre 400 g',
            'unit_id'        => $unite->id,
            'sale_price'     => 3000,
            'alert_quantity' => 5,
            'is_perishable'  => true,
            'is_active'      => true,
        ]);
    }

    public function test_une_vente_sous_le_seuil_ouvre_une_alerte_de_stock_bas(): void
    {
        $this->stock->receive($this->admin, [new EntryLineData($this->produit->id, 10, 2200)]);

        $this->assertSame(0, StockAlert::query()->open()->count());

        $this->stock->issue($this->admin, ExitType::Vente, [new ExitLineData($this->produit->id, 6, 3000)]);

        $alerte = StockAlert::query()->open()->first();

        $this->assertNotNull($alerte);
        $this->assertSame(AlertType::StockBas, $alerte->type);
        $this->assertEquals(4, (float) $alerte->current_value);
    }

    public function test_vider_le_stock_remplace_lalerte_de_stock_bas_par_une_rupture(): void
    {
        $this->stock->receive($this->admin, [new EntryLineData($this->produit->id, 10, 2200)]);
        $this->stock->issue($this->admin, ExitType::Vente, [new ExitLineData($this->produit->id, 6, 3000)]);

        $this->assertSame(AlertType::StockBas, StockAlert::query()->open()->first()->type);

        $this->stock->issue($this->admin, ExitType::Vente, [new ExitLineData($this->produit->id, 4, 3000)]);

        $ouvertes = StockAlert::query()->open()->get();

        $this->assertCount(1, $ouvertes, 'Les deux alertes ne doivent jamais coexister.');
        $this->assertSame(AlertType::Rupture, $ouvertes->first()->type);
    }

    public function test_un_reapprovisionnement_referme_lalerte(): void
    {
        $this->stock->receive($this->admin, [new EntryLineData($this->produit->id, 10, 2200)]);
        $this->stock->issue($this->admin, ExitType::Vente, [new ExitLineData($this->produit->id, 8, 3000)]);

        $this->assertSame(1, StockAlert::query()->open()->count());

        $this->stock->receive($this->admin, [new EntryLineData($this->produit->id, 20, 2300)]);

        $this->assertSame(0, StockAlert::query()->open()->count());
        $this->assertSame(1, StockAlert::query()->whereNotNull('resolved_at')->count());
    }

    public function test_le_scan_ne_duplique_pas_une_alerte_deja_ouverte(): void
    {
        $this->stock->receive($this->admin, [new EntryLineData($this->produit->id, 3, 2200)]);

        $this->alerts->scanStockLevels();
        $this->alerts->scanStockLevels();
        $this->alerts->scanStockLevels();

        $this->assertSame(1, StockAlert::query()->open()->count());
    }

    public function test_un_lot_proche_de_la_peremption_declenche_une_alerte(): void
    {
        $this->stock->receive($this->admin, [
            new EntryLineData($this->produit->id, 10, 2200, Carbon::now()->addDays(5)),
        ]);

        $ouvertes = $this->alerts->scanExpiries();

        $this->assertCount(1, $ouvertes);

        $alerte = $ouvertes->first();
        $this->assertSame(AlertType::PeremptionProche, $alerte->type);
        $this->assertSame(\App\Enums\AlertSeverity::Critique, $alerte->severity, 'Sous 7 jours, la gravité passe en critique.');
        $this->assertSame(5, $alerte->days_remaining);
    }

    public function test_un_lot_perime_remplace_lalerte_de_peremption_proche(): void
    {
        $this->stock->receive($this->admin, [
            new EntryLineData($this->produit->id, 10, 2200, Carbon::now()->addDays(3)),
        ]);

        $this->alerts->scanExpiries();
        $this->assertSame(1, StockAlert::query()->open()->ofType(AlertType::PeremptionProche)->count());

        // Quatre jours plus tard, le lot est périmé.
        $this->travel(4)->days();

        $this->alerts->scanExpiries();

        $this->assertSame(0, StockAlert::query()->open()->ofType(AlertType::PeremptionProche)->count());
        $this->assertSame(1, StockAlert::query()->open()->ofType(AlertType::Perime)->count());
    }

    public function test_sortir_un_lot_perime_referme_son_alerte(): void
    {
        $this->stock->receive($this->admin, [
            new EntryLineData($this->produit->id, 10, 2200, Carbon::now()->addDays(2)),
        ]);

        $this->travel(5)->days();

        $this->alerts->scanExpiries();
        $this->assertSame(1, StockAlert::query()->open()->ofType(AlertType::Perime)->count());

        $resultat = $this->stock->writeOffExpired($this->admin);

        $this->assertSame(1, $resultat['lots']);
        $this->assertSame(10 * 2200, $resultat['valeur']);

        $this->alerts->scanExpiries();

        $this->assertSame(0, StockAlert::query()->open()->ofType(AlertType::Perime)->count());
    }

    public function test_les_alertes_notifient_les_administrateurs_et_gerants(): void
    {
        Notification::fake();

        $caissier = User::create([
            'name' => 'Caissier', 'email' => 'caisse@naneushop.tg', 'password' => 'motdepasse',
            'role' => UserRole::Caissier, 'is_active' => true,
        ]);

        $gerant = User::create([
            'name' => 'Gérant', 'email' => 'gerant@naneushop.tg', 'password' => 'motdepasse',
            'role' => UserRole::Gerant, 'is_active' => true,
        ]);

        $this->stock->receive($this->admin, [new EntryLineData($this->produit->id, 3, 2200)]);

        Notification::assertSentTo([$this->admin, $gerant], StockAlertNotification::class);
        Notification::assertNotSentTo($caissier, StockAlertNotification::class);
    }

    public function test_la_commande_de_verification_des_peremptions_sexecute(): void
    {
        $this->stock->receive($this->admin, [
            new EntryLineData($this->produit->id, 10, 2200, Carbon::now()->addDays(10)),
        ]);

        $this->artisan('naneu:verifier-peremptions')
            ->assertSuccessful();

        $this->assertSame(1, StockAlert::query()->open()->ofType(AlertType::PeremptionProche)->count());
    }

    public function test_la_commande_de_verification_du_stock_sexecute(): void
    {
        $this->stock->receive($this->admin, [new EntryLineData($this->produit->id, 2, 2200)]);

        $this->artisan('naneu:verifier-stock')->assertSuccessful();

        $this->assertSame(1, StockAlert::query()->open()->ofType(AlertType::StockBas)->count());
    }
}
