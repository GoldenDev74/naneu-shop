<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * Deux familles de fournisseurs cohabitent chez Naneu Shop :
 *
 *  — les circuits **locaux togolais** (grossistes de Lomé, marchés,
 *    producteurs), qui livrent vite et servent le fond de rayon ;
 *  — les **filières gabonaises**, qui alimentent la gamme de spécialité.
 *    Ces produits voyagent : le délai est long, les lots arrivent par
 *    gros volumes et leur durée de vie restante à réception est déjà
 *    entamée. C'est précisément le cas où le suivi par lot et l'alerte
 *    de péremption paient.
 */
class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $fournisseurs = [
            // --- Importation depuis le Gabon ---------------------------
            [
                'name'         => 'Coopérative du Woleu-Ntem (Gabon)',
                'contact_name' => 'Responsable export',
                'city'         => 'Oyem, Gabon',
                'is_local'     => false,
                'notes'        => 'Odika, arachides, poudre de piment. Import par conteneur, '
                    .'délai 4 à 6 semaines. Vérifier la DLC restante à chaque réception.',
            ],
            [
                'name'         => 'Exportateur Libreville Denrées',
                'contact_name' => 'Service commercial',
                'city'         => 'Libreville, Gabon',
                'is_local'     => false,
                'notes'        => 'Huile de palme artisanale, poisson fumé, épices. '
                    .'Groupage maritime via le port de Lomé.',
            ],

            // --- Circuits togolais --------------------------------------
            [
                'name'         => 'Grand Marché d\'Adawlato — grossistes',
                'city'         => 'Lomé',
                'is_local'     => true,
                'notes'        => 'Achats en vrac : tubercules, légumes, condiments. Paiement comptant.',
            ],
            [
                'name'         => 'Marché de Hédzranawoé',
                'city'         => 'Lomé',
                'is_local'     => true,
                'notes'        => 'Produits frais et maraîchage. Livraison tôt le matin.',
            ],
            [
                'name'         => 'CDPA Togo — centrale d\'achat',
                'contact_name' => 'Service clients',
                'city'         => 'Lomé',
                'is_local'     => true,
                'notes'        => 'Épicerie sèche, conserves, hygiène. Facture mensuelle.',
            ],
            [
                'name'         => 'Brasserie BB Lomé',
                'city'         => 'Lomé',
                'is_local'     => true,
                'notes'        => 'Boissons : eaux, sodas, bières. Suivre la consigne des casiers.',
            ],
            [
                'name'         => 'Nioto — huiles et savons',
                'city'         => 'Lomé',
                'is_local'     => true,
                'notes'        => 'Huile végétale et savonnerie, production togolaise.',
            ],
            [
                'name'         => 'Boulangerie du quartier',
                'city'         => 'Lomé',
                'is_local'     => true,
                'notes'        => 'Pain livré deux fois par jour. Produit à rotation très courte.',
            ],
        ];

        foreach ($fournisseurs as $fournisseur) {
            Supplier::firstOrCreate(['name' => $fournisseur['name']], $fournisseur + ['is_active' => true]);
        }
    }
}
