<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comptabilité : charges complémentaires (transport, vignettes,
 * électricité / eau…) déduites de la marge brute pour le bénéfice net.
 * Elles ne modifient pas le coût des lots : le transport reste une
 * charge à part, éventuellement rattachée au bon d'entrée concerné.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            // Catégories utilisées par le code (frais de transport d'un bon).
            $table->string('slug', 40)->nullable()->unique();
            $table->string('icon', 40)->default('receipt');
            $table->string('color', 7)->default('#56c389');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->date('expense_date');
            $table->string('label', 150);
            $table->unsignedBigInteger('amount');
            $table->foreignId('stock_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('expense_date');
        });

        $maintenant = now();

        $categories = [
            ['name' => 'Transport', 'slug' => 'transport', 'icon' => 'truck', 'color' => '#3b82f6'],
            ['name' => 'Vignettes (étiquettes produits)', 'slug' => 'vignettes', 'icon' => 'tag', 'color' => '#f59e0b'],
            ['name' => 'Électricité / eau', 'slug' => 'energie', 'icon' => 'zap', 'color' => '#8b5cf6'],
        ];

        DB::table('expense_categories')->insert(array_map(
            fn (array $c) => $c + ['created_at' => $maintenant, 'updated_at' => $maintenant],
            $categories,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
