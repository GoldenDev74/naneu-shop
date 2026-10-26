<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();          // INV-2026-0001
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('count_date');
            $table->string('scope', 20)->default('complet'); // complet | partiel
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('brouillon'); // brouillon | valide | annule
            $table->bigInteger('total_variance_value')->default(0);
            $table->text('note')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->index('count_date');
            $table->index('status');
        });

        Schema::create('inventory_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('expected_quantity', 14, 3);    // stock théorique au moment du comptage
            $table->decimal('counted_quantity', 14, 3)->nullable();
            $table->decimal('variance', 14, 3)->default(0); // compté - théorique
            $table->bigInteger('variance_value')->default(0);
            $table->string('variance_reason', 50)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['inventory_count_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_lines');
        Schema::dropIfExists('inventory_counts');
    }
};
