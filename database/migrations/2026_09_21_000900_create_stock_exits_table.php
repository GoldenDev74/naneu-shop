<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_exits', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();          // SOR-2026-0001

            // vente | perte | peremption | casse | ajustement | retour_fournisseur | consommation
            $table->string('type', 30)->default('vente');

            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('exit_date');

            $table->unsignedBigInteger('total_cost')->default(0);   // valorisation FIFO des lots consommés
            $table->unsignedBigInteger('total_sale')->default(0);   // chiffre d'affaires (si vente)
            $table->bigInteger('total_margin')->default(0);         // peut être négatif

            $table->text('note')->nullable();
            $table->string('status', 20)->default('valide');
            $table->timestamps();

            $table->index('exit_date');
            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_exits');
    }
};
