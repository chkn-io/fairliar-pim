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
        Log::info('Fetching variants page...', ['page' => $page, 'location_id' => $locationId, 'search' => $search, 'sync_status' => $syncStatusFilter]);
            
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
        
        // Store cursors in session for pagination
        $sessionKey = 'stock_sync_cursors_' . ($locationId ?? 'all') . '_' . md5($search . $syncStatusFilter);
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
                    $tempVariants = $this->fetchVariantsUntilCount($perPage, $tempCursor, $locationId, $sortKey, $reverse, $searchQuery, $syncStatusFilter);
                    $tempCursor = $tempVariants['cursor'];
                    $cursors[$i + 1] = $tempCursor;
                }
            }
            $cursor = $tempCursor;
            session([$sessionKey => $cursors]);
        }
        
        // Fetch variants with filtering until we have enough for this page
        $fetchResult = $this->fetchVariantsUntilCount($perPage, $cursor, $locationId, $sortKey, $reverse, $searchQuery, $syncStatusFilter);
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
                'variant_title' => $variant['variant_title'],
                'sku' => $variant['sku'],
                'barcode' => $variant['barcode'],
                'shopify_stock' => $shopifyStock,
                'warehouse_stock' => null, // Will be loaded via AJAX
                'pim_sync' => $pimSync,
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
                return response()->json([
                    'success' => true,
                    'message' => 'Stock synced successfully from warehouse to Shopify',
                    'new_stock' => $warehouseStock
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
     * @return array ['variants' => array, 'cursor' => string, 'hasNextPage' => bool]
     */
    private function fetchVariantsUntilCount($desiredCount, $cursor, $locationId, $sortKey, $reverse, $searchQuery, $syncStatusFilter)
    {
        $filteredVariants = [];
        $currentCursor = $cursor;
        $hasMore = true;
        $maxAttempts = 10; // Prevent infinite loops
        $attempts = 0;
        
        while (count($filteredVariants) < $desiredCount && $hasMore && $attempts < $maxAttempts) {
            $attempts++;
            
            // Fetch a batch of variants from Shopify
            $result = $this->shopifyService->getProductVariants(false, $locationId, $currentCursor, $sortKey, $reverse, $searchQuery);
            $variants = $result['variants'];
            $hasMore = $result['pageInfo']['hasNextPage'];
            $currentCursor = $result['pageInfo']['endCursor'];
            
            // Apply sync status filter
            foreach ($variants as $variant) {
                $pimSync = $variant['pim_sync'] ?? '';
                
                $matches = true;
                if ($syncStatusFilter) {
                    if ($syncStatusFilter === 'included' && $pimSync !== 'true') $matches = false;
                    if ($syncStatusFilter === 'excluded' && $pimSync !== 'false') $matches = false;
                    if ($syncStatusFilter === 'unset' && !empty($pimSync)) $matches = false;
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
}
