<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal de sécurité : connexions, échecs, changements de mot de passe,
     * création/suspension d'utilisateurs, annulations de mouvements. Table en
     * ajout seul, jamais modifiée depuis l'application.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_email')->nullable();   // conservé même si le compte est supprimé
            $table->string('event', 60);                // connexion.reussie, utilisateur.cree, ...
            $table->nullableMorphs('auditable');
            $table->json('properties')->nullable();     // diff avant/après, sans aucun secret
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['event', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
