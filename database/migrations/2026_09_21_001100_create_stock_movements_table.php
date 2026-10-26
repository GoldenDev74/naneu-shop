<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grand livre du stock : chaque mouvement, entrée comme sortie, y laisse
     * une ligne immuable. C'est la piste d'audit — on ne la modifie jamais,
     * on ne fait qu'y ajouter. Permet de reconstruire le stock à n'importe
     * quelle date et de justifier chaque écart d'inventaire.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained()->nullOnDelete();

            $table->string('direction', 3);                 // in | out
            $table->string('reason', 30);                   // achat | vente | perte | peremption | casse | ajustement | inventaire

            $table->decimal('quantity', 14, 3);             // toujours positive
            $table->unsignedBigInteger('unit_cost')->default(0);
            $table->unsignedBigInteger('total_cost')->default(0);

            $table->decimal('quantity_before', 14, 3)->default(0);
            $table->decimal('quantity_after', 14, 3)->default(0);

            $table->nullableMorphs('source');               // StockEntry | StockExit | InventoryCount
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['product_id', 'occurred_at']);
            $table->index('direction');
            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
