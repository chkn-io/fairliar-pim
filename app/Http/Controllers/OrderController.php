<?php

namespace App\Http\Controllers;

use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    private $shopifyService;

    public function __construct(ShopifyService $shopifyService)
    {
        $this->shopifyService = $shopifyService;
    }

    /**
     * Display a paginated list of orders
     */
    public function index(Request $request): View
    {
        $perPage = $request->get('per_page', 20);
        $after = $request->get('after');
        $before = $request->get('before');
        
        // Build query from multiple filters
        $query = $this->buildQuery($request);
        
        // Get sorting parameters
        $sortBy = $request->get('sort_by', 'CREATED_AT');
        $sortOrder = $request->get('sort_order', 'desc');
        $reverse = $sortOrder === 'desc';

        // Validate per_page
        $perPage = min(max($perPage, 5), 50); // Between 5 and 50

        $response = $this->shopifyService->getOrders($perPage, $after, $query, $sortBy, $reverse);

        return view('orders.index', [
            'orders' => $response['orders'],
            'pageInfo' => $response['pageInfo'],
            'errors' => $response['errors'],
            'currentQuery' => $query,
            'perPage' => $perPage,
            'currentPage' => $request->get('page', 1),
            'filters' => [
                'text_search' => $request->get('text_search', ''),
                'fulfillment_status' => $request->get('fulfillment_status', ''),
                'financial_status' => $request->get('financial_status', ''),
                'date_from' => $request->get('date_from', ''),
                'date_to' => $request->get('date_to', ''),
                'custom_query' => $request->get('custom_query', '')
            ],
            'sorting' => [
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder
            ]
        ]);
    }

    /**
     * Export orders to CSV
     */
    public function export(Request $request)
    {
        $query = $this->buildQuery($request);
        
        // Get sorting parameters
        $sortBy = $request->get('sort_by', 'CREATED_AT');
        $sortOrder = $request->get('sort_order', 'desc');
        $reverse = $sortOrder === 'desc';
        
        // Fetch more orders for export (up to 250)
        $response = $this->shopifyService->getOrders(250, null, $query, $sortBy, $reverse);
        
        if (!empty($response['errors'])) {
            return back()->with('error', 'Failed to export orders: ' . implode(', ', $response['errors']));
        }

        $orders = $response['orders'];
        $filename = 'shopify-orders-' . date('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($orders) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'Order Number',
                'Order ID',
                'Date Created',
                'Customer Name',
                'Shipping Address',
                'City',
                'Province',
                'Country',
                'ZIP',
                'Item Name',
                'SKU',
                'Quantity',
                'Unit Price',
                'Line Total',
                'Fulfillment Location',
                'Fulfillment Status',
                'Order Total',
                'Currency'
            ]);

            foreach ($orders as $order) {
                $baseOrderData = [
                    $order['name'],
                    $order['id'],
                    date('Y-m-d H:i:s', strtotime($order['created_at'])),
                    $order['shipping_address']['name'],
                    $order['shipping_address']['address1'],
                    $order['shipping_address']['city'],
                    $order['shipping_address']['province'],
                    $order['shipping_address']['country'],
                    $order['shipping_address']['zip']
                ];

                if (empty($order['line_items'])) {
                    // Order with no line items
                    fputcsv($file, array_merge($baseOrderData, [
                        '', '', '', '', '', '', '', $order['total_price'], $order['currency']
                    ]));
                } else {
                    // One row per line item
                    foreach ($order['line_items'] as $item) {
                        fputcsv($file, array_merge($baseOrderData, [
                            $item['name'],
                            $item['sku'],
                            $item['quantity'],
                            number_format($item['price'], 2),
                            number_format($item['price'] * $item['quantity'], 2),
                            $item['fulfillment_location'],
                            $item['fulfillment_status'],
                            $order['total_price'],
                            $order['currency']
                        ]));
                    }
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Build GraphQL query from request filters
     */
    private function buildQuery(Request $request): string
    {
        $queryParts = [];
        
        // Custom query takes precedence
        if ($customQuery = $request->get('custom_query')) {
            return $customQuery;
        }
        
        // Text search
        if ($textSearch = $request->get('text_search')) {
            $queryParts[] = 'name:*' . $textSearch . '*';
        }
        
        // Fulfillment status
        if ($fulfillmentStatus = $request->get('fulfillment_status')) {
            $queryParts[] = 'fulfillment_status:' . $fulfillmentStatus;
        }
        
        // Financial status
        if ($financialStatus = $request->get('financial_status')) {
            $queryParts[] = 'financial_status:' . $financialStatus;
        }
        
        // Date range
        if ($dateFrom = $request->get('date_from')) {
            $queryParts[] = 'created_at:>=' . $dateFrom;
        }
        
        if ($dateTo = $request->get('date_to')) {
            $queryParts[] = 'created_at:<=' . $dateTo;
        }
        
        // Default query if no filters applied
        if (empty($queryParts)) {
            return 'fulfillment_status:unfulfilled';
        }
        
        return implode(' AND ', $queryParts);
    }

    /**
     * Show detailed view of a specific order
     */
    public function show(Request $request, $orderId)
    {
        // Decode the URL-encoded order ID
        $decodedOrderId = urldecode($orderId);
        
        // For now, we'll fetch all orders and find the specific one
        // In a production app, you'd want a separate GraphQL query for single orders
        $response = $this->shopifyService->getOrders(250);
        
        // Try to find the order by exact ID match first
        $order = collect($response['orders'])->firstWhere('id', $decodedOrderId);
        
        // If not found, try to find by the numeric part of the ID
        if (!$order) {
            // Extract numeric ID from GraphQL ID format like "gid://shopify/Order/6584422138028"
            if (preg_match('/\/(\d+)$/', $decodedOrderId, $matches)) {
                $numericId = $matches[1];
                $order = collect($response['orders'])->first(function($o) use ($numericId) {
                    return str_contains($o['id'], $numericId);
                });
            }
        }
        
        if (!$order) {
            return back()->with('error', 'Order not found. It may not be in the current filtered results.');
        }

        return view('orders.show', [
            'order' => $order
        ]);
    }
}