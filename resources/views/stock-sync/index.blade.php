@extends('layouts.app')

@section('page-title', 'Stock Sync')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-3">
        <h1>📦 Stock Sync</h1>
        <div>
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

    <!-- Search and Filter Form -->
    <div class="card mb-4">
        <div class="card-header" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#searchFilterCollapse" aria-expanded="false">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="bi bi-search me-2"></i>Search & Filters
                </h6>
                <i class="bi bi-chevron-down" id="searchFilterIcon"></i>
            </div>
        </div>
        <div class="collapse" id="searchFilterCollapse">
            <div class="card-body">
                <form method="GET" action="{{ route('stock-sync.index') }}" id="filterForm">
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" 
                               class="form-control" 
                               id="search" 
                               name="search" 
                               value="{{ $search }}"
                               placeholder="Product name, SKU, barcode, variant ID...">
                    </div>
                    
                    <div class="col-12 col-md-2">
                        <label for="tag" class="form-label">Product Tag</label>
                        <input type="text" 
                               class="form-control" 
                               id="tag" 
                               name="tag" 
                               value="{{ $tag }}"
                               placeholder="e.g. 24ss, 26ss...">
                    </div>
                    
                    <div class="col-12 col-md-3">
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
                    
                    <div class="col-12 col-sm-6 col-md-2">
                        <label for="sort" class="form-label">Sort By</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="product_asc" {{ request('sort') == 'product_asc' ? 'selected' : '' }}>Product Name (A-Z)</option>
                            <option value="product_desc" {{ request('sort') == 'product_desc' ? 'selected' : '' }}>Product Name (Z-A)</option>
                            <option value="sku_asc" {{ request('sort') == 'sku_asc' ? 'selected' : '' }}>SKU (A-Z)</option>
                            <option value="sku_desc" {{ request('sort') == 'sku_desc' ? 'selected' : '' }}>SKU (Z-A)</option>
                        </select>
                    </div>
                    
                    <div class="col-12 col-sm-6 col-md-2">
                        <label for="sync_status" class="form-label">Sync Status</label>
                        <select class="form-select" id="sync_status" name="sync_status">
                            <option value="" {{ request('sync_status') == '' ? 'selected' : '' }}>All Statuses</option>
                            <option value="included" {{ request('sync_status') == 'included' ? 'selected' : '' }}>Included Only</option>
                            <option value="excluded" {{ request('sync_status') == 'excluded' ? 'selected' : '' }}>Excluded Only</option>
                            <option value="unset" {{ request('sync_status') == 'unset' ? 'selected' : '' }}>Unset Only</option>
                        </select>
                    </div>
                    
                    <div class="col-md-12 col-lg-2">
                        <label class="form-label d-none d-lg-block">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill p-0">
                                 Search
                            </button>
                            <a href="{{ route('stock-sync.index') }}" class="btn btn-outline-secondary flex-fill">
                                Reset
                            </a>
                        </div>
                    </div>
                </div>
            </form>
            </div>
        </div>
    </div>

    <!-- Bulk Update by Tag -->
    <div class="card mb-4">
        <div class="card-header" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#bulkUpdateCollapse" aria-expanded="false">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="bi bi-tags me-2"></i>Bulk Update by Tag
                </h6>
                <i class="bi bi-chevron-down" id="bulkUpdateIcon"></i>
            </div>
        </div>
        <div class="collapse" id="bulkUpdateCollapse">
            <div class="card-body">
            <form id="bulkTagForm">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <label for="bulk_tag" class="form-label">Product Tag *</label>
                        <input type="text" 
                               class="form-control" 
                               id="bulk_tag" 
                               name="bulk_tag" 
                               placeholder="e.g. 26ss"
                               required>
                    </div>
                    
                    <div class="col-12 col-md-3">
                        <label for="bulk_status" class="form-label">Action *</label>
                        <select class="form-select" id="bulk_status" name="bulk_status" required>
                            <option value="include">✅ Include in Sync (true)</option>
                            <option value="exclude">❌ Exclude from Sync (false)</option>
                            <option value="unset">⚪ Unset (empty)</option>
                        </select>
                    </div>
                    
                    <div class="col-12 col-md-2">
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   id="bulk_inverse" 
                                   name="bulk_inverse">
                            <label class="form-check-label" for="bulk_inverse">
                                Inverse (products WITHOUT this tag)
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-4">
                        <button type="submit" class="btn btn-primary w-100" id="bulk_submit_btn">
                            🚀 Start Bulk Update
                        </button>
                        <button type="button" class="btn btn-danger w-100 d-none" id="bulk_cancel_btn" onclick="cancelBulkUpdate()">
                            🛑 Cancel
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Progress Display -->
            <div id="bulkProgressContainer" class="mt-4 d-none">
                <div class="progress mb-3" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         id="bulkProgressBar"
                         role="progressbar" 
                         style="width: 0%"
                         aria-valuenow="0" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                        0 / 0
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Processing Log</span>
                        <button class="btn btn-sm btn-outline-secondary" onclick="clearBulkLog()">Clear</button>
                    </div>
                    <div class="card-body p-2" style="max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 0.85rem;" id="bulkLog">
                        <!-- Log entries will be added here -->
                    </div>
                </div>
                
                <!-- Summary -->
                <div id="bulkSummary" class="mt-3 d-none">
                    <div class="alert alert-info">
                        <strong>Summary:</strong>
                        <span id="bulkSummaryText"></span>
                    </div>
                    <div id="bulkFailedList" class="d-none">
                        <h6>Failed Variants:</h6>
                        <ul id="bulkFailedItems"></ul>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <!-- Results Table -->
    @if(count($syncData) > 0)
    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span>Stock Comparison @if($search)(Search Results)@else(Page {{ $currentPage }}@if($lastPage > $currentPage) of {{ $lastPage }}+@endif)@endif</span>
                    <span class="badge bg-secondary">{{ count($syncData) }} variants on this page</span>
                </div>
                <!-- Bulk Actions Toolbar -->
                <div id="bulkActionsToolbar" class="d-none">
                    <div class="d-flex gap-2 align-items-center">
                        <span class="text-muted small" id="selectedCount">0 selected</span>
                        <button class="btn btn-sm btn-success" onclick="bulkTogglePimSync(false)" title="Include selected variants in PIM sync">
                            <i class="bi bi-check-circle"></i> Include
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="bulkTogglePimSync(true)" title="Exclude selected variants from PIM sync">
                            <i class="bi bi-x-circle"></i> Exclude
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="clearAllSelections()" title="Clear selection">
                            <i class="bi bi-x"></i> Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" class="form-check-input" id="selectAll" title="Select all variants on this page">
                            </th>
                            <th>Product</th>
                            <th>Variant</th>
                            <th>SKU</th>
                            <th>Barcode</th>
                            <th>Tags</th>
                            <th class="text-center">Sync Status</th>
                            <th class="text-center">Shopify Stock</th>
                            <th class="text-center">Warehouse Stock</th>
                            <th class="text-center">Difference</th>
                            <th class="text-center">Last Synced</th>
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
                                <input type="checkbox" 
                                       class="form-check-input variant-checkbox" 
                                       data-variant-gid="{{ $item['variant_gid'] }}" 
                                       data-product-gid="{{ $item['product_gid'] }}" 
                                       data-product-title="{{ addslashes($item['product_title']) }}" 
                                       data-variant-title="{{ addslashes($item['variant_title']) }}"
                                       data-variant-id="{{ $item['variant_id'] }}"
                                       data-inventory-item-id="{{ $item['inventory_item_id'] }}"
                                       data-shopify-stock="{{ $item['shopify_stock'] }}">
                            </td>
                            <td>
                                <div class="fw-bold">{{ $item['product_title'] }}</div>
                                <small class="text-muted">ID: {{ $item['variant_id'] }}</small>
                            </td>
                            <td>{{ $item['variant_title'] }}</td>
                            <td><code>{{ $item['sku'] ?: '-' }}</code></td>
                            <td><code>{{ $item['barcode'] ?: '-' }}</code></td>
                            <td>
                                @if(!empty($item['product_tags']))
                                    @foreach(array_slice(array_map('trim', explode(',', $item['product_tags'])), 0, 3) as $tag)
                                        <span class="badge bg-info text-dark me-1 mb-1">{{ $tag }}</span>
                                    @endforeach
                                    @if(count(explode(',', $item['product_tags'])) > 3)
                                        <span class="badge bg-light text-muted">+{{ count(explode(',', $item['product_tags'])) - 3 }}</span>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
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
                                <span class="warehouse-stock" 
                                      data-variant-id="{{ $item['variant_id'] }}"
                                      data-sku="{{ $item['sku'] }}">
                                    <span class="spinner-border spinner-border-sm text-secondary" role="status"></span>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="stock-difference" data-variant-id="{{ $item['variant_id'] }}" data-shopify-stock="{{ $item['shopify_stock'] }}">
                                    <span class="text-muted">-</span>
                                </span>
                            </td>
                            <td class="text-center sync-timestamp-cell" data-variant-id="{{ $item['variant_id'] }}">
                                @if(!empty($item['sync_timestamp']))
                                    <small class="text-muted" title="{{ $item['sync_timestamp'] }}">
                                        {{ \Carbon\Carbon::parse($item['sync_timestamp'])->diffForHumans() }}
                                    </small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
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
// Bulk Selection Management
function updateBulkActionsToolbar() {
    const checkboxes = document.querySelectorAll('.variant-checkbox:checked');
    const toolbar = document.getElementById('bulkActionsToolbar');
    const countEl = document.getElementById('selectedCount');
    
    if (checkboxes.length > 0) {
        toolbar.classList.remove('d-none');
        countEl.textContent = `${checkboxes.length} selected`;
    } else {
        toolbar.classList.add('d-none');
    }
}

