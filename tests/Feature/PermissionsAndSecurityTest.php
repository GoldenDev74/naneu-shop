<?php

namespace Tests\Feature;

use App\Enums\AccessLevel;
use App\Enums\UserRole;
use App\Http\Middleware\LogoutIdleSession;
use App\Livewire\Categories\CategoryManager;
use App\Livewire\Users\UserManager;
use App\Models\User;
use App\Notifications\AccountLockedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Droits par menu attribués par la gérance (aucun / lecture / complet) et
 * connexion sécurisée (verrouillage, inactivité, sessions coupées).
 */
class PermissionsAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $gerante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gerante = $this->compte(UserRole::Gerant, 'gerante');
    }

    private function compte(UserRole $role, string $identifiant, ?array $permissions = null): User
    {
        $user = User::create([
            'name' => ucfirst($identifiant), 'username' => $identifiant, 'email' => "{$identifiant}@naneushop.tg",
            'password' => Hash::make('MotDePasseSolide2026!'), 'role' => $role, 'is_active' => true,
        ]);
        $user->forceFill(['permissions' => $permissions])->save();

        return $user->fresh();
    }

    public function test_le_profil_vente_a_la_caisse_et_le_reste_en_lecture(): void
    {
        $vente = $this->compte(UserRole::Caissier, 'afi');

        $this->assertSame(AccessLevel::Complet, $vente->accessLevel('caisse'));
        foreach (['produits', 'categories', 'fournisseurs', 'entrees', 'sorties'] as $menu) {
            $this->assertSame(AccessLevel::Lecture, $vente->accessLevel($menu), $menu);
        }
        $this->assertNull($vente->accessLevel('inventaires'));
        $this->assertSame('caisse', $vente->homeRoute());
    }

    public function test_la_gerante_attribue_des_menus_et_l_acces_suit(): void
    {
        $vente = $this->compte(UserRole::Caissier, 'afi');

        // Avant : produits en lecture seule.
        $this->actingAs($vente)->get(route('produits.create'))->assertForbidden();
        $this->actingAs($vente)->get(route('inventaires.index'))->assertForbidden();

        Livewire::actingAs($this->gerante)->test(UserManager::class)
            ->call('editer', $vente->id)
            ->assertSet('droits.produits', 'lecture')
            ->set('droits.produits', 'complet')
            ->set('droits.inventaires', 'complet')
            ->set('droits.sorties', '')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $vente->refresh();
        $this->assertSame('complet', $vente->customPermissions()['produits']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'utilisateur.droits_modifies']);

        $this->actingAs($vente)->get(route('produits.create'))->assertOk();
        $this->actingAs($vente)->get(route('inventaires.index'))->assertOk()->assertSee(__('Lancer un inventaire'));
        $this->actingAs($vente)->get(route('sorties.index'))->assertForbidden();
    }

    public function test_revenir_aux_droits_du_profil_efface_la_personnalisation(): void
    {
        $vente = $this->compte(UserRole::Caissier, 'afi', ['caisse' => 'complet']);

        Livewire::actingAs($this->gerante)->test(UserManager::class)
            ->call('editer', $vente->id)
            ->call('droitsDuProfil')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertNull($vente->fresh()->customPermissions());
    }

    public function test_les_menus_de_gerance_ne_s_attribuent_jamais(): void
    {
        $stock = $this->compte(UserRole::Magasinier, 'kofi', ['comptabilite' => 'complet', 'utilisateurs' => 'complet', 'lots' => 'complet']);

        $this->assertFalse($stock->canAccess('comptabilite'));
        $this->assertFalse($stock->canAccess('utilisateurs'));
        // Les lots se consultent seulement.
        $this->assertSame(AccessLevel::Lecture, $stock->accessLevel('lots'));

        $this->actingAs($stock)->get(route('comptabilite.index'))->assertForbidden();
    }

    public function test_lecture_seule_cache_les_boutons_et_refuse_les_actions(): void
    {
        $vente = $this->compte(UserRole::Caissier, 'afi');

        $this->actingAs($vente)->get(route('categories.index'))->assertOk()->assertDontSee(__('Nouvelle catégorie'));

        Livewire::actingAs($vente)->test(CategoryManager::class)
            ->set('name', 'Pirate')->call('enregistrer')->assertForbidden();
    }

    public function test_le_compte_est_verrouille_apres_trop_d_echecs_et_la_gerance_prevenue(): void
    {
        Notification::fake();
        config(['naneu.security.max_failed_logins' => 3]);
        $vente = $this->compte(UserRole::Caissier, 'afi');

        foreach (range(1, 3) as $essai) {
            Volt::test('pages.auth.login')->set('form.login', 'afi')->set('form.password', 'mauvais')->call('login')
                ->assertHasErrors('form.login');
        }

        $this->assertTrue($vente->fresh()->isLocked());
        Notification::assertSentTo($this->gerante, AccountLockedNotification::class);

        // Même le bon mot de passe est refusé tant que le verrou tient.
        Volt::test('pages.auth.login')->set('form.login', 'afi')->set('form.password', 'MotDePasseSolide2026!')->call('login')
            ->assertHasErrors('form.login');
        $this->assertGuest();

        // La gérance déverrouille : la connexion repasse.
        Livewire::actingAs($this->gerante)->test(UserManager::class)->call('deverrouiller', $vente->id);
        auth()->logout();

        Volt::test('pages.auth.login')->set('form.login', 'afi')->set('form.password', 'MotDePasseSolide2026!')->call('login')
            ->assertHasNoErrors();
        $this->assertAuthenticatedAs($vente);
    }

    public function test_une_session_inactive_est_fermee(): void
    {
        config(['naneu.security.idle_minutes' => 30]);

        $this->actingAs($this->gerante)
            ->withSession([LogoutIdleSession::CLE => now()->subMinutes(31)->timestamp])
            ->get(route('produits.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'deconnexion.inactivite']);
    }

    public function test_une_session_active_reste_ouverte(): void
    {
        config(['naneu.security.idle_minutes' => 30]);

        $this->actingAs($this->gerante)
            ->withSession([LogoutIdleSession::CLE => now()->subMinutes(5)->timestamp])
            ->get(route('produits.index'))
            ->assertOk();
    }

    public function test_changer_le_mot_de_passe_d_un_employe_coupe_ses_sessions(): void
    {
        config(['session.driver' => 'database']);
        $vente = $this->compte(UserRole::Caissier, 'afi');

        DB::table('sessions')->insert([
            'id' => 'session-de-afi', 'user_id' => $vente->id, 'ip_address' => '10.0.0.2',
            'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->timestamp,
        ]);

        Livewire::actingAs($this->gerante)->test(UserManager::class)
            ->call('ouvrirMotDePasse', $vente->id)
            ->set('mdpMode', 'generer')
            ->call('appliquerMotDePasse');

        $this->assertDatabaseMissing('sessions', ['id' => 'session-de-afi']);
    }
}
