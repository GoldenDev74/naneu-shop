<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Encaissement au comptoir : ce que le client a donné, ce qu'on lui a rendu. */
    public function up(): void
    {
        Schema::table('stock_exits', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('total_margin');
            $table->unsignedBigInteger('amount_tendered')->nullable()->after('payment_method');
            $table->unsignedBigInteger('change_given')->nullable()->after('amount_tendered');
        });
    }

    public function down(): void
    {
        Schema::table('stock_exits', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'amount_tendered', 'change_given']);
        });
    }
};
