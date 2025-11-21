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
        Schema::table('warehouse_variants', function (Blueprint $table) {
            // Drop unnecessary columns
            $table->dropColumn(['barcode', 'warehouse_stocks', 'shop_codes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_variants', function (Blueprint $table) {
            // Restore columns if needed
            $table->string('barcode')->nullable();
            $table->json('warehouse_stocks')->nullable();
            $table->json('shop_codes')->nullable();
        });
    }
};
