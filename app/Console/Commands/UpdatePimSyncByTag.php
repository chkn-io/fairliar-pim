<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopifyService;
use App\Models\Setting;
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
                            {--location= : Shopify location ID to use when zeroing excluded stock}
                            {--sku= : Update only one variant by exact SKU}
                            {--variant-gid= : Update only one variant by exact Shopify variant GID}
                            {--dry-run : Preview updates without changing metafields or stock}
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
        $isDryRun = (bool) $this->option('dry-run');
        $singleSku = trim((string) $this->option('sku'));
        $singleVariantGid = trim((string) $this->option('variant-gid'));
        
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

        $shouldZeroStock = $status === 'exclude';
        $fallbackLocationId = null;

        if ($shouldZeroStock) {
            // --location flag takes priority; otherwise fall back to the default_location_id setting
            $configuredLocation = $this->option('location') ?: Setting::get('default_location_id');
            $fallbackLocationId = $this->resolveSingleLocationId($configuredLocation);

            if (!$fallbackLocationId) {
                $this->error('Error: Could not resolve a valid Shopify location.');
                $this->info('Set the default location in Settings > Warehouse, or pass --location=<id>.');
                return 1;
            }
        }
        
        $this->info("╔══════════════════════════════════════════════════════════════╗");
        $this->info("║          Update PIM Sync Status by Tag                      ║");
        $this->info("╚══════════════════════════════════════════════════════════════╝");
        $this->newLine();

        $this->info("Tag:    <fg=cyan>" . ($inverse ? 'NOT ' : '') . "{$tag}</>");
        $this->info("Status: <fg=yellow>" . strtoupper($status) . "</>");
        $this->info("Action: Set custom.pim_sync = " . ($metafieldValue ?: '(empty)'));
        if ($isDryRun) {
            $this->warn("Mode:   DRY RUN - no changes will be written to Shopify");
        }
        if ($shouldZeroStock) {
            $this->warn("Stock:  Excluded variants will be set to 0 in Shopify");
            $locationSource = $this->option('location') ? '(from --location flag)' : '(from Settings default)';
            $this->info("Location: {$fallbackLocationId} {$locationSource}");
        }
        if ($singleSku !== '') {
            $this->info("Single variant filter (SKU): {$singleSku}");
        }
        if ($singleVariantGid !== '') {
            $this->info("Single variant filter (GID): {$singleVariantGid}");
        }
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
        $result = $this->shopifyService->getVariantsByProductTag($tag, true, $inverse, $fallbackLocationId);
        $allVariants = $result['variants'];

        if ($singleSku !== '' || $singleVariantGid !== '') {
            $allVariants = array_values(array_filter($allVariants, function ($variant) use ($singleSku, $singleVariantGid) {
                $matchesSku = $singleSku === '' || strcasecmp((string)($variant['sku'] ?? ''), $singleSku) === 0;
                $matchesGid = $singleVariantGid === '' || (string)($variant['variant_gid'] ?? '') === $singleVariantGid;
                return $matchesSku && $matchesGid;
            }));
        }
        
        // Debug info
        $this->comment("GraphQL query returned " . count($allVariants) . " variants after filtering");
        
        if (count($allVariants) === 0) {
            $this->comment("Tip: Check your Laravel logs (storage/logs/laravel.log) for details");
            $this->comment("     The logs will show if products were fetched but filtered out");
            if (!empty($result['errors'])) {
                $firstError = $result['errors'][0]['message'] ?? 'Unknown Shopify error';
                $this->error("Shopify fetch error: {$firstError}");
            }
        }
        
        if (empty($allVariants)) {
            $scope = $inverse ? " without tag '{$tag}'" : " with tag '{$tag}'";
            if ($singleSku !== '' || $singleVariantGid !== '') {
                $scope .= ' matching single-variant filter';
            }
            $this->warn("No variants found{$scope}");
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

                if ($isDryRun) {
                    if ($shouldZeroStock) {
                        $totalStock   = $variant['total_inventory'] ?? 0;
                        $dryRunAction = "Would set custom.pim_sync=false and stock=0 | current total stock (all locations): {$totalStock}";
                    } else {
                        $dryRunAction = 'Would set custom.pim_sync=' . ($metafieldValue ?: '(empty)');
                    }

                    $successCount++;
                    $this->info("~[{$timestamp}] 🧪 [{$num}] {$dryRunAction}: {$sku}");
                    continue;
                }
                
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
                    if ($shouldZeroStock) {
                        $zeroResult = $this->zeroVariantStock(
                            $variant,
                            $fallbackLocationId
                        );

                        if (!$zeroResult['success']) {
                            $failedCount++;
                            $failedVariants[] = [
                                'sku' => $sku,
                                'product' => $productTitle,
                                'variant' => $variantTitle,
                                'reason' => $zeroResult['error']
                            ];
                            $this->error("✗[{$timestamp}] ❌ [{$num}] Excluded but failed to set stock=0: {$sku}");
                            $this->error("✗[{$timestamp}] ❌ {$zeroResult['error']}");
                            $this->warn("⏭️  Skipping to next variant...");
                            continue;
                        }

                        $successCount++;
                        $this->info("✓[{$timestamp}] ✅ [{$num}] Success: {$sku} (stock set to 0 at {$zeroResult['locations_updated']} location(s))");
                    } else {
                        $successCount++;
                        $this->info("✓[{$timestamp}] ✅ [{$num}] Success: {$sku}");
                    }
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

    /**
     * Set Shopify stock to 0 for an excluded variant.
     *
     * Attempts all known variant inventory locations; if none are present,
     * falls back to provided default location.
     *
     * @param array $variant
     * @param string|null $fallbackLocationId
     * @return array ['success' => bool, 'error' => string|null, 'locations_updated' => int]
     */
    protected function zeroVariantStock(array $variant, ?string $fallbackLocationId = null): array
    {
        $inventoryItemId = $variant['inventory_item_id'] ?? null;

        if (!$inventoryItemId) {
            return [
                'success' => false,
                'error' => 'Missing inventory item ID; cannot set stock to 0',
                'locations_updated' => 0,
            ];
        }

        $locationIds = [];
        if (!empty($fallbackLocationId)) {
            $locationIds[] = $fallbackLocationId;
        }

        $locationIds = array_values(array_unique(array_filter($locationIds)));

        if (empty($locationIds)) {
            return [
                'success' => false,
                'error' => 'No fallback location configured. Set default location in Settings or pass --location',
                'locations_updated' => 0,
            ];
        }

        $updatedCount = 0;
        foreach ($locationIds as $locationId) {
            $success = $this->shopifyService->updateInventoryLevel(
                $inventoryItemId,
                $locationId,
                0
            );

            if (!$success) {
                return [
                    'success' => false,
                    'error' => "Failed to set stock=0 at location {$locationId}",
                    'locations_updated' => $updatedCount,
                ];
            }

            $updatedCount++;
        }

        return [
            'success' => true,
            'error' => null,
            'locations_updated' => $updatedCount,
        ];
    }

    /**
     * Resolve a location input (numeric ID or GID) to a valid Shopify Location GID.
     */
    protected function resolveSingleLocationId($configuredLocation): ?string
    {
        $raw = trim((string) $configuredLocation);
        if ($raw === '') {
            return null;
        }

        $candidate = str_starts_with($raw, 'gid://shopify/Location/')
            ? $raw
            : 'gid://shopify/Location/' . preg_replace('/\D+/', '', $raw);

        if ($candidate === 'gid://shopify/Location/') {
            return null;
        }

        $locations = $this->shopifyService->getLocations();
        if (empty($locations)) {
            return $candidate;
        }

        foreach ($locations as $location) {
            $id = (string) ($location['id'] ?? '');
            if ($id === $candidate || preg_replace('/^gid:\/\/shopify\/Location\//', '', $id) === preg_replace('/^gid:\/\/shopify\/Location\//', '', $candidate)) {
                return $id;
            }
        }

        return null;
    }
}
