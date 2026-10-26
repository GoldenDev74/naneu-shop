<?php

namespace Tests\Feature;

use App\Data\EntryLineData;
use App\Enums\UserRole;
use App\Livewire\Products\ProductList;
use App\Livewire\Stock\EntryList;
use App\Livewire\Users\UserManager;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filtres par colonne (entonnoir des en-têtes) : appliqués en base, sur
 * toutes les lignes, et combinables entre eux.
 */
class ColumnFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $gerante;
    private Category $epicerie;
    private Category $riz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gerante = User::factory()->gerant()->create();
        $unite = Unit::create(['name' => 'Pièce', 'symbol' => 'pcs', 'allows_decimal' => false]);
        $this->epicerie = Category::create(['name' => 'Épicerie', 'color' => '#55BF81']);
        $this->riz = Category::create(['name' => 'Riz', 'parent_id' => $this->epicerie->id, 'color' => '#55BF81']);

        foreach ([
            ['NAN-1', 'Crème fraîche', 1500, $this->epicerie->id, true],
            ['NAN-2', 'Riz parfumé 5 kg', 4500, $this->riz->id, false],
            ['NAN-3', 'Savon', 300, null, false],
        ] as [$sku, $nom, $prix, $categorie, $perissable]) {
            Product::create([
                'sku' => $sku, 'name' => $nom, 'unit_id' => $unite->id, 'sale_price' => $prix, 'vat_rate' => 0,
                'alert_quantity' => 1, 'category_id' => $categorie, 'is_perishable' => $perissable, 'is_active' => true,
            ]);
        }
    }

    private function noms($composant): array
    {
        return $composant->viewData('produits')->pluck('name')->sort()->values()->all();
    }

    public function test_texte_sans_accents_nombre_et_categorie_avec_sous_categories(): void
    {
        $liste = Livewire::actingAs($this->gerante)->test(ProductList::class);

        $this->assertSame(['Crème fraîche'], $this->noms($liste->set('filtres.produit', 'creme')));

        $liste->set('filtres.produit', '');
        $this->assertSame(['Crème fraîche', 'Riz parfumé 5 kg'], $this->noms($liste->set('filtres.prix', '>1 000')));
        $this->assertSame(['Savon'], $this->noms($liste->set('filtres.prix', '100-500')));

        $liste->set('filtres.prix', '');
        // La catégorie parente inclut ses sous-catégories.
        $this->assertSame(['Crème fraîche', 'Riz parfumé 5 kg'], $this->noms($liste->set('filtres.categorie', (string) $this->epicerie->id)));
        // Filtres combinés.
        $this->assertSame(['Crème fraîche'], $this->noms($liste->set('filtres.perissable', '1')));

        $liste->call('effacerFiltres');
        $this->assertCount(3, $liste->viewData('produits'));
    }

    public function test_filtre_montant_sur_les_entrees(): void
    {
        $stock = app(StockService::class);
        $riz = Product::where('sku', 'NAN-2')->first();

        $stock->receive($this->gerante, [new EntryLineData($riz->id, 10, 3000)]);   // 30 000
        $stock->receive($this->gerante, [new EntryLineData($riz->id, 1, 3000)]);    //  3 000

        $liste = Livewire::actingAs($this->gerante)->test(EntryList::class)->set('filtres.total', '>= 10 000');

        $this->assertSame([30000], $liste->viewData('entrees')->pluck('total_cost')->all());
    }

    public function test_filtre_etat_des_comptes(): void
    {
        $verrouille = User::factory()->create(['role' => UserRole::Caissier]);
        $verrouille->forceFill(['locked_until' => now()->addMinutes(10)])->save();

        $liste = Livewire::actingAs($this->gerante)->test(UserManager::class)->set('filtres.etat', 'verrouille');

        $this->assertSame([$verrouille->id], $liste->viewData('utilisateurs')->pluck('id')->all());
    }
}
