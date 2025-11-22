@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>📦 Stock Sync</h1>
        <div>
            @if(auth()->user()->role === 'admin')
            <button class="btn btn-warning me-2" onclick="syncWarehouse()">
                ⚙️ Sync Warehouse
            </button>
            @endif
            @if(count($syncData) > 0)
            <a href="{{ route('stock-sync.export', ['location_id' => $selectedLocation]) }}" 
               class="btn btn-success">
                📥 Export All
            </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Warehouse Sync Status -->
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <strong>📊 Warehouse Data Status:</strong>
        @if($warehouseVariantsCount > 0)
            {{ number_format($warehouseVariantsCount) }} variants synced
            @if($lastSyncTime)
                (Last sync: {{ $lastSyncTime->diffForHumans() }})
            @endif
        @else
            No warehouse data synced yet. Click "Sync Warehouse" to fetch data from Sellmate.
        @endif
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <!-- Search and Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('stock-sync.index') }}" id="filterForm">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" 
                               class="form-control" 
                               id="search" 
                               name="search" 
                               value="{{ $search }}"
                               placeholder="Product name, SKU, barcode, variant ID...">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="location_id" class="form-label">Location</label>
                        <select class="form-select" id="location_id" name="location_id">
                            <option value="">All Locations (Total Stock)</option>
                            @foreach($locations as $location)
                                <option value="{{ $location['id'] }}" {{ $selectedLocation == $location['id'] ? 'selected' : '' }}>
                                    {{ $location['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="sort" class="form-label">Sort By</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="product_asc" {{ request('sort') == 'product_asc' ? 'selected' : '' }}>Product Name (A-Z)</option>
                            <option value="product_desc" {{ request('sort') == 'product_desc' ? 'selected' : '' }}>Product Name (Z-A)</option>
                            <option value="sku_asc" {{ request('sort') == 'sku_asc' ? 'selected' : '' }}>SKU (A-Z)</option>
                            <option value="sku_desc" {{ request('sort') == 'sku_desc' ? 'selected' : '' }}>SKU (Z-A)</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="sync_status" class="form-label">Sync Status</label>
                        <select class="form-select" id="sync_status" name="sync_status">
                            <option value="" {{ request('sync_status') == '' ? 'selected' : '' }}>All Statuses</option>
                            <option value="included" {{ request('sync_status') == 'included' ? 'selected' : '' }}>Included Only</option>
                            <option value="excluded" {{ request('sync_status') == 'excluded' ? 'selected' : '' }}>Excluded Only</option>
                            <option value="unset" {{ request('sync_status') == 'unset' ? 'selected' : '' }}>Unset Only</option>
                        </select>
                    </div>
                    
                    <div class="col-md-1 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            🔍
                        </button>
                        <a href="{{ route('stock-sync.index') }}" class="btn btn-outline-secondary">
                            🔄
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

   

    <!-- Results Table -->
    @if(count($syncData) > 0)
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Stock Comparison @if($search)(Search Results)@else(Page {{ $currentPage }}@if($lastPage > $currentPage) of {{ $lastPage }}+@endif)@endif</span>
            <span class="badge bg-secondary">{{ count($syncData) }} variants on this page</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Product</th>
                            <th>Variant</th>
                            <th>SKU</th>
                            <th>Barcode</th>
                            <th class="text-center">Sync Status</th>
                            <th class="text-center">Shopify Stock</th>
                            <th class="text-center">Warehouse Stock</th>
                            <th class="text-center">Difference</th>
                            <th class="text-center" style="min-width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($syncData as $item)
                        @php
                            $diff = null;
                            $diffClass = '';
                            if ($item['warehouse_stock'] !== null) {
                                $diff = $item['shopify_stock'] - $item['warehouse_stock'];
                                if ($diff > 0) {
                                    $diffClass = 'text-success';
                                } elseif ($diff < 0) {
                                    $diffClass = 'text-danger';
                                } else {
                                    $diffClass = 'text-muted';
                                }
                            }
                            
                            // Check pim_sync status: 'true' = included, 'false' = excluded, empty/blank = unset
                            $pimSync = $item['pim_sync'] ?? '';
                            $isSyncEnabled = $pimSync === 'true';
                            $isExplicitlyExcluded = $pimSync === 'false';
                            
                            // Get store domain for Shopify link
                            $storeDomain = config('shopify.store_domain');
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $item['product_title'] }}</div>
                                <small class="text-muted">ID: {{ $item['variant_id'] }}</small>
                            </td>
                            <td>{{ $item['variant_title'] }}</td>
                            <td><code>{{ $item['sku'] ?: '-' }}</code></td>
                            <td><code>{{ $item['barcode'] ?: '-' }}</code></td>
                            <td class="text-center">
                                @if($isSyncEnabled)
                                    <span class="badge bg-success" title="This variant is included in PIM sync">✓ Included</span>
                                @elseif($isExplicitlyExcluded)
                                    <span class="badge bg-warning text-dark" title="This variant is excluded from PIM sync">✗ Excluded</span>
                                @else
                                    <span class="badge bg-secondary" title="Sync status not set for this variant">○ Unset</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary">{{ $item['shopify_stock'] }}</span>
                            </td>
                            <td class="text-center">
                                <span class="warehouse-stock" data-variant-id="{{ $item['variant_id'] }}">
                                    <span class="spinner-border spinner-border-sm text-secondary" role="status"></span>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="stock-difference" data-variant-id="{{ $item['variant_id'] }}" data-shopify-stock="{{ $item['shopify_stock'] }}">
                                    <span class="text-muted">-</span>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center flex-wrap">
                                    <!-- View on Shopify -->
                                    <a href="https://{{ $storeDomain }}/admin/products/{{ $item['product_id'] }}/variants/{{ $item['variant_id'] }}" 
                                       target="_blank" 
                                       class="btn btn-sm btn-outline-secondary"
                                       title="View variant on Shopify"
                                       style="min-width: 80px;">
                                        <i class="bi bi-box-arrow-up-right"></i> View
                                    </a>
                                    
                                    <!-- Toggle PIM Sync Buttons -->
                                    @if($isSyncEnabled)
                                        <!-- Only show Exclude button when included -->
                                        <button onclick="togglePimSync('{{ $item['variant_gid'] }}', '{{ $item['product_gid'] }}', '{{ addslashes($item['product_title']) }}', true)" 
                                                class="btn btn-sm btn-warning"
                                                title="Exclude this variant from PIM sync"
                                                style="min-width: 80px;">
                                            <i class="bi bi-x-circle"></i> Exclude
                                        </button>
                                    @elseif($isExplicitlyExcluded)
                                        <!-- Only show Include button when excluded -->
                                        <button onclick="togglePimSync('{{ $item['variant_gid'] }}', '{{ $item['product_gid'] }}', '{{ addslashes($item['product_title']) }}', false)" 
                                                class="btn btn-sm btn-success"
                                                title="Include this variant in PIM sync"
                                                style="min-width: 80px;">
                                            <i class="bi bi-check-circle"></i> Include
                                        </button>
                                    @else
                                        <!-- Show both Include and Exclude buttons when unset -->
                                        <button onclick="togglePimSync('{{ $item['variant_gid'] }}', '{{ $item['product_gid'] }}', '{{ addslashes($item['product_title']) }}', false)" 
                                                class="btn btn-sm btn-success"
                                                title="Include this variant in PIM sync"
                                                style="min-width: 80px;">
                                            <i class="bi bi-check-circle"></i> Include
                                        </button>
                                        <button onclick="togglePimSync('{{ $item['variant_gid'] }}', '{{ $item['product_gid'] }}', '{{ addslashes($item['product_title']) }}', true)" 
                                                class="btn btn-sm btn-outline-warning"
                                                title="Exclude this variant from PIM sync"
                                                style="min-width: 80px;">
                                            <i class="bi bi-x-circle"></i> Exclude
                                        </button>
                                    @endif
                                    
                                    <!-- Sync Stock (only show if variant is included in sync) -->
                                    @if($isSyncEnabled && $selectedLocation)
                                        <button class="btn btn-sm btn-info sync-stock-btn" 
                                                data-variant-id="{{ $item['variant_id'] }}"
                                                data-inventory-item-id="{{ $item['inventory_item_id'] }}"
                                                data-location-id="{{ $selectedLocation }}"
                                                data-product-title="{{ addslashes($item['product_title']) }}"
                                                data-variant-title="{{ addslashes($item['variant_title']) }}"
                                                data-shopify-stock="{{ $item['shopify_stock'] }}"
                                                title="Sync stock from warehouse to Shopify"
                                                style="min-width: 80px;"
                                                disabled>
                                            <i class="bi bi-arrow-repeat"></i> Sync
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        @if($lastPage > 1)
        <div class="card-footer">
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mb-0">
                    <!-- Previous Button -->
                    <li class="page-item {{ $currentPage == 1 ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ route('stock-sync.index', array_merge(request()->except('page'), ['page' => $currentPage - 1])) }}">
                            ← Previous
                        </a>
                    </li>
                    
                    <!-- First Page -->
                    @if($currentPage > 3)
                    <li class="page-item">
                        <a class="page-link" href="{{ route('stock-sync.index', array_merge(request()->except('page'), ['page' => 1])) }}">1</a>
                    </li>
                    @if($currentPage > 4)
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                    @endif
                    @endif
                    
                    <!-- Page Numbers -->
                    @for($i = max(1, $currentPage - 2); $i <= min($lastPage, $currentPage + 2); $i++)
                    <li class="page-item {{ $i == $currentPage ? 'active' : '' }}">
                        <a class="page-link" href="{{ route('stock-sync.index', array_merge(request()->except('page'), ['page' => $i])) }}">
                            {{ $i }}
                        </a>
                    </li>
                    @endfor
                    
                    <!-- Last Page (if search mode with known total) -->
                    @if($search && $currentPage < $lastPage - 2)
                    @if($currentPage < $lastPage - 3)
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                    @endif
                    <li class="page-item">
                        <a class="page-link" href="{{ route('stock-sync.index', array_merge(request()->except('page'), ['page' => $lastPage])) }}">{{ $lastPage }}</a>
                    </li>
                    @endif
                    
                    <!-- Next Button -->
                    <li class="page-item {{ $currentPage >= $lastPage ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ route('stock-sync.index', array_merge(request()->except('page'), ['page' => $currentPage + 1])) }}">
                            Next →
                        </a>
                    </li>
                </ul>
            </nav>
            <div class="text-center text-muted mt-2">
                <small>
                    @if($search)
                        Page {{ $currentPage }} of {{ $lastPage }} ({{ $totalVariants }} total results)
                    @else
                        Page {{ $currentPage }} • 20 variants per page • Click Next to load more
                    @endif
                </small>
            </div>
        </div>
        @endif
    </div>
    @else
    <div class="alert alert-info">
        <h5>ℹ️ No results found</h5>
        <p class="mb-0">Try adjusting your search criteria or filters.</p>
    </div>
    @endif
</div>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<!-- Loading Overlay -->
<div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center;">
    <div class="text-center">
        <div class="spinner-border text-light" role="status" style="width: 4rem; height: 4rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="text-light mt-3">
            <h4 id="loadingMessage">Loading stock data...</h4>
            <p id="loadingSubtext">This may take a moment as we fetch data from Shopify.</p>
        </div>
    </div>
</div>

<script>
function showLoading(message = 'Loading stock data...', subtext = 'This may take a moment as we fetch data from Shopify.') {
    document.getElementById('loadingMessage').textContent = message;
    document.getElementById('loadingSubtext').textContent = subtext;
    document.getElementById('loadingOverlay').style.display = 'flex';
}

function togglePimSync(variantGid, productGid, productTitle, exclude) {
    const action = exclude ? 'exclude from' : 'include in';
    const actionVerb = exclude ? 'Exclude' : 'Include';
    
    Swal.fire({
        title: `${actionVerb} from PIM Sync?`,
        html: `Are you sure you want to <strong>${action}</strong> PIM sync?<br><br><small class="text-muted">${productTitle}</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: exclude ? '#ffc107' : '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Yes, ${actionVerb}`,
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading('Updating sync status...', 'Please wait...');
            
            fetch('{{ route('stock-sync.toggle-pim-sync') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    variant_gid: variantGid,
                    product_gid: productGid,
                    exclude: exclude
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingOverlay').style.display = 'none';
                
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message,
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            })
            .catch(error => {
                document.getElementById('loadingOverlay').style.display = 'none';
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to update sync status: ' + error.message,
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            });
        }
    });
}

