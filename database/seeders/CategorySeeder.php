<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $arborescence = [
            // La spécialité de la boutique : gamme importée du Gabon,
            // complétée par les produits vivriers togolais de même famille.
            'Produits gabonais et vivriers' => [
                'couleur' => '#0f766e',
                'enfants' => ['Épices et condiments locaux', 'Huiles et corps gras', 'Tubercules et féculents', 'Poissons et viandes fumés'],
            ],
            'Épicerie sèche' => [
                'couleur' => '#b45309',
                'enfants' => ['Riz et céréales', 'Pâtes et semoules', 'Conserves', 'Sucre, sel et farine'],
            ],
            'Boissons' => [
                'couleur' => '#1d4ed8',
                'enfants' => ['Eaux minérales', 'Sodas et jus', 'Bières et boissons alcoolisées'],
            ],
            'Produits frais' => [
                'couleur' => '#15803d',
                'enfants' => ['Fruits et légumes', 'Produits laitiers', 'Pain et viennoiserie'],
            ],
            'Hygiène et entretien' => [
                'couleur' => '#7e22ce',
                'enfants' => ['Savons et soins', 'Produits ménagers'],
            ],
            'Petit déjeuner' => [
                'couleur' => '#be123c',
                'enfants' => ['Lait et poudres', 'Confitures et pâtes à tartiner', 'Café, thé et cacao'],
            ],
        ];

        foreach ($arborescence as $nom => $details) {
            $parent = Category::firstOrCreate(
                ['name' => $nom],
                ['color' => $details['couleur'], 'is_active' => true],
            );

            foreach ($details['enfants'] as $enfant) {
                Category::firstOrCreate(
                    ['name' => $enfant],
                    ['parent_id' => $parent->id, 'color' => $details['couleur'], 'is_active' => true],
                );
            }
        }
    }
}
