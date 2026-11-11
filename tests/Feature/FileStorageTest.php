<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Reports\ReportCentre;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Photos de produits, bons scannés et exports sont rangés sur le disque du
 * serveur et servis par des liens signés de courte durée.
 */
class FileStorageTest extends TestCase
{
    use RefreshDatabase;

    private string $racine;

    protected function setUp(): void
    {
        parent::setUp();

        // Dossier jetable : les tests n'écrivent jamais dans storage/app.
        $this->racine = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('naneu-fichiers-', true);
        config(['filesystems.disks.local.root' => $this->racine]);
        Storage::forgetDisk('local');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->racine);

        parent::tearDown();
    }

    public function test_files_go_to_the_server_disk_by_default(): void
    {
        $this->assertSame('local', config('naneu.storage.disk'));
        $this->assertSame('local', config('naneu.storage.signing_disk'));
    }

    public function test_product_image_is_served_through_a_signed_link(): void
    {
        $produit = $this->produitAvecImage('produits/oignon.jpg', 'contenu-de-la-photo');

        $lien = $produit->imageUrl();

        $this->assertNotNull($lien);
        $reponse = $this->get($lien)->assertOk();
        $this->assertSame('contenu-de-la-photo', $reponse->streamedContent());
    }

    public function test_image_link_without_signature_is_refused(): void
    {
        $produit = $this->produitAvecImage('produits/oignon.jpg', 'contenu-de-la-photo');

        $lienSansSignature = strtok($produit->imageUrl(), '?');

        $this->get($lienSansSignature)->assertForbidden();
    }

    public function test_inventory_archive_is_written_to_the_exports_folder(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@naneushop.tg', 'password' => 'motdepasse',
            'role' => UserRole::Admin, 'is_active' => true,
        ]);

        Livewire::actingAs($admin)->test(ReportCentre::class)->call('archiverInventaire');

        $archives = Storage::disk('local')->files(config('naneu.storage.exports_path'));
        $this->assertCount(1, $archives);
        $this->assertStringContainsString('Inventaire valorisé', Storage::disk('local')->get($archives[0]));
    }

    private function produitAvecImage(string $chemin, string $contenu): Product
    {
        Storage::disk(config('naneu.storage.disk'))->put($chemin, $contenu);

        $unite = Unit::create(['name' => 'Kilogramme', 'symbol' => 'kg', 'allows_decimal' => true]);

        $produit = Product::create([
            'sku'        => 'NAN-0100',
            'name'       => 'Oignon',
            'unit_id'    => $unite->id,
            'sale_price' => 800,
            'is_active'  => true,
        ]);

        $produit->forceFill(['image_path' => $chemin])->save();

        return $produit;
    }
}
