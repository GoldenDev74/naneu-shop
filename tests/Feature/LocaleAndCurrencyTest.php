<?php

namespace Tests\Feature;

use App\Livewire\Settings\SettingsPanel;
use App\Models\Setting;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Langue d'interface (français / anglais) et devise d'affichage.
 */
class LocaleAndCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_franc_cfa_est_la_devise_par_defaut(): void
    {
        $this->assertSame('XOF', Money::currency()['code']);
        $this->assertSame('12 500 FCFA', Money::format(12500));
    }

    public function test_le_format_suit_la_langue(): void
    {
        app()->setLocale('en');

        $this->assertSame('12,500 FCFA', Money::format(12500));
        $this->assertSame('1,234.500', Money::number(1234.5, 3));
    }

    public function test_une_devise_choisie_change_le_symbole_et_sa_position(): void
    {
        Setting::put('currency_code', 'EUR');

        $this->assertSame('12 500 €', Money::format(12500));

        app()->setLocale('en');
        $this->assertSame('€12,500', Money::format(12500));
    }

    public function test_la_saisie_accepte_les_separateurs_de_milliers_des_deux_langues(): void
    {
        $this->assertSame(12500, Money::parse('12 500'));
        $this->assertSame(12500, Money::parse('12,500'));
        $this->assertSame(12500, Money::parse('12.500'));
        $this->assertSame(13, Money::parse('12,5'));
    }

    public function test_la_langue_choisie_est_memorisee_sur_le_compte(): void
    {
        $utilisateur = User::factory()->admin()->create();

        $this->actingAs($utilisateur)
            ->from(route('tableau-de-bord'))
            ->post(route('langue', 'en'))
            ->assertRedirect(route('tableau-de-bord'));

        $this->assertSame('en', $utilisateur->fresh()->locale);

        $this->get(route('tableau-de-bord'))->assertSee('Dashboard');
    }

    public function test_une_langue_inconnue_est_refusee(): void
    {
        $this->post(route('langue', 'de'))->assertNotFound();
    }

    public function test_la_page_de_connexion_bascule_en_anglais(): void
    {
        $this->post(route('langue', 'en'));

        $this->get(route('login'))->assertSee('Sign in')->assertSee('Remember me');
    }

    public function test_l_administrateur_choisit_la_devise_dans_les_parametres(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(SettingsPanel::class)
            ->assertSet('currency_code', 'XOF')
            ->set('currency_code', 'USD')
            ->assertSee('12 500 $')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame('USD', Setting::get('currency_code'));
    }

    public function test_une_devise_hors_liste_est_refusee(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(SettingsPanel::class)
            ->set('currency_code', 'BTC')
            ->call('enregistrer')
            ->assertHasErrors('currency_code');
    }
}
