<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Services\ShopifyService;
use GuzzleHttp\Client;

echo "Testing Shopify GraphQL API...\n\n";

// First, test raw GraphQL query
echo "=== RAW GRAPHQL TEST ===\n";
$client = new Client();
$apiKey = config('shopify.api_key');
$endpoint = config('shopify.graphql_endpoint');

echo "Endpoint: {$endpoint}\n";
echo "API Key length: " . strlen($apiKey) . "\n\n";

$query = '{
    products(first: 5) {
        pageInfo {
            hasNextPage
            endCursor
        }
        edges {
            node {
                id
                title
                status
                variants(first: 3) {
                    edges {
                        node {
                            id
                            title
                            sku
                            inventoryQuantity
                        }
                    }
                }
            }
        }
    }
}';

try {
    $response = $client->post($endpoint, [
        'headers' => [
            'Content-Type' => 'application/json',
            'X-Shopify-Access-Token' => $apiKey,
        ],
        'json' => [
            'query' => $query
        ]
    ]);

    $data = json_decode($response->getBody()->getContents(), true);
    
    if (isset($data['errors'])) {
        echo "❌ GraphQL Errors:\n";
        print_r($data['errors']);
    } else {
        echo "✅ Raw query successful!\n";
        $products = $data['data']['products']['edges'] ?? [];
        echo "Products returned: " . count($products) . "\n\n";
        
        foreach ($products as $productEdge) {
            $product = $productEdge['node'];
            echo "Product: {$product['title']} (Status: {$product['status']})\n";
            $variants = $product['variants']['edges'] ?? [];
            echo "  Variants: " . count($variants) . "\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n\n=== SERVICE TEST ===\n";
$shopify = new ShopifyService();

echo "1. Testing getLocations()...\n";
$locations = $shopify->getLocations();
echo "   Locations found: " . count($locations) . "\n";
if (!empty($locations)) {
    foreach ($locations as $location) {
        echo "   - {$location['name']} (ID: {$location['id']})\n";
    }
}
echo "\n";

echo "2. Testing getProductVariants() - first page only...\n";
$variants = $shopify->getProductVariants(false, null);

echo "   Variants found: " . count($variants) . "\n";
if (!empty($variants)) {
    echo "\n   First 3 variants:\n";
    foreach (array_slice($variants, 0, 3) as $variant) {
        echo "   - Product: {$variant['product_title']}\n";
        echo "     Variant: {$variant['variant_title']}\n";
        echo "     ID: {$variant['variant_id']}\n";
        echo "     SKU: " . ($variant['sku'] ?? 'N/A') . "\n";
        echo "     Stock: {$variant['total_inventory']}\n";
        echo "\n";
    }
}

// Check logs
echo "\n\n=== CHECK LARAVEL LOGS ===\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recentLines = array_slice($lines, -20);
    echo implode('', $recentLines);
}

