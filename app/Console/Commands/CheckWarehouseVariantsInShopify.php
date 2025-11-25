<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WarehouseService;
use App\Services\ShopifyService;
use Illuminate\Support\Facades\Log;

class CheckWarehouseVariantsInShopify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'warehouse:check-shopify {--log-only : Only log results, do not display in console}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if warehouse variants exist in Shopify and log those that do not';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $warehouseService = new WarehouseService();
        $shopifyService = new ShopifyService();
        
        $this->info('🔍 Starting warehouse variants check in Shopify...');
        
        // Initialize log file
        $logFile = storage_path('logs/warehouse-variants-not-in-shopify-' . date('Y-m-d_His') . '.log');
        file_put_contents($logFile, "Warehouse Variants Not Found in Shopify\n");
        file_put_contents($logFile, "Generated: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        file_put_contents($logFile, str_repeat('=', 100) . "\n\n", FILE_APPEND);
        file_put_contents($logFile, sprintf("%-20s | %-15s | %-30s | %-15s | %s\n", "Shopify Variant ID", "SKU", "Variant Name", "Barcode1", "Stock"), FILE_APPEND);
        file_put_contents($logFile, str_repeat('-', 100) . "\n", FILE_APPEND);
        
        // First, get all variants from Shopify to build a lookup map
        $this->info('📦 Fetching all variants from Shopify...');
        $shopifyVariants = [];
        $shopifyResponse = $shopifyService->getProductVariants(true); // Fetch all
        
        if (isset($shopifyResponse['variants'])) {
            foreach ($shopifyResponse['variants'] as $variant) {
                // Use variant_id (numeric ID) as the key
                $shopifyVariants[$variant['variant_id']] = true;
            }
            $this->info("✅ Found " . count($shopifyVariants) . " variants in Shopify");
        } else {
            $this->error('❌ Failed to fetch Shopify variants');
            return 1;
        }
        
        // Now fetch all warehouse variants
        $this->info('📦 Fetching all variants from warehouse API...');
        $firstPage = $warehouseService->getProductVariants(1);
        
        if (isset($firstPage['errors'])) {
            $this->error('❌ Failed to fetch data from warehouse API');
            return 1;
        }
        
        $totalPages = $firstPage['meta']['last_page'] ?? 1;
        $totalVariants = $firstPage['meta']['total'] ?? 0;
        
        $this->info("Total pages: {$totalPages}");
        $this->info("Total variants: {$totalVariants}");
        $this->newLine();
        
        // Progress bar
        $bar = $this->output->createProgressBar($totalPages);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | Not Found: %message%');
        $bar->setMessage('0');
        $bar->start();
        
        $notFoundCount = 0;
        $checkedCount = 0;
        $skippedCount = 0;
        $page = 1;
        
        do {
            $response = $warehouseService->getProductVariants($page);
            
            if (!isset($response['data']) || !is_array($response['data'])) {
                $page++;
                $bar->advance();
                continue;
            }
            
            foreach ($response['data'] as $variant) {
                // Extract Shopify variant ID, SKU from option_has_code_by_shop
                $shopifyVariantId = null;
                $sku = null;
                $hasShopId18 = false;
                $hasShopId28 = false;
                
                if (isset($variant['option_has_code_by_shop'])) {
                    foreach ($variant['option_has_code_by_shop'] as $shopCode) {
                        // shop_id 28 is Shopify
                        if ($shopCode['shop_id'] == '28') {
                            $shopifyVariantId = $shopCode['option_code'];
                            $hasShopId28 = true;
                        }
                        // shop_id 18 is SKU
                        if ($shopCode['shop_id'] == '18') {
                            $sku = $shopCode['option_code'];
                            $hasShopId18 = true;
                        }
                    }
                }
                
                // Skip if doesn't have both shop_id 18 and 28
                if (!$hasShopId18 || !$hasShopId28 || !$shopifyVariantId) {
                    $skippedCount++;
                    continue;
                }
                
                $checkedCount++;
                
                // Check if exists in Shopify
                if (!isset($shopifyVariants[$shopifyVariantId])) {
                    $notFoundCount++;
                    
                    // Log to file
                    $logEntry = sprintf(
                        "%-20s | %-15s | %-30s | %-15s | %s\n",
                        $shopifyVariantId,
                        $sku ?? 'N/A',
                        substr($variant['variant_name'] ?? 'N/A', 0, 30),
                        $variant['barcode1'] ?? 'N/A',
                        $variant['stock'] ?? '0'
                    );
                    file_put_contents($logFile, $logEntry, FILE_APPEND);
                    
                    // Display in console if not log-only mode
                    if (!$this->option('log-only')) {
                        $this->newLine();
                        $this->warn("❌ Not found in Shopify:");
                        $this->line("   Shopify Variant ID: {$shopifyVariantId}");
                        $this->line("   SKU: " . ($sku ?? 'N/A'));
                        $this->line("   Variant: " . ($variant['variant_name'] ?? 'N/A'));
                        $this->line("   Barcode1: " . ($variant['barcode1'] ?? 'N/A'));
                        $this->line("   Stock: " . ($variant['stock'] ?? '0'));
                    }
                    
                    $bar->setMessage((string)$notFoundCount);
                }
            }
            
            $bar->advance();
            $page++;
            
            // Safety limit
            if ($page > 1100) {
                $this->warn("\nReached safety limit of 1100 pages");
                break;
            }
            
        } while ($page <= $totalPages);
        
        $bar->finish();
        $this->newLine(2);
        
        // Summary
        $this->info("✅ Check complete!");
        $this->info("📊 Total checked: {$checkedCount}");
        $this->info("⏭️  Skipped: {$skippedCount} (missing shop_id 18 or 28)");
        
        if ($notFoundCount > 0) {
            $this->warn("❌ Not found in Shopify: {$notFoundCount}");
        } else {
            $this->info("✅ All variants exist in Shopify!");
        }
        
        $this->newLine();
        $this->info("📝 Results logged to:");
        $this->line("   {$logFile}");
        
        return 0;
    }
}
