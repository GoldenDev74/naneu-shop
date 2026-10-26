<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crée les comptes de démarrage.
 *
 * En production, ce seeder ne crée rien : le premier administrateur se
 * crée en console avec `php artisan naneu:creer-admin`, pour qu'aucun mot
 * de passe connu d'avance ne se retrouve dans une installation réelle.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn(
                'Environnement de production : aucun compte de démonstration créé. '
                .'Utilisez « php artisan naneu:creer-admin ».'
            );

            return;
        }

        $comptes = [
            ['Aimée Naneu',    'admin',      'admin@naneushop.tg',      UserRole::Admin],
            ['Gérant Naneu',   'gerant',     'gerant@naneushop.tg',     UserRole::Gerant],
            ['Magasinier',     'magasinier', 'magasinier@naneushop.tg', UserRole::Magasinier],
            ['Caissier',       'caisse',     'caisse@naneushop.tg',     UserRole::Caissier],
        ];

        foreach ($comptes as [$nom, $identifiant, $email, $role]) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name'                 => $nom,
                    'username'             => $identifiant,
                    'password'             => Hash::make('NaneuShop2026!'),
                    'role'                 => $role,
                    'is_active'            => true,
                    'must_change_password' => false,
                    'email_verified_at'    => now(),
                ],
            );
        }

        $this->command?->info('Comptes de démonstration créés — mot de passe : NaneuShop2026!');
    }
}
