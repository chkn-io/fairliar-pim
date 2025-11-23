# Shopify Stock Sync Command

## Overview
This command syncs stock from the warehouse to Shopify for variants that have been marked as syncable (where `custom.pim_sync = 'true'`).

## Command Usage

### Run manually:
```bash
php artisan shopify:sync-stock
```

### Dry run (preview changes without syncing):
```bash
php artisan shopify:sync-stock --dry-run
```

### With specific location ID:
```bash
php artisan shopify:sync-stock --location=gid://shopify/Location/123456
```

### Dry run with specific location:
```bash
php artisan shopify:sync-stock --dry-run --location=gid://shopify/Location/123456
```

## Configuration

The command automatically reads the default location from your database settings (Settings > Warehouse > Default Location).

Alternatively, you can add it to your `.env` file:

```env
SHOPIFY_DEFAULT_LOCATION_ID=gid://shopify/Location/YOUR_LOCATION_ID
```

To find your location ID:
1. Go to Shopify Admin > Settings > Locations
2. Click on your location
3. The location ID is in the URL: `https://admin.shopify.com/store/YOUR_STORE/settings/locations/LOCATION_ID`
4. Format it as: `gid://shopify/Location/LOCATION_ID`

## How It Works

1. **Fetch Variants**: Retrieves all active variants from Shopify
2. **Filter**: Keeps only variants where `custom.pim_sync = 'true'`
3. **Match**: Finds corresponding warehouse stock from the `warehouse_variants` table
4. **Compare**: Checks if Shopify stock differs from warehouse stock
5. **Sync**: Updates Shopify inventory to match warehouse stock (only when different)

## Automation with CRON

### Hostinger CRON Setup:

**File**: `cron-sync-shopify.php`

**Command**:
```bash
/usr/bin/php /home/[your-username]/domains/pim-fairliar.com/public_html/cron-sync-shopify.php
```

**Schedule Examples**:
- Every 30 minutes: `*/30 * * * *`
- Every hour: `0 * * * *`
- Every 2 hours: `0 */2 * * *`
- Every 4 hours: `0 */4 * * *`

**Recommended**: Run every 30 minutes to keep stock in sync

### Log File
All cron executions are logged to:
```
storage/logs/cron-shopify-sync.log
```

## Complete Automation Setup

For a complete stock sync system, set up both cron jobs:

1. **Warehouse Sync** (Daily at 2 AM):
   ```
   0 2 * * * /usr/bin/php /path/to/cron-sync-warehouse.php
   ```
   - Updates local warehouse variants table

2. **Shopify Stock Sync** (Every 30 minutes):
   ```
   */30 * * * * /usr/bin/php /path/to/cron-sync-shopify.php
   ```
   - Syncs stock to Shopify for enabled variants

## Output

### Normal Mode
The command shows:
- Total variants with `pim_sync = 'true'`
- Progress bar
- Success count (stock synced)
- Skipped count (not in warehouse or stock already matches)
- Failed count (errors)

Example output:
```
Starting Shopify stock sync...
Location ID: gid://shopify/Location/123456

Fetching variants with pim_sync = true from Shopify...
Found 150 variants to sync

 150/150 [============================] 100% | Success: 45

✅ Sync complete!
📊 Synced: 45
⏭️  Skipped: 105 (not in warehouse or stock matches)
```

### Dry Run Mode
Shows a detailed table of what would be changed:

```bash
php artisan shopify:sync-stock --dry-run
```

Example output:
```
🔍 DRY RUN MODE - No changes will be made

Starting Shopify stock sync...
Location ID: gid://shopify/Location/123456

Fetching variants with pim_sync = true from Shopify...
Found 150 variants to sync

 150/150 [============================] 100% | Success: 45

🔍 DRY RUN COMPLETE - No changes were made

📋 Changes that would be made:

+-------------+--------------------------------+--------------------------------+---------+-----------+------+
| SKU         | Product                        | Variant                        | Shopify | Warehouse | Diff |
+-------------+--------------------------------+--------------------------------+---------+-----------+------+
| PROD-001    | Premium Cotton T-Shirt         | Small / Blue                   | 10      | 25        | +15  |
| PROD-002    | Classic Jeans                  | Medium / Black                 | 5       | 0         | -5   |
| PROD-003    | Summer Dress                   | Large / Red                    | 0       | 50        | +50  |
+-------------+--------------------------------+--------------------------------+---------+-----------+------+

✅ Sync complete!
📊 Would sync: 45
⏭️  Skipped: 105 (not in warehouse or stock matches)

To execute the sync, run without --dry-run flag:
  php artisan shopify:sync-stock
```

## Requirements

- Shopify API token with `write_inventory` scope
- `warehouse_variants` table must be populated (run `php artisan warehouse:sync` first)
- Default location configured in Settings > Warehouse (or in `.env`)

## Workflow

1. **First time setup**:
   ```bash
   # Sync warehouse data first
   php artisan warehouse:sync
   
   # Preview what would be synced
   php artisan shopify:sync-stock --dry-run
   
   # If everything looks good, run the actual sync
   php artisan shopify:sync-stock
   ```

2. **Regular usage**:
   - Warehouse sync runs daily (via cron)
   - Shopify stock sync runs every 30 minutes (via cron)
   - Use `--dry-run` anytime to preview changes before syncing

## Troubleshooting

**Error: "No location ID configured"**
- Go to Settings > Warehouse and set the Default Location
- Or add `SHOPIFY_DEFAULT_LOCATION_ID` to your `.env` file

**Error: "Failed to update inventory"**
- Check that your Shopify API token has `write_inventory` scope
- Verify the location ID is correct

**Variants skipped**
- Variant not in `warehouse_variants` table (run `warehouse:sync` first)
- Stock already matches between Shopify and warehouse
- Variant doesn't have `custom.pim_sync = 'true'`

**Preview before syncing**
- Always use `--dry-run` first to see what would be changed
- The dry run shows a table with all variants that would be updated
