<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Base vierge : paramètres, unités de mesure et comptes de démarrage.
 * Le catalogue se construit ensuite depuis l'application.
 *
 * Pour une démonstration avec un catalogue prérempli :
 *   php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            UnitSeeder::class,
            AdminSeeder::class,
        ]);
    }
}
