<?php

namespace App\Http\Controllers;

use App\Services\ShopifyService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    private $shopifyService;

    public function __construct(ShopifyService $shopifyService)
    {
        $this->shopifyService = $shopifyService;
    }

    /**
     * Display inventory listing
     */
    public function index(Request $request)
    {
        $locations = [];
        try {
            $locations = $this->shopifyService->getLocations();
        } catch (\Exception $e) {
            $errors[] = 'Failed to fetch locations: ' . $e->getMessage();
        }
        
        $filters = [
            'location_id' => $request->get('location_id', ''),
            'product_type' => $request->get('product_type', ''),
            'vendor' => $request->get('vendor', ''),
            'status' => $request->get('status', 'active'),
            'sku_filter' => $request->get('sku_filter', ''),
            'page' => max(1, (int)$request->get('page', 1)),
        ];

        $inventory = [];
        $errors = [];
        $pagination = [
            'current_page' => $filters['page'],
            'has_next' => false,
            'has_prev' => $filters['page'] > 1,
            'total_shown' => 0
        ];

        if ($request->has('preview')) {
            // Get inventory data for preview with pagination
            try {
                $result = $this->shopifyService->getInventoryPage(
                    $filters['location_id'],
                    $filters['product_type'],
                    $filters['vendor'],
                    50,
                    $filters['page']
                );

                $errors = $result['errors'] ?? [];
                $inventory = $result['inventory'] ?? [];
                $pagination['has_next'] = $result['has_next_page'] ?? false;
                
                // Apply additional filters
                if ($filters['sku_filter']) {
                    $inventory = array_filter($inventory, function($item) use ($filters) {
                        return stripos($item['sku'] ?? '', $filters['sku_filter']) !== false;
                    });
                }
                
                if ($filters['status'] !== 'all') {
                    $inventory = array_filter($inventory, function($item) use ($filters) {
                        return strtolower($item['product_status'] ?? '') === strtolower($filters['status']);
                    });
                }
                
                $pagination['total_shown'] = count($inventory);
                
            } catch (\Exception $e) {
                $errors[] = 'Failed to fetch inventory data: ' . $e->getMessage();
                $inventory = [];
            }
        }

        return view('inventory.index', compact('locations', 'filters', 'inventory', 'errors', 'pagination'));
    }

    /**
     * Export inventory to CSV
     */
    public function export(Request $request)
    {
        // Location is now optional since we show totals
        $filters = [
            'location_id' => $request->get('location_id'),
            'product_type' => $request->get('product_type', ''),
            'vendor' => $request->get('vendor', ''),
            'status' => $request->get('status', 'active'),
            'sku_filter' => $request->get('sku_filter', ''),
        ];

        // Get ALL inventory data for export with pagination
        $result = $this->shopifyService->getInventory(
            $filters['location_id'],
            $filters['product_type'],
            $filters['vendor'],
            50,  // Fetch 50 products per page to stay under API cost limit
            true   // Fetch all pages (will automatically paginate through all products)
        );

        if (!empty($result['errors'])) {
            return back()->with('error', 'Failed to fetch inventory: ' . implode(', ', $result['errors']));
        }

        $inventory = $result['inventory'];
        
        // Apply additional filters
        if ($filters['sku_filter']) {
            $inventory = array_filter($inventory, function($item) use ($filters) {
                return stripos($item['sku'], $filters['sku_filter']) !== false;
            });
        }
        
        if ($filters['status'] !== 'all') {
            $inventory = array_filter($inventory, function($item) use ($filters) {
                return strtolower($item['product_status']) === strtolower($filters['status']);
            });
        }

        // Generate CSV
        $filename = 'inventory-export-' . date('Y-m-d-H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($inventory) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'Product ID',
                'Product Title',
                'Product Handle',
                'Product Type',
                'Vendor',
                'Status',
                'Tags',
                'Variant ID',
                'Variant Title',
                'SKU',
                'Price',
                'Inventory Quantity',
                'Inventory Item ID',
                'Location ID',
                'Location Name'
            ]);

            // CSV Data
            foreach ($inventory as $item) {
                // Extract numeric IDs from Shopify GIDs (e.g., gid://shopify/Product/123456 -> 123456)
                $productIdNumeric = preg_replace('/^gid:\/\/shopify\/\w+\//', '', $item['product_id']);
                $variantIdNumeric = preg_replace('/^gid:\/\/shopify\/\w+\//', '', $item['variant_id']);
                $inventoryItemIdNumeric = preg_replace('/^gid:\/\/shopify\/\w+\//', '', $item['inventory_item_id']);
                $locationIdNumeric = preg_replace('/^gid:\/\/shopify\/\w+\//', '', $item['location_id']);
                
                fputcsv($file, [
                    $productIdNumeric,
                    $item['product_title'],
                    $item['product_handle'],
                    $item['product_type'],
                    $item['vendor'],
                    $item['product_status'],
                    $item['tags'] ?? '',
                    $variantIdNumeric,
                    $item['variant_title'],
                    $item['sku'],
                    number_format((float)$item['price'], 2),
                    $item['available_quantity'],
                    $inventoryItemIdNumeric,
                    $locationIdNumeric,
                    $item['location_name']
                ]);
            }
            
            fclose($file);
        }, 200, $headers);
    }
}