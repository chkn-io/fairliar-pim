<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('warehouse_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->unique(); // Warehouse internal ID
            $table->string('shopify_variant_id')->nullable()->index(); // Shopify variant ID from option_code
            $table->string('variant_name')->nullable();
            $table->integer('stock')->default(0);
            $table->string('barcode')->nullable();
            $table->string('sku')->nullable(); // sguid from warehouse
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('selling_price', 10, 2)->nullable();
            $table->json('warehouse_stocks')->nullable(); // All warehouse location stocks
            $table->json('shop_codes')->nullable(); // option_has_code_by_shop data
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_variants');
    }
};
