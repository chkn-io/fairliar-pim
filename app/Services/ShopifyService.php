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
                        shippingAddress {
                            name
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
                'shipping_address' => [
                    'name' => $order['shippingAddress']['name'] ?? '',
                    'address1' => $order['shippingAddress']['address1'] ?? '',
                    'city' => $order['shippingAddress']['city'] ?? '',
                    'province' => $order['shippingAddress']['province'] ?? '',
                    'country' => $order['shippingAddress']['country'] ?? '',
                    'zip' => $order['shippingAddress']['zip'] ?? '',
                ],
                'line_items' => [],
                'fulfillment_orders' => []
            ];

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
}