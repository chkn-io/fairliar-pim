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
        
        // If searching, fetch all variants to search through them
        if ($search) {
            Log::info('Searching all variants...', ['search' => $search, 'location_id' => $locationId]);
            $result = $this->shopifyService->getProductVariants(true, $locationId);
            $shopifyVariants = $result['variants'];
            
            // Build comparison data with warehouse stock
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

                // Get warehouse stock
                $warehouseVariant = WarehouseVariant::where('shopify_variant_id', $variantId)->first();
                $warehouseStock = $warehouseVariant ? $warehouseVariant->stock : null;

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
                    'warehouse_stock' => $warehouseStock,
                    'pim_sync' => $variant['pim_sync'] ?? '',
                    'inventory_item_id' => $variant['inventory_item_id'] ?? '',
                    'inventory_levels' => $variant['inventory_levels'],
                ];
            }
            
            // Filter by search
            $searchLower = strtolower($search);
            $syncData = array_filter($comparisonData, function($item) use ($searchLower) {
                return stripos($item['product_title'], $searchLower) !== false ||
                       stripos($item['variant_title'], $searchLower) !== false ||
                       stripos($item['sku'], $searchLower) !== false ||
                       stripos($item['barcode'], $searchLower) !== false ||
                       stripos($item['variant_id'], $searchLower) !== false;
            });
            $syncData = array_values($syncData);
            
            $totalVariants = count($syncData);
            $lastPage = ceil($totalVariants / $perPage);
            
            // Sort by product title
            usort($syncData, function($a, $b) {
                return strcmp($a['product_title'], $b['product_title']);
            });
            
            // Paginate the search results
            $offset = ($page - 1) * $perPage;
            $syncData = array_slice($syncData, $offset, $perPage);
            
        } else {
            // No search - use cursor-based pagination but track pages in session
            Log::info('Fetching variants page...', ['page' => $page, 'location_id' => $locationId]);
            
            // Store cursors in session for pagination
            $sessionKey = 'stock_sync_cursors_' . ($locationId ?? 'all');
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
                        // Fetch page to get next cursor
                        $tempResult = $this->shopifyService->getProductVariants(false, $locationId, $tempCursor);
                        $tempCursor = $tempResult['pageInfo']['endCursor'];
                        $cursors[$i + 1] = $tempCursor;
                    }
                }
                $cursor = $tempCursor;
                session([$sessionKey => $cursors]);
            }
            
            $result = $this->shopifyService->getProductVariants(false, $locationId, $cursor);
            $shopifyVariants = $result['variants'];
            $hasNextPage = $result['pageInfo']['hasNextPage'];
            $nextCursor = $result['pageInfo']['endCursor'];
            
            // Store next page cursor
            if ($hasNextPage && $nextCursor) {
                $cursors[$page + 1] = $nextCursor;
                session([$sessionKey => $cursors]);
            }
            
            // Build comparison data with warehouse stock
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

                // Get warehouse stock
                $warehouseVariant = WarehouseVariant::where('shopify_variant_id', $variantId)->first();
                $warehouseStock = $warehouseVariant ? $warehouseVariant->stock : null;

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
                    'warehouse_stock' => $warehouseStock,
                    'pim_sync' => $variant['pim_sync'] ?? '',
                    'inventory_item_id' => $variant['inventory_item_id'] ?? '',
                    'inventory_levels' => $variant['inventory_levels'],
                ];
            }
            
            $syncData = $comparisonData;
            $totalVariants = count($syncData);
            
            // Sort by product title
            usort($syncData, function($a, $b) {
                return strcmp($a['product_title'], $b['product_title']);
            });
            
            // Estimate last page (we don't know exact total, but can show "Next" if hasNextPage)
            $lastPage = $hasNextPage ? $page + 1 : $page;
        }

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
            
            $success = $this->shopifyService->updateProductMetafield(
                $productGid,
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
}
