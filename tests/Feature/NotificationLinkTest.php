<?php

namespace Tests\Feature;

use App\Data\EntryLineData;
use App\Livewire\Notifications\Bell;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le lien « Ouvrir » d'une notification doit rester sur l'origine que
 * l'utilisateur a dans son navigateur.
 *
 * Une URL absolue figée à la création (APP_URL au moment où la console
 * tournait) ferait basculer vers un autre hôte — `localhost` au lieu de
 * `127.0.0.1`, ou un autre port. Le cookie de session étant lié à l'hôte,
 * l'utilisateur atterrirait sur l'écran de connexion sans comprendre
 * pourquoi.
 */
class NotificationLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $produit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create()->fresh();

        $unite = Unit::create(['name' => 'Kilogramme', 'symbol' => 'kg', 'allows_decimal' => true]);

        $this->produit = Product::create([
            'sku'            => 'NAN-0001',
            'name'           => 'Odika du Gabon',
            'unit_id'        => $unite->id,
            'sale_price'     => 8500,
            'alert_quantity' => 8,
            'is_perishable'  => true,
            'is_active'      => true,
        ]);
    }

    private function declencherUneAlerte(): void
    {
        app(StockService::class)->receive($this->admin, [
            new EntryLineData($this->produit->id, 3, 5200),
        ]);
    }

    public function test_le_lien_stocke_est_relatif(): void
    {
        // On force une APP_URL différente de l'origine du navigateur :
        // c'est la situation d'une notification créée par le planificateur.
        config(['app.url' => 'http://localhost:8000']);

        $this->declencherUneAlerte();

        $donnees = $this->admin->notifications()->first()->data;

        $this->assertSame('/alertes', $donnees['url']);
        $this->assertStringStartsNotWith('http', $donnees['url']);
    }

    public function test_une_ancienne_url_absolue_est_ramenee_a_un_chemin(): void
    {
        $this->declencherUneAlerte();

        // Simule une notification écrite avant la correction.
        $notification = $this->admin->notifications()->first();
        $donnees = $notification->data;
        $donnees['url'] = 'http://localhost:8000/alertes?filtre=ouvertes';

        DB::table('notifications')
            ->where('id', $notification->id)
            ->update(['data' => json_encode($donnees)]);

        $composant = Livewire::actingAs($this->admin)->test(Bell::class);

        $this->assertSame(
            '/alertes?filtre=ouvertes',
            $composant->instance()->lien('http://localhost:8000/alertes?filtre=ouvertes'),
        );

        // Et le rendu ne doit plus contenir d'adresse absolue.
        $composant->assertDontSee('http://localhost:8000/alertes', false);
    }

    public function test_le_lien_rendu_reste_sur_lorigine_courante(): void
    {
        config(['app.url' => 'http://localhost:8000']);

        $this->declencherUneAlerte();

        Livewire::actingAs($this->admin)
            ->test(Bell::class)
            ->assertSee('href="/alertes"', false);
    }

    public function test_une_url_vide_ou_absente_ne_casse_pas_le_rendu(): void
    {
        $this->declencherUneAlerte();

        $composant = Livewire::actingAs($this->admin)->test(Bell::class);

        $this->assertNull($composant->instance()->lien(null));
        $this->assertNull($composant->instance()->lien(''));
    }

    public function test_les_messages_du_systeme_sont_en_francais(): void
    {
        // Sans fichiers de langue, Laravel renvoie la clé brute :
        // l'utilisateur lisait littéralement « passwords.sent ».
        $this->assertSame(
            'Un lien de réinitialisation vient de vous être envoyé par e-mail.',
            __('passwords.sent'),
        );

        $this->assertSame('Ces identifiants ne correspondent à aucun compte.', __('auth.failed'));

        $this->assertStringNotContainsString('validation.', __('validation.required', ['attribute' => 'nom']));
    }

    public function test_il_nexiste_quune_seule_page_de_connexion(): void
    {
        $routesConnexion = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_contains((string) $route->uri(), 'connexion')
                || str_contains((string) $route->getName(), 'login'))
            ->map(fn ($route) => $route->uri())
            ->unique()
            ->values();

        $this->assertEquals(['connexion', 'deconnexion'], $routesConnexion->sort()->values()->all());
    }
}
