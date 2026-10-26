<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $unites = [
            ['name' => 'Pièce',       'symbol' => 'pcs',    'allows_decimal' => false],
            ['name' => 'Kilogramme',  'symbol' => 'kg',     'allows_decimal' => true],
            ['name' => 'Gramme',      'symbol' => 'g',      'allows_decimal' => true],
            ['name' => 'Litre',       'symbol' => 'L',      'allows_decimal' => true],
            ['name' => 'Centilitre',  'symbol' => 'cl',     'allows_decimal' => false],
            ['name' => 'Sachet',      'symbol' => 'sachet', 'allows_decimal' => false],
            ['name' => 'Carton',      'symbol' => 'carton', 'allows_decimal' => false],
            ['name' => 'Casier',      'symbol' => 'casier', 'allows_decimal' => false],
            ['name' => 'Bouteille',   'symbol' => 'bout.',  'allows_decimal' => false],
            ['name' => 'Régime',      'symbol' => 'régime', 'allows_decimal' => false],
            ['name' => 'Botte',       'symbol' => 'botte',  'allows_decimal' => false],
        ];

        foreach ($unites as $unite) {
            Unit::firstOrCreate(['symbol' => $unite['symbol']], $unite);
        }
    }
}
