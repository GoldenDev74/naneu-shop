<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();          // ENT-2026-0001
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('entry_date');
            $table->string('invoice_number')->nullable();   // n° facture fournisseur
            $table->string('document_path')->nullable();    // scan du bon de livraison (MinIO)
            $table->unsignedBigInteger('total_cost')->default(0);
            $table->text('note')->nullable();
            $table->string('status', 20)->default('valide'); // brouillon | valide | annule
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->index('entry_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_entries');
    }
};
