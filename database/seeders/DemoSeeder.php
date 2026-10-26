<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Catalogue de démonstration (catégories, fournisseurs, produits). */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DatabaseSeeder::class,
            CategorySeeder::class,
            SupplierSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
