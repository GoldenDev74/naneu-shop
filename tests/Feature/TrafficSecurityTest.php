<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Audit\IpRules;
use App\Models\IpRule;
use App\Models\User;
use App\Services\GeoIp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filtrage du trafic : pays autorisés, adresses bloquées / autorisées à la
 * main, limite de requêtes, journal JSON des requêtes et des évènements.
 */
class TrafficSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const TOGO = '102.64.1.10';

    private const FRANCE = '90.1.2.3';

    private string $journaux;

    protected function setUp(): void
    {
        parent::setUp();

        // Géolocalisation simulée : pas de base .mmdb dans les tests.
        $this->app->instance(GeoIp::class, new class('') extends GeoIp
        {
            public function pays(?string $ip): ?string
            {
                if (! $ip || $this->estPrivee($ip)) {
                    return null;
                }

                return str_starts_with($ip, '102.64.') ? 'TG' : 'FR';
            }
        });

        config([
            'naneu.security.geo.enabled'   => true,
            'naneu.security.geo.countries' => ['TG'],
        ]);

        // Journaux JSON dans des fichiers temporaires, relus par les tests.
        $this->journaux = sys_get_temp_dir().'/naneu-tests-'.uniqid();
        mkdir($this->journaux);
        foreach (['requetes', 'securite'] as $canal) {
            config(["logging.channels.{$canal}.driver" => 'single', "logging.channels.{$canal}.path" => "{$this->journaux}/{$canal}.log"]);
        }
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->journaux}/*") ?: []);
        @rmdir($this->journaux);

        parent::tearDown();
    }

    private function depuis(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    /** @return list<array<string, mixed>> */
    private function lignes(string $canal): array
    {
        $fichier = "{$this->journaux}/{$canal}.log";

        if (! is_file($fichier)) {
            return [];
        }

        return array_map(fn ($l) => json_decode($l, true), array_filter(explode("\n", file_get_contents($fichier))));
    }

    private function compte(UserRole $role, string $identifiant): User
    {
        $user = User::create([
            'name' => ucfirst($identifiant), 'username' => $identifiant, 'email' => "{$identifiant}@naneushop.tg",
            'password' => Hash::make('MotDePasseSolide2026!'), 'role' => $role, 'is_active' => true,
        ]);

        return $user->fresh();
    }

    public function test_le_site_repond_depuis_le_togo(): void
    {
        $this->depuis(self::TOGO)->get('/connexion')->assertOk();
    }

    public function test_le_site_est_refuse_hors_du_togo_et_le_refus_est_trace(): void
    {
        $this->depuis(self::FRANCE)->get('/connexion')
            ->assertForbidden()
            ->assertSee('uniquement depuis le Togo');

        $evenement = collect($this->lignes('securite'))->firstWhere('message', 'acces.pays_refuse');
        $this->assertNotNull($evenement);
        $this->assertSame(self::FRANCE, $evenement['context']['ip']);
        $this->assertSame('FR', $evenement['context']['pays']);
    }

    public function test_les_adresses_privees_et_les_chemins_techniques_ne_sont_pas_filtres(): void
    {
        $this->depuis('100.116.61.60')->get('/connexion')->assertOk(); // Tailscale
        $this->depuis(self::FRANCE)->get('/up')->assertOk();
    }

    public function test_sans_filtre_active_l_etranger_passe(): void
    {
        config(['naneu.security.geo.enabled' => false]);

        $this->depuis(self::FRANCE)->get('/connexion')->assertOk();
    }

    public function test_une_adresse_autorisee_passe_hors_du_togo(): void
    {
        IpRule::create(['ip' => '90.1.2.0/24', 'type' => IpRule::AUTORISEE]);

        $this->depuis(self::FRANCE)->get('/connexion')->assertOk();
    }

    public function test_une_adresse_bloquee_est_refusee_meme_au_togo(): void
    {
        IpRule::create(['ip' => self::TOGO, 'type' => IpRule::BLOQUEE]);

        $this->depuis(self::TOGO)->get('/connexion')->assertForbidden()->assertSee('bloqué');
        $this->assertNotNull(collect($this->lignes('securite'))->firstWhere('message', 'acces.ip_bloquee'));
    }

    public function test_une_regle_expiree_ne_s_applique_plus(): void
    {
        IpRule::create(['ip' => self::TOGO, 'type' => IpRule::BLOQUEE, 'expires_at' => now()->subMinute()]);

        $this->depuis(self::TOGO)->get('/connexion')->assertOk();
    }

    public function test_trop_de_requetes_renvoie_429_et_est_trace(): void
    {
        config(['naneu.security.rate_limits.guest' => 3]);

        foreach (range(1, 3) as $_) {
            $this->depuis(self::TOGO)->get('/connexion')->assertOk();
        }

        $this->depuis(self::TOGO)->get('/connexion')->assertStatus(429)->assertSee('Trop de requêtes');
        $this->assertNotNull(collect($this->lignes('securite'))->firstWhere('message', 'acces.trop_de_requetes'));
    }

    public function test_chaque_requete_est_journalisee_sans_la_chaine_de_requete(): void
    {
        $this->depuis(self::TOGO)->get('/connexion?token=secret123')->assertOk();

        $ligne = collect($this->lignes('requetes'))->last();
        $this->assertSame(self::TOGO, $ligne['context']['ip']);
        $this->assertSame('TG', $ligne['context']['pays']);
        $this->assertSame('/connexion', $ligne['context']['chemin']);
        $this->assertSame(200, $ligne['context']['statut']);
        $this->assertStringNotContainsString('secret123', file_get_contents("{$this->journaux}/requetes.log"));
    }

    public function test_la_gerance_bloque_une_adresse_depuis_le_journal(): void
    {
        $gerante = $this->compte(UserRole::Gerant, 'gerante');

        Livewire::actingAs($gerante)->test(IpRules::class)
            ->set('ip', '41.207.1.1')
            ->set('type', IpRule::BLOQUEE)
            ->set('note', 'Essais de connexion répétés')
            ->set('duree', '7')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $regle = IpRule::sole();
        $this->assertSame('41.207.1.1', $regle->ip);
        $this->assertTrue($regle->expires_at->isFuture());
        $this->assertDatabaseHas('audit_logs', ['event' => 'ip.bloquee', 'user_id' => $gerante->id]);
        $this->assertSame(IpRule::BLOQUEE, IpRule::pour('41.207.1.1'));
    }

    public function test_adresse_invalide_ou_propre_adresse_refusees(): void
    {
        $gerante = $this->compte(UserRole::Gerant, 'gerante');

        Livewire::actingAs($gerante)->test(IpRules::class)
            ->set('ip', '999.1.1.1')->call('enregistrer')->assertHasErrors('ip')
            ->set('ip', '0.0.0.0/0')->call('enregistrer')->assertHasErrors('ip')
            ->set('ip', '127.0.0.1')->call('enregistrer')->assertHasErrors('ip');

        $this->assertSame(0, IpRule::count());
    }

    public function test_seule_la_gerance_gere_les_adresses(): void
    {
        $caissier = $this->compte(UserRole::Caissier, 'afi');

        Livewire::actingAs($caissier)->test(IpRules::class)->assertForbidden();
    }

    public function test_le_journal_d_audit_est_copie_dans_le_journal_json(): void
    {
        \App\Models\AuditLog::record('compte.verrouille', null, null, ['identifiant' => 'afi', 'password' => 'x']);

        $ligne = collect($this->lignes('securite'))->firstWhere('message', 'compte.verrouille');
        $this->assertSame('afi', $ligne['context']['proprietes']['identifiant']);
        $this->assertArrayNotHasKey('password', $ligne['context']['proprietes']);
    }
}
