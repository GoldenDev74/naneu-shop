<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_exit_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_exit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->unsignedBigInteger('sale_price')->default(0);   // prix unitaire de vente appliqué
            $table->unsignedBigInteger('line_total')->default(0);
            $table->unsignedBigInteger('cost_total')->default(0);   // somme des coûts FIFO consommés
            $table->bigInteger('margin')->default(0);
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_exit_lines');
    }
};
