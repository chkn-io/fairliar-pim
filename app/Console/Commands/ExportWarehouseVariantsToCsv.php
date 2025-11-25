<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WarehouseService;

class ExportWarehouseVariantsToCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'warehouse:export-csv {--output= : Custom output file path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export all warehouse variants to CSV file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $warehouseService = new WarehouseService();
        
        $this->info('📦 Starting warehouse variants export to CSV...');
        
        // Determine output file path
        $outputPath = $this->option('output');
        if (!$outputPath) {
            $outputPath = storage_path('exports/warehouse-variants-' . date('Y-m-d_His') . '.csv');
        }
        
        // Create directory if it doesn't exist
        $directory = dirname($outputPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // First, get page 1 to determine total pages
        $this->info('Fetching page 1 to determine total pages...');
        $firstPage = $warehouseService->getProductVariants(1);
        
        if (isset($firstPage['errors'])) {
            $this->error('Failed to fetch data from warehouse API');
            $this->error(json_encode($firstPage['errors']));
            return 1;
        }
        
        $totalPages = $firstPage['meta']['last_page'] ?? 1;
        $totalVariants = $firstPage['meta']['total'] ?? 0;
        
        $this->info("Total pages: {$totalPages}");
        $this->info("Total variants: {$totalVariants}");
        $this->newLine();
        
        // Open CSV file for writing
        $file = fopen($outputPath, 'w');
        
        // Write CSV header
        fputcsv($file, [
            'Warehouse ID',
            'Variant Name',
            'Stock',
            'Barcode1',
            'Cost Price',
            'Selling Price',
            'Shopify Variant ID',
            'SKU',
            'KSU (Shopify Product)',
            'Created At',
            'Updated At'
        ]);
        
        // Progress bar
        $bar = $this->output->createProgressBar($totalPages);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | Exported: %message%');
        $bar->setMessage('0');
        $bar->start();
        
        $exportedCount = 0;
        $page = 1;
        
        do {
            $response = $warehouseService->getProductVariants($page);
            
            if (!isset($response['data']) || !is_array($response['data'])) {
                $this->newLine();
                $this->error("Failed to fetch page {$page}");
                $page++;
                $bar->advance();
                continue;
            }
            
            foreach ($response['data'] as $variant) {
                // Extract Shopify variant ID, SKU, and KSU from option_has_code_by_shop
                $shopifyVariantId = '';
                $sku = '';
                $ksu = '';
                
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
                        // shop_id 19 is KSU (shopify_product_ksu)
                        if ($shopCode['shop_id'] == '19') {
                            $ksu = $shopCode['option_code'];
                        }
                    }
                }
                
                // Write row to CSV
                fputcsv($file, [
                    $variant['id'] ?? '',
                    $variant['variant_name'] ?? '',
                    $variant['stock'] ?? '0',
                    $variant['barcode1'] ?? '',
                    $variant['cost_price'] ?? '',
                    $variant['selling_price'] ?? '',
                    $shopifyVariantId,
                    $sku,
                    $ksu,
                    $variant['created_at'] ?? '',
                    $variant['updated_at'] ?? ''
                ]);
                
                $exportedCount++;
            }
            
            $bar->setMessage((string)$exportedCount);
            $bar->advance();
            $page++;
            
            // Safety limit
            if ($page > 1100) {
                $this->warn("\nReached safety limit of 1100 pages");
                break;
            }
            
        } while ($page <= $totalPages);
        
        fclose($file);
        
        $bar->finish();
        $this->newLine(2);
        
        // Summary
        $this->info("✅ Export complete!");
        $this->info("📊 Total exported: {$exportedCount} variants");
        $this->newLine();
        $this->info("📁 CSV file saved to:");
        $this->line("   {$outputPath}");
        
        return 0;
    }
}
