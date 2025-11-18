@extends('layouts.app')

@section('title', 'Inventory Export')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-boxes text-primary"></i> Inventory Export
            </h1>
            <div class="d-flex gap-2">
                <a href="{{ route('orders.index') }}" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Back to Orders
                </a>
            </div>
        </div>
        
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> 
            <strong>Note:</strong> Select a location to filter inventory by that specific location, or leave blank to see all locations. 
            Each variant will be shown separately per location with its available quantity.
        </div>

        @if(!empty($errors))
            <div class="alert alert-danger">
                <h6>API Errors:</h6>
                <ul class="mb-0">
                    @foreach($errors as $error)
                        <li>{{ is_array($error) ? json_encode($error) : $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Inventory Export Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Export Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" id="inventoryFilterForm">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="location_id" class="form-label">Location (Optional)</label>
                            <select name="location_id" id="location_id" class="form-select">
                                <option value="">All Locations</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location['id'] }}" {{ $filters['location_id'] == $location['id'] ? 'selected' : '' }}>
                                        {{ $location['name'] }}
                                        @if($location['city'] || $location['country'])
                                            ({{ $location['city'] }}{{ $location['city'] && $location['country'] ? ', ' : '' }}{{ $location['country'] }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Filter by specific location or show all</div>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="product_type" class="form-label">Product Type</label>
                            <input type="text" class="form-control" id="product_type" name="product_type" 
                                   value="{{ $filters['product_type'] }}" 
                                   placeholder="e.g., Clothing">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="vendor" class="form-label">Vendor</label>
                            <input type="text" class="form-control" id="vendor" name="vendor" 
                                   value="{{ $filters['vendor'] }}" 
                                   placeholder="e.g., Nike">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="status" class="form-label">Product Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="all" {{ $filters['status'] == 'all' ? 'selected' : '' }}>All</option>
                                <option value="active" {{ $filters['status'] == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="draft" {{ $filters['status'] == 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="archived" {{ $filters['status'] == 'archived' ? 'selected' : '' }}>Archived</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="sku_filter" class="form-label">SKU Contains</label>
                            <input type="text" class="form-control" id="sku_filter" name="sku_filter" 
                                   value="{{ $filters['sku_filter'] }}" 
                                   placeholder="Filter by SKU">
                        </div>
                        
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" name="preview" value="1" class="btn btn-info">
                                    <i class="bi bi-eye"></i> Preview
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex gap-2">
                                <button type="button" onclick="exportInventory()" class="btn btn-success">
                                    <i class="bi bi-download"></i> Export to CSV
                                </button>
                                <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise"></i> Reset Filters
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-end">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i> 
                                    Preview shows first 50 products. Export automatically paginates to fetch ALL products.
                                </small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Preview Results -->
        @if(!empty($inventory))
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Inventory Preview ({{ count($inventory) }} items shown)</h5>
                        <small class="text-muted">
                            @if($filters['location_id'])
                                Filtered by selected location
                            @else
                                Showing all locations (variants may appear multiple times)
                            @endif
                        </small>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Variant</th>
                                <th>Price</th>
                                <th>Available</th>
                                <th>Location</th>
                                <th>Type</th>
                                <th>Vendor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($inventory as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item['product_title'] }}</strong>
                                    <br><small class="text-muted">ID: {{ substr($item['product_id'], -8) }}</small>
                                </td>
                                <td>
                                    <code>{{ $item['sku'] ?: 'N/A' }}</code>
                                </td>
                                <td>
                                    {{ $item['variant_title'] }}
                                    <br><small class="text-muted">{{ substr($item['variant_id'], -8) }}</small>
                                </td>
                                <td>
                                    <strong>${{ number_format($item['price'], 2) }}</strong>
                                    @if(!empty($item['compare_at_price']))
                                        <br><small class="text-muted">
                                            Compare: ${{ number_format($item['compare_at_price'], 2) }}
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $item['available_quantity'] > 0 ? 'success' : 'warning' }}">
                                        {{ $item['available_quantity'] }}
                                    </span>
                                </td>
                                <td>
                                    <strong>{{ $item['location_name'] }}</strong>
                                    @if($item['location_id'])
                                        <br><small class="text-muted">ID: {{ substr($item['location_id'], -8) }}</small>
                                    @endif
                                </td>
                                <td>{{ $item['product_type'] ?: 'N/A' }}</td>
                                <td>{{ $item['vendor'] ?: 'N/A' }}</td>
                                <td>
                                    <span class="badge bg-{{ $item['product_status'] === 'ACTIVE' ? 'success' : ($item['product_status'] === 'DRAFT' ? 'warning' : 'secondary') }}">
                                        {{ ucfirst(strtolower($item['product_status'] ?? 'unknown')) }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    No inventory found with current filters
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if(empty($inventory) && !request()->has('preview'))
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-boxes text-muted mb-3" style="font-size: 3rem;"></i>
                    <h5>Inventory Export</h5>
                    <p class="text-muted mb-4">
                        Select a location and apply filters to preview and export your inventory data.
                    </p>
                    <div class="text-muted">
                        <small>
                            <i class="bi bi-lightbulb"></i> 
                            <strong>Tip:</strong> Select a location to filter, or leave blank to see all locations. Click Preview to see your inventory data before exporting.
                        </small>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
function exportInventory() {
    const form = document.getElementById('inventoryFilterForm');
    const formData = new FormData(form);
    
    // Remove preview parameter for export
    formData.delete('preview');
    
    // Create a new form for export
    const exportForm = document.createElement('form');
    exportForm.method = 'GET';
    exportForm.action = '{{ route("inventory.export") }}';
    
    // Add all form data as hidden inputs
    for (let [key, value] of formData.entries()) {
        if (value) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            exportForm.appendChild(input);
        }
    }
    
    document.body.appendChild(exportForm);
    exportForm.submit();
    document.body.removeChild(exportForm);
}
</script>
@endsection