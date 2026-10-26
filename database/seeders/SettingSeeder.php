<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $parametres = [
            ['key' => 'shop_name',    'value' => 'Naneu Shop', 'type' => 'string', 'group' => 'boutique', 'label' => 'Nom de la boutique', 'is_public' => true],
            ['key' => 'shop_phone',   'value' => '',           'type' => 'string', 'group' => 'boutique', 'label' => 'Téléphone'],
            ['key' => 'shop_address', 'value' => 'Lomé, Togo', 'type' => 'string', 'group' => 'boutique', 'label' => 'Adresse'],
            ['key' => 'shop_nif',     'value' => '',           'type' => 'string', 'group' => 'boutique', 'label' => 'Numéro d\'identification fiscale (NIF)'],

            ['key' => 'stock_strategy', 'value' => 'fifo', 'type' => 'string', 'group' => 'stock', 'label' => 'Ordre de consommation des lots',
             'description' => 'fifo : premier entré, premier sorti. fefo : premier périmé, premier sorti.'],
            ['key' => 'expiry_alert_days',    'value' => '30', 'type' => 'int', 'group' => 'stock', 'label' => 'Alerte péremption (jours)'],
            ['key' => 'expiry_critical_days', 'value' => '7',  'type' => 'int', 'group' => 'stock', 'label' => 'Seuil critique de péremption (jours)'],
            ['key' => 'low_stock_threshold',  'value' => '5',  'type' => 'int', 'group' => 'stock', 'label' => 'Seuil de stock bas par défaut'],

            ['key' => 'vat_enabled',      'value' => '0',  'type' => 'bool',   'group' => 'fiscalite', 'label' => 'TVA activée'],
            ['key' => 'vat_default_rate', 'value' => '18', 'type' => 'string', 'group' => 'fiscalite', 'label' => 'Taux de TVA par défaut (%)'],
        ];

        foreach ($parametres as $parametre) {
            Setting::firstOrCreate(['key' => $parametre['key']], $parametre);
        }
    }
}
