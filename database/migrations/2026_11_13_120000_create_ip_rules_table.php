<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adresses (ou plages CIDR) bloquées ou autorisées à la main depuis
        // le Journal de sécurité. « autorisee » passe outre le filtre par pays.
        Schema::create('ip_rules', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 64);
            $table->string('type', 16); // bloquee | autorisee
            $table->string('note', 255)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ip', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_rules');
    }
};
