<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La recherche ignore la casse, quel que soit le moteur : sous PostgreSQL,
 * un simple LIKE distinguerait « riz » de « Riz ».
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_produit_se_trouve_en_minuscules(): void
    {
        $unite = Unit::create(['name' => 'Pièce', 'symbol' => 'pcs', 'allows_decimal' => false]);

        Product::create([
            'sku'            => 'NAN-0001',
            'name'           => 'Riz parfumé 5 kg',
            'unit_id'        => $unite->id,
            'sale_price'     => 4200,
            'alert_quantity' => 5,
            'is_active'      => true,
        ]);

        $this->assertSame(1, Product::query()->search('riz')->count());
        $this->assertSame(1, Product::query()->search('nan-0001')->count());
    }

    public function test_un_fournisseur_se_trouve_en_minuscules(): void
    {
        Supplier::create(['name' => 'Brasserie BB Lomé', 'is_active' => true]);

        $this->assertSame(1, Supplier::query()->search('brasserie')->count());
    }
}