function syncStock(variantId, inventoryItemId, locationId, warehouseStock, productTitle, variantTitle) {
    Swal.fire({
        title: 'Sync Stock from Warehouse?',
        html: `This will update Shopify stock to <strong>${warehouseStock}</strong> units.<br><br>` +
              `<small class="text-muted">${productTitle} - ${variantTitle}</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0dcaf0',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Sync Stock',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading('Syncing stock...', 'Updating Shopify inventory...');
            
            fetch('{{ route('stock-sync.sync-stock') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    variant_id: variantId,
                    inventory_item_id: inventoryItemId,
                    location_id: locationId,
                    warehouse_stock: warehouseStock
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingOverlay').style.display = 'none';
                
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        html: `Stock synced successfully!<br>New stock level: <strong>${data.new_stock}</strong>`,
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message,
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            })
            .catch(error => {
                document.getElementById('loadingOverlay').style.display = 'none';
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to sync stock: ' + error.message,
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            });
        }
    });
}

function syncWarehouse() {
    Swal.fire({
        title: 'Sync Warehouse Data?',
        text: 'This will sync all warehouse variants from Sellmate API. This may take several minutes.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Start Sync',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading('Syncing warehouse data...', 'Fetching all variants from Sellmate API. This will take several minutes.');
            
            fetch('{{ route('warehouse.sync') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingOverlay').style.display = 'none';
                
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        html: `Warehouse sync completed successfully!<br>Variants synced: <strong>${data.count}</strong>`,
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Sync failed: ' + data.message,
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            })
            .catch(error => {
                document.getElementById('loadingOverlay').style.display = 'none';
                Swal.fire({
                    title: 'Error!',
                    text: 'Sync failed: ' + error.message,
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            });
        }
    });
}

// Show loading on pagination clicks
document.addEventListener('DOMContentLoaded', function() {
    const paginationLinks = document.querySelectorAll('.pagination a');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!this.parentElement.classList.contains('disabled')) {
                showLoading('Loading page...', 'Fetching variants from Shopify...');
            }
        });
    });
    
    // Fetch warehouse stocks after page load
    fetchWarehouseStocks();
});

function fetchWarehouseStocks() {
    // Get all variant IDs from the table
    const variantElements = document.querySelectorAll('.warehouse-stock[data-variant-id]');
    const variantIds = Array.from(variantElements).map(el => el.dataset.variantId);
    
    if (variantIds.length === 0) return;
    
    // Fetch warehouse stocks via AJAX
    fetch('{{ route('stock-sync.get-warehouse-stock') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ variant_ids: variantIds })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update warehouse stock for each variant
            variantElements.forEach(el => {
                const variantId = el.dataset.variantId;
                const warehouseStock = data.stocks[variantId];
                
                if (warehouseStock !== undefined && warehouseStock !== null) {
                    el.innerHTML = `<span class="badge bg-info">${warehouseStock}</span>`;
                } else {
                    el.innerHTML = '<span class="badge bg-secondary">N/A</span>';
                }
            });
            
            // Update difference column
            document.querySelectorAll('.stock-difference[data-variant-id]').forEach(el => {
                const variantId = el.dataset.variantId;
                const shopifyStock = parseInt(el.dataset.shopifyStock);
                const warehouseStock = data.stocks[variantId];
                
                if (warehouseStock !== undefined && warehouseStock !== null) {
                    const diff = shopifyStock - warehouseStock;
                    let diffClass = 'text-muted';
                    if (diff > 0) diffClass = 'text-success';
                    else if (diff < 0) diffClass = 'text-danger';
                    
                    el.innerHTML = `<span class="fw-bold ${diffClass}">${diff > 0 ? '+' : ''}${diff}</span>`;
                } else {
                    el.innerHTML = '<span class="text-muted">-</span>';
                }
            });
            
            // Enable sync buttons with warehouse stock data
            document.querySelectorAll('.sync-stock-btn[data-variant-id]').forEach(btn => {
                const variantId = btn.dataset.variantId;
                const warehouseStock = data.stocks[variantId];
                const shopifyStock = parseInt(btn.dataset.shopifyStock);
                
                if (warehouseStock !== undefined && warehouseStock !== null) {
                    btn.dataset.warehouseStock = warehouseStock;
                    // Enable button only if stocks don't match
                    if (shopifyStock !== warehouseStock) {
                        btn.disabled = false;
                        btn.onclick = function() {
                            syncStock(
                                btn.dataset.variantId,
                                btn.dataset.inventoryItemId,
                                btn.dataset.locationId,
                                warehouseStock,
                                btn.dataset.productTitle,
                                btn.dataset.variantTitle
                            );
                        };
                    }
                }
            });
        }
    })
    .catch(error => {
        console.error('Failed to fetch warehouse stocks:', error);
        // Show N/A on error
        variantElements.forEach(el => {
            el.innerHTML = '<span class="badge bg-secondary">N/A</span>';
        });
    });
}
</script>

<style>
.sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
}

.table-hover tbody tr:hover {
    background-color: rgba(0,0,0,.02);
}

code {
    color: #d63384;
    font-size: 0.875em;
}
</style>
@endsection