function clearAllSelections() {
    document.querySelectorAll('.variant-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    updateBulkActionsToolbar();
}

// Select All functionality
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.variant-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    updateBulkActionsToolbar();
});

// Individual checkbox change
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('variant-checkbox')) {
        updateBulkActionsToolbar();
        
        // Update "select all" checkbox state
        const allCheckboxes = document.querySelectorAll('.variant-checkbox');
        const checkedCheckboxes = document.querySelectorAll('.variant-checkbox:checked');
        const selectAllCheckbox = document.getElementById('selectAll');
        
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = allCheckboxes.length === checkedCheckboxes.length && allCheckboxes.length > 0;
            selectAllCheckbox.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
        }
    }
});

function bulkTogglePimSync(exclude) {
    const checkboxes = document.querySelectorAll('.variant-checkbox:checked');
    
    if (checkboxes.length === 0) {
        Swal.fire('No Selection', 'Please select at least one variant.', 'warning');
        return;
    }
    
    const action = exclude ? 'exclude from' : 'include in';
    const actionVerb = exclude ? 'Exclude' : 'Include';
    const count = checkboxes.length;
    
    Swal.fire({
        title: `${actionVerb} ${count} Variant${count > 1 ? 's' : ''}?`,
        html: `Are you sure you want to <strong>${action}</strong> PIM sync for <strong>${count}</strong> selected variant${count > 1 ? 's' : ''}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: exclude ? '#ffc107' : '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Yes, ${actionVerb} All`,
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            pauseWarehouseRequests();
            showLoading(`Updating ${count} variants...`, 'Please wait...');
            
            const variants = Array.from(checkboxes).map(cb => ({
                variant_gid: cb.dataset.variantGid,
                product_gid: cb.dataset.productGid,
                product_title: cb.dataset.productTitle,
                variant_title: cb.dataset.variantTitle
            }));
            
            // Process variants in batches
            processBulkToggle(variants, exclude, 0);
        }
    });
}

