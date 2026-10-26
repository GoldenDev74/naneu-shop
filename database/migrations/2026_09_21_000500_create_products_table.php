<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // --- Identification ---
            $table->string('sku')->unique();                        // référence interne NAN-0001
            $table->string('barcode')->nullable()->unique();        // EAN-13 imprimé par le fabricant
            $table->string('internal_code')->nullable()->unique();  // CODE128 généré (produits vrac étiquetés)
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // --- Classification ---
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('default_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            // --- Prix (XAF, entiers : le franc CFA n'a pas de centimes) ---
            $table->unsignedBigInteger('sale_price')->default(0);
            $table->unsignedBigInteger('last_purchase_price')->default(0);
            $table->decimal('vat_rate', 5, 2)->default(18.00);
            $table->boolean('is_vat_exempt')->default(false);

            // --- Stock (dénormalisé pour lecture rapide ; source de vérité = stock_batches) ---
            $table->decimal('quantity_on_hand', 14, 3)->default(0);
            $table->decimal('alert_quantity', 14, 3)->default(5);    // seuil de réappro
            $table->decimal('reorder_quantity', 14, 3)->default(0);  // quantité conseillée à commander

            // --- Péremption ---
            $table->boolean('is_perishable')->default(true);
            $table->unsignedSmallInteger('expiry_alert_days')->nullable(); // null => valeur globale

            // --- POS / ergonomie ---
            $table->boolean('is_quick_item')->default(false);  // vignette caisse (vrac sans code-barres)
            $table->string('image_path')->nullable();          // clé MinIO
            $table->unsignedSmallInteger('display_order')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('category_id');
            $table->index('is_active');
            $table->index('is_quick_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
