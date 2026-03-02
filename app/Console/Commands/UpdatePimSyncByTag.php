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
                            {--confirm : Skip confirmation prompt}
                            {--max-retries=3 : Maximum number of retries for failed requests}
                            {--retry-delay=2 : Delay in seconds between retries}';

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
        $maxRetries = (int) $this->option('max-retries');
        $retryDelay = (int) $this->option('retry-delay');
        
        foreach ($allVariants as $index => $variant) {
            $num = $index + 1;
            $total = count($allVariants);
            
            try {
                $sku = $variant['sku'] ?: 'NO-SKU';
                $productTitle = $variant['product_title'];
                $variantTitle = $variant['variant_title'];
                
                // Display progress
                $timestamp = date('g:i:s A');
                $this->line("→[{$timestamp}] [{$num}/{$total}] Processing: {$sku} - {$productTitle} ({$variantTitle})");
                
                // Try updating with retries
                $result = $this->updateVariantWithRetry(
                    $variant['variant_gid'],
                    $metafieldValue,
                    $maxRetries,
                    $retryDelay,
                    $num
                );
                
                $timestamp = date('g:i:s A');
                if ($result['success']) {
                    $successCount++;
                    $this->info("✓[{$timestamp}] ✅ [{$num}] Success: {$sku}");
                } else {
                    $failedCount++;
                    $failedVariants[] = [
                        'sku' => $sku,
                        'product' => $productTitle,
                        'variant' => $variantTitle,
                        'reason' => $result['error']
                    ];
                    $this->error("✗[{$timestamp}] ❌ [{$num}] Failed after {$result['attempts']} attempts: {$sku}");
                    $this->error("✗[{$timestamp}] ❌ {$result['error']}");
                    $this->warn("⏭️  Skipping to next variant...");
                }
                
            } catch (\Throwable $e) {
                // Catch ANY error (including fatal errors) to ensure we continue
                $timestamp = date('g:i:s A');
                $sku = $variant['sku'] ?? 'UNKNOWN-SKU';
                $failedCount++;
                $failedVariants[] = [
                    'sku' => $sku,
                    'product' => $variant['product_title'] ?? 'Unknown',
                    'variant' => $variant['variant_title'] ?? 'Unknown',
                    'reason' => 'Critical error: ' . $e->getMessage()
                ];
                $this->error("✗[{$timestamp}] ❌ [{$num}] Critical error for {$sku}: " . $e->getMessage());
                $this->warn("⏭️  Skipping to next variant...");
                
                // Log the full exception for debugging
                Log::error("Critical error in UpdatePimSyncByTag", [
                    'variant_gid' => $variant['variant_gid'] ?? null,
                    'sku' => $sku,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Small delay to avoid rate limiting
            usleep(50000); // 50ms delay
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

    /**
     * Update variant metafield with retry logic
     *
     * @param string $variantGid
     * @param string $metafieldValue
     * @param int $maxRetries
     * @param int $retryDelay
     * @param int $itemNumber
     * @return array ['success' => bool, 'error' => string|null, 'attempts' => int]
     */
    protected function updateVariantWithRetry($variantGid, $metafieldValue, $maxRetries, $retryDelay, $itemNumber)
    {
        $attempts = 0;
        $lastError = null;
        
        while ($attempts <= $maxRetries) {
            $attempts++;
            
            try {
                $success = $this->shopifyService->updateVariantMetafield(
                    $variantGid,
                    'custom',
                    'pim_sync',
                    $metafieldValue
                );
                
                if ($success) {
                    return [
                        'success' => true,
                        'error' => null,
                        'attempts' => $attempts
                    ];
                }
                
                $lastError = 'API returned false';
                
            } catch (\GuzzleHttp\Exception\ConnectException $e) {
                // Connection error - network issue
                $lastError = 'Connection error: ' . $e->getMessage();
                
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                // HTTP error (includes 429 rate limiting, 5xx errors, etc.)
                if ($e->hasResponse()) {
                    $statusCode = $e->getResponse()->getStatusCode();
                    if ($statusCode == 429) {
                        $lastError = 'Rate limit exceeded';
                        // For rate limiting, wait longer
                        sleep($retryDelay * 2);
                    } else {
                        $lastError = "HTTP {$statusCode}: " . $e->getMessage();
                    }
                } else {
                    $lastError = 'Request error or stream ended unexpectedly';
                }
                
            } catch (\GuzzleHttp\Exception\TransferException $e) {
                // Stream errors, transfer issues
                $lastError = 'Transfer error or stream ended unexpectedly';
                
            } catch (\Throwable $e) {
                // Any other exception (catch Throwable instead of Exception to catch fatal errors)
                $lastError = 'Unknown error: ' . $e->getMessage();
            }
            
            // If we haven't succeeded and have retries left, wait and try again
            if ($attempts <= $maxRetries) {
                $timestamp = date('g:i:s A');
                $this->warn("⚠[{$timestamp}] Attempt {$attempts} failed for item {$itemNumber}: {$lastError}");
                $this->warn("⚠[{$timestamp}] Retrying in {$retryDelay} seconds... (" . ($maxRetries - $attempts + 1) . " attempts remaining)");
                sleep($retryDelay);
            }
        }
        
        // All retries exhausted
        return [
            'success' => false,
            'error' => $lastError ?? 'Unknown error occurred',
            'attempts' => $attempts
        ];
    }
}
