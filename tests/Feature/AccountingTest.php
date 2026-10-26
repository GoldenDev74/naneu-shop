<?php

namespace Tests\Feature;

use App\Data\EntryLineData;
use App\Data\ExitLineData;
use App\Enums\ExitType;
use App\Livewire\Accounting\AccountingBoard;
use App\Livewire\Stock\EntryForm;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\StockEntry;
use App\Models\Unit;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    private User $gerant;
    private Product $riz;

    protected function setUp(): void
    {
        parent::setUp();

        config(['naneu.vat.enabled' => false]);

        $this->gerant = User::factory()->gerant()->create()->refresh();

        $unite = Unit::create(['name' => 'Sac', 'symbol' => 'sac', 'allows_decimal' => false]);
        $this->riz = Product::create([
            'sku' => 'NAN-0001', 'name' => 'Riz parfumé 5 kg', 'unit_id' => $unite->id,
            'sale_price' => 4200, 'alert_quantity' => 2, 'is_perishable' => false, 'is_active' => true,
        ]);
    }

    public function test_default_expense_categories_exist(): void
    {
        $this->assertEqualsCanonicalizing(
            ['transport', 'vignettes', 'energie'],
            ExpenseCategory::pluck('slug')->all(),
        );
    }

    public function test_net_profit_deducts_losses_and_expenses_from_gross_margin(): void
    {
        $stock = app(StockService::class);
        $stock->receive($this->gerant, [new EntryLineData($this->riz->id, 10, 3500)]);
        $stock->issue($this->gerant, ExitType::Vente, [new ExitLineData($this->riz->id, 2, 4200)]); // marge 1 400
        $stock->issue($this->gerant, ExitType::Casse, [new ExitLineData($this->riz->id, 1)]);         // perte 3 500

        Expense::create([
            'reference' => Expense::nextReference(), 'expense_category_id' => ExpenseCategory::where('slug', 'vignettes')->value('id'),
            'expense_date' => now()->toDateString(), 'label' => 'Rouleau', 'amount' => 600, 'user_id' => $this->gerant->id,
        ]);

        $r = app(AccountingService::class)->profitAndLoss(now()->startOfMonth()->toDateString(), now()->toDateString());

        $this->assertSame(8400, $r['ventes']);
        $this->assertSame(7000, $r['cout_ventes']);
        $this->assertSame(1400, $r['marge_brute']);
        $this->assertSame(3500, $r['pertes']);
        $this->assertSame(600, $r['charges']);
        $this->assertSame(1400 - 3500 - 600, $r['resultat']);
        $this->assertSame(600, $r['par_categorie']->firstWhere('nom', 'Vignettes (étiquettes produits)')['montant']);
    }

    public function test_manager_records_edits_and_deletes_an_expense(): void
    {
        $energie = ExpenseCategory::where('slug', 'energie')->value('id');

        $t = Livewire::actingAs($this->gerant)->test(AccountingBoard::class)
            ->call('nouvelle')
            ->set('expense_category_id', $energie)
            ->set('label', 'Facture électricité')
            ->set('amount', '18 450')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $charge = Expense::sole();
        $this->assertSame(18450, $charge->amount);
        $this->assertStringStartsWith('CHG-', $charge->reference);

        $t->call('editer', $charge->id)->set('amount', '19 000')->call('enregistrer')->assertHasNoErrors();
        $this->assertSame(19000, $charge->fresh()->amount);

        $t->call('supprimer', $charge->id);
        $this->assertSoftDeleted($charge);
        $this->assertNotSame($charge->reference, Expense::nextReference());
    }

    public function test_expense_requires_positive_amount_and_category(): void
    {
        Livewire::actingAs($this->gerant)->test(AccountingBoard::class)
            ->call('nouvelle')
            ->set('label', 'Taxi')
            ->set('amount', '0')
            ->call('enregistrer')
            ->assertHasErrors(['expense_category_id']);

        $this->assertSame(0, Expense::count());
    }

    public function test_entry_form_records_transport_as_linked_expense(): void
    {
        Livewire::actingAs($this->gerant)->test(EntryForm::class)
            ->call('ajouterProduit', $this->riz->id)
            ->set('lignes.0.quantity', 4)
            ->set('lignes.0.purchase_price', '3500')
            ->set('transport', '2 000')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $entree = StockEntry::sole();
        $charge = Expense::sole();

        $this->assertSame($entree->id, $charge->stock_entry_id);
        $this->assertSame('transport', $charge->category->slug);
        $this->assertSame(2000, $charge->amount);
        // Le transport reste une charge : le prix d'achat du lot ne bouge pas.
        $this->assertSame(14000, $entree->total_cost);
        $this->assertSame(3500, $entree->batches()->first()->purchase_price);
    }

    public function test_cashier_cannot_open_accounting(): void
    {
        $caissier = User::factory()->create()->refresh();

        $this->actingAs($caissier)->get(route('comptabilite.index'))->assertForbidden();
        $this->actingAs($this->gerant)->get(route('comptabilite.index'))->assertOk()->assertSee('Bénéfice net');
    }
}
