<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number');                 // LOT-2026-000123
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            // Coût réel de CE lot — base du calcul FIFO de la marge
            $table->unsignedBigInteger('purchase_price');

            $table->decimal('quantity_received', 14, 3);
            $table->decimal('quantity_remaining', 14, 3);

            $table->date('received_at');                    // clé de tri FIFO
            $table->date('expiry_date')->nullable();        // clé de tri FEFO
            $table->timestamp('depleted_at')->nullable();

            $table->timestamps();

            $table->unique(['product_id', 'batch_number']);
            // Index composite : la requête FIFO/FEFO la plus chaude de l'application
            $table->index(['product_id', 'quantity_remaining', 'received_at'], 'batches_fifo_idx');
            $table->index(['product_id', 'quantity_remaining', 'expiry_date'], 'batches_fefo_idx');
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
