<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, text, boolean, integer
            $table->string('group')->nullable(); // e.g., 'warehouse', 'shopify', 'general'
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default warehouse settings
        DB::table('settings')->insert([
            [
                'key' => 'warehouse_api_url',
                'value' => 'https://c-api.sellmate.co.kr/external/fairliar/productVariants',
                'type' => 'string',
                'group' => 'warehouse',
                'description' => 'Warehouse API endpoint URL',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'warehouse_api_token',
                'value' => env('WAREHOUSE_API_TOKEN', ''),
                'type' => 'text',
                'group' => 'warehouse',
                'description' => 'Warehouse API Bearer Token',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