function processBulkToggle(variants, exclude, index) {
    if (index >= variants.length) {
        document.getElementById('loadingOverlay').style.display = 'none';
        
        Swal.fire({
            title: 'Success!',
            text: `Successfully updated ${variants.length} variant${variants.length > 1 ? 's' : ''}.`,
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
        
        // Clear selections and update toolbar
        clearAllSelections();
        resumeWarehouseRequests();
        return;
    }
    
    const variant = variants[index];
    const progress = `${index + 1} of ${variants.length}`;
    document.getElementById('loadingSubtext').textContent = `Processing variant ${progress}...`;
    
    fetch('{{ route('stock-sync.toggle-pim-sync') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            variant_gid: variant.variant_gid,
            product_gid: variant.product_gid,
            exclude: exclude
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the row for this variant
            updateRowAfterBulkToggle(variant.variant_gid, data.pim_sync, variant.product_gid, variant.product_title);
            
            // Continue with next variant
            setTimeout(() => processBulkToggle(variants, exclude, index + 1), 200);
        } else {
            throw new Error(data.message || 'Failed to update variant');
        }
    })
    .catch(error => {
        document.getElementById('loadingOverlay').style.display = 'none';
        Swal.fire({
            title: 'Error',
            text: `Failed to update variant: ${error.message}. ${index} of ${variants.length} variants were updated.`,
            icon: 'error',
            confirmButtonText: 'OK'
        });
        clearAllSelections();
        resumeWarehouseRequests();
    });
}

