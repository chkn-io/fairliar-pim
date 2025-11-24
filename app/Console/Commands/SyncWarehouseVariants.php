<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WarehouseService;
use App\Models\WarehouseVariant;
use Illuminate\Support\Facades\DB;

class SyncWarehouseVariants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'warehouse:sync {--fresh : Truncate table before syncing} {--log-skipped : Log skipped variants to file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all warehouse variants from Sellmate API to local database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $warehouseService = new WarehouseService();
        
        $this->info('Starting warehouse variants sync...');
        
        // Truncate table if --fresh option is used
        if ($this->option('fresh')) {
            $this->warn('Truncating warehouse_variants table...');
            DB::table('warehouse_variants')->truncate();
        }
        
        // Initialize log file for skipped variants if --log-skipped option is used
        $logSkipped = $this->option('log-skipped');
        $skippedLogFile = storage_path('logs/warehouse-sync-skipped-' . date('Y-m-d_His') . '.log');
        $duplicatesLogFile = storage_path('logs/warehouse-sync-duplicates-' . date('Y-m-d_His') . '.log');
        
        if ($logSkipped) {
            file_put_contents($skippedLogFile, "Warehouse Sync - Skipped Variants Log\n");
            file_put_contents($skippedLogFile, "Generated: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            file_put_contents($skippedLogFile, str_repeat('=', 80) . "\n\n", FILE_APPEND);
            
            file_put_contents($duplicatesLogFile, "Warehouse Sync - Duplicate Shopify Variant IDs Log\n");
            file_put_contents($duplicatesLogFile, "Generated: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            file_put_contents($duplicatesLogFile, "Multiple warehouse records pointing to the same Shopify variant\n", FILE_APPEND);
            file_put_contents($duplicatesLogFile, str_repeat('=', 80) . "\n\n", FILE_APPEND);
        }
        
        // First, get page 1 to determine total pages
        $this->info('Fetching page 1 to determine total pages...');
        $firstPage = $warehouseService->getProductVariants(1);
        
        if (isset($firstPage['errors'])) {
            $this->error('Failed to fetch data from Sellmate API');
            $this->error(json_encode($firstPage['errors']));
            return 1;
        }
        
        $totalPages = $firstPage['meta']['last_page'] ?? 1;
        $totalVariants = $firstPage['meta']['total'] ?? 0;
        
        $this->info("Total pages: {$totalPages}");
        $this->info("Total variants: {$totalVariants}");
        $this->newLine();
        
        // Progress bar for pages
        $bar = $this->output->createProgressBar($totalPages);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | Page %current% | Synced: %message%');
        $bar->setMessage('0');
        $bar->start();
        
        $synced = 0;
        $skipped = 0;
        $failed = 0;
        $page = 1;
        $syncedShopifyVariantIds = []; // Track by Shopify variant ID instead
        $duplicates = 0;
        
        // Fetch and save page by page
        do {
            // Fetch page
            $response = $warehouseService->getProductVariants($page);
            
            if (!isset($response['data']) || !is_array($response['data'])) {
                $this->error("\nFailed to fetch page {$page}");
                $failed += 10; // Assume 10 variants per page
                $page++;
                $bar->advance();
                continue;
            }
            
            // Process each variant in the page
            foreach ($response['data'] as $variant) {
                try {
                    // Extract Shopify variant ID and SKU from option_has_code_by_shop
                    $shopifyVariantId = null;
                    $sku = null;
                    
                    if (isset($variant['option_has_code_by_shop'])) {
                        foreach ($variant['option_has_code_by_shop'] as $shopCode) {
                            // shop_id 28 is Shopify
                            if ($shopCode['shop_id'] == '28') {
                                $shopifyVariantId = $shopCode['option_code'];
                            }
                            // shop_id 18 is SKU
                            if ($shopCode['shop_id'] == '18') {
                                $sku = $shopCode['option_code'];
                            }
                        }
                    }
                    
                    // Skip if no Shopify variant ID
                    if (!$shopifyVariantId) {
                        $skipped++;
                        
                        // Log skipped variant details if flag is set
                        if ($logSkipped) {
                            $logEntry = sprintf(
                                "Warehouse ID: %s | Variant: %s | SKU: %s | Stock: %s | Reason: No Shopify variant ID\n",
                                $variant['id'] ?? 'N/A',
                                $variant['variant_name'] ?? 'N/A',
                                $sku ?? 'N/A',
                                $variant['stock'] ?? '0'
                            );
                            file_put_contents($skippedLogFile, $logEntry, FILE_APPEND);
                        }
                        
                        continue;
                    }
                    
                    // Update or create the variant
                    $warehouseVariant = WarehouseVariant::updateOrCreate(
                        ['warehouse_id' => $variant['id']],
                        [
                            'shopify_variant_id' => $shopifyVariantId,
                            'variant_name' => $variant['variant_name'] ?? null,
                            'stock' => (int)($variant['stock'] ?? 0),
                            'sku' => $sku,
                            'cost_price' => $variant['cost_price'] ?? null,
                            'selling_price' => $variant['selling_price'] ?? null,
                            'synced_at' => now(),
                        ]
                    );
                    
                    // Track by Shopify variant ID (the important one for syncing)
                    if (!isset($syncedShopifyVariantIds[$shopifyVariantId])) {
                        $syncedShopifyVariantIds[$shopifyVariantId] = [
                            'warehouse_id' => $variant['id'],
                            'variant_name' => $variant['variant_name'] ?? 'N/A',
                            'sku' => $sku ?? 'N/A',
                            'stock' => $variant['stock'] ?? 0
                        ];
                        $synced++;
                    } else {
                        // Same Shopify variant ID appears in multiple warehouse variants
                        $duplicates++;
                        if ($logSkipped) {
                            $firstRecord = $syncedShopifyVariantIds[$shopifyVariantId];
                            $logEntry = sprintf(
                                "Shopify Variant ID: %s\n" .
                                "  First Record  -> Warehouse ID: %s | Variant: %s | SKU: %s | Stock: %s\n" .
                                "  Duplicate     -> Warehouse ID: %s | Variant: %s | SKU: %s | Stock: %s\n\n",
                                $shopifyVariantId,
                                $firstRecord['warehouse_id'],
                                $firstRecord['variant_name'],
                                $firstRecord['sku'],
                                $firstRecord['stock'],
                                $variant['id'] ?? 'N/A',
                                $variant['variant_name'] ?? 'N/A',
                                $sku ?? 'N/A',
                                $variant['stock'] ?? '0'
                            );
                            file_put_contents($duplicatesLogFile, $logEntry, FILE_APPEND);
                        }
                    }
                } catch (\Exception $e) {
                    $failed++;
                    
                    // Log failed variant
                    if ($logSkipped) {
                        $logEntry = sprintf(
                            "Warehouse ID: %s | Variant: %s | SKU: %s | Stock: %s | Reason: Exception - %s\n",
                            $variant['id'] ?? 'N/A',
                            $variant['variant_name'] ?? 'N/A',
                            $sku ?? 'N/A',
                            $variant['stock'] ?? '0',
                            $e->getMessage()
                        );
                        file_put_contents($skippedLogFile, $logEntry, FILE_APPEND);
                    }
                    
                    $this->error("\nFailed to sync variant ID {$variant['id']}: " . $e->getMessage());
                }
            }
            
            $bar->setMessage((string)$synced);
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
        
        // Remove variants that no longer exist in the warehouse
        $this->info('Cleaning up stale records...');
        $deleted = 0;
        
        if (!empty($syncedShopifyVariantIds)) {
            // Get warehouse IDs that were synced (warehouse_id from first record of each Shopify variant)
            $syncedWarehouseIds = array_column($syncedShopifyVariantIds, 'warehouse_id');
            $deleted = WarehouseVariant::whereNotIn('warehouse_id', $syncedWarehouseIds)->delete();
        }
        
        $this->info("✅ Sync complete!");
        $this->info("📊 Synced: {$synced} unique Shopify variants");
        $this->info("⏭️  Skipped: {$skipped} (no Shopify variant ID)");
        if ($duplicates > 0) {
            $this->warn("🔄 Duplicates: {$duplicates} (same Shopify variant ID in multiple warehouse records)");
        }
        if ($deleted > 0) {
            $this->info("🗑️  Deleted: {$deleted} (no longer in warehouse)");
        }
        if ($failed > 0) {
            $this->warn("❌ Failed: {$failed}");
        }
        
        if ($logSkipped) {
            $this->newLine();
            $this->info("📝 Skipped variants logged to:");
            $this->line("   {$skippedLogFile}");
            if ($duplicates > 0) {
                $this->info("📝 Duplicate Shopify variant IDs logged to:");
                $this->line("   {$duplicatesLogFile}");
            }
        }
        
        return 0;
    }
}
