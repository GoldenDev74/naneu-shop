<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Durée de conservation : les produits frais sans date imprimée
     * (oignons, piment, pain…) reçoivent une date estimée à la réception.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('shelf_life_days')->nullable()->after('expiry_alert_days');
        });

        Schema::table('stock_batches', function (Blueprint $table) {
            $table->boolean('expiry_estimated')->default(false)->after('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('stock_batches', fn (Blueprint $table) => $table->dropColumn('expiry_estimated'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('shelf_life_days'));
    }
};
