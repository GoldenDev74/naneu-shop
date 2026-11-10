<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Tests\TestCase;

/**
 * Les droits s'appliquent aussi aux pages déjà ouvertes : chaque clic dans
 * une page Livewire passe par /livewire/update, qui doit rejouer les
 * contrôles de la route d'origine (menu, compte actif, mot de passe).
 * Et le temps réel (Reverb) suit les mêmes droits que l'interface.
 */
class LiveAccessTest extends TestCase
{
    use RefreshDatabase;

    private function compte(UserRole $role, string $identifiant, ?array $permissions = null): User
    {
        $user = User::create([
            'name' => ucfirst($identifiant), 'username' => $identifiant, 'email' => "{$identifiant}@naneushop.tg",
            'password' => Hash::make('MotDePasseSolide2026!'), 'role' => $role, 'is_active' => true,
        ]);
        $user->forceFill(['permissions' => $permissions])->save();

        return $user->fresh();
    }

    /** Ouvre la page et renvoie l'instantané du composant demandé, tel que le navigateur le reçoit. */
    private function ouvrir(string $route, string $composant): string
    {
        $html = $this->get(route($route))->assertOk()->getContent();

        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $trouves);

        foreach ($trouves[1] as $brut) {
            $instantane = htmlspecialchars_decode($brut, ENT_QUOTES);

            if (str_contains(json_decode($instantane, true)['memo']['name'] ?? '', $composant)) {
                return $instantane;
            }
        }

        $this->fail("Composant {$composant} introuvable sur la page {$route}.");
    }

    /** Rejoue un aller-retour serveur (ce que fait chaque clic) depuis la page déjà ouverte. */
    private function cliquer(string $instantane): TestResponse
    {
        return $this->withHeaders(['X-Livewire' => 'true'])->postJson(
            app(HandleRequests::class)->getUpdateUri(),
            ['components' => [[
                'snapshot' => $instantane,
                'updates'  => [],
                'calls'    => [],
            ]]],
        );
    }

    public function test_un_gerant_retrograde_perd_les_rapports_deja_ouverts(): void
    {
        $gerant = $this->compte(UserRole::Gerant, 'kossi');
        $this->actingAs($gerant);

        $rapports = $this->ouvrir('rapports.index', 'report-centre');
        $this->cliquer($rapports)->assertOk();

        $gerant->forceFill(['role' => UserRole::Magasinier])->save();

        $this->cliquer($rapports)->assertForbidden();
    }

    public function test_un_menu_retire_par_la_gerance_se_ferme_meme_page_ouverte(): void
    {
        $stock = $this->compte(UserRole::Magasinier, 'yawa');
        $this->actingAs($stock);

        $fournisseurs = $this->ouvrir('fournisseurs.index', 'supplier-manager');
        $this->cliquer($fournisseurs)->assertOk();

        $stock->forceFill(['permissions' => ['produits' => 'complet']])->save();

        $this->cliquer($fournisseurs)->assertForbidden();
    }

    public function test_un_compte_suspendu_ne_peut_plus_agir_depuis_une_page_ouverte(): void
    {
        $gerant = $this->compte(UserRole::Gerant, 'kossi');
        $this->actingAs($gerant);

        $rapports = $this->ouvrir('rapports.index', 'report-centre');

        $gerant->forceFill(['is_active' => false])->save();

        $this->cliquer($rapports)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_le_canal_des_alertes_suit_le_droit_sur_le_menu_alertes(): void
    {
        $autoriser = Broadcast::driver()->getChannels()['naneu.alertes'];

        $venteAvecAlertes = $this->compte(UserRole::Caissier, 'afi', ['caisse' => 'complet', 'alertes' => 'lecture']);
        $stockSansAlertes = $this->compte(UserRole::Magasinier, 'yawa', ['produits' => 'complet']);
        $venteParDefaut   = $this->compte(UserRole::Caissier, 'ama');
        $gerant           = $this->compte(UserRole::Gerant, 'kossi');

        $this->assertTrue($autoriser($venteAvecAlertes));
        $this->assertFalse($autoriser($stockSansAlertes));
        $this->assertFalse($autoriser($venteParDefaut));
        $this->assertTrue($autoriser($gerant));

        $gerant->forceFill(['is_active' => false])->save();
        $this->assertFalse($autoriser($gerant->fresh()));
    }
}
