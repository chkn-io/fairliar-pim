<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Services\ShopifyService;

echo "Testing fetchAll with Shopify GraphQL...\n\n";

$shopify = new ShopifyService();

echo "Fetching ALL variants (this may take a while)...\n";
$startTime = microtime(true);

$variants = $shopify->getProductVariants(true, null); // fetchAll = true

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n✅ Fetch complete!\n";
echo "Total variants: " . count($variants) . "\n";
echo "Time taken: {$duration} seconds\n";

if (count($variants) > 0) {
    echo "\nFirst 5 variants:\n";
    foreach (array_slice($variants, 0, 5) as $i => $variant) {
        echo ($i + 1) . ". {$variant['product_title']} - {$variant['variant_title']} (Stock: {$variant['total_inventory']})\n";
    }
    
    echo "\nLast 5 variants:\n";
    foreach (array_slice($variants, -5) as $i => $variant) {
        echo ($i + 1) . ". {$variant['product_title']} - {$variant['variant_title']} (Stock: {$variant['total_inventory']})\n";
    }
}