function updateRowAfterBulkToggle(variantGid, pimSync, productGid, productTitle) {
    // Find the checkbox for this variant
    const checkbox = document.querySelector(`.variant-checkbox[data-variant-gid="${variantGid}"]`);
    if (!checkbox) return;
    
    const row = checkbox.closest('tr');
    if (!row) return;
    
    // Get data from checkbox
    const variantId = checkbox.dataset.variantId;
    const inventoryItemId = checkbox.dataset.inventoryItemId;
    const variantTitle = checkbox.dataset.variantTitle;
    const shopifyStock = checkbox.dataset.shopifyStock;
    
    // Update status badge (column index 7: checkbox, product, variant, sku, barcode, tags, sync status)
    const statusCell = row.querySelector('td:nth-child(7)');
    if (statusCell) {
        if (pimSync === 'true') {
            statusCell.innerHTML = '<span class="badge bg-success" title="This variant is included in PIM sync">✓ Included</span>';
        } else if (pimSync === 'false') {
            statusCell.innerHTML = '<span class="badge bg-warning text-dark" title="This variant is excluded from PIM sync">✗ Excluded</span>';
        } else {
            statusCell.innerHTML = '<span class="badge bg-secondary" title="Sync status not set for this variant">○ Unset</span>';
        }
    }
    
    // Update action buttons (last column)
    const actionsCell = row.querySelector('td:last-child');
    if (actionsCell) {
        const viewButton = actionsCell.querySelector('a[target="_blank"]');
        const oldSyncButton = actionsCell.querySelector('.sync-stock-btn');
        
        // Rebuild buttons container
        let newButtonsHtml = '<div class="d-flex gap-1 justify-content-center flex-wrap">';
        
        // Keep View button
        if (viewButton) {
            newButtonsHtml += viewButton.outerHTML;
        }
        
        // Add appropriate Include/Exclude buttons based on new status
        const escapedTitle = productTitle.replace(/'/g, "\\'");
        const escapedVariantTitle = variantTitle.replace(/'/g, "\\'");
        
        if (pimSync === 'true') {
            newButtonsHtml += `<button onclick="togglePimSync('${variantGid}', '${productGid}', '${escapedTitle}', true)" 
                    class="btn btn-sm btn-warning"
                    title="Exclude this variant from PIM sync"
                    style="min-width: 80px;">
                <i class="bi bi-x-circle"></i> Exclude
            </button>`;
            
            // Add Sync button if location is selected
            const selectedLocation = '{{ $selectedLocation }}';
            if (selectedLocation) {
                // Get warehouse stock from the row if available
                const warehouseStockEl = row.querySelector(`.warehouse-stock[data-variant-id="${variantId}"]`);
                const warehouseStock = warehouseStockEl ? warehouseStockEl.dataset.warehouseStock : null;
                
                newButtonsHtml += `<button class="btn btn-sm btn-info sync-stock-btn" 
                        data-variant-id="${variantId}"
                        data-inventory-item-id="${inventoryItemId}"
                        data-location-id="${selectedLocation}"
                        data-product-title="${escapedTitle}"
                        data-variant-title="${escapedVariantTitle}"
                        data-shopify-stock="${shopifyStock}"
                        ${warehouseStock ? `data-warehouse-stock="${warehouseStock}"` : ''}
                        title="Sync stock from warehouse to Shopify"
                        style="min-width: 80px;"
                        ${!warehouseStock || warehouseStock === shopifyStock ? 'disabled' : ''}>
                    <i class="bi bi-arrow-repeat"></i> Sync
                </button>`;
                
                // Set up onclick handler if warehouse stock is available
                if (warehouseStock && warehouseStock !== shopifyStock) {
                    setTimeout(() => {
                        const syncBtn = row.querySelector('.sync-stock-btn');
                        if (syncBtn) {
                            syncBtn.onclick = function() {
                                syncStock(variantId, inventoryItemId, selectedLocation, warehouseStock, escapedTitle, escapedVariantTitle);
                            };
                        }
                    }, 0);
                }
            }
        } else if (pimSync === 'false') {
            newButtonsHtml += `<button onclick="togglePimSync('${variantGid}', '${productGid}', '${escapedTitle}', false)" 
                    class="btn btn-sm btn-success"
                    title="Include this variant in PIM sync"
                    style="min-width: 80px;">
                <i class="bi bi-check-circle"></i> Include
            </button>`;
        } else {
            // Unset state - show both buttons
            newButtonsHtml += `<button onclick="togglePimSync('${variantGid}', '${productGid}', '${escapedTitle}', false)" 
                    class="btn btn-sm btn-success"
                    title="Include this variant in PIM sync"
                    style="min-width: 80px;">
                <i class="bi bi-check-circle"></i> Include
            </button>
            <button onclick="togglePimSync('${variantGid}', '${productGid}', '${escapedTitle}', true)" 
                    class="btn btn-sm btn-outline-warning"
                    title="Exclude this variant from PIM sync"
                    style="min-width: 80px;">
                <i class="bi bi-x-circle"></i> Exclude
            </button>`;
        }
        
        newButtonsHtml += '</div>';
        actionsCell.innerHTML = newButtonsHtml;
    }
    
    // Uncheck the checkbox
    checkbox.checked = false;
}

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
            // Pause background requests to prioritize this action
            pauseWarehouseRequests();
            
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
                    // Update the row instead of reloading
                    const row = document.querySelector(`button[onclick*="${variantGid}"]`).closest('tr');
                    const statusCell = row.querySelector('td:nth-child(7)'); // Sync Status column (after checkbox, product, variant, sku, barcode, tags)
                    const actionsCell = row.querySelector('td:last-child'); // Actions column
                    
                    // Update status badge
                    if (data.pim_sync === 'true') {
                        statusCell.innerHTML = '<span class="badge bg-success" title="This variant is included in PIM sync">✓ Included</span>';
                    } else if (data.pim_sync === 'false') {
                        statusCell.innerHTML = '<span class="badge bg-warning text-dark" title="This variant is excluded from PIM sync">✗ Excluded</span>';
                    }
                    
                    // Update action buttons
                    const variantData = {
                        variantGid: variantGid,
                        productGid: productGid,
                        productTitle: productTitle
                    };
                    
                    const buttonsHtml = actionsCell.innerHTML;
                    const viewButton = actionsCell.querySelector('a[target="_blank"]');
                    const syncButton = actionsCell.querySelector('.sync-stock-btn');
                    
                    // Rebuild buttons container
                    let newButtonsHtml = '<div class="d-flex gap-1 justify-content-center flex-wrap">';
                    
                    // Keep View button
                    if (viewButton) {
                        newButtonsHtml += viewButton.outerHTML;
                    }
                    
                    // Add appropriate Include/Exclude buttons
                    if (data.pim_sync === 'true') {
                        newButtonsHtml += `<button onclick="togglePimSync('${variantGid}', '${productGid}', '${productTitle.replace(/'/g, "\\'")}', true)" 
                                class="btn btn-sm btn-warning"
                                title="Exclude this variant from PIM sync"
                                style="min-width: 80px;">
                            <i class="bi bi-x-circle"></i> Exclude
                        </button>`;
                    } else if (data.pim_sync === 'false') {
                        newButtonsHtml += `<button onclick="togglePimSync('${variantGid}', '${productGid}', '${productTitle.replace(/'/g, "\\'")}', false)" 
                                class="btn btn-sm btn-success"
                                title="Include this variant in PIM sync"
                                style="min-width: 80px;">
                            <i class="bi bi-check-circle"></i> Include
                        </button>`;
                    }
                    
                    // Keep Sync button if it exists
                    if (syncButton) {
                        newButtonsHtml += syncButton.outerHTML;
                    }
                    
                    newButtonsHtml += '</div>';
                    actionsCell.innerHTML = newButtonsHtml;
                    
                    // Show success message
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // Resume warehouse requests after action completes
                    resumeWarehouseRequests();
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
                
                // Resume warehouse requests even on error
                resumeWarehouseRequests();
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
            // Pause background requests to prioritize this action
            pauseWarehouseRequests();
            
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
                    // Update the row instead of reloading
                    const syncButton = document.querySelector(`.sync-stock-btn[data-variant-id="${variantId}"]`);
                    const row = syncButton.closest('tr');
                    
                    // Update Shopify Stock column (column 8: checkbox, product, variant, sku, barcode, tags, sync status, shopify stock)
                    const shopifyStockCell = row.querySelector('td:nth-child(8)'); // Shopify Stock column
                    shopifyStockCell.innerHTML = `<span class="badge bg-primary">${data.new_stock}</span>`;
                    
                    // Update Difference column
                    const differenceCell = row.querySelector('.stock-difference');
                    const diff = data.new_stock - warehouseStock;
                    let diffClass = 'text-muted';
                    if (diff > 0) diffClass = 'text-success';
                    else if (diff < 0) diffClass = 'text-danger';
                    differenceCell.innerHTML = `<span class="fw-bold ${diffClass}">${diff > 0 ? '+' : ''}${diff}</span>`;
                    
                    // Update Last Synced column
                    if (data.sync_timestamp) {
                        const timestampCell = row.querySelector('.sync-timestamp-cell');
                        if (timestampCell) {
                            timestampCell.innerHTML = `<small class="text-success" title="${data.sync_timestamp}">Just now</small>`;
                        }
                    }
                    
                    // Disable sync button since stocks now match
                    syncButton.disabled = true;
                    syncButton.dataset.shopifyStock = data.new_stock;
                    
                    // Show success message
                    Swal.fire({
                        title: 'Success!',
                        text: `Stock synced successfully! Updated to ${data.new_stock} units.`,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // Resume warehouse requests after action completes
                    resumeWarehouseRequests();
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
                
                // Resume warehouse requests even on error
                resumeWarehouseRequests();
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
                // Cancel all pending warehouse stock requests
                cancelAllWarehouseRequests();
                showLoading('Loading page...', 'Fetching variants from Shopify...');
            }
        });
    });
    
    // Cancel requests when changing location
    const locationSelect = document.querySelector('select[name="location_id"]');
    if (locationSelect) {
        locationSelect.addEventListener('change', function() {
            cancelAllWarehouseRequests();
        });
    }
    
    // Cancel requests when changing filters
    const searchForm = document.querySelector('form');
    if (searchForm) {
        searchForm.addEventListener('submit', function() {
            cancelAllWarehouseRequests();
        });
    }
    
    // Fetch warehouse stocks after page load
    fetchWarehouseStocks();
});

