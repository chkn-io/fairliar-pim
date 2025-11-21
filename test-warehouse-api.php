<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Warehouse API Connection...\n\n";

$service = new App\Services\WarehouseService();

echo "Fetching page 1...\n";
$result = $service->getProductVariants(1);

if (isset($result['errors'])) {
    echo "❌ API Error:\n";
    print_r($result['errors']);
    exit(1);
}

if (isset($result['data'])) {
    echo "✅ API Response received!\n";
    echo "Variants in page 1: " . count($result['data']) . "\n";
    echo "Current page: " . ($result['meta']['current_page'] ?? 'unknown') . "\n";
    echo "Total pages: " . ($result['meta']['last_page'] ?? 'unknown') . "\n";
    echo "Total variants: " . ($result['meta']['total'] ?? 'unknown') . "\n";
    echo "\nFirst variant sample:\n";
    if (!empty($result['data'][0])) {
        $first = $result['data'][0];
        echo "  ID: " . ($first['id'] ?? 'N/A') . "\n";
        echo "  Name: " . ($first['variant_name'] ?? 'N/A') . "\n";
        echo "  Stock: " . ($first['stock'] ?? 'N/A') . "\n";
        echo "  Has shop codes: " . (isset($first['option_has_code_by_shop']) ? 'YES' : 'NO') . "\n";
    }
} else {
    echo "❌ No data returned\n";
    echo "Full response:\n";
    print_r($result);
}
