# Products API Documentation

## Overview
This API allows you to fetch products from any configured Shopify store in the Fairliar PIM system without requiring authentication.

## Endpoint

```
GET /api/products
```

## Parameters

| Parameter | Type   | Required | Description                                      | Example               |
|-----------|--------|----------|--------------------------------------------------|-----------------------|
| `store`   | string | Yes      | The name of the store (as configured in Settings) | `USA`                 |
| `status`  | string | No       | Product status filter (comma-separated)          | `active,draft`        |

### Status Values
- `active` - Published and active products (default)
- `draft` - Draft products
- `archived` - Archived products

Multiple statuses can be combined using commas: `active,draft`

## Example Requests

### Fetch all active products from USA store
```
GET /api/products?store=USA
```

### Fetch active and draft products from USA store
```
GET /api/products?store=USA&status=active,draft
```

### Fetch only draft products
```
GET /api/products?store=USA&status=draft
```

## Response Format

### Success Response (200 OK)
```json
{
    "success": true,
    "message": "Products fetched successfully",
    "store": {
        "name": "USA",
        "domain": "fairliarusa.myshopify.com"
    },
    "data": [
        {
            "id": "gid://shopify/Product/1234567890",
            "title": "Product Name",
            "handle": "product-name",
            "description": "Product description",
            "description_html": "<p>Product description</p>",
            "status": "ACTIVE",
            "created_at": "2026-01-15T10:30:00Z",
            "updated_at": "2026-02-01T14:20:00Z",
            "published_at": "2026-01-15T10:30:00Z",
            "vendor": "Brand Name",
            "product_type": "Category",
            "tags": ["tag1", "tag2"],
            "online_store_url": "https://store.com/products/product-name",
            "total_inventory": 100,
            "featured_image": {
                "id": "gid://shopify/ProductImage/1234567890",
                "url": "https://cdn.shopify.com/...",
                "alt_text": "Product image",
                "width": 1000,
                "height": 1000
            },
            "images": [
                {
                    "id": "gid://shopify/ProductImage/1234567890",
                    "url": "https://cdn.shopify.com/...",
                    "alt_text": "Product image",
                    "width": 1000,
                    "height": 1000
                }
            ],
            "variants": [
                {
                    "id": "gid://shopify/ProductVariant/1234567890",
                    "title": "Default Title",
                    "sku": "SKU-123",
                    "price": "29.99",
                    "compare_at_price": "39.99",
                    "position": 1,
                    "inventory_quantity": 50,
                    "available_for_sale": true,
                    "barcode": "123456789012",
                    "weight": 0.5,
                    "weight_unit": "KILOGRAMS",
                    "image": {
                        "id": "gid://shopify/ProductImage/1234567890",
                        "url": "https://cdn.shopify.com/...",
                        "alt_text": "Variant image"
                    },
                    "selected_options": [
                        {
                            "name": "Size",
                            "value": "Medium"
                        }
                    ]
                }
            ],
            "options": [
                {
                    "id": "gid://shopify/ProductOption/1234567890",
                    "name": "Size",
                    "position": 1,
                    "values": ["Small", "Medium", "Large"]
                }
            ]
        }
    ],
    "count": 1
}
```

### Error Response - Store Not Found (404)
```json
{
    "success": false,
    "message": "Store not found or inactive",
    "data": []
}
```

### Error Response - Server Error (500)
```json
{
    "success": false,
    "message": "Error fetching products: [error details]",
    "data": []
}
```

### Error Response - Validation Error (422)
```json
{
    "message": "The store field is required.",
    "errors": {
        "store": [
            "The store field is required."
        ]
    }
}
```

## Notes

1. **No Authentication Required**: This is a public API endpoint that does not require authentication tokens.

2. **Store Configuration**: The store must be configured in the PIM system's Settings > Store API Keys section and marked as active.

3. **Rate Limits**: This endpoint is subject to Shopify's API rate limits. The system will fetch all products in batches of 250 (Shopify's maximum).

4. **Performance**: Fetching all products may take some time for stores with large catalogs. Consider implementing pagination for better performance in production.

## Testing

### Using cURL
```bash
curl "http://your-domain.com/api/products?store=USA&status=active"
```

### Using Postman
1. Method: GET
2. URL: `http://your-domain.com/api/products`
3. Query Parameters:
   - `store`: USA
   - `status`: active

### Using Browser
Simply navigate to:
```
http://your-domain.com/api/products?store=USA
```

## Error Handling

The API includes comprehensive error logging. Check `storage/logs/laravel.log` for detailed error information if requests fail.

## Future Enhancements

Potential improvements for production use:
- Add pagination support
- Implement caching for frequently requested data
- Add API authentication with rate limiting
- Support for additional filters (vendor, product_type, tags, etc.)
- Collection-based filtering
