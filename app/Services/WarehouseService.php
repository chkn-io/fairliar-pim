<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Setting;

class WarehouseService
{
    private $client;
    private $apiUrl;
    private $apiToken;

    public function __construct()
    {
        $this->client = new Client();
        // Read from database settings instead of config
        $this->apiUrl = Setting::get('warehouse_api_url', 'https://c-api.sellmate.co.kr/external/fairliar/productVariants');
        $this->apiToken = Setting::get('warehouse_api_token', '');
    }

    /**
     * Fetch all product variants from warehouse API with pagination
     * 
     * @param int $page Page number to fetch
     * @return array
     */
    public function getProductVariants($page = 1)
    {
        try {
            $response = $this->client->request('GET', $this->apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'f' => [],
                    'ap' => [],
                    'c' => ['optionHasCodeByShop', 'availabilityInfo']
                ],
                'query' => [
                    'page' => $page,
                    'per_page' => 100
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Warehouse API Response:', [
                'page' => $page,
                'variants_count' => count($data['data'] ?? []),
                'total' => $data['meta']['total'] ?? 0
            ]);

            return $data;

        } catch (RequestException $e) {
            Log::error('Warehouse API request failed:', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            
            return [
                'data' => [],
                'meta' => [],
                'errors' => ['API request failed: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Fetch all variants from all pages (no caching here - let controller handle it)
     * 
     * @return array
     */
    public function getAllProductVariants()
    {
        $allVariants = [];
        $page = 1;
        $totalPages = 1;

        Log::info('Warehouse API: Starting to fetch all variants...');

        do {
            $response = $this->getProductVariants($page);
            
            if (isset($response['data']) && is_array($response['data'])) {
                $allVariants = array_merge($allVariants, $response['data']);
            }

            $totalPages = $response['meta']['last_page'] ?? 1;
            
            // Log progress every 100 pages
            if ($page % 100 === 0) {
                Log::info("Warehouse API: Fetched {$page} pages so far, {$totalPages} total pages");
            }
            
            $page++;

            // Safety limit to prevent infinite loops
            if ($page > 1100) {
                Log::warning('Warehouse API: Reached page limit of 1100');
                break;
            }

        } while ($page <= $totalPages);

        Log::info('Warehouse API: Fetch complete', [
            'total_variants' => count($allVariants),
            'pages_fetched' => $page - 1
        ]);

        return $allVariants;
    }

    /**
     * Get warehouse stock by Shopify variant ID from option_has_code_by_shop
     * shop_id 28 appears to be Shopify based on the sample data
     * 
     * @param string $shopifyVariantId Shopify variant ID
     * @return array|null
     */
    public function getStockByShopifyId($shopifyVariantId)
    {
        $allVariants = $this->getAllProductVariants();
        
        foreach ($allVariants as $variant) {
            if (isset($variant['option_has_code_by_shop'])) {
                foreach ($variant['option_has_code_by_shop'] as $shopCode) {
                    // shop_id 28 appears to be Shopify based on sample data
                    if ($shopCode['shop_id'] == '28' && $shopCode['option_code'] == $shopifyVariantId) {
                        return [
                            'warehouse_id' => $variant['id'],
                            'variant_name' => $variant['variant_name'],
                            'stock' => $this->extractAvailableQty($variant),
                            'barcode' => $variant['barcode1'] ?? '',
                            'sku' => $variant['sguid'] ?? '',
                            'warehouse_stocks' => $variant['variant_stock'] ?? []
                        ];
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Search variants by SKU, barcode, or variant name
     * 
     * @param string $searchTerm
     * @return array
     */
    public function searchVariants($searchTerm)
    {
        $allVariants = $this->getAllProductVariants();
        $searchTerm = strtolower($searchTerm);
        
        return array_filter($allVariants, function($variant) use ($searchTerm) {
            return stripos($variant['variant_name'], $searchTerm) !== false ||
                   stripos($variant['barcode1'] ?? '', $searchTerm) !== false ||
                   stripos($variant['sguid'] ?? '', $searchTerm) !== false ||
                   stripos($variant['code'] ?? '', $searchTerm) !== false;
        });
    }

    /**
     * Create a map of Shopify IDs to warehouse stock for quick lookup
     * 
     * @return array Associative array with Shopify variant IDs as keys
     */
    public function getShopifyStockMap()
    {
        $allVariants = $this->getAllProductVariants();
        $stockMap = [];
        
        foreach ($allVariants as $variant) {
            if (isset($variant['option_has_code_by_shop'])) {
                foreach ($variant['option_has_code_by_shop'] as $shopCode) {
                    // shop_id 28 is Shopify
                    if ($shopCode['shop_id'] == '28') {
                        $shopifyId = $shopCode['option_code'];
                        $stockMap[$shopifyId] = [
                            'warehouse_id' => $variant['id'],
                            'variant_name' => $variant['variant_name'],
                            'stock' => $this->extractAvailableQty($variant),
                            'barcode' => $variant['barcode1'] ?? '',
                            'sku' => $variant['sguid'] ?? '',
                            'cost_price' => $variant['cost_price'] ?? 0,
                            'selling_price' => $variant['selling_price'] ?? 0
                        ];
                    }
                }
            }
        }
        
        return $stockMap;
    }

    /**
     * Clear the cache
     */
    public function clearCache()
    {
        Cache::forget('warehouse_all_variants');
    }

    /**
     * Get warehouse stock for multiple SKUs in one request
     * Uses the 'in' operator to fetch multiple variants at once
     * 
     * @param array $skus Array of SKUs/barcodes to search for
     * @return array Associative array with SKU as key and stock data as value
     */
    public function getStockBySkuBatch($skus)
    {
        if (empty($skus) || !is_array($skus)) {
            return [];
        }

        // Filter out empty SKUs
        $skus = array_filter($skus, function($sku) {
            return !empty($sku);
        });

        if (empty($skus)) {
            return [];
        }

        try {
            // Join SKUs with pipe delimiter for 'in' operator
            $skuList = implode('|', array_map('urlencode', $skus));
            $url = $this->apiUrl . '?f[]=barcode1,in,' . $skuList . ',and&c[]=optionHasCodeByShop&c[]=availabilityInfo&per_page=100';
            
            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiToken,
                    'Accept' => 'application/json',
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Warehouse API Batch Response:', [
                'requested_skus' => count($skus),
                'returned_variants' => count($data['data'] ?? []),
                'meta' => $data['meta'] ?? null
            ]);
            
            $results = [];
            
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $variant) {
                    $barcode = $variant['barcode1'] ?? '';
                    
                    if (empty($barcode)) {
                        continue;
                    }
                    
                    // Extract Shopify variant ID from option_has_code_by_shop
                    $shopifyVariantId = null;
                    if (isset($variant['option_has_code_by_shop'])) {
                        foreach ($variant['option_has_code_by_shop'] as $shopCode) {
                            if ($shopCode['shop_id'] == '28') {
                                $shopifyVariantId = $shopCode['option_code'];
                                break;
                            }
                        }
                    }
                    
                    $results[$barcode] = [
                        'warehouse_id' => $variant['id'],
                        'shopify_variant_id' => $shopifyVariantId,
                        'variant_name' => $variant['variant_name'] ?? '',
                        'stock' => $this->extractAvailableQty($variant),
                        'barcode1' => $barcode,
                        'sku' => $barcode,
                        'cost_price' => $variant['cost_price'] ?? 0,
                        'selling_price' => $variant['selling_price'] ?? 0
                    ];
                }
            }

            return $results;

        } catch (RequestException $e) {
            Log::error('Warehouse API batch SKU search failed:', [
                'sku_count' => count($skus),
                'message' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('Warehouse API batch request error:', [
                'sku_count' => count($skus),
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get warehouse stock by SKU (barcode1)
     * Uses the filter endpoint with barcode1 search
     * 
     * @param string $sku The SKU/barcode to search for
     * @return array|null
     */
    public function getStockBySku($sku)
    {
        if (empty($sku)) {
            return null;
        }

        try {
            // Build URL with filter parameters as string
            $url = $this->apiUrl . '?f[]=barcode1,=,' . urlencode($sku) . ',and&c[]=optionHasCodeByShop&c[]=availabilityInfo';
            
            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiToken,
                    'Accept' => 'application/json',
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['data']) && is_array($data['data']) && count($data['data']) > 0) {
                $variant = $data['data'][0]; // Get first result
                
                // Extract Shopify variant ID from option_has_code_by_shop
                $shopifyVariantId = null;
                if (isset($variant['option_has_code_by_shop'])) {
                    foreach ($variant['option_has_code_by_shop'] as $shopCode) {
                        if ($shopCode['shop_id'] == '28') {
                            $shopifyVariantId = $shopCode['option_code'];
                            break;
                        }
                    }
                }
                
                return [
                    'warehouse_id' => $variant['id'],
                    'shopify_variant_id' => $shopifyVariantId,
                    'variant_name' => $variant['variant_name'] ?? '',
                    'stock' => $this->extractAvailableQty($variant),
                    'barcode1' => $variant['barcode1'] ?? '',
                    'sku' => $sku,
                    'cost_price' => $variant['cost_price'] ?? 0,
                    'selling_price' => $variant['selling_price'] ?? 0
                ];
            }

            return null;

        } catch (RequestException $e) {
            Log::error('Warehouse API SKU search failed:', [
                'sku' => $sku,
                'message' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            
            return null;
        }
    }

    private function extractAvailableQty(array $variant): int
    {
        if (!array_key_exists('availability_info', $variant) || $variant['availability_info'] === null) {
            return 0;
        }

        if (!is_array($variant['availability_info'])) {
            return 0;
        }

        $availableQty = $variant['availability_info']['available_qty'] ?? 0;
        return (int) $availableQty;
    }
}
