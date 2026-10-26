<?php

namespace Tests\Feature;

use App\Data\EntryLineData;
use App\Enums\ExitType;
use App\Enums\UserRole;
use App\Livewire\Products\ProductForm;
use App\Livewire\Stock\EntryForm;
use App\Livewire\Stock\ExitForm;
use App\Livewire\Users\UserManager;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SupplierSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Parcourt chaque écran avec un jeu de données réaliste. Sert de garde
 * contre les erreurs de rendu (composant manquant, variable non passée à
 * la vue) que les tests de service ne verraient pas.
 */
class ScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingSeeder::class, UnitSeeder::class, CategorySeeder::class, SupplierSeeder::class]);
        $this->seed(\Database\Seeders\ProductSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@naneushop.tg', 'password' => 'motdepasse',
            'role' => UserRole::Admin, 'is_active' => true,
        ])->fresh();

        $this->actingAs($this->admin);
    }

    private function approvisionner(): void
    {
        $stock = app(StockService::class);

        Product::query()->limit(8)->get()->each(function (Product $produit, int $index) use ($stock) {
            $stock->receive($this->admin, [
                new EntryLineData(
                    productId: $produit->id,
                    quantity: 10 + $index,
                    purchasePrice: max(100, $produit->last_purchase_price),
                    expiryDate: $produit->is_perishable ? Carbon::now()->addDays(5 + $index * 10) : null,
                ),
            ]);
        });
    }

    public function test_tous_les_ecrans_principaux_repondent(): void
    {
        $this->approvisionner();

        $routes = [
            'tableau-de-bord', 'produits.index', 'produits.create',
            'categories.index', 'fournisseurs.index',
            'entrees.index', 'entrees.create',
            'sorties.index', 'sorties.create',
            'lots.index', 'mouvements.index',
            'alertes.index', 'peremptions.index',
            'inventaires.index', 'rapports.index',
            'utilisateurs.index', 'parametres.index', 'journal.index',
            'profile',
        ];

        foreach ($routes as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertDontSee('Whoops', false);
        }
    }

    public function test_les_ecrans_de_detail_repondent(): void
    {
        $this->approvisionner();

        $produit = Product::whereHas('batches')->first();

        $this->get(route('produits.edit', $produit))->assertOk();
        $this->get(route('produits.lots', $produit))->assertOk();
        $this->get(route('entrees.show', StockEntry::first()))->assertOk();

        app(StockService::class)->issue(
            $this->admin,
            ExitType::Vente,
            [new \App\Data\ExitLineData($produit->id, 2, $produit->sale_price)],
        );

        $this->get(route('sorties.show', StockExit::first()))->assertOk();
    }

    public function test_le_formulaire_dentree_cree_un_lot(): void
    {
        $produit = Product::where('is_perishable', true)->first();

        Livewire::test(EntryForm::class)
            ->call('ajouterProduit', $produit->id)
            ->set('lignes.0.quantity', 24)
            ->set('lignes.0.purchase_price', '4 200')
            ->set('lignes.0.expiry_date', now()->addDays(60)->toDateString())
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame(1, StockEntry::count());

        $lot = StockBatch::first();
        $this->assertEquals(24, (float) $lot->quantity_remaining);
        // Le séparateur de milliers saisi au clavier doit être absorbé.
        $this->assertSame(4200, $lot->purchase_price);

        $this->assertEquals(24, (float) $produit->fresh()->quantity_on_hand);
    }

    public function test_le_formulaire_dentree_refuse_une_date_de_peremption_passee(): void
    {
        $produit = Product::where('is_perishable', true)->first();

        Livewire::test(EntryForm::class)
            ->call('ajouterProduit', $produit->id)
            ->set('lignes.0.quantity', 5)
            ->set('lignes.0.purchase_price', 1000)
            ->set('lignes.0.expiry_date', now()->subDay()->toDateString())
            ->call('enregistrer')
            ->assertHasErrors('lignes.0.expiry_date');

        $this->assertSame(0, StockEntry::count());
    }

    public function test_le_scan_dun_code_barres_ajoute_directement_la_ligne(): void
    {
        $produit = Product::whereNotNull('barcode')->first();

        Livewire::test(EntryForm::class)
            ->set('recherche', $produit->barcode)
            ->call('scanner')
            ->assertSet('lignes.0.product_id', $produit->id)
            ->assertSet('recherche', '');
    }

    public function test_lapercu_fifo_annonce_les_lots_qui_seront_consommes(): void
    {
        $stock = app(StockService::class);
        $produit = Product::first();

        $stock->receive($this->admin, [new EntryLineData($produit->id, 10, 4000)], ['entry_date' => now()->subDays(5)->toDateString()]);
        $stock->receive($this->admin, [new EntryLineData($produit->id, 10, 4500)], ['entry_date' => now()->toDateString()]);

        $composant = Livewire::test(ExitForm::class)
            ->call('ajouterProduit', $produit->id)
            ->set('lignes.0.quantity', 14);

        $apercu = $composant->get('apercu');

        $this->assertCount(2, $apercu[0]['prelevements']);
        $this->assertSame((10 * 4000) + (4 * 4500), $apercu[0]['cout']);
        $this->assertSame(0.0, (float) $apercu[0]['manquant']);
    }

    public function test_le_formulaire_de_sortie_bloque_une_quantite_superieure_au_stock(): void
    {
        $stock = app(StockService::class);
        $produit = Product::first();

        $stock->receive($this->admin, [new EntryLineData($produit->id, 5, 4000)]);

        Livewire::test(ExitForm::class)
            ->call('ajouterProduit', $produit->id)
            ->set('lignes.0.quantity', 50)
            ->call('enregistrer')
            ->assertHasErrors('lignes.0.quantity');

        $this->assertSame(0, StockExit::count());
        $this->assertEquals(5, (float) $produit->fresh()->quantity_on_hand);
    }

    public function test_la_creation_dun_produit_normalise_un_code_barres_vide(): void
    {
        Livewire::test(ProductForm::class)
            ->set('name', 'Manioc râpé')
            ->set('sku', 'NAN-9999')
            ->set('barcode', '')
            ->set('sale_price', '1 200')
            ->set('alert_quantity', '4')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $produit = Product::where('sku', 'NAN-9999')->first();

        $this->assertNotNull($produit);
        // Chaîne vide convertie en NULL, sinon la contrainte UNIQUE
        // n'accepterait qu'un seul produit sans code-barres.
        $this->assertNull($produit->barcode);
        $this->assertSame(1200, $produit->sale_price);
    }

    public function test_un_code_barres_deja_pris_est_refuse(): void
    {
        $existant = Product::whereNotNull('barcode')->first();

        Livewire::test(ProductForm::class)
            ->set('name', 'Doublon')
            ->set('sku', 'NAN-8888')
            ->set('barcode', $existant->barcode)
            ->set('sale_price', '1000')
            ->set('alert_quantity', '1')
            ->call('enregistrer')
            ->assertHasErrors('barcode');
    }

    public function test_un_administrateur_cree_un_compte_avec_mot_de_passe_genere(): void
    {
        $composant = Livewire::test(UserManager::class)
            ->call('nouveau')
            ->set('name', 'Nouvelle caissière')
            ->set('email', 'nouvelle@naneushop.tg')
            ->set('role', UserRole::Caissier->value)
            ->set('genererMotDePasse', true)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $utilisateur = User::where('email', 'nouvelle@naneushop.tg')->first();

        $this->assertNotNull($utilisateur);
        $this->assertSame(UserRole::Caissier, $utilisateur->role);
        $this->assertTrue($utilisateur->must_change_password);

        // Le mot de passe est affiché une fois, puis stocké haché uniquement.
        $clair = $composant->get('motDePasseAffiche');
        $this->assertNotNull($clair);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($clair, $utilisateur->password));

        $this->assertDatabaseHas('audit_logs', ['event' => 'utilisateur.cree']);
    }

    public function test_un_mot_de_passe_trop_faible_est_refuse(): void
    {
        Livewire::test(UserManager::class)
            ->call('nouveau')
            ->set('name', 'Compte faible')
            ->set('email', 'faible@naneushop.tg')
            ->set('genererMotDePasse', false)
            ->set('password', 'azerty')
            ->set('password_confirmation', 'azerty')
            ->call('enregistrer')
            ->assertHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'faible@naneushop.tg']);
    }

    public function test_la_recherche_produit_accepte_le_scan_et_le_texte(): void
    {
        $produit = Product::whereNotNull('barcode')->first();

        $this->assertTrue(
            Product::search($produit->barcode)->get()->contains('id', $produit->id),
            'Le code-barres doit être trouvé en égalité stricte.'
        );

        $this->assertTrue(
            Product::search(mb_substr($produit->name, 0, 5))->get()->contains('id', $produit->id),
            'Le nom doit être trouvé en recherche partielle.'
        );
    }
}
