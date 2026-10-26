<?php

namespace Tests\Feature;

use App\Data\EntryLineData;
use App\Data\ExitLineData;
use App\Enums\DocumentStatus;
use App\Enums\ExitType;
use App\Enums\UserRole;
use App\Livewire\Inventory\InventoryForm;
use App\Livewire\Products\ProductForm;
use App\Livewire\Products\ProductList;
use App\Livewire\Settings\SettingsPanel;
use App\Livewire\Stock\EntryForm;
use App\Livewire\Stock\ExitForm;
use App\Models\Category;
use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Incohérences relevées au test « gérante » du 19/10 :
 * R2 réception antidatée, R3 prix d'achat, S3 prix de vente modifié,
 * S5 numérotation des bons, I1/I3 inventaire, RE2 recherche sans accents,
 * PR3/PR4/PR5 fiche et liste produits, C1 ordre des catégories.
 */
class CoherenceRulesTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;
    private User $gerante;
    private User $magasinier;
    private Product $riz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = app(StockService::class);
        $this->gerante = User::factory()->gerant()->create();
        $this->magasinier = User::factory()->magasinier()->create();

        $unite = Unit::create(['name' => 'Pièce', 'symbol' => 'pcs', 'allows_decimal' => false]);

        $this->riz = Product::create([
            'sku'            => 'NAN-0100',
            'name'           => 'Riz parfumé',
            'unit_id'        => $unite->id,
            'sale_price'     => 1000,
            'vat_rate'       => 0,
            'alert_quantity' => 1,
            'is_perishable'  => false,
            'is_active'      => true,
        ]);
    }

    private function recevoir(float $quantite, int $prix, string $date = 'today'): void
    {
        $this->stock->receive(
            $this->gerante,
            [new EntryLineData($this->riz->id, $quantite, $prix)],
            ['entry_date' => Carbon::parse($date)->toDateString()],
        );
    }

    private function vendre(float $quantite): StockExit
    {
        return $this->stock->issue($this->gerante, ExitType::Vente, [new ExitLineData($this->riz->id, $quantite)]);
    }

    private function ligneEntree(array $surcharge = []): array
    {
        return array_merge([
            'product_id' => $this->riz->id, 'nom' => $this->riz->name, 'sku' => $this->riz->sku,
            'unite' => 'pcs', 'decimal' => false, 'perissable' => true, 'conservation' => null,
            'quantity' => 2, 'purchase_price' => '500', 'expiry_date' => '', 'batch_number' => '',
        ], $surcharge);
    }

    public function test_r2_une_reception_antidatee_accepte_une_peremption_deja_passee_aujourdhui(): void
    {
        Livewire::actingAs($this->gerante)->test(EntryForm::class)
            ->set('entry_date', now()->subDays(20)->toDateString())
            ->set('lignes', [$this->ligneEntree(['expiry_date' => now()->subDays(5)->toDateString()])])
            ->call('enregistrer')
            ->assertHasNoErrors();

        Livewire::actingAs($this->gerante)->test(EntryForm::class)
            ->set('entry_date', now()->subDays(20)->toDateString())
            ->set('lignes', [$this->ligneEntree(['expiry_date' => now()->subDays(25)->toDateString()])])
            ->call('enregistrer')
            ->assertHasErrors('lignes.0.expiry_date');
    }

    public function test_r3_un_prix_d_achat_nul_ou_non_numerique_est_refuse(): void
    {
        foreach (['0', 'abc', ''] as $prix) {
            Livewire::actingAs($this->gerante)->test(EntryForm::class)
                ->set('lignes', [$this->ligneEntree(['perissable' => false, 'purchase_price' => $prix])])
                ->call('enregistrer')
                ->assertHasErrors('lignes.0.purchase_price');
        }

        $this->assertSame(0, StockEntry::count());

        $this->expectException(\InvalidArgumentException::class);
        $this->recevoir(1, 0);
    }

    public function test_s3_prix_modifie_motif_obligatoire_plafond_et_trace(): void
    {
        $this->recevoir(10, 500);

        $ligne = fn (string $prix) => [[
            'product_id' => $this->riz->id, 'nom' => $this->riz->name, 'sku' => $this->riz->sku, 'unite' => 'pcs',
            'stock' => 10, 'tva' => 0, 'quantity' => 1, 'sale_price' => $prix, 'catalogue' => 1000,
        ]];

        // Prix nul : refusé.
        Livewire::actingAs($this->gerante)->test(ExitForm::class)
            ->set('type', ExitType::Vente->value)->set('lignes', $ligne('0'))
            ->call('enregistrer')->assertHasErrors('lignes.0.sale_price');

        // Remise sans motif : refusée.
        Livewire::actingAs($this->gerante)->test(ExitForm::class)
            ->set('type', ExitType::Vente->value)->set('lignes', $ligne('950'))
            ->call('enregistrer')->assertHasErrors('note');

        // Remise de 30 % par le magasinier : au-delà du plafond.
        Livewire::actingAs($this->magasinier)->test(ExitForm::class)
            ->set('type', ExitType::Vente->value)->set('lignes', $ligne('700'))->set('note', 'Client fidèle')
            ->call('enregistrer')->assertHasErrors('lignes.0.sale_price');

        // La gérante peut l'accorder ; l'écart est tracé au journal.
        Livewire::actingAs($this->gerante)->test(ExitForm::class)
            ->set('type', ExitType::Vente->value)->set('lignes', $ligne('700'))->set('note', 'Client fidèle')
            ->call('enregistrer')->assertHasNoErrors();

        $this->assertSame(700, (int) StockExit::sole()->total_sale);
        $this->assertDatabaseHas('audit_logs', ['event' => 'vente.prix_modifie']);
    }

    public function test_s5_la_reference_est_attribuee_a_l_enregistrement(): void
    {
        $this->recevoir(10, 500);

        // Deux formulaires ouverts en même temps affichent le même numéro provisoire…
        $a = Livewire::actingAs($this->gerante)->test(EntryForm::class);
        $b = Livewire::actingAs($this->gerante)->test(EntryForm::class);
        $this->assertSame($a->get('reference'), $b->get('reference'));

        // …mais chaque enregistrement reçoit le sien.
        foreach ([$a, $b] as $formulaire) {
            $formulaire->set('lignes', [$this->ligneEntree(['perissable' => false])])
                ->call('enregistrer')->assertHasNoErrors();
        }

        $this->assertSame(3, StockEntry::distinct()->count('reference'));
    }

    public function test_s5_un_numero_supprime_ne_resert_pas(): void
    {
        $this->recevoir(1, 500);
        $this->recevoir(1, 500);

        StockEntry::orderBy('id')->first()->lines()->delete();
        StockEntry::orderBy('id')->first()->delete();

        $this->assertStringEndsWith('-0003', StockEntry::nextReference());
    }

    public function test_i1_l_ecart_est_valorise_au_cout_fifo_des_lots(): void
    {
        $this->recevoir(5, 400, '-10 days');
        $this->recevoir(5, 600, '-2 days');   // dernier prix d'achat : 600

        // Manque de 6 : 5 × 400 (lot ancien) + 1 × 600.
        $this->assertSame(-2600, $this->stock->varianceValue($this->riz->fresh(), -6));

        // Surplus de 2 : coût moyen des lots en stock (500).
        $this->assertSame(1000, $this->stock->varianceValue($this->riz->fresh(), 2));
    }

    public function test_i3_une_vente_pendant_le_comptage_ne_fausse_pas_l_ecart(): void
    {
        $this->recevoir(10, 500);

        $inventaire = InventoryCount::create([
            'reference' => InventoryCount::nextReference(), 'user_id' => $this->gerante->id,
            'count_date' => now()->toDateString(), 'scope' => 'complet', 'status' => DocumentStatus::Brouillon,
        ]);
        $ligne = $inventaire->lines()->create([
            'product_id' => $this->riz->id, 'expected_quantity' => 10, 'variance' => 0, 'variance_value' => 0,
        ]);

        // Vente de 3 après le lancement, avant le comptage : 7 en rayon, rien ne manque.
        $this->vendre(3);

        $formulaire = Livewire::actingAs($this->gerante)->test(InventoryForm::class, ['count' => $inventaire])
            ->set("saisies.{$ligne->id}", '7')
            ->call('compter', $ligne->id);

        $this->assertEquals(0, (float) $ligne->fresh()->variance);

        // Recompté à 6 (une pièce manque), puis une vente de 2 avant la validation.
        $formulaire->set("saisies.{$ligne->id}", '6')->call('compter', $ligne->id);
        $this->vendre(2);
        $formulaire->call('valider');

        // 7 − 2 vendues − 1 manquante = 4 : la vente n'est pas écrasée.
        $this->assertEquals(4, (float) $this->riz->fresh()->quantity_on_hand);
        $this->assertSame(-500, (int) $inventaire->fresh()->total_variance_value);
    }

    public function test_re2_la_recherche_ignore_les_accents_et_la_casse(): void
    {
        $this->riz->update(['name' => 'Crème fraîche ÉPAISSE']);

        foreach (['creme', 'CRÈME', 'fraiche', 'epaisse', 'Épaisse'] as $terme) {
            $this->assertSame(1, Product::query()->search($terme)->count(), $terme);
        }
    }

    public function test_s3_la_gerante_regle_le_plafond_de_remise_dans_les_parametres(): void
    {
        $this->recevoir(10, 500);

        Livewire::actingAs($this->gerante)->test(SettingsPanel::class)
            ->set('max_discount_percent', '0')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertEquals(0, config('naneu.sales.max_discount_percent'));

        // Plafond à 0 : même 5 % de remise passe par la gérance.
        Livewire::actingAs($this->magasinier)->test(ExitForm::class)
            ->set('type', ExitType::Vente->value)
            ->set('lignes', [[
                'product_id' => $this->riz->id, 'nom' => $this->riz->name, 'sku' => $this->riz->sku, 'unite' => 'pcs',
                'stock' => 10, 'tva' => 0, 'quantity' => 1, 'sale_price' => '950', 'catalogue' => 1000,
            ]])
            ->set('note', 'Fin de série')
            ->call('enregistrer')
            ->assertHasErrors('lignes.0.sale_price');
    }

    public function test_c1_categories_en_ordre_francais_et_en_arbre(): void
    {
        $riz = Category::create(['name' => 'Riz', 'color' => '#56c389']);
        $epicerie = Category::create(['name' => 'Épicerie', 'color' => '#56c389']);
        Category::create(['name' => 'Sucre', 'parent_id' => $epicerie->id, 'color' => '#56c389']);
        Category::create(['name' => 'Conserves', 'parent_id' => $epicerie->id, 'color' => '#56c389']);
        Category::create(['name' => 'Basmati', 'parent_id' => $riz->id, 'color' => '#56c389']);

        $arbre = Category::enArbre(Category::all());

        $this->assertSame(['Épicerie', 'Conserves', 'Sucre', 'Riz', 'Basmati'], $arbre->pluck('name')->all());
        $this->assertSame([0, 1, 1, 0, 1], $arbre->pluck('profondeur')->all());
    }

    public function test_pr3_pr4_unite_obligatoire_et_perissable_selon_la_categorie(): void
    {
        $hygiene = Category::create(['name' => 'Hygiène', 'color' => '#56c389']);
        $frais = Category::create(['name' => 'Frais', 'color' => '#56c389']);
        Product::create(['sku' => 'NAN-0200', 'name' => 'Savon', 'category_id' => $hygiene->id, 'unit_id' => $this->riz->unit_id,
            'sale_price' => 300, 'vat_rate' => 0, 'alert_quantity' => 1, 'is_perishable' => false, 'is_active' => true]);
        Product::create(['sku' => 'NAN-0201', 'name' => 'Lait', 'category_id' => $frais->id, 'unit_id' => $this->riz->unit_id,
            'sale_price' => 500, 'vat_rate' => 0, 'alert_quantity' => 1, 'is_perishable' => true, 'is_active' => true]);

        $formulaire = Livewire::actingAs($this->gerante)->test(ProductForm::class)
            ->assertSet('is_perishable', false)
            ->assertSet('unit_id', $this->riz->unit_id)
            ->set('category_id', $frais->id)
            ->assertSet('is_perishable', true)
            ->set('category_id', $hygiene->id)
            ->assertSet('is_perishable', false);

        $formulaire->set('name', 'Dentifrice')->set('unit_id', null)
            ->call('enregistrer')
            ->assertHasErrors('unit_id');
    }

    public function test_pr5_liste_vide_distingue_filtres_et_catalogue_vide(): void
    {
        Livewire::actingAs($this->gerante)->test(ProductList::class)
            ->set('recherche', 'introuvable')
            ->assertSee(__('Aucun produit ne correspond à ces filtres.'));

        Product::query()->forceDelete();

        Livewire::actingAs($this->gerante)->test(ProductList::class)
            ->assertSee(__('Le catalogue est vide pour l\'instant.'));
    }
}
