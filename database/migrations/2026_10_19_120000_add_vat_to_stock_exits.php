<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les prix de vente sont désormais TTC : la TVA contenue dans chaque vente
 * est mémorisée pour calculer le CA hors taxe, la marge et la TVA collectée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_exit_lines', function (Blueprint $table) {
            $table->bigInteger('vat_amount')->default(0)->after('line_total');
        });

        Schema::table('stock_exits', function (Blueprint $table) {
            $table->bigInteger('total_vat')->default(0)->after('total_sale');
        });
    }

    public function down(): void
    {
        Schema::table('stock_exit_lines', fn (Blueprint $table) => $table->dropColumn('vat_amount'));
        Schema::table('stock_exits', fn (Blueprint $table) => $table->dropColumn('total_vat'));
    }
};