// Track all pending requests
let pendingWarehouseRequests = [];
let warehouseRequestQueue = [];
let isProcessingWarehouseQueue = false;
let warehouseRequestsPaused = false;

function pauseWarehouseRequests() {
    if (warehouseRequestsPaused) return;
    
    warehouseRequestsPaused = true;
    console.log('⏸️ Warehouse stock requests paused');
}

function resumeWarehouseRequests() {
    if (!warehouseRequestsPaused) return;
    
    warehouseRequestsPaused = false;
    console.log('▶️ Resuming warehouse stock requests');
    
    // Resume processing if there are items in queue
    if (warehouseRequestQueue.length > 0 && !isProcessingWarehouseQueue) {
        processWarehouseQueue();
    }
}

function cancelAllWarehouseRequests() {
    // Abort all pending fetch requests
    pendingWarehouseRequests.forEach(controller => {
        try {
            controller.abort();
        } catch (e) {
            // Ignore abort errors
        }
    });
    
    // Clear the arrays
    pendingWarehouseRequests = [];
    warehouseRequestQueue = [];
    isProcessingWarehouseQueue = false;
    warehouseRequestsPaused = false;
    
    console.log('🛑 All warehouse stock requests cancelled');
}

function fetchWarehouseStocks() {
    // Get all SKU elements from the table
    const stockElements = document.querySelectorAll('.warehouse-stock[data-sku]');
    
    if (stockElements.length === 0) return;
    
    // Collect all SKUs and create lookup map
    const skus = [];
    const skuMap = new Map(); // Map SKU to array of elements
    
    stockElements.forEach((el) => {
        const sku = el.dataset.sku;
        if (sku) {
            skus.push(sku);
            if (!skuMap.has(sku)) {
                skuMap.set(sku, []);
            }
            skuMap.get(sku).push({
                element: el,
                variantId: el.dataset.variantId
            });
        } else {
            el.innerHTML = '<span class="badge bg-secondary">No SKU</span>';
        }
    });
    
    if (skus.length === 0) return;
    
    console.log(`📦 Fetching warehouse stock for ${skus.length} variants in batch...`);
    
    // Fetch all stocks in one request
    fetch('{{ route('stock-sync.get-warehouse-stock-batch') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ skus: skus })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log(`✅ Received warehouse stock for ${data.count} variants`);
            
            // Update all elements with the fetched data
            skuMap.forEach((items, sku) => {
                const stockData = data.data[sku];
                
                items.forEach(({ element, variantId }) => {
                    if (stockData) {
                        const stock = stockData.stock;
                        element.dataset.warehouseStock = stock;
                        element.innerHTML = `<span class="badge bg-info">${stock}</span>`;
                        updateDifference(variantId, stock);
                        updateSyncButton(variantId, stock);
                    } else {
                        element.innerHTML = '<span class="badge bg-secondary">Not Found</span>';
                        updateDifference(variantId, null);
                    }
                });
            });
        } else {
            console.error('Batch warehouse stock fetch failed:', data.message);
            // Fall back to showing error on all elements
            stockElements.forEach(el => {
                el.innerHTML = '<span class="badge bg-danger">Error</span>';
            });
        }
    })
    .catch(error => {
        console.error('Batch warehouse stock fetch error:', error);
        // Fall back to showing error on all elements
        stockElements.forEach(el => {
            el.innerHTML = '<span class="badge bg-danger">Error</span>';
        });
    });
}

