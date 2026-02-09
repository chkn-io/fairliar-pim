<?php

namespace App\Console\Commands;

use App\Models\Store;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncInventoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:sync 
                            {csv : Path to the CSV file with inventory data}
                            {--store= : Store name (e.g., USA)}
                            {--limit= : Limit number of products to process}
                            {--dryrun : Run without making actual changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync inventory from CSV file to Shopify store';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $csvPath = $this->argument('csv');
        $storeName = $this->option('store');
        $limit = $this->option('limit');
        $dryrun = $this->option('dryrun');

        // Validate CSV file exists
        if (!file_exists($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");
            return 1;
        }

        // Validate store
        if (!$storeName) {
            $this->error('Store name is required. Use --store=USA');
            return 1;
        }

        $store = Store::where('name', $storeName)
                      ->where('is_active', true)
                      ->first();

        if (!$store) {
            $this->error("Store '{$storeName}' not found or inactive");
            return 1;
        }

        $this->info("Starting inventory sync for store: {$storeName}");
        if ($dryrun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Read CSV file
        $this->info("Reading CSV file: {$csvPath}");
        $inventoryData = $this->readCsvFile($csvPath);
        $this->info("Found " . count($inventoryData) . " items in CSV");

        // Fetch products from Shopify
        $this->info("Fetching products from Shopify...");
        $products = $this->fetchShopifyProducts($store);
        $this->info("Found " . count($products) . " products from Shopify");

        // Build SKU to variant map from Shopify
        $this->info("Building SKU index...");
        $shopifyVariants = [];
        foreach ($products as $product) {
            foreach ($product['variants'] as $variant) {
                if (!empty($variant['sku'])) {
                    $shopifyVariants[$variant['sku']] = $variant;
                }
            }
        }
        $this->info("Indexed " . count($shopifyVariants) . " variants");

        // Process CSV entries
        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $notFound = 0;
        $errors = 0;

        foreach ($inventoryData as $csvItem) {
            if ($limit && $processed >= $limit) {
                $this->warn("Reached limit of {$limit} items");
                break;
            }

            $sku = $csvItem['sku'];
            $newInventory = (int) $csvItem['on_hand'];
            
            // Check if SKU exists in Shopify
            if (!isset($shopifyVariants[$sku])) {
                $this->line("<fg=gray>{$sku}: Not found in Shopify</>");
                $notFound++;
                $processed++;
                continue;
            }

            $variant = $shopifyVariants[$sku];
            $shopifyInventory = $variant['inventory_quantity'];

            if ($shopifyInventory == $newInventory) {
                $this->line("<fg=gray>{$sku}: No change needed (current: {$shopifyInventory})</>");
                $skipped++;
            } else {
                $this->info("{$sku}: {$shopifyInventory} → {$newInventory}");
                
                if (!$dryrun) {
                    try {
                        $this->updateInventory($store, $variant['id'], $newInventory);
                        $this->line("<fg=green>✓ Updated {$sku}</>");
                        $updated++;
                    } catch (\Exception $e) {
                        $this->error("✗ Failed to update {$sku}: " . $e->getMessage());
                        $errors++;
                    }
                } else {
                    $this->line("<fg=yellow>[DRY RUN] Would update {$sku}</>");
                    $updated++;
                }
            }

            $processed++;
        }

        // Summary
        $this->newLine();
        $this->info('=== Sync Summary ===');
        $this->info("Processed: {$processed}");
        $this->info("Updated: {$updated}");
        $this->info("Skipped (no change): {$skipped}");
        $this->info("Not found in Shopify: {$notFound}");
        if ($errors > 0) {
            $this->error("Errors: {$errors}");
        }

        return 0;
    }

    /**
     * Read CSV file and parse inventory data
     */
    private function readCsvFile($path)
    {
        $data = [];
        $file = fopen($path, 'r');
        $headers = fgetcsv($file); // Read header row

        // Find column indices
        $nameIdx = array_search('Name', $headers);
        $onHandIdx = array_search('On Hand', $headers);
        $skuIdx = array_search('SKU', $headers);

        while (($row = fgetcsv($file)) !== false) {
            $data[] = [
                'name' => $row[$nameIdx],
                'sku' => $row[$skuIdx],
                'on_hand' => $row[$onHandIdx],
            ];
        }

        fclose($file);
        return $data;
    }

    /**
     * Fetch products from Shopify
     */
    private function fetchShopifyProducts($store)
    {
        $client = new Client();
        $apiVersion = config('shopify.api_version', '2025-10');
        $graphqlEndpoint = "https://{$store->shop_domain}/admin/api/{$apiVersion}/graphql.json";

        $allProducts = [];
        $hasNextPage = true;
        $cursor = null;

        while ($hasNextPage) {
            $afterClause = $cursor ? ', after: "' . $cursor . '"' : '';
            
            $query = '{
                products(first: 250' . $afterClause . ') {
                    edges {
                        cursor
                        node {
                            id
                            title
                            variants(first: 100) {
                                edges {
                                    node {
                                        id
                                        sku
                                        inventoryQuantity
                                    }
                                }
                            }
                        }
                    }
                    pageInfo {
                        hasNextPage
                    }
                }
            }';

            $response = $client->post($graphqlEndpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Shopify-Access-Token' => $store->access_token,
                ],
                'json' => ['query' => $query]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['errors'])) {
                $this->error('Shopify API error: ' . json_encode($data['errors']));
                exit(1);
            }

            $edges = $data['data']['products']['edges'] ?? [];
            $pageInfo = $data['data']['products']['pageInfo'] ?? [];

            foreach ($edges as $edge) {
                $product = $edge['node'];
                $allProducts[] = [
                    'id' => $product['id'],
                    'title' => $product['title'],
                    'variants' => array_map(function($v) {
                        return [
                            'id' => $v['node']['id'],
                            'sku' => $v['node']['sku'],
                            'inventory_quantity' => $v['node']['inventoryQuantity']
                        ];
                    }, $product['variants']['edges'])
                ];
            }

            $hasNextPage = $pageInfo['hasNextPage'] ?? false;
            $cursor = $hasNextPage && !empty($edges) ? end($edges)['cursor'] : null;
        }

        return $allProducts;
    }

    /**
     * Update inventory for a variant
     */
    private function updateInventory($store, $variantId, $quantity)
    {
        $client = new Client();
        $apiVersion = config('shopify.api_version', '2025-10');
        $graphqlEndpoint = "https://{$store->shop_domain}/admin/api/{$apiVersion}/graphql.json";

        Log::info('Starting inventory update', [
            'variant_id' => $variantId,
            'new_quantity' => $quantity
        ]);

        // First, get the inventory item ID
        $query = '{
            productVariant(id: "' . $variantId . '") {
                inventoryItem {
                    id
                }
            }
        }';

        $response = $client->post($graphqlEndpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $store->access_token,
            ],
            'json' => ['query' => $query]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $inventoryItemId = $data['data']['productVariant']['inventoryItem']['id'];

        Log::info('Retrieved inventory item ID', ['inventory_item_id' => $inventoryItemId]);

        // Get the location ID (using first available location)
        $locationQuery = '{
            locations(first: 1) {
                edges {
                    node {
                        id
                    }
                }
            }
        }';

        $response = $client->post($graphqlEndpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $store->access_token,
            ],
            'json' => ['query' => $locationQuery]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $locationId = $data['data']['locations']['edges'][0]['node']['id'];

        Log::info('Retrieved location ID', ['location_id' => $locationId]);

        // Update inventory
        $mutation = 'mutation {
            inventorySetQuantities(input: {
                reason: "correction"
                name: "available"
                ignoreCompareQuantity: true
                quantities: [
                    {
                        inventoryItemId: "' . $inventoryItemId . '"
                        locationId: "' . $locationId . '"
                        quantity: ' . $quantity . '
                    }
                ]
            }) {
                inventoryAdjustmentGroup {
                    reason
                }
                userErrors {
                    field
                    message
                }
            }
        }';

        Log::info('Executing inventory update mutation', [
            'mutation' => $mutation
        ]);

        $response = $client->post($graphqlEndpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $store->access_token,
            ],
            'json' => ['query' => $mutation]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        Log::info('Inventory update response', ['response' => $data]);

        if (isset($data['data']['inventorySetQuantities']['userErrors']) && 
            !empty($data['data']['inventorySetQuantities']['userErrors'])) {
            Log::error('Inventory update failed', [
                'errors' => $data['data']['inventorySetQuantities']['userErrors']
            ]);
            throw new \Exception(json_encode($data['data']['inventorySetQuantities']['userErrors']));
        }

        Log::info('Inventory updated successfully', [
            'variant_id' => $variantId,
            'quantity' => $quantity
        ]);
    }
}
