<?php

namespace Tests\Feature;

use App\Enums\ExitType;
use App\Enums\UserRole;
use App\Models\StockExit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `fresh()` recharge la ligne complète : une session réelle dispose de
     * toutes les colonnes, et le mode strict d'Eloquent refuse de lire un
     * attribut qui n'a pas été récupéré.
     */
    private function creer(UserRole $role, bool $actif = true): User
    {
        return User::create([
            'name'      => $role->label(),
            'email'     => $role->value.'@naneushop.tg',
            'password'  => Hash::make('MotDePasseSolide2026!'),
            'role'      => $role,
            'is_active' => $actif,
        ])->fresh();
    }

    public function test_la_page_dinscription_publique_nexiste_pas(): void
    {
        // Naneu Shop n'est pas un service ouvert : seul un administrateur
        // crée des comptes.
        $this->get('/register')->assertNotFound();
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('register'));
    }

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get(route('tableau-de-bord'))->assertRedirect(route('login'));
        $this->get(route('produits.index'))->assertRedirect(route('login'));
        $this->get(route('utilisateurs.index'))->assertRedirect(route('login'));
    }

    public function test_un_compte_suspendu_perd_laccès_meme_avec_une_session_ouverte(): void
    {
        $utilisateur = $this->creer(UserRole::Caissier);

        $this->actingAs($utilisateur);
        $this->get(route('caisse'))->assertOk();

        $utilisateur->forceFill(['is_active' => false])->save();

        $this->get(route('caisse'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_un_caissier_natteint_ni_les_rapports_ni_ladministration(): void
    {
        $this->actingAs($this->creer(UserRole::Caissier));

        $this->get(route('rapports.index'))->assertForbidden();
        $this->get(route('utilisateurs.index'))->assertForbidden();
        $this->get(route('parametres.index'))->assertForbidden();
        $this->get(route('journal.index'))->assertForbidden();
    }

    public function test_la_gerante_atteint_tous_les_menus(): void
    {
        $this->actingAs($this->creer(UserRole::Gerant));

        foreach (['tableau-de-bord', 'caisse', 'rapports.index', 'comptabilite.index', 'utilisateurs.index', 'parametres.index', 'journal.index', 'entrees.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_le_profil_vente_ne_voit_que_la_caisse_et_le_catalogue_en_lecture(): void
    {
        $this->actingAs($this->creer(UserRole::Caissier));

        // Arrivée directe à la caisse.
        $this->get(route('tableau-de-bord'))->assertRedirect(route('caisse'));

        // Lecture seule sur le catalogue, les entrées et les sorties.
        foreach (['caisse', 'produits.index', 'categories.index', 'fournisseurs.index', 'entrees.index', 'sorties.index'] as $route) {
            $this->get(route($route))->assertOk();
        }

        foreach (['lots.index', 'peremptions.index', 'mouvements.index', 'inventaires.index', 'alertes.index', 'comptabilite.index',
                  'produits.create', 'entrees.create', 'sorties.create'] as $route) {
            $this->get(route($route))->assertForbidden();
        }

        $this->get(route('entrees.index'))->assertDontSee(__('Nouvelle entrée'));
        $this->get(route('sorties.index'))->assertDontSee(__('Nouvelle sortie'));

        // Lecture seule : ni bouton, ni action.
        $this->get(route('fournisseurs.index'))->assertDontSee(__('Nouveau fournisseur'));
        Livewire::actingAs(auth()->user())->test(\App\Livewire\Suppliers\SupplierManager::class)->call('nouveau')->assertForbidden();
        Livewire::test(\App\Livewire\Categories\CategoryManager::class)->set('name', 'Pirate')->call('enregistrer')->assertForbidden();
        $this->assertDatabaseMissing('categories', ['name' => 'Pirate']);
    }

    public function test_la_gestion_de_stock_voit_tout_sauf_le_pilotage(): void
    {
        $this->actingAs($this->creer(UserRole::Magasinier));

        foreach (['tableau-de-bord', 'caisse', 'produits.index', 'categories.index', 'fournisseurs.index', 'entrees.index', 'sorties.index', 'lots.index', 'peremptions.index', 'mouvements.index', 'inventaires.index', 'alertes.index'] as $route) {
            $this->get(route($route))->assertOk();
        }

        foreach (['rapports.index', 'comptabilite.index', 'utilisateurs.index', 'parametres.index', 'journal.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_un_administrateur_atteint_tout(): void
    {
        $this->actingAs($this->creer(UserRole::Admin));

        $this->get(route('tableau-de-bord'))->assertOk();
        $this->get(route('rapports.index'))->assertOk();
        $this->get(route('utilisateurs.index'))->assertOk();
        $this->get(route('parametres.index'))->assertOk();
        $this->get(route('journal.index'))->assertOk();
    }

    public function test_seul_un_magasinier_ou_plus_peut_sortir_du_stock_hors_vente(): void
    {
        $caissier = $this->creer(UserRole::Caissier);
        $magasinier = $this->creer(UserRole::Magasinier);

        // Un caissier vend, mais ne déclare pas une perte.
        $this->assertTrue($caissier->can('createOfType', [StockExit::class, ExitType::Vente]));
        $this->assertFalse($caissier->can('createOfType', [StockExit::class, ExitType::Perte]));
        $this->assertFalse($caissier->can('createOfType', [StockExit::class, ExitType::Peremption]));

        $this->assertTrue($magasinier->can('createOfType', [StockExit::class, ExitType::Perte]));
    }

    public function test_un_administrateur_ne_peut_pas_se_retrograder_lui_meme(): void
    {
        $admin = $this->creer(UserRole::Admin);

        $this->assertFalse($admin->can('changeRole', $admin));
        $this->assertFalse($admin->can('toggleActive', $admin));
        $this->assertFalse($admin->can('delete', $admin));
    }

    public function test_le_dernier_administrateur_actif_est_protege(): void
    {
        $seul = $this->creer(UserRole::Admin);

        $autre = User::create([
            'name' => 'Second admin', 'username' => 'admin2', 'email' => 'admin2@naneushop.tg',
            'password' => Hash::make('MotDePasseSolide2026!'),
            'role' => UserRole::Admin, 'is_active' => true,
        ]);

        // Tant qu'ils sont deux, la suspension de l'un est permise.
        $this->assertTrue($autre->can('toggleActive', $seul));

        $autre->forceFill(['is_active' => false])->save();

        // Il ne reste qu'un administrateur actif : il devient intouchable.
        $this->assertFalse($autre->fresh()->can('toggleActive', $seul->fresh()));
    }

    public function test_le_mot_de_passe_est_hache_et_jamais_serialise(): void
    {
        $utilisateur = $this->creer(UserRole::Caissier);

        $this->assertNotSame('MotDePasseSolide2026!', $utilisateur->password);
        $this->assertTrue(Hash::check('MotDePasseSolide2026!', $utilisateur->password));

        $serialise = $utilisateur->toArray();

        $this->assertArrayNotHasKey('password', $serialise);
        $this->assertArrayNotHasKey('remember_token', $serialise);
    }

    public function test_une_connexion_reussie_ou_echouee_est_journalisee(): void
    {
        $utilisateur = $this->creer(UserRole::Gerant);

        // La connexion passe par le composant Volt de Breeze, pas par une
        // route POST : c'est ce chemin-là qu'il faut éprouver.
        Volt::test('pages.auth.login')
            ->set('form.login', $utilisateur->email)
            ->set('form.password', 'MotDePasseSolide2026!')
            ->call('login');

        $this->assertAuthenticatedAs($utilisateur);

        $this->assertDatabaseHas('audit_logs', [
            'event'      => 'connexion.reussie',
            'user_email' => $utilisateur->email,
        ]);

        auth()->logout();

        Volt::test('pages.auth.login')
            ->set('form.login', $utilisateur->email)
            ->set('form.password', 'mauvais-mot-de-passe')
            ->call('login')
            ->assertHasErrors('form.login');

        $this->assertDatabaseHas('audit_logs', ['event' => 'connexion.echouee']);

        // Le mot de passe essayé ne doit jamais atterrir dans le journal.
        $journal = \App\Models\AuditLog::where('event', 'connexion.echouee')->first();

        $this->assertArrayNotHasKey('password', $journal->properties ?? []);
    }

    public function test_les_entetes_de_durcissement_sont_presents(): void
    {
        $this->actingAs($this->creer(UserRole::Admin));

        $this->get(route('tableau-de-bord'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'same-origin');
    }
}
