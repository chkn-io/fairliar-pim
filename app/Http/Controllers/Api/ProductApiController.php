<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductApiController extends Controller
{
    /**
     * Get products from a specific store
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProducts(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'store' => 'required|string',
            'status' => 'nullable|string',
            'collection_id' => 'nullable|string',
        ]);

        $storeName = $request->input('store');
        $status = $request->input('status', 'active'); // default to active
        $collectionId = $request->input('collection_id');

        // Find the store by name
        $store = Store::where('name', $storeName)
                      ->where('is_active', true)
                      ->first();

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found or inactive',
                'data' => []
            ], 404);
        }

        // Build query filters
        $queryFilters = $this->buildQueryFilters($status, $collectionId);

        Log::info('Fetching products with filters', [
            'store' => $storeName,
            'query' => $queryFilters
        ]);

        try {
            // Fetch products from Shopify
            $products = $collectionId 
                ? $this->fetchProductsFromCollection(
                    $store->shop_domain,
                    $store->access_token,
                    $collectionId,
                    $queryFilters
                  )
                : $this->fetchProductsFromShopify(
                    $store->shop_domain,
                    $store->access_token,
                    $queryFilters
                  );

            return response()->json([
                'success' => true,
                'message' => 'Products fetched successfully',
                'store' => [
                    'name' => $store->name,
                    'domain' => $store->shop_domain,
                ],
                'data' => $products,
                'count' => count($products)
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching products from store: ' . $storeName, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching products: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Build query filters for Shopify
     */
    private function buildQueryFilters($status, $collectionId = null)
    {
        $queries = [];

        // Add status filter
        if ($status) {
            $statuses = array_map('trim', explode(',', $status));
            $statusQueries = [];

            foreach ($statuses as $s) {
                switch (strtolower($s)) {
                    case 'active':
                        $statusQueries[] = 'status:active';
                        break;
                    case 'draft':
                        $statusQueries[] = 'status:draft';
                        break;
                    case 'archived':
                        $statusQueries[] = 'status:archived';
                        break;
                }
            }

            if (!empty($statusQueries)) {
                if (count($statusQueries) > 1) {
                    $queries[] = '(' . implode(' OR ', $statusQueries) . ')';
                } else {
                    $queries[] = $statusQueries[0];
                }
            }
        }

        // Add collection filter - format as gid if numeric
        // Note: Collection filtering is handled separately via fetchProductsFromCollection
        // so we don't add it to the query string here

        $finalQuery = implode(' AND ', $queries);
        
        // If no query built, return empty to get all products
        return $finalQuery ?: '';
    }

    /**
     * Fetch all products from Shopify using GraphQL
     */
    private function fetchProductsFromShopify($shopDomain, $accessToken, $statusQuery)
    {
        $client = new Client();
        $apiVersion = config('shopify.api_version', '2024-10');
        $graphqlEndpoint = "https://{$shopDomain}/admin/api/{$apiVersion}/graphql.json";

        $allProducts = [];
        $hasNextPage = true;
        $cursor = null;
        $first = 250; // Maximum allowed by Shopify

        while ($hasNextPage) {
            $graphqlQuery = $this->buildProductsQuery($first, $cursor, $statusQuery);

            Log::info('Shopify GraphQL Query', ['query' => $graphqlQuery]);

            try {
                $response = $client->post($graphqlEndpoint, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-Shopify-Access-Token' => $accessToken,
                    ],
                    'json' => [
                        'query' => $graphqlQuery
                    ]
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                if (isset($data['errors'])) {
                    Log::error('Shopify GraphQL errors:', $data['errors']);
                    throw new \Exception('Shopify API returned errors');
                }

                $edges = $data['data']['products']['edges'] ?? [];
                $pageInfo = $data['data']['products']['pageInfo'] ?? [];

                foreach ($edges as $edge) {
                    $product = $edge['node'];
                    $allProducts[] = $this->formatProduct($product);
                }

                $hasNextPage = $pageInfo['hasNextPage'] ?? false;
                $cursor = $hasNextPage && !empty($edges) ? end($edges)['cursor'] : null;

            } catch (RequestException $e) {
                Log::error('Shopify API request failed:', [
                    'message' => $e->getMessage(),
                    'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
                ]);
                throw new \Exception('Failed to fetch products from Shopify');
            }
        }

        return $allProducts;
    }

    /**
     * Fetch products from a specific collection using GraphQL
     */
    private function fetchProductsFromCollection($shopDomain, $accessToken, $collectionId, $statusQuery)
    {
        $client = new Client();
        $apiVersion = config('shopify.api_version', '2024-10');
        $graphqlEndpoint = "https://{$shopDomain}/admin/api/{$apiVersion}/graphql.json";

        // Convert numeric ID to GID format
        if (is_numeric($collectionId)) {
            $collectionId = 'gid://shopify/Collection/' . $collectionId;
        }

        // Parse status filter to get allowed statuses
        $allowedStatuses = $this->parseStatusFilter($statusQuery);

        $allProducts = [];
        $hasNextPage = true;
        $cursor = null;
        $first = 250;

        while ($hasNextPage) {
            $graphqlQuery = $this->buildCollectionProductsQuery($collectionId, $first, $cursor);

            Log::info('Shopify Collection GraphQL Query', ['query' => $graphqlQuery]);

            try {
                $response = $client->post($graphqlEndpoint, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-Shopify-Access-Token' => $accessToken,
                    ],
                    'json' => [
                        'query' => $graphqlQuery
                    ]
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                if (isset($data['errors'])) {
                    Log::error('Shopify GraphQL errors:', $data['errors']);
                    throw new \Exception('Shopify API returned errors');
                }

                $edges = $data['data']['collection']['products']['edges'] ?? [];
                $pageInfo = $data['data']['collection']['products']['pageInfo'] ?? [];

                foreach ($edges as $edge) {
                    $product = $edge['node'];
                    
                    // Filter by status if specified
                    if (!empty($allowedStatuses)) {
                        $productStatus = strtoupper($product['status']);
                        if (!in_array($productStatus, $allowedStatuses)) {
                            continue; // Skip this product
                        }
                    }
                    
                    $allProducts[] = $this->formatProduct($product);
                }

                $hasNextPage = $pageInfo['hasNextPage'] ?? false;
                $cursor = $hasNextPage && !empty($edges) ? end($edges)['cursor'] : null;

            } catch (RequestException $e) {
                Log::error('Shopify API request failed:', [
                    'message' => $e->getMessage(),
                    'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
                ]);
                throw new \Exception('Failed to fetch products from Shopify');
            }
        }

        return $allProducts;
    }

    /**
     * Parse status filter query and return allowed statuses
     */
    private function parseStatusFilter($statusQuery)
    {
        if (empty($statusQuery)) {
            return [];
        }

        $statuses = [];
        
        // Extract status values from query like "status:active" or "(status:active OR status:draft)"
        if (preg_match_all('/status:(active|draft|archived)/i', $statusQuery, $matches)) {
            foreach ($matches[1] as $status) {
                $statuses[] = strtoupper($status);
            }
        }

        return $statuses;
    }

    /**
     * Build GraphQL query for products in a collection
     */
    private function buildCollectionProductsQuery($collectionId, $first, $cursor)
    {
        $afterClause = $cursor ? ', after: "' . $cursor . '"' : '';

        return '{
            collection(id: "' . $collectionId . '") {
                products(first: ' . $first . $afterClause . ') {
                    edges {
                        cursor
                        node {
                            id
                            title
                            handle
                            description
                            descriptionHtml
                            status
                            createdAt
                            updatedAt
                            publishedAt
                            vendor
                            productType
                            tags
                            onlineStoreUrl
                            totalInventory
                            featuredImage {
                                id
                                url
                                altText
                                width
                                height
                            }
                            images(first: 10) {
                                edges {
                                    node {
                                        id
                                        url
                                        altText
                                        width
                                        height
                                    }
                                }
                            }
                            variants(first: 100) {
                                edges {
                                    node {
                                        id
                                        title
                                        sku
                                        price
                                        compareAtPrice
                                        position
                                        inventoryQuantity
                                        availableForSale
                                        barcode
                                        image {
                                            id
                                            url
                                            altText
                                        }
                                        selectedOptions {
                                            name
                                            value
                                        }
                                    }
                                }
                            }
                            options {
                                id
                                name
                                position
                                values
                            }
                        }
                    }
                    pageInfo {
                        hasNextPage
                        hasPreviousPage
                    }
                }
            }
        }';
    }

    /**
     * Build GraphQL query for products
     */
    private function buildProductsQuery($first, $cursor, $statusQuery)
    {
        $afterClause = $cursor ? ', after: "' . $cursor . '"' : '';
        $queryClause = $statusQuery ? ', query: "' . $statusQuery . '"' : '';

        return '{
            products(first: ' . $first . $afterClause . $queryClause . ') {
                edges {
                    cursor
                    node {
                        id
                        title
                        handle
                        description
                        descriptionHtml
                        status
                        createdAt
                        updatedAt
                        publishedAt
                        vendor
                        productType
                        tags
                        onlineStoreUrl
                        totalInventory
                        featuredImage {
                            id
                            url
                            altText
                            width
                            height
                        }
                        images(first: 10) {
                            edges {
                                node {
                                    id
                                    url
                                    altText
                                    width
                                    height
                                }
                            }
                        }
                        variants(first: 100) {
                            edges {
                                node {
                                    id
                                    title
                                    sku
                                    price
                                    compareAtPrice
                                    position
                                    inventoryQuantity
                                    availableForSale
                                    barcode
                                    image {
                                        id
                                        url
                                        altText
                                    }
                                    selectedOptions {
                                        name
                                        value
                                    }
                                }
                            }
                        }
                        options {
                            id
                            name
                            position
                            values
                        }
                    }
                }
                pageInfo {
                    hasNextPage
                    hasPreviousPage
                }
            }
        }';
    }

    /**
     * Format product data
     */
    private function formatProduct($product)
    {
        return [
            'id' => $product['id'],
            'title' => $product['title'],
            'handle' => $product['handle'] ?? null,
            'description' => $product['description'] ?? null,
            'description_html' => $product['descriptionHtml'] ?? null,
            'status' => $product['status'],
            'created_at' => $product['createdAt'],
            'updated_at' => $product['updatedAt'],
            'published_at' => $product['publishedAt'] ?? null,
            'vendor' => $product['vendor'] ?? null,
            'product_type' => $product['productType'] ?? null,
            'tags' => $product['tags'] ?? [],
            'online_store_url' => $product['onlineStoreUrl'] ?? null,
            'total_inventory' => $product['totalInventory'] ?? 0,
            'featured_image' => $product['featuredImage'] ? [
                'id' => $product['featuredImage']['id'],
                'url' => $product['featuredImage']['url'],
                'alt_text' => $product['featuredImage']['altText'] ?? null,
                'width' => $product['featuredImage']['width'] ?? null,
                'height' => $product['featuredImage']['height'] ?? null,
            ] : null,
            'images' => array_map(function($edge) {
                return [
                    'id' => $edge['node']['id'],
                    'url' => $edge['node']['url'],
                    'alt_text' => $edge['node']['altText'] ?? null,
                    'width' => $edge['node']['width'] ?? null,
                    'height' => $edge['node']['height'] ?? null,
                ];
            }, $product['images']['edges'] ?? []),
            'variants' => array_map(function($edge) {
                return [
                    'id' => $edge['node']['id'],
                    'title' => $edge['node']['title'],
                    'sku' => $edge['node']['sku'] ?? null,
                    'price' => $edge['node']['price'],
                    'compare_at_price' => $edge['node']['compareAtPrice'] ?? null,
                    'position' => $edge['node']['position'],
                    'inventory_quantity' => $edge['node']['inventoryQuantity'],
                    'available_for_sale' => $edge['node']['availableForSale'],
                    'barcode' => $edge['node']['barcode'] ?? null,
                    'image' => $edge['node']['image'] ? [
                        'id' => $edge['node']['image']['id'],
                        'url' => $edge['node']['image']['url'],
                        'alt_text' => $edge['node']['image']['altText'] ?? null,
                    ] : null,
                    'selected_options' => $edge['node']['selectedOptions'] ?? [],
                ];
            }, $product['variants']['edges'] ?? []),
            'options' => array_map(function($option) {
                return [
                    'id' => $option['id'],
                    'name' => $option['name'],
                    'position' => $option['position'],
                    'values' => $option['values'] ?? [],
                ];
            }, $product['options'] ?? []),
        ];
    }
}
