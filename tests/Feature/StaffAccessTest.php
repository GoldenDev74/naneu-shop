<?php

namespace Tests\Feature;

use App\Data\EntryLineData;
use App\Enums\UserRole;
use App\Livewire\Categories\CategoryManager;
use App\Livewire\Products\ProductList;
use App\Livewire\Users\UserManager;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\StaffActivityNotification;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Gestion des comptes par la gérance et notifications d'activité de la
 * gestion de stock.
 */
class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $gerante;
    private User $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->compte(UserRole::Admin, 'admin@naneushop.tg');
        $this->gerante = $this->compte(UserRole::Gerant, 'gerante@naneushop.tg');
        $this->stock = $this->compte(UserRole::Magasinier, 'stock@naneushop.tg');
    }

    private function compte(UserRole $role, string $email): User
    {
        return User::create([
            'name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => Hash::make('MotDePasseSolide2026!'),
            'role' => $role, 'is_active' => true,
        ])->fresh();
    }

    public function test_la_gerante_cree_un_compte_vente_mais_pas_un_gerant(): void
    {
        Livewire::actingAs($this->gerante)->test(UserManager::class)
            ->call('nouveau')
            ->set('name', 'Afi')->set('email', 'afi@naneushop.tg')->set('role', 'caissier')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame(UserRole::Caissier, User::where('email', 'afi@naneushop.tg')->value('role'));

        Livewire::actingAs($this->gerante)->test(UserManager::class)
            ->call('nouveau')
            ->set('name', 'Intrus')->set('email', 'intrus@naneushop.tg')->set('role', 'gerant')
            ->call('enregistrer')
            ->assertHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'intrus@naneushop.tg']);
    }

    public function test_la_gerante_ne_touche_pas_aux_comptes_admin(): void
    {
        $this->assertTrue($this->gerante->can('resetPassword', $this->stock));
        $this->assertFalse($this->gerante->can('resetPassword', $this->admin));
        $this->assertFalse($this->gerante->can('update', $this->admin));
        $this->assertFalse($this->gerante->can('delete', $this->admin));
        $this->assertTrue($this->admin->can('update', $this->gerante));

        Livewire::actingAs($this->gerante)->test(UserManager::class)
            ->call('ouvrirMotDePasse', $this->admin->id)
            ->assertForbidden();
    }

    public function test_la_gerante_choisit_ou_genere_le_mot_de_passe_dun_employe(): void
    {
        $t = Livewire::actingAs($this->gerante)->test(UserManager::class)
            ->call('ouvrirMotDePasse', $this->stock->id)
            ->set('mdpMode', 'saisir')
            ->set('nouveauMdp', 'NouveauSecret2026')
            ->set('nouveauMdp_confirmation', 'NouveauSecret2026')
            ->set('mdpExigerChangement', false)
            ->call('appliquerMotDePasse')
            ->assertHasNoErrors();

        $this->stock->refresh();
        $this->assertTrue(Hash::check('NouveauSecret2026', $this->stock->password));
        $this->assertFalse($this->stock->must_change_password);

        $t->call('ouvrirMotDePasse', $this->stock->id)
            ->set('mdpMode', 'generer')
            ->call('appliquerMotDePasse');

        $genere = $t->get('motDePasseAffiche');
        $this->stock->refresh();
        $this->assertNotEmpty($genere);
        $this->assertTrue(Hash::check($genere, $this->stock->password));
        $this->assertTrue($this->stock->must_change_password);
    }

    public function test_les_modifications_de_la_gestion_de_stock_sont_notifiees_a_la_gerance(): void
    {
        $unite = Unit::create(['name' => 'Sac', 'symbol' => 'sac', 'allows_decimal' => false]);

        $this->actingAs($this->stock);

        $produit = Product::create([
            'sku' => 'NAN-0001', 'name' => 'Riz parfumé 5 kg', 'unit_id' => $unite->id,
            'sale_price' => 4200, 'alert_quantity' => 2, 'is_perishable' => false, 'is_active' => true,
        ]);
        // Modification faite plus tard (autre requête) : instance relue.
        $produit->fresh()->update(['sale_price' => 4500]);

        // Mise à jour du stock par l'application : pas de bruit.
        app(StockService::class)->receive($this->stock, [new EntryLineData($produit->id, 5, 3500)]);

        Livewire::test(ProductList::class)->call('basculerActif', $produit->id);

        $categorie = Category::create(['name' => 'Épicerie', 'color' => '#56c389']);
        Livewire::test(CategoryManager::class)->call('supprimer', $categorie->id);

        $messages = $this->gerante->notifications()->get()
            ->filter(fn ($n) => $n->type === StaffActivityNotification::class)
            ->pluck('data.message')->sort()->values()->all();

        $this->assertSame(collect([
            'Stock (Gestion de stock) a créé le produit « Riz parfumé 5 kg ».',
            'Stock (Gestion de stock) a modifié le produit « Riz parfumé 5 kg » : prix de vente 4 200 FCFA → 4 500 FCFA.',
            'Stock (Gestion de stock) a enregistré l\'entrée « '.\App\Models\StockEntry::first()->reference.' » — 1 produit(s), 17 500 FCFA.',
            'Stock (Gestion de stock) a désactivé le produit « Riz parfumé 5 kg ».',
            'Stock (Gestion de stock) a créé la catégorie « Épicerie ».',
            'Stock (Gestion de stock) a supprimé la catégorie « Épicerie ».',
        ])->sort()->values()->all(), $messages);

        // Une seule ligne de journal par entrée (le listener était inscrit deux fois).
        $this->assertSame(1, \App\Models\AuditLog::where('event', 'stock.entree')->count());

        // L'administrateur reçoit les mêmes ; l'auteur, rien.
        $this->assertSame(6, $this->admin->notifications()->count());
        $this->assertSame(0, $this->stock->notifications()->count());
    }

    public function test_la_gerance_nest_pas_notifiee_de_ses_propres_actions(): void
    {
        $this->actingAs($this->gerante);

        Category::create(['name' => 'Boissons', 'color' => '#56c389']);

        $this->assertSame(0, $this->admin->notifications()->count());
    }
}
