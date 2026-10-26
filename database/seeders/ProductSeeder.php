<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Catalogue de départ d'une alimentation générale de Lomé spécialisée dans
 * les produits gabonais.
 *
 * Deux régimes de gestion coexistent :
 *
 *  — la **gamme gabonaise importée** : lots volumineux, reçus après
 *    plusieurs semaines de transport, donc avec une durée de vie déjà
 *    entamée. Seuils d'alerte hauts (une rupture met des semaines à se
 *    résorber) et fenêtre de péremption large.
 *  — le **fond de rayon togolais** : réapprovisionnement en quelques jours
 *    sur les marchés de Lomé, seuils plus serrés.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::pluck('id', 'name');
        $unites = Unit::pluck('id', 'symbol');
        $fournisseurs = Supplier::pluck('id', 'name');

        $coopGabon   = 'Coopérative du Woleu-Ntem (Gabon)';
        $exportGabon = 'Exportateur Libreville Denrées';
        $adawlato    = 'Grand Marché d\'Adawlato — grossistes';
        $hedzra      = 'Marché de Hédzranawoé';
        $cdpa        = 'CDPA Togo — centrale d\'achat';
        $brasserie   = 'Brasserie BB Lomé';
        $nioto       = 'Nioto — huiles et savons';
        $boulangerie = 'Boulangerie du quartier';

        $produits = [
            // ============================================================
            // GAMME GABONAISE IMPORTÉE — vendue en vrac, sans code-barres
            // ============================================================
            ['Odika (chocolat indigène du Gabon)', 'Épices et condiments locaux', 'kg', null, 5200, 8500, 8, true, true, 180, $coopGabon],
            ['Piment rouge gabonais en poudre', 'Épices et condiments locaux', 'sachet', null, 600, 1200, 25, true, true, 365, $coopGabon],
            ['Arachides grillées du Gabon', 'Épices et condiments locaux', 'kg', null, 2100, 3500, 10, true, true, 90, $coopGabon],
            ['Poisson fumé du Gabon (machoiron)', 'Poissons et viandes fumés', 'kg', null, 6500, 10000, 6, true, true, 60, $exportGabon],
            ['Huile de palme artisanale du Gabon', 'Huiles et corps gras', 'L', null, 1800, 3000, 12, true, true, 240, $exportGabon],
            ['Noix de cola du Gabon', 'Épices et condiments locaux', 'kg', null, 3000, 4800, 5, true, true, 45, $coopGabon],
            ['Écorces et épices à nyembwe', 'Épices et condiments locaux', 'sachet', null, 900, 1600, 15, true, true, 300, $coopGabon],
            ['Pâte de noix de palme (nyembwe)', 'Huiles et corps gras', 'pcs', '6154200000117', 1700, 2800, 12, true, false, 540, $exportGabon],

            // ============================================================
            // FOND DE RAYON TOGOLAIS — vrac et frais
            // ============================================================
            ['Feuilles de manioc pilées', 'Tubercules et féculents', 'sachet', null, 500, 900, 8, true, true, 5, $hedzra],
            ['Gari', 'Tubercules et féculents', 'kg', null, 700, 1100, 15, true, true, 180, $adawlato],
            ['Igname', 'Tubercules et féculents', 'kg', null, 850, 1400, 20, true, true, 45, $adawlato],
            ['Banane plantain', 'Fruits et légumes', 'régime', null, 2500, 4000, 5, true, true, 10, $adawlato],
            ['Taro', 'Tubercules et féculents', 'kg', null, 800, 1300, 8, true, true, 21, $adawlato],
            ['Gingembre frais', 'Épices et condiments locaux', 'kg', null, 1000, 1700, 4, true, true, 21, $hedzra],
            ['Tomates fraîches', 'Fruits et légumes', 'kg', null, 900, 1500, 6, true, true, 7, $hedzra],
            ['Oignons', 'Fruits et légumes', 'kg', null, 700, 1200, 10, true, true, 30, $hedzra],
            ['Pain baguette', 'Pain et viennoiserie', 'pcs', null, 125, 200, 25, true, true, 2, $boulangerie],
            ['Œufs (plateau de 30)', 'Produits laitiers', 'pcs', null, 2600, 3500, 8, true, true, 21, $hedzra],

            // ============================================================
            // ÉPICERIE SÈCHE — produits emballés, code-barres fabricant
            // ============================================================
            ['Riz parfumé 5 kg', 'Riz et céréales', 'sachet', '6001234567890', 4000, 5200, 12, true, false, 540, $cdpa],
            ['Riz brisé 25 kg', 'Riz et céréales', 'sachet', '6001234567906', 15500, 19500, 5, true, false, 540, $cdpa],
            ['Spaghetti 500 g', 'Pâtes et semoules', 'pcs', '8076800195057', 550, 850, 24, true, false, 730, $cdpa],
            ['Concentré de tomate 400 g', 'Conserves', 'pcs', '6111035000128', 600, 950, 30, true, false, 900, $cdpa],
            ['Sardines à l\'huile 125 g', 'Conserves', 'pcs', '3038350201010', 500, 800, 36, true, false, 1095, $cdpa],
            ['Sucre en morceaux 1 kg', 'Sucre, sel et farine', 'pcs', '3165430000102', 850, 1250, 18, true, false, 730, $cdpa],
            ['Farine de blé 1 kg', 'Sucre, sel et farine', 'pcs', '3245390091108', 650, 1000, 18, true, false, 365, $cdpa],
            ['Sel fin 1 kg', 'Sucre, sel et farine', 'pcs', '3017800238004', 250, 450, 24, false, false, null, $cdpa],
            ['Huile végétale 1 L', 'Huiles et corps gras', 'bout.', '3560070976621', 1250, 1900, 15, true, false, 540, $nioto],

            // ============================================================
            // BOISSONS
            // ============================================================
            ['Eau minérale 1,5 L', 'Eaux minérales', 'bout.', '6181100000019', 300, 500, 60, false, false, null, $brasserie],
            ['Coca-Cola 1,5 L', 'Sodas et jus', 'bout.', '5449000054227', 750, 1150, 30, true, false, 270, $brasserie],
            ['Bière Pils 65 cl', 'Bières et boissons alcoolisées', 'bout.', '6181100000200', 600, 900, 48, true, false, 365, $brasserie],
            ['Jus d\'ananas 1 L', 'Sodas et jus', 'bout.', '3560070123456', 900, 1450, 15, true, false, 300, $cdpa],

            // ============================================================
            // PETIT DÉJEUNER
            // ============================================================
            ['Lait en poudre 400 g', 'Lait et poudres', 'pcs', '7613034626844', 2100, 2900, 12, true, false, 540, $cdpa],
            ['Lait concentré sucré 397 g', 'Lait et poudres', 'pcs', '6111242100015', 900, 1350, 20, true, false, 540, $cdpa],
            ['Café soluble 100 g', 'Café, thé et cacao', 'pcs', '7613034321091', 2300, 3100, 10, true, false, 730, $cdpa],
            ['Chocolat en poudre 400 g', 'Café, thé et cacao', 'pcs', '3033490004545', 1800, 2500, 10, true, false, 540, $cdpa],

            // ============================================================
            // HYGIÈNE ET ENTRETIEN
            // ============================================================
            ['Savon de ménage 400 g', 'Savons et soins', 'pcs', '6181200000104', 350, 600, 30, false, false, null, $nioto],
            ['Eau de javel 1 L', 'Produits ménagers', 'bout.', '3011360003140', 550, 900, 15, true, false, 365, $cdpa],
            ['Papier hygiénique (lot de 4)', 'Produits ménagers', 'pcs', '3245414672154', 850, 1350, 15, false, false, null, $cdpa],
            ['Détergent lessive 1 kg', 'Produits ménagers', 'sachet', '8001090023339', 1500, 2200, 12, true, false, 730, $cdpa],
        ];

        foreach ($produits as [
            $nom, $categorie, $unite, $codeBarres, $prixAchat, $prixVente,
            $seuil, $perissable, $vignette, $joursPeremption, $fournisseur,
        ]) {
            Product::firstOrCreate(
                ['name' => $nom],
                [
                    'sku'                 => Product::nextSku(),
                    'barcode'             => $codeBarres,
                    'category_id'         => $categories[$categorie] ?? null,
                    'unit_id'             => $unites[$unite] ?? null,
                    'default_supplier_id' => $fournisseur ? ($fournisseurs[$fournisseur] ?? null) : null,
                    'sale_price'          => $prixVente,
                    'last_purchase_price' => $prixAchat,
                    'vat_rate'            => 18,
                    'alert_quantity'      => $seuil,
                    'reorder_quantity'    => $seuil * 3,
                    'is_perishable'       => $perissable,

                    // Les produits à rotation très courte (pain, feuilles,
                    // maraîchage) doivent alerter bien avant la fenêtre
                    // générale de 30 jours, sinon l'alerte arrive après la
                    // perte.
                    'expiry_alert_days'   => $joursPeremption !== null && $joursPeremption <= 30
                        ? max(1, (int) ceil($joursPeremption / 3))
                        : null,

                    'is_quick_item'       => $vignette,
                    'is_active'           => true,
                ],
            );
        }
    }
}
