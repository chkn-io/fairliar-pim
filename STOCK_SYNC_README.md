# Stock Sync Feature

## Overview
The Stock Sync page allows you to compare inventory levels between your Shopify store and the warehouse system (Sellmate API). It helps identify variants that need stock synchronization.

## Features

### 1. **Search & Filter**
- Search by product name, SKU, barcode, or variant ID
- Filter by Shopify location
- View all variants or search specific items

### 2. **Stock Comparison Table**
The table displays:
- **Status**: Visual indicator showing if variants are in sync, need sync, or not matched
  - ✅ **In Sync**: Shopify and warehouse stock match
  - ⚠️ **Needs Sync**: Stock levels differ between systems
  - ❌ **Not Matched**: Variant exists in Shopify but not found in warehouse
  
- **Product Information**: Title, variant details, SKU, barcode
- **Stock Levels**: Side-by-side comparison of Shopify vs Warehouse stock
- **Difference**: Shows the stock difference (warehouse - shopify)
- **Warehouse Info**: Warehouse-specific SKU and barcode for reference

### 3. **Summary Statistics**
- Total variants displayed
- Count of variants needing sync
- Count of variants already in sync

### 4. **Export Functionality**
Export the complete stock comparison to CSV for:
- Bulk updates
- Record keeping
- Analysis in Excel/Google Sheets

### 5. **Cache Management**
- Warehouse data is cached for 5 minutes for performance
- Use "Clear Cache" button to force refresh warehouse data

## How It Works

### Data Matching
Variants are matched between systems using the Shopify variant ID stored in the warehouse API:
- The warehouse API stores Shopify variant IDs in `option_has_code_by_shop` field
- Shop ID 28 represents the Shopify shop
- The system matches variants and compares stock levels

### Stock Comparison
- **Shopify Stock**: Fetched via GraphQL API with location-specific inventory levels
- **Warehouse Stock**: Aggregated total stock from all warehouse locations
- **Needs Sync**: Flagged when stock levels don't match

## Configuration

### Environment Variables (.env)
```env
WAREHOUSE_API_URL=https://c-api.sellmate.co.kr/external/fairliar/productVariants
WAREHOUSE_API_TOKEN=your_jwt_token_here
```

### Warehouse API Details
- **Endpoint**: GET `/external/fairliar/productVariants`
- **Authentication**: Bearer token
- **Response**: Paginated list of product variants with stock information
- **Pagination**: Handles 1000+ pages automatically

## Usage Guide

### Basic Search
1. Navigate to **Stock Sync** from the main menu
2. Enter search terms (product name, SKU, etc.)
3. Select a location (optional)
4. Click **Search**

### View All Variants
1. Click **Show All** button
2. System fetches all Shopify variants and compares with warehouse
3. Results are paginated (50 per page)

### Export Data
1. Perform a search or view all
2. Click **Export All** button
3. CSV file downloads with complete comparison

### Refresh Warehouse Data
If you've recently updated warehouse stock:
1. Click **Clear Cache** button
2. Perform your search again to fetch fresh data

## Technical Details

### Services
- **WarehouseService**: Handles API calls to warehouse system
- **ShopifyService**: Extended with `getProductVariants()` method
- **StockSyncController**: Orchestrates data fetching and comparison

### Performance
- Warehouse data is cached for 5 minutes to reduce API calls
- Shopify data is fetched per request (not cached)
- Pagination limits results to 50 per page for performance

### Data Flow
```
User Request
    ↓
StockSyncController
    ↓
    ├→ ShopifyService.getProductVariants()
    │       ↓ (GraphQL API)
    │   Shopify Data
    │
    └→ WarehouseService.getShopifyStockMap()
            ↓ (REST API + Cache)
        Warehouse Data
    
Data Comparison & Display
```

## Troubleshooting

### No Results Found
- Check if variants exist in both systems
- Verify Shopify variant IDs are properly stored in warehouse (shop_id 28)
- Clear cache and try again

### Slow Performance
- Use search instead of "Show All" for large catalogs
- Cache is automatically used for warehouse data
- Consider filtering by location to reduce dataset

### Stock Doesn't Match
- Check if variant IDs are correctly mapped
- Verify warehouse stock is up to date
- Clear cache to ensure fresh data

## API Rate Limits
- **Shopify**: GraphQL cost limits apply (optimized queries used)
- **Warehouse**: No specific rate limits mentioned, cached to reduce calls

## Future Enhancements
Potential improvements:
- Bulk sync functionality to update Shopify from warehouse
- Scheduled sync reports
- Stock difference alerts
- Historical comparison tracking
