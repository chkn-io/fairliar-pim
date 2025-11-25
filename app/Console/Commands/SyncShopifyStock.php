<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopifyService;
use App\Services\WarehouseService;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class SyncShopifyStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:sync-stock {--location= : Shopify location ID} {--dry-run : Preview changes without syncing} {--export-missing : Export SKUs not found in warehouse to CSV}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync stock to Shopify for variants with custom.pim_sync = true';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if Shopify stock sync is enabled
        $syncEnabled = Setting::get('enable_shopify_stock_sync', true);
        
        if (!$syncEnabled) {
            $this->warn('⚠️  Shopify stock sync is disabled in settings');
            $this->info('To enable it, go to Settings > Warehouse and toggle "Enable Shopify Stock Sync"');
            return 0;
        }
        
        $shopifyService = new ShopifyService();
        $warehouseService = new WarehouseService();
        $isDryRun = $this->option('dry-run');
        $exportMissing = $this->option('export-missing');
        
        // Get minimum stock threshold setting
        $minStockThreshold = (int) Setting::get('min_stock_threshold', 2);
        
        // Get location from settings or option
        $locationId = $this->option('location') ?? Setting::get('default_location_id');
        
        if (!$locationId) {
            $this->error('No location ID configured. Please set it in Settings > Warehouse or use --location option');
            return 1;
        }
        
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }
        
        $this->info('Starting Shopify stock sync...');
        $this->info("Location ID: {$locationId}");
        $this->info("Min Stock Threshold: {$minStockThreshold} (warehouse stock <= {$minStockThreshold} will set Shopify to 0)");
        $this->newLine();
        
        // Fetch all variants with pim_sync = 'true'
        $this->info('Fetching variants with pim_sync = true from Shopify...');
        $allVariants = [];
        $after = null;
        $hasNextPage = true;
        $pageCount = 0;
        
        while ($hasNextPage && $pageCount < 1000) {
            $pageCount++;
            $response = $shopifyService->getProductVariants(false, $locationId, $after);
            
            if (isset($response['errors'])) {
                $this->error('Failed to fetch variants from Shopify');
                $this->error(json_encode($response['errors']));
                return 1;
            }
            
            $variants = $response['variants'] ?? [];
            
            // Filter variants with pim_sync = 'true'
            foreach ($variants as $variant) {
                if (isset($variant['pim_sync']) && $variant['pim_sync'] === 'true') {
                    $allVariants[] = $variant;
                }
            }
            
            $hasNextPage = $response['pageInfo']['hasNextPage'] ?? false;
            $after = $response['pageInfo']['endCursor'] ?? null;
            
            $this->info("Fetched page {$pageCount}... Found " . count($allVariants) . " variants to sync");
        }
        
        $totalVariants = count($allVariants);
        
        if ($totalVariants === 0) {
            $this->warn('No variants found with pim_sync = true');
            return 0;
        }
        
        $this->info("Found {$totalVariants} variants to sync");
        $this->newLine();
        
        // Progress bar
        $bar = $this->output->createProgressBar($totalVariants);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | Success: %message%');
        $bar->setMessage('0');
        $bar->start();
        
        $synced = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        $changes = []; // Track changes for dry-run
        $skippedDetails = []; // Track why variants were skipped
        $missingInWarehouse = []; // Track variants not found in warehouse for export
        
        foreach ($allVariants as $variant) {
            try {
                $variantId = $variant['variant_id'] ?? null;
                $sku = $variant['sku'] ?? null;
                
                if (!$variantId || !$sku) {
                    $skippedDetails[] = "Variant ID {$variantId}: Missing SKU";
                    $skipped++;
                    $bar->advance();
                    continue;
                }
                
                // Fetch warehouse stock from API using SKU
                $warehouseData = $warehouseService->getStockBySku($sku);
                
                if (!$warehouseData) {
                    $productTitle = $variant['product_title'] ?? 'N/A';
                    $variantTitle = $variant['variant_title'] ?? 'N/A';
                    $skippedDetails[] = "SKU {$sku}: Not found in warehouse API | Product: {$productTitle} - {$variantTitle}";
                    
                    // Track for export if flag is set
                    if ($exportMissing) {
                        $missingInWarehouse[] = [
                            'variant_id' => $variantId,
                            'sku' => $sku,
                            'product_title' => $productTitle,
                            'variant_title' => $variantTitle,
                            'shopify_stock' => $variant['total_inventory'] ?? 0,
                            'product_url' => "https://admin.shopify.com/store/" . parse_url(config('services.shopify.shop_url'), PHP_URL_HOST) . "/products/{$variant['product_id']}"
                        ];
                    }
                    
                    $skipped++;
                    $bar->advance();
                    continue;
                }
                
                // Get current Shopify stock for the specific location
                $shopifyStock = 0;
                if (isset($variant['inventory_levels']) && is_array($variant['inventory_levels'])) {
                    foreach ($variant['inventory_levels'] as $level) {
                        if ($level['location_id'] === $locationId) {
                            $shopifyStock = $level['available'];
                            break;
                        }
                    }
                } else {
                    // Fallback to total inventory if inventory_levels not available
                    $shopifyStock = $variant['total_inventory'] ?? 0;
                }
                
                $warehouseStock = $warehouseData['stock'];
                
                // Apply minimum stock threshold logic
                // If warehouse stock is at or below threshold, set Shopify stock to 0
                $targetShopifyStock = $warehouseStock;
                if ($warehouseStock <= $minStockThreshold) {
                    $targetShopifyStock = 0;
                }
                
                // Skip if stocks match
                if ($shopifyStock == $targetShopifyStock) {
                    if ($warehouseStock <= $minStockThreshold && $targetShopifyStock == 0) {
                        $skippedDetails[] = "SKU {$sku}: Already at 0 (warehouse: {$warehouseStock} <= threshold: {$minStockThreshold})";
                    } else {
                        $skippedDetails[] = "SKU {$sku}: Stock already matches (Shopify: {$shopifyStock}, Warehouse: {$warehouseStock})";
                    }
                    $skipped++;
                    $bar->advance();
                    continue;
                }
                
                // Track change for dry-run or actual sync
                $changeInfo = [
                    'sku' => $sku,
                    'product' => $variant['product_title'] ?? 'N/A',
                    'variant' => $variant['variant_title'] ?? 'N/A',
                    'shopify_stock' => $shopifyStock,
                    'warehouse_stock' => $warehouseStock,
                    'target_stock' => $targetShopifyStock,
                    'difference' => $targetShopifyStock - $shopifyStock,
                    'reason' => $warehouseStock <= $minStockThreshold ? "Low stock (≤{$minStockThreshold})" : 'Stock update'
                ];
                
                if ($isDryRun) {
                    // Dry run - just track the change
                    $changes[] = $changeInfo;
                    $synced++;
                    $bar->setMessage((string)$synced);
                    $bar->advance();
                    continue;
                }
                
                // Update inventory level
                $inventoryItemId = $variant['inventory_item_id'] ?? null;
                
                if (!$inventoryItemId) {
                    $this->warn("\nNo inventory item ID for variant: {$sku}");
                    $skipped++;
                    $bar->advance();
                    continue;
                }
                
                $success = $shopifyService->updateInventoryLevel(
                    $inventoryItemId,
                    $locationId,
                    $targetShopifyStock
                );
                
                if ($success) {
                    // Update sync timestamp metafield
                    $variantGid = $variant['variant_gid'] ?? null;
                    if ($variantGid) {
                        $timestamp = now()->toIso8601String();
                        $shopifyService->updateVariantMetafield($variantGid, 'custom', 'pim_kr_sync_timestamp', $timestamp);
                    }
                    
                    $synced++;
                    $bar->setMessage((string)$synced);
                } else {
                    $failed++;
                    $errors[] = "Failed to update {$sku} (Shopify: {$shopifyStock} -> Target: {$targetShopifyStock})";
                }
                
            } catch (\Exception $e) {
                $failed++;
                $sku = $variant['sku'] ?? 'unknown';
                $errors[] = "Exception for {$sku}: " . $e->getMessage();
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        if ($isDryRun) {
            $this->info("🔍 DRY RUN COMPLETE - No changes were made");
            $this->newLine();
            
            if (!empty($changes)) {
                $this->info("📋 Changes that would be made:");
                $this->newLine();
                
                // Create table headers
                $headers = ['SKU', 'Product', 'Variant', 'Shopify', 'Warehouse', 'Target', 'Reason'];
                $rows = [];
                
                foreach ($changes as $change) {
                    $rows[] = [
                        $change['sku'],
                        strlen($change['product']) > 30 ? substr($change['product'], 0, 27) . '...' : $change['product'],
                        strlen($change['variant']) > 30 ? substr($change['variant'], 0, 27) . '...' : $change['variant'],
                        $change['shopify_stock'],
                        $change['warehouse_stock'],
                        $change['target_stock'],
                        $change['reason']
                    ];
                }
                
                $this->table($headers, $rows);
                $this->newLine();
            }
        }
        
        $this->info("✅ Sync complete!");
        $this->info("📊 " . ($isDryRun ? 'Would sync' : 'Synced') . ": {$synced}");
        $this->info("⏭️  Skipped: {$skipped} (not in warehouse or stock matches)");
        
        if (!empty($skippedDetails) && $isDryRun) {
            $this->newLine();
            $this->warn("Skipped variants details:");
            foreach ($skippedDetails as $detail) {
                $this->line("  - {$detail}");
            }
        }
        
        if ($failed > 0) {
            $this->warn("❌ Failed: {$failed}");
            $this->newLine();
            $this->warn("Errors:");
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
        }
        
        if ($isDryRun && $synced > 0) {
            $this->newLine();
            $this->info("To execute the sync, run without --dry-run flag:");
            $this->line("  php artisan shopify:sync-stock");
        }
        
        // Export missing variants if requested
        if ($exportMissing && !empty($missingInWarehouse)) {
            $this->newLine();
            $filename = 'missing-in-warehouse-' . date('Y-m-d_His') . '.csv';
            $filepath = storage_path('exports/' . $filename);
            
            // Ensure exports directory exists
            if (!is_dir(storage_path('exports'))) {
                mkdir(storage_path('exports'), 0755, true);
            }
            
            $fp = fopen($filepath, 'w');
            
            // Write headers
            fputcsv($fp, ['Variant ID', 'SKU', 'Product Title', 'Variant Title', 'Shopify Stock', 'Product URL']);
            
            // Write data
            foreach ($missingInWarehouse as $item) {
                fputcsv($fp, [
                    $item['variant_id'],
                    $item['sku'],
                    $item['product_title'],
                    $item['variant_title'],
                    $item['shopify_stock'],
                    $item['product_url']
                ]);
            }
            
            fclose($fp);
            
            $this->info("📄 Exported " . count($missingInWarehouse) . " missing variants to: {$filepath}");
        }
        
        return 0;
    }
}
