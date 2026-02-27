<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use App\Services\ShopifyService;
use App\Services\WarehouseService;
use App\Models\WarehouseVariant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class StockSyncController extends Controller
{
    private $shopifyService;
    private $warehouseService;

    public function __construct(ShopifyService $shopifyService, WarehouseService $warehouseService)
    {
        $this->shopifyService = $shopifyService;
        $this->warehouseService = $warehouseService;
    }

    /**
     * Display the stock sync page
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $tag = $request->input('tag');
        $sort = $request->input('sort', 'product_asc');
        
        // Get default location from settings if not specified
        $defaultLocationId = Setting::get('default_location_id');
        $locationId = $request->input('location_id', $defaultLocationId);
        
        $page = (int) $request->input('page', 1);
        $perPage = 20; // 20 variants per page

        // Get locations for filter dropdown
        $locations = $this->shopifyService->getLocations();

        $comparisonData = [];
        $totalVariants = 0;
        $currentPage = $page;
        $lastPage = 1;
        
        // Map sort parameter to Shopify GraphQL sortKey
        list($sortKey, $reverse) = $this->mapSortToGraphQL($sort);
        
        // Get sync status filter
        $syncStatusFilter = $request->input('sync_status', '');
        
        // Use cursor-based pagination with GraphQL sorting
        Log::info('Fetching variants page...', ['page' => $page, 'location_id' => $locationId, 'search' => $search, 'tag' => $tag, 'sync_status' => $syncStatusFilter]);
            
        // Build search query for GraphQL if search term provided
        $searchQuery = '';
        if (!empty($search)) {
            $searchTerm = trim($search);
            // Shopify search query format for productVariants
            // Always search all fields with wildcards for partial matching
            $searchQuery = ' AND (' .
                'product_title:*' . $searchTerm . '* OR ' .
                'title:*' . $searchTerm . '* OR ' .
                'sku:*' . $searchTerm . '* OR ' .
                'barcode:*' . $searchTerm . '*)';
        }
        
        // Add tag filter to search query if provided (partial match to get candidates)
        if (!empty($tag)) {
            $tagTerm = trim($tag);
            $searchQuery .= ' AND product_tag:' . $tagTerm;
        }
        
        // Store cursors in session for pagination
        $sessionKey = 'stock_sync_cursors_' . ($locationId ?? 'all') . '_' . md5($search . $tag . $syncStatusFilter);
        $cursors = session($sessionKey, [1 => null]); // Page 1 starts with null cursor
        
        // Get cursor for requested page
        $cursor = $cursors[$page] ?? null;
        
        // If we don't have cursor for this page, we need to build up to it
        if (!isset($cursors[$page]) && $page > 1) {
            $tempCursor = null;
            for ($i = 1; $i < $page; $i++) {
                if (isset($cursors[$i + 1])) {
                    $tempCursor = $cursors[$i + 1];
                } else {
                    // Fetch enough variants to get a full page after filtering
                    $tempVariants = $this->fetchVariantsUntilCount($perPage, $tempCursor, $locationId, $sortKey, $reverse, $searchQuery, $syncStatusFilter, $tag);
                    $tempCursor = $tempVariants['cursor'];
                    $cursors[$i + 1] = $tempCursor;
                }
            }
            $cursor = $tempCursor;
            session([$sessionKey => $cursors]);
        }
        
        // Fetch variants with filtering until we have enough for this page
        $fetchResult = $this->fetchVariantsUntilCount($perPage, $cursor, $locationId, $sortKey, $reverse, $searchQuery, $syncStatusFilter, $tag);
        $shopifyVariants = $fetchResult['variants'];
        $hasNextPage = $fetchResult['hasNextPage'];
        $nextCursor = $fetchResult['cursor'];
        
        // Store next page cursor
        if ($hasNextPage && $nextCursor) {
            $cursors[$page + 1] = $nextCursor;
            session([$sessionKey => $cursors]);
        }

        // Build comparison data WITHOUT warehouse stock (will be loaded via AJAX)
        foreach ($shopifyVariants as $variant) {
            $variantId = $variant['variant_id'];
            $shopifyStock = 0;
            
            if ($locationId) {
                foreach ($variant['inventory_levels'] as $level) {
                    if ($level['location_id'] === $locationId) {
                        $shopifyStock = $level['available'];
                        break;
                    }
                }
            } else {
                $shopifyStock = $variant['total_inventory'];
            }

            $pimSync = $variant['pim_sync'] ?? '';
            
            // Note: Filtering already done in fetchVariantsUntilCount

            $comparisonData[] = [
                'variant_id' => $variantId,
                'variant_gid' => $variant['variant_gid'],
                'product_id' => $variant['product_id'],
                'product_gid' => $variant['product_gid'],
                'product_title' => $variant['product_title'],
                'product_handle' => $variant['product_handle'] ?? '',
                'product_tags' => $variant['product_tags'] ?? '',
                'variant_title' => $variant['variant_title'],
                'sku' => $variant['sku'],
                'barcode' => $variant['barcode'],
                'shopify_stock' => $shopifyStock,
                'warehouse_stock' => null, // Will be loaded via AJAX
                'pim_sync' => $pimSync,
                'sync_timestamp' => $variant['sync_timestamp'] ?? '',
                'inventory_item_id' => $variant['inventory_item_id'] ?? '',
                'inventory_levels' => $variant['inventory_levels'],
            ];
        }
        
        $syncData = $comparisonData;
        $totalVariants = count($syncData);
        
        // Note: Sorting is handled by GraphQL sortKey, no need to sort here
        
        // Estimate last page (we don't know exact total, but can show "Next" if hasNextPage)
        $lastPage = $hasNextPage ? $page + 1 : $page;

        // Check if warehouse data is synced
        $warehouseVariantsCount = WarehouseVariant::count();
        $lastSyncTime = WarehouseVariant::max('synced_at');
        
        // Convert to Carbon instance if it's a string
        if ($lastSyncTime && is_string($lastSyncTime)) {
            $lastSyncTime = \Carbon\Carbon::parse($lastSyncTime);
        }

        return view('stock-sync.index', [
            'syncData' => $syncData,
            'locations' => $locations,
            'selectedLocation' => $locationId,
            'search' => $search,
            'tag' => $tag,
            'sort' => $sort,
            'syncStatusFilter' => $syncStatusFilter,
            'currentPage' => $currentPage,
            'lastPage' => $lastPage,
            'perPage' => $perPage,
            'totalVariants' => $totalVariants,
            'needsSyncCount' => 0,
            'cacheExists' => false,
            'warehouseVariantsCount' => $warehouseVariantsCount,
            'lastSyncTime' => $lastSyncTime
        ]);
    }

    /**
     * Map sort parameter to Shopify GraphQL sortKey and reverse flag
     */
    private function mapSortToGraphQL($sort)
    {
        // Shopify productVariants valid sortKey options: ID, TITLE, SKU, INVENTORY_MANAGEMENT, FULL_TITLE
        switch($sort) {
            case 'product_desc':
                return ['TITLE', true];
            
            case 'sku_asc':
                return ['SKU', false];
            
            case 'sku_desc':
                return ['SKU', true];
            
            case 'product_asc':
            default:
                return ['TITLE', false];
        }
    }

    /**
     * Apply sorting to sync data
     */
    private function applySorting($data, $sort)
    {
        usort($data, function($a, $b) use ($sort) {
            switch($sort) {
                case 'product_desc':
                    return strcmp($b['product_title'], $a['product_title']);
                
                case 'shopify_stock_asc':
                    return $a['shopify_stock'] <=> $b['shopify_stock'];
                
                case 'shopify_stock_desc':
                    return $b['shopify_stock'] <=> $a['shopify_stock'];
                
                case 'warehouse_stock_asc':
                    $aStock = $a['warehouse_stock'] ?? -1;
                    $bStock = $b['warehouse_stock'] ?? -1;
                    return $aStock <=> $bStock;
                
                case 'warehouse_stock_desc':
                    $aStock = $a['warehouse_stock'] ?? -1;
                    $bStock = $b['warehouse_stock'] ?? -1;
                    return $bStock <=> $aStock;
                
                case 'difference_asc':
                    $aDiff = $a['warehouse_stock'] !== null ? ($a['shopify_stock'] - $a['warehouse_stock']) : 999999;
                    $bDiff = $b['warehouse_stock'] !== null ? ($b['shopify_stock'] - $b['warehouse_stock']) : 999999;
                    return $aDiff <=> $bDiff;
                
                case 'difference_desc':
                    $aDiff = $a['warehouse_stock'] !== null ? ($a['shopify_stock'] - $a['warehouse_stock']) : -999999;
                    $bDiff = $b['warehouse_stock'] !== null ? ($b['shopify_stock'] - $b['warehouse_stock']) : -999999;
                    return $bDiff <=> $aDiff;
                
                case 'sku_asc':
                    return strcmp($a['sku'] ?? '', $b['sku'] ?? '');
                
                case 'sku_desc':
                    return strcmp($b['sku'] ?? '', $a['sku'] ?? '');
                
                case 'product_asc':
                default:
                    return strcmp($a['product_title'], $b['product_title']);
            }
        });
        
        return $data;
    }

    /**
     * Get warehouse stock for specific variants (AJAX endpoint)
     */
    public function getWarehouseStock(Request $request)
    {
        $variantIds = $request->input('variant_ids', []);
        
        if (empty($variantIds)) {
            return response()->json(['success' => false, 'message' => 'No variant IDs provided']);
        }
        
        $warehouseStocks = WarehouseVariant::whereIn('shopify_variant_id', $variantIds)
            ->pluck('stock', 'shopify_variant_id')
            ->toArray();
        
        return response()->json([
            'success' => true,
            'stocks' => $warehouseStocks
        ]);
    }

    /**
     * Get warehouse stock for multiple SKUs in batch (AJAX endpoint)
     * Fetches stock from warehouse API for multiple SKUs at once
     */
    public function getWarehouseStockBatch(Request $request)
    {
        $skus = $request->input('skus', []);
        
        if (empty($skus) || !is_array($skus)) {
            return response()->json([
                'success' => false,
                'message' => 'No SKUs provided'
            ]);
        }
        
        $warehouseData = $this->warehouseService->getStockBySkuBatch($skus);
        
        return response()->json([
            'success' => true,
            'data' => $warehouseData,
            'count' => count($warehouseData)
        ]);
    }

    /**
     * Get warehouse stock by SKU (AJAX endpoint)
     * Fetches stock from warehouse API using SKU/barcode search
     */
    public function getWarehouseStockBySku(Request $request)
    {
        $sku = $request->input('sku');
        
        if (empty($sku)) {
            return response()->json([
                'success' => false,
                'message' => 'No SKU provided'
            ]);
        }
        
        $warehouseData = $this->warehouseService->getStockBySku($sku);
        
        if ($warehouseData) {
            return response()->json([
                'success' => true,
                'stock' => $warehouseData['stock'],
                'warehouse_id' => $warehouseData['warehouse_id'],
                'variant_name' => $warehouseData['variant_name']
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Not found in warehouse',
            'stock' => null
        ]);
    }

    /**
     * Clear warehouse cache
     */
    public function clearCache()
    {
        // No caching anymore, just redirect back
        return redirect()->route('stock-sync.index')->with('success', 'Refreshed successfully.');
    }

    /**
     * Sync warehouse variants from API to database
     */
    public function syncWarehouse()
    {
        try {
            Log::info('Starting warehouse sync via endpoint...');
            
            // Run the artisan command
            Artisan::call('warehouse:sync', ['--fresh' => true]);
            
            $output = Artisan::output();
            Log::info('Warehouse sync completed', ['output' => $output]);
            
            // Clear related caches
            Cache::forget('warehouse_stock_map');
            Cache::forget('stock_sync_data');
            
            return response()->json([
                'success' => true,
                'message' => 'Warehouse variants synced successfully',
                'output' => $output,
                'count' => WarehouseVariant::count()
            ]);
        } catch (\Exception $e) {
            Log::error('Warehouse sync failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export stock comparison to CSV
     */
    public function export(Request $request)
    {
        $locationId = $request->input('location_id');
        
        Log::info('Stock sync export started:', [
            'location_id' => $locationId
        ]);

        // Fetch all variants for export
        $result = $this->shopifyService->getProductVariants(true, $locationId);
        $shopifyVariants = $result['variants'];

        $filename = 'stock-sync-' . date('Y-m-d-His') . '.csv';

        return response()->stream(function() use ($shopifyVariants, $locationId) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers (removed warehouse columns)
            fputcsv($file, [
                'Variant ID',
                'Product Title',
                'Variant Title',
                'SKU',
                'Barcode',
                'Shopify Stock',
                'Status'
            ]);

            // Data rows
            foreach ($shopifyVariants as $variant) {
                $variantId = $variant['variant_id'];

                // Get stock for location
                $shopifyStock = 0;
                if ($locationId) {
                    foreach ($variant['inventory_levels'] as $level) {
                        if ($level['location_id'] === $locationId) {
                            $shopifyStock = $level['available'];
                            break;
                        }
                    }
                } else {
                    $shopifyStock = $variant['total_inventory'];
                }

                fputcsv($file, [
                    $variantId,
                    $variant['product_title'],
                    $variant['variant_title'],
                    $variant['sku'],
                    $variant['barcode'],
                    $shopifyStock,
                    'Active'
                ]);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Toggle PIM sync status for a variant (include/exclude from sync)
     */
    public function togglePimSync(Request $request)
    {
        $request->validate([
            'variant_gid' => 'required|string',
            'product_gid' => 'required|string',
            'exclude' => 'required|boolean'
        ]);

        try {
            $variantGid = $request->variant_gid;
            $productGid = $request->product_gid;
            $exclude = $request->exclude;
            
            // Set metafield value: 'false' to exclude, 'true' to include
            $metafieldValue = $exclude ? 'false' : 'true';
            
            // Update VARIANT metafield (not product) so each variant can have independent status
            $success = $this->shopifyService->updateVariantMetafield(
                $variantGid,
                'custom',
                'pim_sync',
                $metafieldValue
            );
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => $exclude 
                        ? 'Variant excluded from sync successfully' 
                        : 'Variant included in sync successfully',
                    'pim_sync' => $metafieldValue
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update sync status'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Toggle PIM sync failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync stock from warehouse to Shopify for a specific variant
     */
    public function syncStock(Request $request)
    {
        $request->validate([
            'variant_id' => 'required|string',
            'inventory_item_id' => 'required|string',
            'location_id' => 'required|string',
            'warehouse_stock' => 'required|integer'
        ]);

        try {
            $variantId = $request->variant_id;
            $inventoryItemId = $request->inventory_item_id;
            $locationId = $request->location_id;
            $warehouseStock = $request->warehouse_stock;
            
            $success = $this->shopifyService->updateInventoryLevel(
                $inventoryItemId,
                $locationId,
                $warehouseStock
            );
            
            if ($success) {
                // Update sync timestamp metafield
                $variantGid = 'gid://shopify/ProductVariant/' . $variantId;
                $timestamp = now()->toIso8601String();
                $this->shopifyService->updateVariantMetafield($variantGid, 'custom', 'pim_kr_sync_timestamp', $timestamp);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Stock synced successfully from warehouse to Shopify',
                    'new_stock' => $warehouseStock,
                    'sync_timestamp' => $timestamp
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to sync stock'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Sync stock failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch variants from Shopify until we have the desired count after filtering
     * 
     * @param int $desiredCount Number of variants needed after filtering
     * @param string|null $cursor Starting cursor for pagination
     * @param string|null $locationId Location ID filter
     * @param string $sortKey GraphQL sort key
     * @param bool $reverse Sort direction
     * @param string $searchQuery Search query string
     * @param string $syncStatusFilter Sync status filter (included/excluded/unset)
     * @param string|null $exactTag Exact tag to filter by (case-insensitive)
     * @return array ['variants' => array, 'cursor' => string, 'hasNextPage' => bool]
     */
    private function fetchVariantsUntilCount($desiredCount, $cursor, $locationId, $sortKey, $reverse, $searchQuery, $syncStatusFilter, $exactTag = null)
    {
        $filteredVariants = [];
        $currentCursor = $cursor;
        $hasMore = true;
        // When exact tag filtering is needed, we need more attempts since backend filtering reduces results
        $maxAttempts = $exactTag ? 50 : 10;
        $attempts = 0;
        
        while (count($filteredVariants) < $desiredCount && $hasMore && $attempts < $maxAttempts) {
            $attempts++;
            
            // Fetch a batch of variants from Shopify
            $result = $this->shopifyService->getProductVariants(false, $locationId, $currentCursor, $sortKey, $reverse, $searchQuery);
            $variants = $result['variants'];
            $hasMore = $result['pageInfo']['hasNextPage'];
            $currentCursor = $result['pageInfo']['endCursor'];
            
            // Apply filters
            foreach ($variants as $variant) {
                $pimSync = $variant['pim_sync'] ?? '';
                
                $matches = true;
                
                // Apply sync status filter
                if ($syncStatusFilter) {
                    if ($syncStatusFilter === 'included' && $pimSync !== 'true') $matches = false;
                    if ($syncStatusFilter === 'excluded' && $pimSync !== 'false') $matches = false;
                    if ($syncStatusFilter === 'unset' && !empty($pimSync)) $matches = false;
                }
                
                // Apply exact tag filter (backend verification for exact match)
                if ($matches && $exactTag) {
                    $productTags = $variant['product_tags'] ?? '';
                    if (empty($productTags)) {
                        $matches = false;
                    } else {
                        $tags = array_map('trim', explode(',', $productTags));
                        // Case-insensitive exact match
                        $exactTagLower = strtolower(trim($exactTag));
                        $tagsLower = array_map('strtolower', $tags);
                        $matches = in_array($exactTagLower, $tagsLower);
                    }
                }
                
                if ($matches) {
                    $filteredVariants[] = $variant;
                    
                    // Stop if we have enough
                    if (count($filteredVariants) >= $desiredCount) {
                        break;
                    }
                }
            }
            
            // If no more pages available, stop
            if (!$hasMore) {
                break;
            }
        }
        
        return [
            'variants' => array_slice($filteredVariants, 0, $desiredCount),
            'cursor' => $currentCursor,
            'hasNextPage' => $hasMore && (count($filteredVariants) >= $desiredCount)
        ];
    }

    /**
     * Bulk update PIM sync status by tag with real-time streaming output
     */
    public function bulkUpdateByTag(Request $request)
    {
        $request->validate([
            'tag' => 'required|string',
            'status' => 'required|in:include,exclude,unset',
            'inverse' => 'sometimes|boolean',
        ]);

        $tag = $request->input('tag');
        $status = $request->input('status');
        $inverse = $request->input('inverse', false);

        // Set response headers for streaming
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering

        // Disable output buffering
        if (ob_get_level()) ob_end_clean();

        // Send initial message
        $this->sendStreamMessage('start', [
            'tag' => $tag,
            'status' => $status,
            'inverse' => $inverse
        ]);

        try {
            // Map status to metafield value
            $metafieldValue = 'n/a';
            if ($status === 'include') {
                $metafieldValue = 'true';
            } elseif ($status === 'exclude') {
                $metafieldValue = 'false';
            }

            $this->sendStreamMessage('info', ['message' => 'Fetching variants...']);

            // Fetch variants by tag
            $result = $this->shopifyService->getVariantsByProductTag($tag, true, $inverse);
            $variants = $result['variants'];

            if (empty($variants)) {
                $this->sendStreamMessage('error', ['message' => 'No variants found']);
                $this->sendStreamMessage('done', ['success' => 0, 'failed' => 0, 'total' => 0]);
                return;
            }

            $total = count($variants);
            $this->sendStreamMessage('total', ['count' => $total]);

            $successCount = 0;
            $failedCount = 0;
            $failedVariants = [];

            // Process each variant
            foreach ($variants as $index => $variant) {
                $num = $index + 1;
                $sku = $variant['sku'] ?: 'NO-SKU';
                $productTitle = $variant['product_title'];
                $variantTitle = $variant['variant_title'];

                // Send progress update
                $this->sendStreamMessage('progress', [
                    'current' => $num,
                    'total' => $total,
                    'sku' => $sku,
                    'product' => $productTitle,
                    'variant' => $variantTitle
                ]);

                try {
                    $success = $this->shopifyService->updateVariantMetafield(
                        $variant['variant_gid'],
                        'custom',
                        'pim_sync',
                        $metafieldValue
                    );

                    if ($success) {
                        $successCount++;
                        $this->sendStreamMessage('success', [
                            'current' => $num,
                            'sku' => $sku
                        ]);
                    } else {
                        $failedCount++;
                        $failedVariants[] = [
                            'sku' => $sku,
                            'product' => $productTitle,
                            'variant' => $variantTitle
                        ];
                        $this->sendStreamMessage('failed', [
                            'current' => $num,
                            'sku' => $sku,
                            'reason' => 'API returned false'
                        ]);
                    }

                    // Small delay to avoid rate limiting
                    usleep(50000); // 50ms

                } catch (\Exception $e) {
                    $failedCount++;
                    $failedVariants[] = [
                        'sku' => $sku,
                        'product' => $productTitle,
                        'variant' => $variantTitle,
                        'reason' => $e->getMessage()
                    ];
                    $this->sendStreamMessage('failed', [
                        'current' => $num,
                        'sku' => $sku,
                        'reason' => $e->getMessage()
                    ]);
                }
            }

            // Send completion message
            $this->sendStreamMessage('done', [
                'success' => $successCount,
                'failed' => $failedCount,
                'total' => $total,
                'failedVariants' => $failedVariants
            ]);

        } catch (\Exception $e) {
            $this->sendStreamMessage('error', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Send a Server-Sent Event message
     */
    private function sendStreamMessage($event, $data)
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
        if (ob_get_level()) ob_flush();
        flush();
    }
}
