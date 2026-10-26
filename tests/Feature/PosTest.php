<?php

namespace Tests\Feature;

use App\Data\EntryLineData;
use App\Enums\UserRole;
use App\Livewire\Pos\Checkout;
use App\Models\Product;
use App\Models\StockExit;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    private User $caissier;
    private Product $riz;

    protected function setUp(): void
    {
        parent::setUp();

        config(['naneu.vat.enabled' => false]);

        $this->caissier = User::create([
            'name' => 'Caissière', 'email' => 'caisse@naneushop.tg', 'password' => 'motdepasse',
            'role' => UserRole::Caissier, 'is_active' => true,
        ])->refresh();

        $unite = Unit::create(['name' => 'Sac', 'symbol' => 'sac', 'allows_decimal' => false]);

        $this->riz = Product::create([
            'sku' => 'NAN-0001', 'name' => 'Riz parfumé 5 kg', 'unit_id' => $unite->id,
            'sale_price' => 4200, 'vat_rate' => 18, 'alert_quantity' => 2, 'is_perishable' => false, 'is_active' => true,
        ]);

        app(StockService::class)->receive($this->caissier, [new EntryLineData($this->riz->id, 10, 3500)]);
    }

    public function test_cash_sale_updates_stock_and_records_change(): void
    {
        Livewire::actingAs($this->caissier)->test(Checkout::class)
            ->call('ajouter', $this->riz->id)
            ->call('plus', 0)
            ->set('remis', '10 000')
            ->call('encaisser')
            ->assertHasNoErrors()
            ->assertSee('1 600');

        $vente = StockExit::latest('id')->first();

        $this->assertSame(8400, $vente->total_sale);
        $this->assertSame(10000, $vente->amount_tendered);
        $this->assertSame(1600, $vente->change_given);
        $this->assertSame(0, $vente->total_vat);
        $this->assertSame(1400, $vente->total_margin); // TVA non facturée : 8 400 − 7 000
        $this->assertEquals(8, (float) $this->riz->fresh()->quantity_on_hand);
    }

    public function test_sale_refused_when_customer_gives_too_little(): void
    {
        Livewire::actingAs($this->caissier)->test(Checkout::class)
            ->call('ajouter', $this->riz->id)
            ->set('remis', '4000')
            ->call('encaisser')
            ->assertHasErrors('remis');

        $this->assertSame(0, StockExit::count());
    }

    public function test_quantity_is_capped_to_sellable_stock(): void
    {
        $t = Livewire::actingAs($this->caissier)->test(Checkout::class)
            ->call('ajouter', $this->riz->id)
            ->set('panier.0.quantite', '25');

        $this->assertSame('10', $t->get('panier.0.quantite'));
    }

    public function test_receipt_is_available_as_page_and_pdf(): void
    {
        Livewire::actingAs($this->caissier)->test(Checkout::class)
            ->call('ajouter', $this->riz->id)
            ->set('remis', '5000')
            ->call('encaisser');

        $vente = StockExit::latest('id')->first();

        $this->actingAs($this->caissier)->get(route('caisse.ticket', $vente))
            ->assertOk()->assertSee($vente->reference)->assertSee('Monnaie rendue');

        $this->actingAs($this->caissier)->get(route('caisse.ticket.pdf', $vente))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_long_receipt_is_split_into_pages_with_total_on_last(): void
    {
        config(['naneu.pos.ticket_lines_per_page' => 1]);
        $unite = Unit::first();
        $sucre = Product::create([
            'sku' => 'NAN-0002', 'name' => 'Sucre 1 kg', 'unit_id' => $unite->id,
            'sale_price' => 900, 'alert_quantity' => 2, 'is_perishable' => false, 'is_active' => true,
        ]);
        app(StockService::class)->receive($this->caissier, [new EntryLineData($sucre->id, 5, 700)]);

        Livewire::actingAs($this->caissier)->test(Checkout::class)
            ->call('ajouter', $this->riz->id)
            ->call('ajouter', $sucre->id)
            ->set('remis', '5100')
            ->call('encaisser');

        $vente = StockExit::latest('id')->first();

        $this->actingAs($this->caissier)->get(route('caisse.ticket', $vente))
            ->assertOk()
            ->assertSeeInOrder(['1/2', 'Sous-total à reporter', '4 200', '2/2', 'Report', 'TOTAL', '5 100']);

        $this->actingAs($this->caissier)->get(route('caisse.ticket.pdf', $vente))->assertOk();
    }

    public function test_exit_list_filters_till_sales(): void
    {
        Livewire::actingAs($this->caissier)->test(Checkout::class)
            ->call('ajouter', $this->riz->id)
            ->set('remis', '5000')
            ->call('encaisser');

        $caisse = StockExit::latest('id')->first();
        $bon = app(StockService::class)->issue($this->caissier, \App\Enums\ExitType::Vente,
            [new \App\Data\ExitLineData($this->riz->id, 1, 4200)]);

        $gerant = User::create([
            'name' => 'Gérante', 'email' => 'gerante@naneushop.tg', 'password' => 'motdepasse',
            'role' => UserRole::Gerant, 'is_active' => true,
        ])->refresh();

        Livewire::actingAs($gerant)->test(\App\Livewire\Stock\ExitList::class)
            ->set('origine', 'caisse')->assertSee($caisse->reference)->assertDontSee($bon->reference)
            ->set('origine', 'stock')->assertSee($bon->reference)->assertDontSee($caisse->reference);
    }
}