function processWarehouseQueue() {
    if (isProcessingWarehouseQueue) return;
    if (warehouseRequestQueue.length === 0) return;
    if (warehouseRequestsPaused) {
        console.log('⏸️ Queue processing paused, waiting to resume...');
        return;
    }
    
    isProcessingWarehouseQueue = true;
    const totalCount = warehouseRequestQueue.length + pendingWarehouseRequests.length;
    
    // Process next 3 requests in parallel
    const batchSize = 3;
    const batch = warehouseRequestQueue.splice(0, batchSize);
    
    batch.forEach(item => {
        const { element: el, sku, variantId } = item;
        
        // Show loading state
        el.innerHTML = '<span class="badge bg-secondary"><span class="spinner-border spinner-border-sm" role="status"></span></span>';
        
        if (!sku) {
            el.innerHTML = '<span class="badge bg-secondary">No SKU</span>';
            updateDifference(variantId, null);
            checkQueueComplete();
            return;
        }
        
        // Create AbortController for this request
        const controller = new AbortController();
        pendingWarehouseRequests.push(controller);
        
        fetch('{{ route('stock-sync.get-warehouse-stock-by-sku') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ sku: sku }),
            signal: controller.signal
        })
        .then(response => response.json())
        .then(data => {
            // Remove controller from pending list
            const index = pendingWarehouseRequests.indexOf(controller);
            if (index > -1) pendingWarehouseRequests.splice(index, 1);
            
            if (data.success && data.stock !== null) {
                el.innerHTML = `<span class="badge bg-info">${data.stock}</span>`;
                el.dataset.warehouseStock = data.stock;
                
                // Update difference column
                updateDifference(variantId, data.stock);
                
                // Update sync button
                updateSyncButton(variantId, data.stock);
            } else {
                el.innerHTML = '<span class="badge bg-secondary">N/A</span>';
                updateDifference(variantId, null);
            }
            
            checkQueueComplete();
        })
        .catch(error => {
            // Remove controller from pending list
            const index = pendingWarehouseRequests.indexOf(controller);
            if (index > -1) pendingWarehouseRequests.splice(index, 1);
            
            if (error.name === 'AbortError') {
                // Request was cancelled
                el.innerHTML = '<span class="badge bg-secondary">Cancelled</span>';
            } else {
                console.error(`Failed to fetch warehouse stock for SKU ${sku}:`, error);
                el.innerHTML = '<span class="badge bg-danger">Error</span>';
            }
            updateDifference(variantId, null);
            checkQueueComplete();
        });
    });
}

function checkQueueComplete() {
    // Continue processing queue if there are more items
    if (warehouseRequestQueue.length > 0) {
        setTimeout(() => {
            isProcessingWarehouseQueue = false;
            processWarehouseQueue();
        }, 50); // Small delay between batches
    } else if (pendingWarehouseRequests.length === 0) {
        isProcessingWarehouseQueue = false;
        console.log('✅ All warehouse stock requests completed');
    }
}

function prioritizeRequest(sku, variantId, element) {
    // Check if request is already pending
    const existingIndex = warehouseRequestQueue.findIndex(item => item.sku === sku);
    
    if (existingIndex > -1) {
        // Move to front of queue
        const item = warehouseRequestQueue.splice(existingIndex, 1)[0];
        warehouseRequestQueue.unshift(item);
        console.log(`⚡ Prioritized request for SKU: ${sku}`);
    }
}

function updateDifference(variantId, warehouseStock) {
    const diffEl = document.querySelector(`.stock-difference[data-variant-id="${variantId}"]`);
    if (!diffEl) return;
    
    const shopifyStock = parseInt(diffEl.dataset.shopifyStock);
    
    if (warehouseStock !== null && warehouseStock !== undefined) {
        const diff = shopifyStock - warehouseStock;
        let diffClass = 'text-muted';
        if (diff > 0) diffClass = 'text-success';
        else if (diff < 0) diffClass = 'text-danger';
        
        diffEl.innerHTML = `<span class="fw-bold ${diffClass}">${diff > 0 ? '+' : ''}${diff}</span>`;
    } else {
        diffEl.innerHTML = '<span class="text-muted">-</span>';
    }
}

function updateSyncButton(variantId, warehouseStock) {
    const btn = document.querySelector(`.sync-stock-btn[data-variant-id="${variantId}"]`);
    if (!btn) return;
    
    const shopifyStock = parseInt(btn.dataset.shopifyStock);
    
    if (warehouseStock !== null && warehouseStock !== undefined) {
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
        } else {
            btn.disabled = true;
        }
    }
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

/* Collapsible card header styles */
.card-header[data-bs-toggle="collapse"] {
    user-select: none;
    transition: background-color 0.2s ease;
}

.card-header[data-bs-toggle="collapse"]:hover {
    background-color: rgba(0,0,0,.03);
}

