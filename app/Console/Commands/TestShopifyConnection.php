<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopifyService;

class TestShopifyConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:test {--limit=5 : Number of orders to fetch for testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the Shopify API connection and fetch sample orders';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Shopify API connection...');
        
        // Check configuration
        $apiKey = config('shopify.api_key');
        $storeDomain = config('shopify.store_domain');
        
        if (!$apiKey) {
            $this->error('SHOPIFY_API_KEY is not configured in .env file');
            return 1;
        }
        
        if (!$storeDomain) {
            $this->error('SHOPIFY_STORE_DOMAIN is not configured in .env file');
            return 1;
        }
        
        $this->info("Store Domain: {$storeDomain}");
        $this->info("API Key: " . substr($apiKey, 0, 12) . '...');
        
        // Test API connection
        $shopifyService = new ShopifyService();
        $limit = $this->option('limit');
        
        $this->info("Fetching {$limit} orders...");
        
        $response = $shopifyService->getOrders($limit);
        
        if (!empty($response['errors'])) {
            $this->error('API Errors:');
            foreach ($response['errors'] as $error) {
                $this->error('- ' . (is_array($error) ? json_encode($error) : $error));
            }
            return 1;
        }
        
        $orders = $response['orders'];
        $pageInfo = $response['pageInfo'];
        
        $this->info("Successfully fetched " . count($orders) . " orders");
        
        if (empty($orders)) {
            $this->warn('No orders found. This might be normal if your store has no unfulfilled orders.');
            return 0;
        }
        
        // Display sample orders
        $this->info("\nSample Orders:");
        $this->table(
            ['Order', 'Date', 'Customer', 'Items', 'Total'],
            collect($orders)->take(3)->map(function ($order) {
                return [
                    $order['name'],
                    date('M j, Y', strtotime($order['created_at'])),
                    $order['shipping_address']['name'] ?: 'N/A',
                    count($order['line_items']) . ' items',
                    number_format($order['total_price'], 2) . ' ' . $order['currency']
                ];
            })->toArray()
        );
        
        if (isset($pageInfo['hasNextPage']) && $pageInfo['hasNextPage']) {
            $this->info("\n✓ More orders available (pagination working)");
        }
        
        $this->info("\n✅ Shopify connection test successful!");
        $this->info("You can now visit: http://localhost:8000/orders");
        
        return 0;
    }
}
