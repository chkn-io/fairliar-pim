<?php

namespace App\Http\Controllers;

use App\Services\ShopifyService;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    private ShopifyService $shopifyService;

    public function __construct(ShopifyService $shopifyService)
    {
        $this->shopifyService = $shopifyService;
    }

    /**
     * Display products listing with filters and preview.
     */
    public function index(Request $request)
    {
        $filters = [
            'tag' => $request->get('tag', ''),
            'sku' => $request->get('sku', ''),
            'name' => $request->get('name', ''),
            'status' => $request->get('status', 'active'),
            'page' => max(1, (int) $request->get('page', 1)),
        ];

        $products = [];
        $errors = [];
        $pagination = [
            'current_page' => $filters['page'],
            'has_next' => false,
            'has_prev' => $filters['page'] > 1,
            'total_shown' => 0,
        ];

        if ($request->has('preview')) {
            try {
                $result = $this->shopifyService->getProductsWithVariantsPage($filters, 20, $filters['page']);
                $products = $result['products'] ?? [];
                $errors = $result['errors'] ?? [];
                $pagination['has_next'] = $result['has_next_page'] ?? false;
                $pagination['total_shown'] = count($products);
            } catch (\Exception $e) {
                $errors[] = 'Failed to fetch products: ' . $e->getMessage();
            }
        }

        return view('products.index', compact('filters', 'products', 'errors', 'pagination'));
    }

    /**
     * Export filtered products (with variants) to CSV.
     */
    public function export(Request $request)
    {
        $filters = [
            'tag' => $request->get('tag', ''),
            'sku' => $request->get('sku', ''),
            'name' => $request->get('name', ''),
            'status' => $request->get('status', 'active'),
        ];

        // Preflight request to surface auth/schema errors before streaming
        $preflight = $this->shopifyService->getProductsWithVariantsBatch($filters, 1, null);
        if (!empty($preflight['errors'])) {
            return back()->with('error', 'Failed to export products: ' . implode(', ', array_map(function ($e) {
                return is_array($e) ? json_encode($e) : (string) $e;
            }, $preflight['errors'])));
        }

        $filename = 'products-export-' . date('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $shopifyService = $this->shopifyService;

        return response()->stream(function () use ($shopifyService, $filters) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Product ID',
                'Handle',
                'Title',
                'Vendor',
                'Type',
                'Tags',
                'Updated At',
                'Status',
                'URL',
                'Category',
                'Variant ID',
                'Variant Name',
                'Variant SKU',
                'Variant Barcode',
                'Variant Price',
            ]);

            $after = null;
            $hasNextPage = true;
            $iterations = 0;
            $maxIterations = 500; // safety

            while ($hasNextPage && $iterations < $maxIterations) {
                $batch = $shopifyService->getProductsWithVariantsBatch($filters, 20, $after);

                if (!empty($batch['errors'])) {
                    // Stop streaming on error
                    break;
                }

                foreach (($batch['products'] ?? []) as $product) {
                    $productId = $product['product_id'] ?? '';
                    $handle = $product['handle'] ?? '';
                    $title = $product['title'] ?? '';
                    $vendor = $product['vendor'] ?? '';
                    $type = $product['type'] ?? '';
                    $tags = $product['tags'] ?? '';
                    $updatedAt = $product['updated_at'] ?? '';
                    $status = $product['status'] ?? '';
                    $url = $product['url'] ?? '';
                    $category = $product['category'] ?? '';

                    $variants = $product['variants'] ?? [];
                    if (empty($variants)) {
                        fputcsv($file, [
                            $productId,
                            $handle,
                            $title,
                            $vendor,
                            $type,
                            $tags,
                            $updatedAt,
                            $status,
                            $url,
                            $category,
                            '',
                            '',
                            '',
                            '',
                            '',
                        ]);
                        continue;
                    }

                    foreach ($variants as $variant) {
                        fputcsv($file, [
                            $productId,
                            $handle,
                            $title,
                            $vendor,
                            $type,
                            $tags,
                            $updatedAt,
                            $status,
                            $url,
                            $category,
                            $variant['variant_id'] ?? '',
                            $variant['variant_name'] ?? '',
                            $variant['sku'] ?? '',
                            $variant['barcode'] ?? '',
                            $variant['price'] ?? '',
                        ]);
                    }
                }

                $pageInfo = $batch['pageInfo'] ?? [];
                $hasNextPage = (bool) ($pageInfo['hasNextPage'] ?? false);
                $after = $pageInfo['endCursor'] ?? null;

                $iterations++;

                if (!$hasNextPage) {
                    break;
                }
            }

            fclose($file);
        }, 200, $headers);
    }
}