.card-header i.bi-chevron-down,
.card-header i.bi-chevron-up {
    transition: transform 0.3s ease;
}
</style>

<script>
// Bulk Update by Tag functionality
let bulkEventSource = null;
let bulkStartTime = null;

document.getElementById('bulkTagForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const tag = document.getElementById('bulk_tag').value.trim();
    const status = document.getElementById('bulk_status').value;
    const inverse = document.getElementById('bulk_inverse').checked;
    
    if (!tag) {
        Swal.fire({
            icon: 'warning',
            title: 'Tag Required',
            text: 'Please enter a tag',
            confirmButtonColor: '#3085d6'
        });
        return;
    }
    
    // Get action display text
    let actionText = '';
    switch(status) {
        case 'include': actionText = '✅ Include in Sync (true)'; break;
        case 'exclude': actionText = '❌ Exclude from Sync (false)'; break;
        case 'unset': actionText = '⚪ Unset (empty)'; break;
    }
    
    Swal.fire({
        icon: 'question',
        title: 'Confirm Bulk Update',
        html: `
            <div class="text-start">
                <p><strong>Tag:</strong> ${tag} ${inverse ? '<span class="badge bg-warning">INVERSE</span>' : ''}</p>
                <p><strong>Action:</strong> ${actionText}</p>
                ${inverse ? '<p class="text-warning"><small>⚠️ This will update products WITHOUT this tag</small></p>' : ''}
                <p class="text-muted"><small>This action will update all matching variants.</small></p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, proceed!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            startBulkUpdate(tag, status, inverse);
        }
    });
});

function startBulkUpdate(tag, status, inverse) {
    // Reset UI
    bulkStartTime = Date.now();
    document.getElementById('bulkProgressContainer').classList.remove('d-none');
    document.getElementById('bulkSummary').classList.add('d-none');
    document.getElementById('bulkFailedList').classList.add('d-none');
    document.getElementById('bulkLog').innerHTML = '';
    document.getElementById('bulkProgressBar').style.width = '0%';
    document.getElementById('bulkProgressBar').textContent = '0 / 0';
    document.getElementById('bulkProgressBar').setAttribute('aria-valuenow', '0');
    
    // Toggle buttons
    document.getElementById('bulk_submit_btn').classList.add('d-none');
    document.getElementById('bulk_cancel_btn').classList.remove('d-none');
    document.getElementById('bulk_tag').disabled = true;
    document.getElementById('bulk_status').disabled = true;
    document.getElementById('bulk_inverse').disabled = true;
    
    addBulkLogEntry('info', `Starting bulk update for tag: ${tag} (${inverse ? 'INVERSE' : 'NORMAL'})`);
    addBulkLogEntry('info', `Action: ${status.toUpperCase()}`);
    
    // Create EventSource for streaming
    const url = new URL('{{ route("stock-sync.bulk-update-by-tag") }}', window.location.origin);
    url.searchParams.append('tag', tag);
    url.searchParams.append('status', status);
    url.searchParams.append('inverse', inverse ? '1' : '0');
    
    bulkEventSource = new EventSource(url.toString());
    
    bulkEventSource.addEventListener('start', function(e) {
        const data = JSON.parse(e.data);
        addBulkLogEntry('info', `Parameters confirmed - Tag: ${data.tag}, Status: ${data.status}, Inverse: ${data.inverse}`);
    });
    
    bulkEventSource.addEventListener('info', function(e) {
        const data = JSON.parse(e.data);
        addBulkLogEntry('info', data.message);
    });
    
    bulkEventSource.addEventListener('total', function(e) {
        const data = JSON.parse(e.data);
        addBulkLogEntry('success', `Found ${data.count} variants to process`);
        document.getElementById('bulkProgressBar').setAttribute('aria-valuemax', data.count);
    });
    
    bulkEventSource.addEventListener('progress', function(e) {
        const data = JSON.parse(e.data);
        const percent = Math.round((data.current / data.total) * 100);
        
        // Update progress bar
        document.getElementById('bulkProgressBar').style.width = percent + '%';
        document.getElementById('bulkProgressBar').textContent = `${data.current} / ${data.total}`;
        document.getElementById('bulkProgressBar').setAttribute('aria-valuenow', data.current);
        
        // Add log entry
        addBulkLogEntry('progress', `[${data.current}/${data.total}] Processing: ${data.sku} - ${data.product} (${data.variant})`);
    });
    
    bulkEventSource.addEventListener('success', function(e) {
        const data = JSON.parse(e.data);
        addBulkLogEntry('success', `✅ [${data.current}] Success: ${data.sku}`);
    });
    
    bulkEventSource.addEventListener('failed', function(e) {
        const data = JSON.parse(e.data);
        addBulkLogEntry('error', `❌ [${data.current}] Failed: ${data.sku} - ${data.reason || 'Unknown error'}`);
    });
    
    bulkEventSource.addEventListener('done', function(e) {
        const data = JSON.parse(e.data);
        const duration = ((Date.now() - bulkStartTime) / 1000).toFixed(2);
        
        addBulkLogEntry('success', `✅ Completed in ${duration}s`);
        addBulkLogEntry('info', `Total: ${data.total} | Success: ${data.success} | Failed: ${data.failed}`);
        
        // Show summary
        document.getElementById('bulkSummary').classList.remove('d-none');
        document.getElementById('bulkSummaryText').textContent = 
            `Total: ${data.total} | Success: ${data.success} | Failed: ${data.failed} | Duration: ${duration}s`;
        
        // Show failed variants if any
        if (data.failed > 0 && data.failedVariants && data.failedVariants.length > 0) {
            document.getElementById('bulkFailedList').classList.remove('d-none');
            const failedList = document.getElementById('bulkFailedItems');
            failedList.innerHTML = '';
            data.failedVariants.forEach(variant => {
                const li = document.createElement('li');
                li.textContent = `${variant.sku} - ${variant.product} (${variant.variant})`;
                if (variant.reason) {
                    li.textContent += ` - Reason: ${variant.reason}`;
                }
                failedList.appendChild(li);
            });
        }
        
        // Update progress bar to green
        const progressBar = document.getElementById('bulkProgressBar');
        progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
        if (data.failed === 0) {
            progressBar.classList.remove('bg-primary');
            progressBar.classList.add('bg-success');
        } else {
            progressBar.classList.remove('bg-primary');
            progressBar.classList.add('bg-warning');
        }
        
        stopBulkUpdate();
    });
    
    bulkEventSource.addEventListener('error', function(e) {
        let errorMsg = 'Unknown error occurred';
        if (e.data) {
            try {
                const data = JSON.parse(e.data);
                errorMsg = data.message || errorMsg;
            } catch (err) {
                // Can't parse, use default
            }
        }
        
        addBulkLogEntry('error', `❌ Error: ${errorMsg}`);
        
        // Update progress bar to red
        const progressBar = document.getElementById('bulkProgressBar');
        progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated', 'bg-primary');
        progressBar.classList.add('bg-danger');
        
        stopBulkUpdate();
    });
    
    bulkEventSource.onerror = function(e) {
        if (bulkEventSource && bulkEventSource.readyState === EventSource.CLOSED) {
            // Connection closed naturally (after 'done' event) - this is expected
            return;
        }
        
        addBulkLogEntry('error', '❌ Connection error or stream ended unexpectedly');
        stopBulkUpdate();
    };
}

function cancelBulkUpdate() {
    Swal.fire({
        icon: 'warning',
        title: 'Cancel Bulk Update?',
        text: 'Are you sure you want to stop the bulk update process?',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, cancel it',
        cancelButtonText: 'No, continue'
    }).then((result) => {
        if (result.isConfirmed) {
            addBulkLogEntry('warning', '⚠️ Cancelled by user');
            stopBulkUpdate();
        }
    });
}

function stopBulkUpdate() {
    if (bulkEventSource) {
        bulkEventSource.close();
        bulkEventSource = null;
    }
    
    // Re-enable form
    document.getElementById('bulk_submit_btn').classList.remove('d-none');
    document.getElementById('bulk_cancel_btn').classList.add('d-none');
    document.getElementById('bulk_tag').disabled = false;
    document.getElementById('bulk_status').disabled = false;
    document.getElementById('bulk_inverse').disabled = false;
}

function addBulkLogEntry(type, message) {
    const logContainer = document.getElementById('bulkLog');
    const entry = document.createElement('div');
    entry.className = 'mb-1';
    
    const timestamp = new Date().toLocaleTimeString();
    let badge = '';
    
    switch(type) {
        case 'success':
            badge = '<span class="badge bg-success me-2">✓</span>';
            break;
        case 'error':
            badge = '<span class="badge bg-danger me-2">✗</span>';
            break;
        case 'warning':
            badge = '<span class="badge bg-warning me-2">⚠</span>';
            break;
        case 'info':
            badge = '<span class="badge bg-info me-2">ℹ</span>';
            break;
        case 'progress':
            badge = '<span class="badge bg-secondary me-2">→</span>';
            break;
    }
    
    entry.innerHTML = `${badge}<span class="text-muted">[${timestamp}]</span> ${escapeHtml(message)}`;
    logContainer.appendChild(entry);
    
    // Auto-scroll to bottom
    logContainer.scrollTop = logContainer.scrollHeight;
}

function clearBulkLog() {
    document.getElementById('bulkLog').innerHTML = '';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Collapse icon toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchFilterCollapse = document.getElementById('searchFilterCollapse');
    const bulkUpdateCollapse = document.getElementById('bulkUpdateCollapse');
    
    // Search filter collapse toggle
    searchFilterCollapse.addEventListener('show.bs.collapse', function () {
        document.getElementById('searchFilterIcon').classList.remove('bi-chevron-down');
        document.getElementById('searchFilterIcon').classList.add('bi-chevron-up');
    });
    
    searchFilterCollapse.addEventListener('hide.bs.collapse', function () {
        document.getElementById('searchFilterIcon').classList.remove('bi-chevron-up');
        document.getElementById('searchFilterIcon').classList.add('bi-chevron-down');
    });
    
    // Bulk update collapse toggle
    bulkUpdateCollapse.addEventListener('show.bs.collapse', function () {
        document.getElementById('bulkUpdateIcon').classList.remove('bi-chevron-down');
        document.getElementById('bulkUpdateIcon').classList.add('bi-chevron-up');
    });
    
    bulkUpdateCollapse.addEventListener('hide.bs.collapse', function () {
        document.getElementById('bulkUpdateIcon').classList.remove('bi-chevron-up');
        document.getElementById('bulkUpdateIcon').classList.add('bi-chevron-down');
    });
});
</script>

@endsection
