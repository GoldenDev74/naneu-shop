<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registre des alertes actives. Sert de garde anti-spam : tant qu'une
     * alerte est ouverte pour un couple (produit, type, lot), le planificateur
     * ne la renotifie pas. Elle se referme seule quand la condition disparaît
     * (réapprovisionnement, lot épuisé ou retiré).
     */
    public function up(): void
    {
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();

            // stock_bas | rupture | peremption_proche | perime
            $table->string('type', 30);

            // info | avertissement | critique
            $table->string('severity', 20)->default('avertissement');

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained()->cascadeOnDelete();

            $table->decimal('threshold_value', 14, 3)->nullable(); // seuil franchi
            $table->decimal('current_value', 14, 3)->nullable();   // valeur au moment du déclenchement
            $table->date('expiry_date')->nullable();
            $table->integer('days_remaining')->nullable();

            $table->string('message');

            $table->timestamp('triggered_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['resolved_at', 'severity']);
            $table->index('type');
        });

        // Une seule alerte OUVERTE par (produit, type, lot). Un index unique
        // ordinaire ne suffirait pas : en SQL deux NULL sont considérés comme
        // distincts, donc `resolved_at IS NULL` n'y contraindrait rien et
        // `stock_batch_id` nul laisserait passer des doublons. D'où l'index
        // partiel avec COALESCE, qui rend le scan quotidien réellement
        // idempotent même s'il tourne deux fois.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX alerts_open_unique
            ON stock_alerts (product_id, type, COALESCE(stock_batch_id, 0))
            WHERE resolved_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
    }
};
