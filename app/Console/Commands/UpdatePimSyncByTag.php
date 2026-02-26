<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopifyService;
use Illuminate\Support\Facades\Log;

class UpdatePimSyncByTag extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pim:update-by-tag 
                            {--tag= : Product tag to filter (e.g., 26ss)}
                            {--status= : Sync status: include, exclude, or unset}
                            {--not : Invert search - update products WITHOUT the specified tag}
                            {--confirm : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update PIM sync status for variants with (or without) a specific product tag';

    protected $shopifyService;

    public function __construct(ShopifyService $shopifyService)
    {
        parent::__construct();
        $this->shopifyService = $shopifyService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tag = $this->option('tag');
        $status = $this->option('status');
        $inverse = $this->option('not');
        
        // Validate required options
        if (!$tag) {
            $this->error('Error: --tag option is required');
            $this->info('Example: php artisan pim:update-by-tag --tag=26ss --status=include');
            $this->info('         php artisan pim:update-by-tag --tag=26ss --status=exclude --not');
            return 1;
        }
        
        if (!$status || !in_array($status, ['include', 'exclude', 'unset'])) {
            $this->error('Error: --status must be one of: include, exclude, unset');
            $this->info('Example: php artisan pim:update-by-tag --tag=26ss --status=include');
            return 1;
        }
        
        // Map status to metafield value
        $metafieldValue = 'n/a';
        if ($status === 'include') {
            $metafieldValue = 'true';
        } elseif ($status === 'exclude') {
            $metafieldValue = 'false';
        }
        // unset = empty string
        
        $this->info("╔══════════════════════════════════════════════════════════════╗");
        $this->info("║          Update PIM Sync Status by Tag                      ║");
        $this->info("╚══════════════════════════════════════════════════════════════╝");
        $this->newLine();
        
        $this->info("Tag:    <fg=cyan>" . ($inverse ? 'NOT ' : '') . "{$tag}</>");
        $this->info("Status: <fg=yellow>" . strtoupper($status) . "</>");
        $this->info("Action: Set custom.pim_sync = " . ($metafieldValue ?: '(empty)'));
        if ($inverse) {
            $this->warn("Mode:   Inverse - Will update products WITHOUT tag '{$tag}'");
        }
        $this->newLine();
        
        // Confirmation
        if (!$this->option('confirm')) {
            if (!$this->confirm('Do you want to proceed?', false)) {
                $this->warn('Operation cancelled.');
                return 0;
            }
        }
        
        $this->info('Fetching variants with tag "' . ($inverse ? 'NOT ' : '') . $tag . '"...');
        $this->newLine();
        
        // Use optimized tag search (fetches products by tag directly in GraphQL)
        $result = $this->shopifyService->getVariantsByProductTag($tag, true, $inverse);
        $allVariants = $result['variants'];
        
        // Debug info
        $this->comment("GraphQL query returned " . count($allVariants) . " variants after filtering");
        
        if (count($allVariants) === 0) {
            $this->comment("Tip: Check your Laravel logs (storage/logs/laravel.log) for details");
            $this->comment("     The logs will show if products were fetched but filtered out");
        }
        
        if (empty($allVariants)) {
            $this->warn("No variants found" . ($inverse ? " without tag '{$tag}'" : " with tag '{$tag}'"));
            return 0;
        }
        
        $this->newLine();
        $this->info("Found <fg=green>" . count($allVariants) . "</> variants to update");
        $this->newLine();
        
        // Process variants
        $successCount = 0;
        $failedCount = 0;
        $failedVariants = [];
        
        foreach ($allVariants as $index => $variant) {
            $num = $index + 1;
            $total = count($allVariants);
            $sku = $variant['sku'] ?: 'NO-SKU';
            $productTitle = $variant['product_title'];
            $variantTitle = $variant['variant_title'];
            
            // Display progress
            $this->line("[{$num}/{$total}] Processing: <fg=cyan>{$sku}</> - {$productTitle} ({$variantTitle})");
            
            try {
                $success = $this->shopifyService->updateVariantMetafield(
                    $variant['variant_gid'],
                    'custom',
                    'pim_sync',
                    $metafieldValue
                );
                
                if ($success) {
                    $successCount++;
                    $this->info("         ✓ Success");
                } else {
                    $failedCount++;
                    $failedVariants[] = [
                        'sku' => $sku,
                        'product' => $productTitle,
                        'variant' => $variantTitle,
                        'reason' => 'API returned false'
                    ];
                    $this->error("         ✗ Failed");
                }
                
                // Small delay to avoid rate limiting
                usleep(50000); // 50ms delay
                
            } catch (\Exception $e) {
                $failedCount++;
                $failedVariants[] = [
                    'sku' => $sku,
                    'product' => $productTitle,
                    'variant' => $variantTitle,
                    'reason' => $e->getMessage()
                ];
                $this->error("         ✗ Error: " . $e->getMessage());
            }
        }
        
        // Summary
        $this->newLine();
        $this->info("╔══════════════════════════════════════════════════════════════╗");
        $this->info("║                    Update Complete                           ║");
        $this->info("╚══════════════════════════════════════════════════════════════╝");
        $this->newLine();
        $this->info("Total variants: <fg=cyan>" . count($allVariants) . "</>");
        $this->info("Successful:     <fg=green>{$successCount}</>");
        
        if ($failedCount > 0) {
            $this->error("Failed:         <fg=red>{$failedCount}</>");
            $this->newLine();
            
            // Show failed variants table
            $this->error("Failed Variants:");
            $headers = ['SKU', 'Product', 'Variant', 'Reason'];
            $this->table($headers, $failedVariants);
        }
        
        $this->newLine();
        
        return 0;
    }
}
