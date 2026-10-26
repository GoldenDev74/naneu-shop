<?php

namespace Tests\Feature;

use App\Data\EntryLineData;
use App\Enums\UserRole;
use App\Livewire\Notifications\Bell;
use App\Models\Product;
use App\Models\StockAlert;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Garde contre une panne silencieuse déjà rencontrée : les alertes
 * s'affichaient bien dans l'écran « Alertes », mais la cloche restait vide
 * parce que les notifications attendaient un worker qui ne tournait pas.
 *
 * Ces tests s'exécutent **sans worker** : la connexion de file d'attente
 * est réglée sur `database`, donc tout ce qui est différé reste en attente.
 * Ce qui doit apparaître dans la cloche doit donc y arriver de façon
 * synchrone.
 */
class BellNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $gerant;
    private User $caissier;
    private Product $produit;
    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();

        // On veut éprouver le comportement sans worker : rien ne doit être
        // exécuté en ligne par la file d'attente.
        config(['queue.default' => 'database']);

        $this->stock = app(StockService::class);

        $this->admin = User::factory()->admin()->create()->fresh();
        $this->gerant = User::factory()->gerant()->create()->fresh();
        $this->caissier = User::factory()->create()->fresh();

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

    public function test_une_alerte_remplit_la_cloche_sans_worker(): void
    {
        $this->assertSame(0, DB::table('notifications')->count());

        // Réception sous le seuil : ouvre une alerte « stock bas ».
        $this->stock->receive($this->admin, [
            new EntryLineData($this->produit->id, 3, 5200),
        ]);

        $this->assertSame(1, StockAlert::query()->open()->count());

        // Le cœur du test : la notification doit exister immédiatement,
        // sans qu'aucun `queue:work` n'ait tourné.
        $this->assertSame(
            1,
            $this->admin->unreadNotifications()->count(),
            'La cloche de l\'administrateur doit se remplir sans worker.'
        );

        $this->assertSame(1, $this->gerant->unreadNotifications()->count());
        $this->assertSame(0, $this->caissier->unreadNotifications()->count());
    }

    public function test_lalerte_est_marquee_comme_notifiee(): void
    {
        $this->stock->receive($this->admin, [
            new EntryLineData($this->produit->id, 3, 5200),
        ]);

        $alerte = StockAlert::query()->open()->first();

        $this->assertNotNull($alerte->notified_at, 'L\'alerte doit porter la trace de sa notification.');
    }

    public function test_le_compteur_de_la_cloche_correspond_aux_alertes(): void
    {
        $this->stock->receive($this->admin, [
            new EntryLineData($this->produit->id, 3, 5200),
        ]);

        $this->actingAs($this->admin);

        Livewire::test(Bell::class)
            ->assertSee('Odika du Gabon')
            ->assertViewHas('nonLues', 1);
    }

    public function test_tout_marquer_lu_vide_le_compteur(): void
    {
        $this->stock->receive($this->admin, [
            new EntryLineData($this->produit->id, 3, 5200),
        ]);

        $this->actingAs($this->admin);

        Livewire::test(Bell::class)
            ->call('toutMarquerLu')
            ->assertViewHas('nonLues', 0);

        $this->assertSame(0, $this->admin->unreadNotifications()->count());
    }

    public function test_la_commande_de_rattrapage_notifie_les_alertes_oubliees(): void
    {
        $this->stock->receive($this->admin, [
            new EntryLineData($this->produit->id, 3, 5200),
        ]);

        // Simule l'incident : l'alerte existe mais n'a jamais été notifiée.
        DB::table('notifications')->delete();
        StockAlert::query()->update(['notified_at' => null]);

        $this->assertSame(0, $this->admin->unreadNotifications()->count());

        $this->artisan('naneu:renotifier-alertes')->assertSuccessful();

        $this->assertSame(1, $this->admin->unreadNotifications()->count());
        $this->assertNotNull(StockAlert::query()->open()->first()->notified_at);
    }

    public function test_le_rattrapage_ne_double_pas_les_notifications(): void
    {
        $this->stock->receive($this->admin, [
            new EntryLineData($this->produit->id, 3, 5200),
        ]);

        $avant = $this->admin->unreadNotifications()->count();

        // Sans --all, une alerte déjà notifiée doit être ignorée.
        $this->artisan('naneu:renotifier-alertes')->assertSuccessful();

        $this->assertSame($avant, $this->admin->fresh()->unreadNotifications()->count());
    }

    public function test_aucun_job_ne_part_sur_une_file_nommee(): void
    {
        $this->stock->receive($this->admin, [
            new EntryLineData($this->produit->id, 3, 5200),
        ]);

        $files = DB::table('jobs')->distinct()->pluck('queue')->all();

        // L'assertion n'aurait aucune valeur si rien n'avait été mis en
        // file : on vérifie d'abord que le travail différé existe bien.
        $this->assertNotEmpty(
            $files,
            'Le courriel et la diffusion WebSocket doivent bien partir en file d\'attente.'
        );

        // Un `php artisan queue:work` ordinaire ne consomme que « default ».
        // Toute autre file signifierait des jobs abandonnés en silence —
        // exactement la panne que ce test existe pour empêcher.
        foreach ($files as $file) {
            $this->assertSame('default', $file, "La file « {$file} » ne serait consommée par aucun worker.");
        }
    }
}
