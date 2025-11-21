<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Services\WarehouseService;
use App\Models\WarehouseVariant;

echo "Testing optimized sync with 2 pages (100 records per page)...\n\n";

$service = new WarehouseService();

// Clear existing data
echo "Truncating table...\n";
DB::table('warehouse_variants')->truncate();

$totalSynced = 0;
$totalSkipped = 0;

for ($page = 1; $page <= 2; $page++) {
    echo "Fetching page {$page}...\n";
    $response = $service->getProductVariants($page);
    
    if (!isset($response['data'])) {
        echo "  ❌ No data in page {$page}\n";
        continue;
    }
    
    $count = count($response['data']);
    echo "  Received {$count} variants\n";
    
    $pageSynced = 0;
    $pageSkipped = 0;
    
    foreach ($response['data'] as $variant) {
        $shopifyVariantId = null;
        $sku = null;
        
        if (isset($variant['option_has_code_by_shop'])) {
            foreach ($variant['option_has_code_by_shop'] as $shopCode) {
                // shop_id 28 is Shopify
                if ($shopCode['shop_id'] == '28') {
                    $shopifyVariantId = $shopCode['option_code'];
                }
                // shop_id 18 is SKU
                if ($shopCode['shop_id'] == '18') {
                    $sku = $shopCode['option_code'];
                }
            }
        }
        
        // Skip if no Shopify variant ID
        if (!$shopifyVariantId) {
            $pageSkipped++;
            continue;
        }
        
        WarehouseVariant::updateOrCreate(
            ['warehouse_id' => $variant['id']],
            [
                'shopify_variant_id' => $shopifyVariantId,
                'variant_name' => $variant['variant_name'] ?? null,
                'stock' => (int)($variant['stock'] ?? 0),
                'sku' => $sku,
                'cost_price' => $variant['cost_price'] ?? null,
                'selling_price' => $variant['selling_price'] ?? null,
                'synced_at' => now(),
            ]
        );
        
        $pageSynced++;
    }
    
    $totalSynced += $pageSynced;
    $totalSkipped += $pageSkipped;
    
    echo "  ✅ Synced {$pageSynced} variants (Skipped {$pageSkipped} without Shopify ID)\n";
}

echo "\n✅ Total synced: {$totalSynced}\n";
echo "⏭️  Total skipped: {$totalSkipped}\n";
echo "📊 Records in database: " . WarehouseVariant::count() . "\n";

// Show sample records
echo "\nSample records:\n";
$samples = WarehouseVariant::limit(3)->get();
foreach ($samples as $sample) {
    echo "  - Warehouse ID: {$sample->warehouse_id} | Shopify ID: {$sample->shopify_variant_id} | SKU: " . ($sample->sku ?? 'N/A') . " | Stock: {$sample->stock}\n";
}

