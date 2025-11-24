<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopifyService;
use App\Models\WarehouseVariant;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class SyncShopifyStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:sync-stock {--location= : Shopify location ID} {--dry-run : Preview changes without syncing}';

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
        $isDryRun = $this->option('dry-run');
        
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
        
        foreach ($allVariants as $variant) {
            try {
                // Variant data structure: variant_id, variant_gid, sku, inventory_item_id, total_inventory, etc.
                $variantId = $variant['variant_id'] ?? null;
                $sku = $variant['sku'] ?? 'N/A';
                
                if (!$variantId) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }
                
                // Find matching warehouse variant using shopify_variant_id
                $warehouseVariant = WarehouseVariant::where('shopify_variant_id', $variantId)->first();
                
                if (!$warehouseVariant) {
                    // Debug: Try to find what we have in database for this SKU
                    $dbVariant = WarehouseVariant::where('sku', $sku)->first();
                    if ($dbVariant) {
                        $skippedDetails[] = "SKU {$sku}: DB has shopify_variant_id='{$dbVariant->shopify_variant_id}', Shopify has variant_id='{$variantId}'";
                    } else {
                        $skippedDetails[] = "SKU {$sku}: Not found in warehouse database (variant_id='{$variantId}')";
                    }
                    $skipped++;
                    $bar->advance();
                    continue;
                }
                
                // Get current Shopify stock
                $shopifyStock = $variant['total_inventory'] ?? 0;
                $warehouseStock = $warehouseVariant->stock;
                
                // Skip if stocks match
                if ($shopifyStock == $warehouseStock) {
                    $skippedDetails[] = "SKU {$sku}: Stock already matches (Shopify: {$shopifyStock}, Warehouse: {$warehouseStock})";
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
                    'difference' => $warehouseStock - $shopifyStock
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
                    $warehouseStock
                );
                
                if ($success) {
                    $synced++;
                    $bar->setMessage((string)$synced);
                } else {
                    $failed++;
                    $errors[] = "Failed to update {$sku} (Shopify: {$shopifyStock} -> Warehouse: {$warehouseStock})";
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
                $headers = ['SKU', 'Product', 'Variant', 'Shopify', 'Warehouse', 'Diff'];
                $rows = [];
                
                foreach ($changes as $change) {
                    $rows[] = [
                        $change['sku'],
                        strlen($change['product']) > 30 ? substr($change['product'], 0, 27) . '...' : $change['product'],
                        strlen($change['variant']) > 30 ? substr($change['variant'], 0, 27) . '...' : $change['variant'],
                        $change['shopify_stock'],
                        $change['warehouse_stock'],
                        ($change['difference'] > 0 ? '+' : '') . $change['difference']
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
        
        return 0;
    }
}
