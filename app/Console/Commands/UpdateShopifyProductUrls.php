<?php

namespace App\Console\Commands;

use App\Services\ShopifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class UpdateShopifyProductUrls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:update-product-urls
                            {csv : Path to the Shopify products export CSV}
                            {--limit= : Limit number of products to process}
                            {--dry-run : Preview changes without updating Shopify}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Shopify product handles (product URLs) based on product titles from a CSV export';

    public function handle(): int
    {
        $csvPath = $this->argument('csv');
        $limit = $this->option('limit');
        $isDryRun = (bool) $this->option('dry-run');

        if (!is_string($csvPath) || !file_exists($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");
            return 1;
        }

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $csvData = $this->readProductsFromCsv($csvPath);
        $products = $csvData['products'];
        $mode = $csvData['mode'];
        $this->info('Unique products found in CSV: ' . count($products));

        if ($mode === 'handle') {
            $this->warn('Note: CSV Product ID values are not unique; using Handle to locate products in Shopify.');
        }

        if ($limit !== null && $limit !== '') {
            $limitInt = (int) $limit;
            if ($limitInt <= 0) {
                $this->error('--limit must be a positive integer');
                return 1;
            }

            $products = array_slice($products, 0, $limitInt, true);
            $this->info("Limiting to {$limitInt} products");
        }

        $shopifyService = new ShopifyService();

        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $notFound = 0;
        $errors = 0;

        foreach ($products as $key => $product) {
            $processed++;

            $title = $product['title'];
            $oldHandle = $product['handle'];
            $newHandle = Str::slug($title);

            if ($newHandle === '') {
                $label = $mode === 'id' ? (string) ($product['product_id'] ?? $key) : (string) $oldHandle;
                $this->error("{$label}: Could not generate handle from title '{$title}'");
                $errors++;
                continue;
            }

            if ($oldHandle === $newHandle) {
                $label = $mode === 'id' ? (string) ($product['product_id'] ?? $key) : (string) $oldHandle;
                $this->line("<fg=gray>{$label}: No change ({$oldHandle})</>");
                $skipped++;
                continue;
            }

            $fromUrl = "https://URL/products/{$oldHandle}";
            $toUrl = "https://URL/products/{$newHandle}";
            $label = $mode === 'id' ? (string) ($product['product_id'] ?? $key) : (string) $oldHandle;
            $this->info("{$label}: {$fromUrl} -> {$toUrl}");

            if ($isDryRun) {
                $this->line('<fg=yellow>[DRY RUN]</> Would update product handle');
                $updated++;
                continue;
            }

            $productGid = null;
            if ($mode === 'id' && !empty($product['product_id'])) {
                $productGid = 'gid://shopify/Product/' . $product['product_id'];
            } else {
                $lookup = $shopifyService->getProductByHandle($oldHandle);
                if (($lookup['success'] ?? false) !== true) {
                    $this->error("✗ Not found in Shopify by handle '{$oldHandle}'");
                    $notFound++;
                    continue;
                }

                $productGid = $lookup['productGid'];
            }

            $result = $shopifyService->updateProductHandle($productGid, $newHandle);

            if (($result['success'] ?? false) === true) {
                $this->line('<fg=green>✓</> Updated');
                $updated++;
                continue;
            }

            $message = $result['message'] ?? 'Unknown error';
            $this->error("✗ Failed to update {$label}: {$message}");
            $errors++;
        }

        $this->newLine();
        $this->info('=== Update Summary ===');
        $this->info("Processed: {$processed}");
        $this->info("Updated: {$updated}");
        $this->info("Skipped: {$skipped}");
        $this->info("Not found: {$notFound}");
        if ($errors > 0) {
            $this->error("Errors: {$errors}");
        }

        return $errors > 0 ? 1 : 0;
    }

    /**
     * Reads a Shopify products export CSV and returns a unique map of productId => ['title' => ..., 'handle' => ...].
     *
     * Notes:
     * - Shopify numeric IDs often appear in scientific notation in CSV exports (e.g. 9.22293E+12).
     */
    private function readProductsFromCsv(string $path): array
    {
        $file = fopen($path, 'r');
        if ($file === false) {
            throw new \RuntimeException("Unable to open CSV file: {$path}");
        }

        $headers = fgetcsv($file);
        if ($headers === false) {
            fclose($file);
            throw new \RuntimeException('CSV appears to be empty');
        }

        $productIdIdx = array_search('Product ID', $headers, true);
        $handleIdx = array_search('Handle', $headers, true);
        $titleIdx = array_search('Title', $headers, true);

        if ($productIdIdx === false || $handleIdx === false || $titleIdx === false) {
            fclose($file);
            throw new \RuntimeException('CSV must contain headers: Product ID, Handle, Title');
        }

        $productsById = [];
        $productsByHandle = [];
        $uniqueIds = [];

        while (($row = fgetcsv($file)) !== false) {
            $rawProductId = trim((string) ($row[$productIdIdx] ?? ''));
            $title = trim((string) ($row[$titleIdx] ?? ''));
            $handle = trim((string) ($row[$handleIdx] ?? ''));

            if ($rawProductId === '' || $title === '') {
                continue;
            }

            $productId = $this->normalizeShopifyNumericId($rawProductId);
            if ($productId !== null) {
                $uniqueIds[$productId] = true;

                if (!isset($productsById[$productId])) {
                    $productsById[$productId] = [
                        'product_id' => $productId,
                        'title' => $title,
                        'handle' => $handle,
                    ];
                }
            }

            if ($handle !== '' && !isset($productsByHandle[$handle])) {
                $productsByHandle[$handle] = [
                    'product_id' => $productId,
                    'title' => $title,
                    'handle' => $handle,
                ];
            }
        }

        fclose($file);

        $uniqueIdCount = count($uniqueIds);

        // If Product IDs collapse to a single value (common when a CSV is processed by tools
        // that round to scientific notation), fall back to handle-based processing.
        if ($uniqueIdCount > 1) {
            ksort($productsById, SORT_NATURAL);
            return [
                'mode' => 'id',
                'products' => $productsById,
            ];
        }

        ksort($productsByHandle, SORT_NATURAL);
        return [
            'mode' => 'handle',
            'products' => $productsByHandle,
        ];
    }

    /**
     * Normalizes a Shopify numeric ID from CSV into an integer string.
     */
    private function normalizeShopifyNumericId(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        // Already looks like an integer
        if (preg_match('/^\d+$/', $trimmed) === 1) {
            return ltrim($trimmed, '0') === '' ? '0' : ltrim($trimmed, '0');
        }

        // Scientific notation, common in exports (e.g. 9.22293E+12)
        if (preg_match('/^(\d+(?:\.\d+)?)E\+?(\d+)$/i', $trimmed, $matches) === 1) {
            return $this->scientificToIntString($matches[1], (int) $matches[2]);
        }

        return null;
    }

    private function scientificToIntString(string $mantissa, int $exponent): string
    {
        // Convert something like 9.22293 * 10^12 into an integer string without float precision loss.
        $mantissa = trim($mantissa);
        $exponent = max(0, $exponent);

        $parts = explode('.', $mantissa, 2);
        $whole = $parts[0] ?? '0';
        $fraction = $parts[1] ?? '';

        $digits = $whole . $fraction;
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return '0';
        }

        $fractionLen = strlen($fraction);
        $shift = $exponent - $fractionLen;

        if ($shift >= 0) {
            return $digits . str_repeat('0', $shift);
        }

        // Exponent smaller than number of fractional digits.
        // For Shopify IDs this should not happen, but handle it safely by truncating.
        $keepLen = strlen($digits) + $shift;
        if ($keepLen <= 0) {
            return '0';
        }

        return substr($digits, 0, $keepLen);
    }
}
