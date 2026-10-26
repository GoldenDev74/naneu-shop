<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Connexion par e-mail OU nom d'utilisateur : un employé sans adresse
 * e-mail doit pouvoir se connecter. L'e-mail devient facultatif, le nom
 * d'utilisateur obligatoire et unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->after('name');
        });

        // Comptes existants : identifiant tiré de la partie locale de l'e-mail.
        $pris = [];

        foreach (DB::table('users')->orderBy('id')->get(['id', 'name', 'email']) as $user) {
            $base = Str::of($user->email ? Str::before($user->email, '@') : $user->name)
                ->ascii()->lower()->replaceMatches('/[^a-z0-9._-]+/', '.')->trim('.')->limit(24, '')->value() ?: 'utilisateur';

            $candidat = $base;
            $suffixe = 2;

            while (in_array($candidat, $pris, true)) {
                $candidat = $base.$suffixe++;
            }

            $pris[] = $candidat;

            DB::table('users')->where('id', $user->id)->update(['username' => $candidat]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable(false)->change();
            $table->unique('username');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
