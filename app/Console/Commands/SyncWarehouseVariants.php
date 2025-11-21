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
    protected $signature = 'warehouse:sync {--fresh : Truncate table before syncing}';

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
                        continue;
                    }
                    
                    // Update or create the variant
                    WarehouseVariant::updateOrCreate(
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
                    
                    $synced++;
                } catch (\Exception $e) {
                    $failed++;
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
        
        $this->info("✅ Sync complete!");
        $this->info("📊 Synced: {$synced}");
        $this->info("⏭️  Skipped: {$skipped} (no Shopify variant ID)");
        if ($failed > 0) {
            $this->warn("❌ Failed: {$failed}");
        }
        
        return 0;
    }
}
