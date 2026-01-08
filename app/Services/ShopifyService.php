<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    private $client;
    private $apiKey;
    private $storeDomain;
    private $apiVersion;
    private $graphqlEndpoint;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = config('shopify.api_key');
        $this->storeDomain = config('shopify.store_domain');
        $this->apiVersion = config('shopify.api_version');
        $this->graphqlEndpoint = config('shopify.graphql_endpoint');
    }

    /**
     * Execute GraphQL query to fetch orders
     */
    public function getOrders($first = 50, $after = null, $query = "fulfillment_status:unfulfilled", $sortKey = "CREATED_AT", $reverse = true)
    {
        $graphqlQuery = $this->buildOrdersQuery($first, $after, $query, $sortKey, $reverse);
        
        try {
            $response = $this->client->post($this->graphqlEndpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Shopify-Access-Token' => $this->apiKey,
                ],
                'json' => [
                    'query' => $graphqlQuery
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['errors'])) {
                Log::error('Shopify GraphQL errors:', $data['errors']);
                return [
                    'orders' => [],
                    'pageInfo' => [],
                    'errors' => $data['errors']
                ];
            }

            return $this->formatOrdersResponse($data);

        } catch (RequestException $e) {
            Log::error('Shopify API request failed:', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            
            return [
                'orders' => [],
                'pageInfo' => [],
                'errors' => ['API request failed: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Build the GraphQL query for orders
     */
    private function buildOrdersQuery($first, $after, $query, $sortKey = "CREATED_AT", $reverse = true)
    {
        $afterClause = $after ? ', after: "' . $after . '"' : '';
        $sortClause = ', sortKey: ' . $sortKey . ', reverse: ' . ($reverse ? 'true' : 'false');
        
        return '{
            orders(first: ' . $first . $afterClause . ', query: "' . $query . '"' . $sortClause . ') {
                edges {
                    cursor
                    node {
                        id
                        name
                        createdAt
                        totalPriceSet {
                            shopMoney {
                                amount
                                currencyCode
                            }
                        }
                        transactions(first: 10) {
                            gateway
                            kind
                            processedAt
                            amountSet {
                                presentmentMoney {
                                    amount
                                    currencyCode
                                }
                            }
                        }
                        customer {
                            email
                            phone
                        }
                        shippingAddress {
                            name
                            phone
                            address1
                            city
                            province
                            country
                            zip
                        }
                        lineItems(first: 50) {
                            edges {
                                node {
                                    name
                                    sku
                                    quantity
                                    originalUnitPriceSet {
                                        shopMoney {
                                            amount
                                            currencyCode
                                        }
                                    }
                                }
                            }
                        }
                        fulfillmentOrders(first: 10) {
                            edges {
                                node {
                                    status
                                    assignedLocation {
                                        location {
                                            name
                                        }
                                    }
                                    lineItems(first: 10) {
                                        edges {
                                            node {
                                                lineItem {
                                                    name
                                                    sku
                                                    quantity
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                pageInfo {
                    hasNextPage
                    hasPreviousPage
                    startCursor
                    endCursor
                }
            }
        }';
    }

    /**
     * Format the API response for easier use
     */
    private function formatOrdersResponse($data)
    {
        $orders = [];
        $pageInfo = $data['data']['orders']['pageInfo'] ?? [];

        foreach ($data['data']['orders']['edges'] ?? [] as $edge) {
            $order = $edge['node'];
            
            $formattedOrder = [
                'id' => $order['id'],
                'name' => $order['name'],
                'created_at' => $order['createdAt'],
                'total_price' => $order['totalPriceSet']['shopMoney']['amount'] ?? '0.00',
                'currency' => $order['totalPriceSet']['shopMoney']['currencyCode'] ?? 'USD',
                'cursor' => $edge['cursor'],
                'customer' => [
                    'email' => $order['customer']['email'] ?? '',
                    'phone' => $order['customer']['phone'] ?? '',
                ],
                'transactions' => [],
                'shipping_address' => [
                    'name' => $order['shippingAddress']['name'] ?? '',
                    'phone' => $order['shippingAddress']['phone'] ?? '',
                    'address1' => $order['shippingAddress']['address1'] ?? '',
                    'city' => $order['shippingAddress']['city'] ?? '',
                    'province' => $order['shippingAddress']['province'] ?? '',
                    'country' => $order['shippingAddress']['country'] ?? '',
                    'zip' => $order['shippingAddress']['zip'] ?? '',
                ],
                'line_items' => [],
                'fulfillment_orders' => []
            ];

            // Format transactions
            foreach ($order['transactions'] ?? [] as $transaction) {
                $formattedOrder['transactions'][] = [
                    'kind' => strtoupper($transaction['kind'] ?? 'UNKNOWN'),
                    'gateway' => $transaction['gateway'] ?? 'unknown',
                    'processed_at' => $transaction['processedAt'] ?? null,
                    'amount' => $transaction['amountSet']['presentmentMoney']['amount'] ?? '0.00',
                    'currency' => $transaction['amountSet']['presentmentMoney']['currencyCode'] ?? 'USD'
                ];
            }

            // Get fulfillment information directly from fulfillmentOrders
            $fulfillmentInfo = [];
            foreach ($order['fulfillmentOrders']['edges'] ?? [] as $fulfillmentEdge) {
                $fulfillment = $fulfillmentEdge['node'];
                $location = $fulfillment['assignedLocation']['location']['name'] ?? 'Not assigned';
                $status = $fulfillment['status'];
                
                // Map each line item in this fulfillment to the location
                foreach ($fulfillment['lineItems']['edges'] ?? [] as $fulfillmentLineItem) {
                    $lineItemData = $fulfillmentLineItem['node']['lineItem'];
                    $key = $lineItemData['name'] . '|' . $lineItemData['sku']; // Use name+sku as key
                    
                    $fulfillmentInfo[$key] = [
                        'location' => $location,
                        'status' => $status
                    ];
                }
            }

            // Format line items with their fulfillment information
            foreach ($order['lineItems']['edges'] ?? [] as $lineItemEdge) {
                $lineItem = $lineItemEdge['node'];
                $key = $lineItem['name'] . '|' . $lineItem['sku'];
                
                // Get fulfillment info for this specific line item
                $fulfillment = $fulfillmentInfo[$key] ?? null;
                
                $formattedOrder['line_items'][] = [
                    'name' => $lineItem['name'],
                    'sku' => $lineItem['sku'],
                    'quantity' => $lineItem['quantity'],
                    'price' => $lineItem['originalUnitPriceSet']['shopMoney']['amount'] ?? '0.00',
                    'fulfillment_location' => $fulfillment['location'] ?? 'Not assigned',
                    'fulfillment_status' => $fulfillment['status'] ?? 'No fulfillment'
                ];
            }

            // Format fulfillment orders (keep for backward compatibility)
            foreach ($order['fulfillmentOrders']['edges'] ?? [] as $fulfillmentEdge) {
                $fulfillment = $fulfillmentEdge['node'];
                $formattedOrder['fulfillment_orders'][] = [
                    'status' => $fulfillment['status'],
                    'location' => $fulfillment['assignedLocation']['location']['name'] ?? '',
                    'line_items' => array_map(function($item) {
                        return [
                            'name' => $item['node']['lineItem']['name'],
                            'sku' => $item['node']['lineItem']['sku'],
                            'quantity' => $item['node']['lineItem']['quantity']
                        ];
                    }, $fulfillment['lineItems']['edges'] ?? [])
                ];
            }

            $orders[] = $formattedOrder;
        }

        return [
            'orders' => $orders,
            'pageInfo' => $pageInfo,
            'errors' => []
        ];
    }

    /**
     * Get inventory levels from all locations
     */
    public function getInventory($locationId = null, $productType = null, $vendor = null, $first = 20, $fetchAll = true)
    {
        $allInventory = [];
        $allErrors = [];
        $after = null;
        $pageCount = 0;
        $hasNextPage = true;
        $maxIterations = 200; // Safety limit to prevent infinite loops
        
        Log::info('Starting inventory fetch:', [
            'location_id' => $locationId ?? 'all',
            'fetch_all' => $fetchAll,
            'products_per_page' => $first
        ]);
        
        while ($hasNextPage && $pageCount < $maxIterations) {
            // For preview mode, only fetch first page
            if (!$fetchAll && $pageCount >= 1) {
                Log::info('Preview mode: stopping after first page');
                break;
            }
            
            $graphqlQuery = $this->buildInventoryQuery($locationId, $productType, $vendor, $first, $after);
            
            try {
                $response = $this->client->post($this->graphqlEndpoint, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-Shopify-Access-Token' => $this->apiKey,
                    ],
                    'json' => [
                        'query' => $graphqlQuery
                    ]
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                
                if (isset($data['errors'])) {
                    Log::error('Shopify GraphQL inventory errors:', $data['errors']);
                    $allErrors = array_merge($allErrors, $data['errors']);
                    break;
                }

                // Get pagination info first
                $pageInfo = $data['data']['products']['pageInfo'] ?? [];
                $currentHasNextPage = $pageInfo['hasNextPage'] ?? false;
                $currentCursor = $pageInfo['endCursor'] ?? null;

                // Log the structure for debugging
                Log::info('Shopify API Response Structure (Page ' . ($pageCount + 1) . '):', [
                    'has_products' => isset($data['data']['products']),
                    'product_count' => count($data['data']['products']['edges'] ?? []),
                    'has_next_page' => $currentHasNextPage,
                    'end_cursor' => $currentCursor ?? 'none',
                    'variants_in_response' => array_sum(array_map(function($edge) {
                        return count($edge['node']['variants']['edges'] ?? []);
                    }, $data['data']['products']['edges'] ?? []))
                ]);

                $result = $this->formatInventoryResponse($data, $locationId);
                $allInventory = array_merge($allInventory, $result['inventory']);
                $allErrors = array_merge($allErrors, $result['errors']);
                
                Log::info('Progress after page ' . ($pageCount + 1) . ':', [
                    'total_inventory_items' => count($allInventory),
                    'new_items_this_page' => count($result['inventory']),
                    'running_total' => count($allInventory)
                ]);
                
                // Update pagination variables
                $hasNextPage = $currentHasNextPage;
                $after = $currentCursor;
                
                $pageCount++;
                
                Log::info('Pagination status:', [
                    'page_just_completed' => $pageCount,
                    'has_next_page' => $hasNextPage,
                    'will_continue' => ($hasNextPage && $fetchAll),
                    'cursor' => $after ?? 'none'
                ]);
                
                // If no more pages, stop
                if (!$hasNextPage) {
                    Log::info('No more pages available, stopping pagination');
                    break;
                }
                
            } catch (RequestException $e) {
                Log::error('Shopify Inventory API request failed:', [
                    'message' => $e->getMessage(),
                    'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
                ]);
                
                $allErrors[] = 'API request failed: ' . $e->getMessage();
                break;
            }
        }

        Log::info('===== INVENTORY FETCH COMPLETE =====', [
            'total_variants' => count($allInventory),
            'total_unique_products' => count(array_unique(array_column($allInventory, 'product_id'))),
            'pages_fetched' => $pageCount,
            'stopped_reason' => !$hasNextPage ? 'no_more_pages' : ($pageCount >= $maxIterations ? 'max_iterations' : 'other'),
            'last_cursor' => $after ?? 'none',
            'location_filter' => $locationId ?? 'all',
            'fetch_all_mode' => $fetchAll,
            'last_has_next_page' => $hasNextPage
        ]);

        return [
            'inventory' => $allInventory,
            'locations' => [],
            'errors' => $allErrors
        ];
    }

    /**
     * Get a single page of inventory for pagination
     */
    public function getInventoryPage($locationId = null, $productType = null, $vendor = null, $first = 20, $page = 1)
    {
        // Calculate cursor position based on page number
        // We'll need to fetch pages sequentially to get the right cursor
        $after = null;
        $currentPage = 1;
        $inventory = [];
        $errors = [];
        $hasNextPage = false;
        
        while ($currentPage <= $page) {
            $graphqlQuery = $this->buildInventoryQuery($locationId, $productType, $vendor, $first, $after);
            
            try {
                $response = $this->client->post($this->graphqlEndpoint, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-Shopify-Access-Token' => $this->apiKey,
                    ],
                    'json' => [
                        'query' => $graphqlQuery
                    ]
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                
                if (isset($data['errors'])) {
                    Log::error('Shopify GraphQL inventory errors:', $data['errors']);
                    $errors = array_merge($errors, $data['errors']);
                    break;
                }

                // Get pagination info
                $pageInfo = $data['data']['products']['pageInfo'] ?? [];
                $hasNextPage = $pageInfo['hasNextPage'] ?? false;
                $after = $pageInfo['endCursor'] ?? null;
                
                // If this is the target page, format and return the data
                if ($currentPage === $page) {
                    $result = $this->formatInventoryResponse($data, $locationId);
                    $inventory = $result['inventory'];
                    $errors = array_merge($errors, $result['errors']);
                    break;
                }
                
                $currentPage++;
                
                // If no more pages and we haven't reached target page, return empty
                if (!$hasNextPage) {
                    break;
                }
                
            } catch (RequestException $e) {
                Log::error('Shopify Inventory API request failed:', [
                    'message' => $e->getMessage(),
                    'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
                ]);
                
                $errors[] = 'API request failed: ' . $e->getMessage();
                break;
            }
        }
        
        return [
            'inventory' => $inventory,
            'has_next_page' => $hasNextPage,
            'current_page' => $page,
            'errors' => $errors
        ];
    }

    /**
     * Get available locations
     */
    public function getLocations()
    {
        $graphqlQuery = '{
            locations(first: 50) {
                edges {
                    node {
                        id
                        name
                        address {
                            city
                            country
                        }
                    }
                }
            }
        }';
        
        try {
            $response = $this->client->post($this->graphqlEndpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Shopify-Access-Token' => $this->apiKey,
                ],
                'json' => [
                    'query' => $graphqlQuery
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $locations = [];

            foreach ($data['data']['locations']['edges'] ?? [] as $edge) {
                $location = $edge['node'];
                $locations[] = [
                    'id' => $location['id'],
                    'name' => $location['name'],
                    'city' => $location['address']['city'] ?? '',
                    'country' => $location['address']['country'] ?? ''
                ];
            }

            return $locations;

        } catch (RequestException $e) {
            Log::error('Shopify Locations API request failed:', [
                'message' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    private function escapeShopifyQueryValue($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        // Escape backslashes and double quotes for embedding inside a GraphQL string literal
        return str_replace(["\\", '"'], ["\\\\", '\\"'], $value);
    }

    private function buildProductsSearchQuery(array $filters): string
    {
        $parts = [];

        $tag = $this->escapeShopifyQueryValue($filters['tag'] ?? '');
        if ($tag !== '') {
            // Quote tag to support spaces/special chars
            $parts[] = 'tag:"' . $tag . '"';
        }

        $sku = $this->escapeShopifyQueryValue($filters['sku'] ?? '');
        if ($sku !== '') {
            // Match any variant SKU containing the term
            $parts[] = 'sku:*' . $sku . '*';
        }

        $name = $this->escapeShopifyQueryValue($filters['name'] ?? '');
        if ($name !== '') {
            $parts[] = 'title:*' . $name . '*';
        }

        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if ($status !== '' && $status !== 'all') {
            $parts[] = 'status:' . $status;
        }

        return implode(' AND ', $parts);
    }

    private function buildProductsWithVariantsQuery(int $first = 20, ?string $after = null, string $queryString = '', bool $includeCategory = true, bool $includeOnlineStoreUrl = true): string
    {
        $afterClause = $after ? ', after: "' . $after . '"' : '';
        $queryClause = $queryString !== '' ? ', query: "' . $queryString . '"' : '';

        $onlineStoreUrlField = $includeOnlineStoreUrl ? "\n                        onlineStoreUrl" : '';
        $categoryField = $includeCategory ? "\n                        category {\n                            fullName\n                        }" : '';

        return '{
            products(first: ' . $first . $queryClause . $afterClause . ') {
                edges {
                    cursor
                    node {
                        id
                        handle
                        title
                        vendor
                        productType
                        tags
                        updatedAt
                        status' . $onlineStoreUrlField . $categoryField . '
                        variants(first: 100) {
                            edges {
                                node {
                                    id
                                    sku
                                    barcode
                                    price
                                }
                            }
                        }
                    }
                }
                pageInfo {
                    hasNextPage
                    endCursor
                }
            }
        }';
    }

    private function formatProductsWithVariantsResponse(array $data): array
    {
        $products = [];
        $edges = $data['data']['products']['edges'] ?? [];

        foreach ($edges as $edge) {
            $product = $edge['node'] ?? [];
            if (empty($product)) {
                continue;
            }

            $productIdNumeric = preg_replace('/^gid:\/\/shopify\/\w+\//', '', $product['id'] ?? '');
            $handle = $product['handle'] ?? '';

            $tags = $product['tags'] ?? [];
            $tagsString = is_array($tags) ? implode(', ', $tags) : (string) $tags;

            $onlineStoreUrl = $product['onlineStoreUrl'] ?? '';
            if (!$onlineStoreUrl && $handle) {
                $onlineStoreUrl = $this->storeDomain ? ('https://' . $this->storeDomain . '/products/' . $handle) : '';
            }

            $category = '';
            if (isset($product['category']['fullName'])) {
                $category = (string) $product['category']['fullName'];
            }

            $variants = [];
            foreach (($product['variants']['edges'] ?? []) as $variantEdge) {
                $variant = $variantEdge['node'] ?? [];
                if (empty($variant)) {
                    continue;
                }

                $variantIdNumeric = preg_replace('/^gid:\/\/shopify\/\w+\//', '', $variant['id'] ?? '');

                $variants[] = [
                    'variant_id' => $variantIdNumeric,
                    'sku' => $variant['sku'] ?? '',
                    'barcode' => $variant['barcode'] ?? '',
                    'price' => $variant['price'] ?? '',
                ];
            }

            $products[] = [
                'product_id' => $productIdNumeric,
                'handle' => $handle,
                'title' => $product['title'] ?? '',
                'vendor' => $product['vendor'] ?? '',
                'type' => $product['productType'] ?? '',
                'tags' => $tagsString,
                'updated_at' => $product['updatedAt'] ?? '',
                'status' => $product['status'] ?? '',
                'url' => $onlineStoreUrl,
                'category' => $category,
                'variants' => $variants,
            ];
        }

        return $products;
    }

    /**
     * Fetch a single cursor-paginated batch of products with variants.
     */
    public function getProductsWithVariantsBatch(array $filters, int $first = 20, ?string $after = null): array
    {
        $queryString = $this->buildProductsSearchQuery($filters);

        $includeCategory = true;
        $includeOnlineStoreUrl = true;

        $graphqlQuery = $this->buildProductsWithVariantsQuery($first, $after, $queryString, $includeCategory, $includeOnlineStoreUrl);

        try {
            $response = $this->client->post($this->graphqlEndpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Shopify-Access-Token' => $this->apiKey,
                ],
                'json' => [
                    'query' => $graphqlQuery,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // If schema doesn't support optional fields, retry once without them.
            if (isset($data['errors']) && is_array($data['errors'])) {
                $messages = array_map(function ($e) {
                    return is_array($e) ? ($e['message'] ?? json_encode($e)) : (string) $e;
                }, $data['errors']);
                $combined = implode(' | ', $messages);

                $unknownCategory = stripos($combined, "Field 'category'") !== false;
                $unknownOnlineStoreUrl = stripos($combined, "Field 'onlineStoreUrl'") !== false;

                if ($unknownCategory || $unknownOnlineStoreUrl) {
                    $includeCategory = !$unknownCategory;
                    $includeOnlineStoreUrl = !$unknownOnlineStoreUrl;
                    $graphqlQuery = $this->buildProductsWithVariantsQuery($first, $after, $queryString, $includeCategory, $includeOnlineStoreUrl);

                    $response = $this->client->post($this->graphqlEndpoint, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'X-Shopify-Access-Token' => $this->apiKey,
                        ],
                        'json' => [
                            'query' => $graphqlQuery,
                        ],
                    ]);

                    $data = json_decode($response->getBody()->getContents(), true);
                }
            }

            if (isset($data['errors'])) {
                Log::error('Shopify GraphQL products errors:', $data['errors']);

                return [
                    'products' => [],
                    'pageInfo' => [],
                    'errors' => $data['errors'],
                ];
            }

            $pageInfo = $data['data']['products']['pageInfo'] ?? [];

            return [
                'products' => $this->formatProductsWithVariantsResponse($data),
                'pageInfo' => $pageInfo,
                'errors' => [],
            ];
        } catch (RequestException $e) {
            Log::error('Shopify Products API request failed:', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);

            return [
                'products' => [],
                'pageInfo' => [],
                'errors' => ['API request failed: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Fetch a specific page of products by walking cursors sequentially.
     */
    public function getProductsWithVariantsPage(array $filters, int $first = 20, int $page = 1): array
    {
        $page = max(1, $page);
        $currentPage = 1;
        $after = null;

        $lastBatch = [
            'products' => [],
            'pageInfo' => [],
            'errors' => [],
        ];

        while ($currentPage <= $page) {
            $lastBatch = $this->getProductsWithVariantsBatch($filters, $first, $after);
            if (!empty($lastBatch['errors'])) {
                break;
            }

            $pageInfo = $lastBatch['pageInfo'] ?? [];
            $hasNextPage = (bool) ($pageInfo['hasNextPage'] ?? false);
            $after = $pageInfo['endCursor'] ?? null;

            if ($currentPage === $page) {
                return [
                    'products' => $lastBatch['products'] ?? [],
                    'has_next_page' => $hasNextPage,
                    'current_page' => $page,
                    'errors' => $lastBatch['errors'] ?? [],
                ];
            }

            $currentPage++;

            if (!$hasNextPage) {
                break;
            }
        }

        return [
            'products' => [],
            'has_next_page' => false,
            'current_page' => $page,
            'errors' => $lastBatch['errors'] ?? ['Unable to fetch requested page'],
        ];
    }

    /**
     * Build inventory GraphQL query
     */
    private function buildInventoryQuery($locationId = null, $productType = null, $vendor = null, $first = 50, $after = null)
    {
        $productQuery = '';
        
        if ($productType || $vendor) {
            $filters = [];
            if ($productType) $filters[] = 'product_type:' . $productType;
            if ($vendor) $filters[] = 'vendor:' . $vendor;
            $productQuery = ', query: "' . implode(' AND ', $filters) . '"';
        }

        $afterClause = $after ? ', after: "' . $after . '"' : '';

        return '{
            products(first: ' . $first . $productQuery . $afterClause . ') {
                edges {
                    cursor
                    node {
                        id
                        title
                        handle
                        productType
                        vendor
                        status
                        tags
                        variants(first: 75) {
                            edges {
                                node {
                                    id
                                    title
                                    sku
                                    price
                                    inventoryItem {
                                        id
                                        inventoryLevels(first: 10) {
                                            edges {
                                                node {
                                                    quantities(names: ["available"]) {
                                                        id
                                                        quantity
                                                    }
                                                    location {
                                                        id
                                                        name
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                pageInfo {
                    hasNextPage
                    endCursor
                }
            }
        }';
    }

    /**
     * Format inventory response
     */
    private function formatInventoryResponse($data, $locationId = null)
    {
        $inventory = [];
        
        // Extract inventory from products using inventoryLevels
        foreach ($data['data']['products']['edges'] ?? [] as $edge) {
            $product = $edge['node'] ?? [];
            
            if (empty($product)) {
                continue;
            }
            
            $tags = $product['tags'] ?? [];
            $tagsString = is_array($tags) ? implode(', ', $tags) : $tags;
            
            foreach ($product['variants']['edges'] ?? [] as $variantEdge) {
                $variant = $variantEdge['node'] ?? [];
                
                if (!$variant) {
                    continue;
                }
                
                // Process inventory levels per location
                $inventoryLevels = $variant['inventoryItem']['inventoryLevels']['edges'] ?? [];
                
                // If no inventory levels, create one entry with 0 quantity
                if (empty($inventoryLevels)) {
                    $inventory[] = [
                        'product_id' => $product['id'] ?? '',
                        'product_title' => $product['title'] ?? '',
                        'product_handle' => $product['handle'] ?? '',
                        'product_type' => $product['productType'] ?? '',
                        'vendor' => $product['vendor'] ?? '',
                        'product_status' => $product['status'] ?? '',
                        'tags' => $tagsString,
                        'variant_id' => $variant['id'] ?? '',
                        'variant_title' => $variant['title'] ?? '',
                        'sku' => $variant['sku'] ?? '',
                        'price' => $variant['price'] ?? '0.00',
                        'compare_at_price' => null,
                        'inventory_policy' => '',
                        'available_quantity' => 0,
                        'inventory_item_id' => $variant['inventoryItem']['id'] ?? '',
                        'location_id' => '',
                        'location_name' => 'No Location',
                        'location_city' => '',
                        'location_country' => ''
                    ];
                    continue;
                }
                
                foreach ($inventoryLevels as $levelEdge) {
                    $level = $levelEdge['node'] ?? [];
                    $location = $level['location'] ?? [];
                    
                    // Filter by location if specified
                    if ($locationId && ($location['id'] ?? '') !== $locationId) {
                        continue;
                    }
                    
                    // Get available quantity
                    $availableQty = 0;
                    if (!empty($level['quantities'])) {
                        foreach ($level['quantities'] as $qty) {
                            $availableQty = $qty['quantity'] ?? 0;
                            break; // We only asked for "available"
                        }
                    }
                    
                    $inventory[] = [
                        'product_id' => $product['id'] ?? '',
                        'product_title' => $product['title'] ?? '',
                        'product_handle' => $product['handle'] ?? '',
                        'product_type' => $product['productType'] ?? '',
                        'vendor' => $product['vendor'] ?? '',
                        'product_status' => $product['status'] ?? '',
                        'tags' => $tagsString,
                        'variant_id' => $variant['id'] ?? '',
                        'variant_title' => $variant['title'] ?? '',
                        'sku' => $variant['sku'] ?? '',
                        'price' => $variant['price'] ?? '0.00',
                        'compare_at_price' => null,
                        'inventory_policy' => '',
                        'available_quantity' => $availableQty,
                        'inventory_item_id' => $variant['inventoryItem']['id'] ?? '',
                        'location_id' => $location['id'] ?? '',
                        'location_name' => $location['name'] ?? 'Unknown Location',
                        'location_city' => '',
                        'location_country' => ''
                    ];
                }
            }
        }
        
        return [
            'inventory' => $inventory,
            'locations' => [],
            'errors' => []
        ];
    }
    
    /**
     * Get inventory in multiple batches for export (to handle larger datasets)
     */
    public function getInventoryForExport($locationId = null, $productType = null, $vendor = null, $maxBatches = 5)
    {
        $allInventory = [];
        $allLocations = [];
        $errors = [];
        
        // Get locations first
        $locations = $this->getLocations();
        
        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $result = $this->getInventory($locationId, $productType, $vendor, 50);
            
            if (!empty($result['errors'])) {
                $errors = array_merge($errors, $result['errors']);
                break;
            }
            
            $inventory = $result['inventory'] ?? [];
            
            if (empty($inventory)) {
                break; // No more data
            }
            
            $allInventory = array_merge($allInventory, $inventory);
            
            // Simple pagination simulation - in a real implementation, 
            // you would use cursors for proper pagination
            if (count($inventory) < 50) {
                break; // Last batch
            }
        }
        
        return [
            'inventory' => $allInventory,
            'locations' => $locations,
            'errors' => $errors,
            'batches_fetched' => $batch + 1
        ];
    }

    /**
     * Build GraphQL query for product variants with inventory
     * Using productVariants query directly for consistent pagination
     */
    private function buildVariantsQuery($first = 20, $after = null, $sortKey = 'TITLE', $reverse = false, $searchQuery = '')
    {
        $afterClause = $after ? ', after: "' . $after . '"' : '';
        $reverseClause = $reverse ? ', reverse: true' : '';
        $queryString = 'status:active' . $searchQuery;
        
        // Fetch variants directly - consistent count per page!
        return '{
            productVariants(first: ' . $first . ', query: "' . $queryString . '", sortKey: ' . $sortKey . $reverseClause . $afterClause . ') {
                pageInfo {
                    hasNextPage
                    endCursor
                }
                edges {
                    cursor
                    node {
                        id
                        title
                        sku
                        barcode
                        inventoryQuantity
                        metafield(namespace: "custom", key: "pim_sync") {
                            value
                        }
                        syncTimestampMetafield: metafield(namespace: "custom", key: "pim_kr_sync_timestamp") {
                            value
                        }
                        product {
                            id
                            title
                            handle
                            status
                        }
                        inventoryItem {
                            id
                            inventoryLevels(first: 10) {
                                edges {
                                    node {
                                        id
                                        quantities(names: ["available"]) {
                                            name
                                            quantity
                                        }
                                        location {
                                            id
                                            name
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }';
    }

    /**
     * Get all product variants with their inventory across locations
     */
    public function getProductVariants($fetchAll = false, $locationId = null, $after = null, $sortKey = 'TITLE', $reverse = false, $searchQuery = '')
    {
        $allVariants = [];
        $hasNextPage = true;
        $pageCount = 0;
        $maxIterations = $fetchAll ? 1000 : 10; // Only 10 iterations for single page, 1000 for full fetch

        Log::info('Starting variant fetch:', [
            'location_id' => $locationId,
            'fetch_all' => $fetchAll,
            'after_cursor' => $after,
            'sort_key' => $sortKey,
            'reverse' => $reverse,
            'search_query' => $searchQuery
        ]);

        while ($hasNextPage && $pageCount < $maxIterations) {
            $pageCount++;
            $graphqlQuery = $this->buildVariantsQuery(20, $after, $sortKey, $reverse, $searchQuery); // 20 active variants per query

            try {
                $response = $this->client->post($this->graphqlEndpoint, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-Shopify-Access-Token' => $this->apiKey,
                    ],
                    'json' => [
                        'query' => $graphqlQuery
                    ]
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                if (isset($data['errors'])) {
                    Log::error('Shopify GraphQL errors:', $data['errors']);
                    break;
                }

                $variants = $data['data']['productVariants']['edges'] ?? [];
                $pageInfo = $data['data']['productVariants']['pageInfo'] ?? [];

                foreach ($variants as $variantEdge) {
                    $variant = $variantEdge['node'];
                    $product = $variant['product'];
                    $inventoryLevels = $variant['inventoryItem']['inventoryLevels']['edges'] ?? [];

                    // Extract numeric ID from GID
                    $variantNumericId = preg_replace('/^gid:\/\/shopify\/\w+\//', '', $variant['id']);
                    $productNumericId = preg_replace('/^gid:\/\/shopify\/\w+\//', '', $product['id']);

                    // Get metafield value from variant (not product)
                    $pimSync = $variant['metafield']['value'] ?? '';
                    $syncTimestamp = $variant['syncTimestampMetafield']['value'] ?? '';
                    
                    // Extract inventory item ID
                    $inventoryItemId = $variant['inventoryItem']['id'] ?? '';

                    $variantData = [
                        'variant_id' => $variantNumericId,
                        'variant_gid' => $variant['id'],
                        'product_id' => $productNumericId,
                        'product_gid' => $product['id'],
                        'product_title' => $product['title'],
                        'product_handle' => $product['handle'] ?? '',
                        'product_status' => $product['status'] ?? 'ACTIVE',
                        'pim_sync' => $pimSync,
                        'sync_timestamp' => $syncTimestamp,
                        'variant_title' => $variant['title'],
                        'sku' => $variant['sku'] ?? '',
                        'barcode' => $variant['barcode'] ?? '',
                        'total_inventory' => $variant['inventoryQuantity'] ?? 0,
                        'inventory_item_id' => $inventoryItemId,
                        'inventory_levels' => []
                    ];

                    // Process inventory levels by location
                    foreach ($inventoryLevels as $levelEdge) {
                        $level = $levelEdge['node'];
                        $locId = $level['location']['id'];
                        
                        // Filter by location if specified
                        if ($locationId && $locId !== $locationId) {
                            continue;
                        }

                        // Extract available quantity from quantities array
                        $availableQty = 0;
                        if (isset($level['quantities']) && is_array($level['quantities'])) {
                            foreach ($level['quantities'] as $qty) {
                                if ($qty['name'] === 'available') {
                                    $availableQty = $qty['quantity'] ?? 0;
                                    break;
                                }
                            }
                        }

                        $variantData['inventory_levels'][] = [
                            'location_id' => $locId,
                            'location_name' => $level['location']['name'],
                            'available' => $availableQty
                        ];
                    }

                    // Only add if we have inventory levels (or not filtering by location)
                    if (!$locationId || !empty($variantData['inventory_levels'])) {
                        $allVariants[] = $variantData;
                    }
                }

                $hasNextPage = $pageInfo['hasNextPage'] ?? false;
                $after = $pageInfo['endCursor'] ?? null;

                Log::info("Variants page {$pageCount} fetched:", [
                    'variants_in_page' => count($variants),
                    'total_variants_so_far' => count($allVariants),
                    'has_next_page' => $hasNextPage
                ]);

                if (!$fetchAll) {
                    break;
                }

            } catch (RequestException $e) {
                Log::error('Shopify API request failed:', [
                    'message' => $e->getMessage(),
                    'page' => $pageCount
                ]);
                break;
            }
        }

        Log::info('===== VARIANT FETCH COMPLETE =====', [
            'total_variants' => count($allVariants),
            'pages_fetched' => $pageCount,
            'has_next_page' => $hasNextPage,
            'end_cursor' => $after
        ]);

        return [
            'variants' => $allVariants,
            'pageInfo' => [
                'hasNextPage' => $hasNextPage,
                'endCursor' => $after
            ]
        ];
    }

    /**
     * Update product metafield
     */
    public function updateProductMetafield($productGid, $namespace, $key, $value)
    {
        $mutation = 'mutation UpdateProductMetafield($input: ProductInput!) {
            productUpdate(input: $input) {
                product {
                    id
                    metafield(namespace: "' . $namespace . '", key: "' . $key . '") {
                        value
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }';

        $variables = [
            'input' => [
                'id' => $productGid,
                'metafields' => [
                    [
                        'namespace' => $namespace,
                        'key' => $key,
                        'value' => $value,
                        'type' => 'single_line_text_field'
                    ]
                ]
            ]
        ];

        try {
            $response = $this->client->post($this->graphqlEndpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Shopify-Access-Token' => $this->apiKey,
                ],
                'json' => [
                    'query' => $mutation,
                    'variables' => $variables
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['errors']) || !empty($data['data']['productUpdate']['userErrors'])) {
                Log::error('Shopify metafield update errors:', $data);
                return false;
            }

            return true;

        } catch (RequestException $e) {
            Log::error('Shopify metafield update failed:', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            return false;
        }
    }

    /**
     * Update variant metafield using metafieldsSet mutation
     */
    public function updateVariantMetafield($variantGid, $namespace, $key, $value)
    {
        // Use metafieldsSet mutation which is the modern way to set metafields
        $mutation = 'mutation metafieldsSet($metafields: [MetafieldsSetInput!]!) {
            metafieldsSet(metafields: $metafields) {
                metafields {
                    id
                    namespace
                    key
                    value
                }
                userErrors {
                    field
                    message
                }
            }
        }';

        $variables = [
            'metafields' => [
                [
                    'ownerId' => $variantGid,
                    'namespace' => $namespace,
                    'key' => $key,
                    'value' => $value,
                    'type' => 'single_line_text_field'
                ]
            ]
        ];

        try {
            $response = $this->client->post($this->graphqlEndpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Shopify-Access-Token' => $this->apiKey,
                ],
                'json' => [
                    'query' => $mutation,
                    'variables' => $variables
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Variant metafield update response:', $data);

            if (isset($data['errors']) || !empty($data['data']['metafieldsSet']['userErrors'])) {
                Log::error('Shopify variant metafield update errors:', $data);
                return false;
            }

            return true;

        } catch (RequestException $e) {
            Log::error('Shopify variant metafield update failed:', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            return false;
        }
    }

    /**
     * Update inventory level for a specific location
     */
    public function updateInventoryLevel($inventoryItemId, $locationId, $availableQuantity)
    {
        $mutation = 'mutation InventorySetQuantities($input: InventorySetQuantitiesInput!) {
            inventorySetQuantities(input: $input) {
                inventoryAdjustmentGroup {
                    id
                }
                userErrors {
                    field
                    message
                }
            }
        }';

        $variables = [
            'input' => [
                'reason' => 'correction',
                'name' => 'available',
                'ignoreCompareQuantity' => true,
                'quantities' => [
                    [
                        'inventoryItemId' => $inventoryItemId,
                        'locationId' => $locationId,
                        'quantity' => $availableQuantity
                    ]
                ]
            ]
        ];

        try {
            $response = $this->client->post($this->graphqlEndpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Shopify-Access-Token' => $this->apiKey,
                ],
                'json' => [
                    'query' => $mutation,
                    'variables' => $variables
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['errors']) || !empty($data['data']['inventorySetQuantities']['userErrors'])) {
                Log::error('Shopify inventory update errors:', $data);
                return false;
            }

            return true;

        } catch (RequestException $e) {
            Log::error('Shopify inventory update failed:', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            return false;
        }
    }
}

