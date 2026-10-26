<?php

namespace Tests\Feature;

use App\Data\EntryLineData;
use App\Data\ExitLineData;
use App\Enums\ExitType;
use App\Enums\UserRole;
use App\Livewire\Categories\CategoryManager;
use App\Livewire\Expiry\ExpiryBoard;
use App\Livewire\Inventory\InventoryList;
use App\Livewire\Stock\EntryForm;
use App\Models\Category;
use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Unit;
use App\Models\User;
use App\Services\ExpiryAdvisor;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Produits frais sans date imprimée, tableau des péremptions orienté
 * décision, inventaire par rayon et TVA par rayon.
 */
class FreshProduceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;
    private User $gerante;
    private Product $oignon;

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

        $kg = Unit::create(['name' => 'Kilogramme', 'symbol' => 'kg', 'allows_decimal' => true]);

        $this->oignon = Product::create([
            'sku'            => 'NAN-0100',
            'name'           => 'Oignon',
            'unit_id'        => $kg->id,
            'sale_price'     => 800,
            'vat_rate'       => 18,
            'alert_quantity' => 2,
            'is_perishable'  => true,
            'is_active'      => true,
        ]);
    }

    public function test_reception_without_date_uses_shelf_life(): void
    {
        $this->oignon->update(['shelf_life_days' => 10]);

        $this->stock->receive($this->gerante, [new EntryLineData($this->oignon->id, 20, 500)], ['entry_date' => today()->toDateString()]);

        $lot = StockBatch::latest('id')->first();
        $this->assertSame(today()->addDays(10)->toDateString(), $lot->expiry_date->toDateString());
        $this->assertTrue($lot->expiry_estimated);
    }

    public function test_printed_date_wins_over_shelf_life(): void
    {
        $this->oignon->update(['shelf_life_days' => 10]);

        $this->stock->receive($this->gerante, [new EntryLineData($this->oignon->id, 5, 500, Carbon::parse('+3 days'))]);

        $lot = StockBatch::latest('id')->first();
        $this->assertSame(today()->addDays(3)->toDateString(), $lot->expiry_date->toDateString());
        $this->assertFalse($lot->expiry_estimated);
    }

    public function test_entry_form_requires_a_date_when_no_shelf_life(): void
    {
        Livewire::actingAs($this->gerante)->test(EntryForm::class)
            ->call('ajouterProduit', $this->oignon->id)
            ->set('lignes.0.quantity', '10')
            ->set('lignes.0.purchase_price', '500')
            ->call('enregistrer')
            ->assertHasErrors('lignes.0.expiry_date');

        $this->assertSame(0, StockBatch::count());
    }

    public function test_setting_shelf_life_from_the_board_dates_existing_lots(): void
    {
        $this->stock->receive($this->gerante, [new EntryLineData($this->oignon->id, 10, 500)], ['entry_date' => today()->subDays(12)->toDateString()]);
        $this->assertNull(StockBatch::first()->expiry_date);

        Livewire::actingAs($this->gerante)->test(ExpiryBoard::class)
            ->set('conservations.'.$this->oignon->id, '7')
            ->call('definirConservation', $this->oignon->id)
            ->assertHasNoErrors();

        $lot = StockBatch::first();
        $this->assertSame(today()->subDays(5)->toDateString(), $lot->expiry_date->toDateString());

        // Passé sa durée de conservation, l'oignon ne se vend plus.
        $this->expectException(\App\Exceptions\InsufficientStockException::class);
        $this->stock->issue($this->gerante, ExitType::Vente, [new ExitLineData($this->oignon->id, 1, 800)]);
    }

    public function test_advisor_predicts_unsold_quantity_from_sales_pace(): void
    {
        // 28 kg vendus sur 4 semaines : 1 kg par jour.
        $this->stock->receive($this->gerante, [new EntryLineData($this->oignon->id, 28, 500, Carbon::parse('+60 days'))], ['entry_date' => today()->subDays(27)->toDateString()]);
        $this->stock->issue($this->gerante, ExitType::Vente, [new ExitLineData($this->oignon->id, 28, 800)], ['exit_date' => today()->subDays(1)->toDateString()]);

        // 10 kg qui expirent dans 4 jours : ~5 kg partiront, ~5 kg resteront.
        $this->stock->receive($this->gerante, [new EntryLineData($this->oignon->id, 10, 500, Carbon::parse('+4 days'))]);

        $analyse = app(ExpiryAdvisor::class)->analyse();

        $this->assertCount(1, $analyse['a_risque']);
        $this->assertEqualsWithDelta(5.0, $analyse['a_risque'][0]['invendu'], 0.01);
        $this->assertSame(2500, $analyse['a_risque'][0]['valeur_risque']);
    }

    public function test_partial_inventory_on_parent_category_includes_subcategories(): void
    {
        $frais = Category::create(['name' => 'Produits frais', 'color' => '#56c389']);
        $legumes = Category::create(['name' => 'Légumes', 'parent_id' => $frais->id, 'color' => '#56c389']);
        $this->oignon->update(['category_id' => $legumes->id]);

        Livewire::actingAs($this->gerante)->test(InventoryList::class)
            ->set('scope', 'partiel')
            ->set('category_id', $frais->id)
            ->call('lancer');

        $this->assertSame(1, InventoryCount::latest('id')->first()->lines()->count());
    }

    public function test_category_vat_exemption_applies_to_all_its_products(): void
    {
        $legumes = Category::create(['name' => 'Légumes', 'color' => '#56c389']);
        $this->oignon->update(['category_id' => $legumes->id]);

        Livewire::actingAs($this->gerante)->test(CategoryManager::class)
            ->call('tvaRayon', $legumes->id, true);

        $this->assertTrue($this->oignon->fresh()->is_vat_exempt);
        $this->assertSame(0.0, $this->oignon->fresh()->effectiveVatRate());
    }
}
