@extends('layouts.app')

@section('title', 'Products Export')
@section('page-title', 'Products Export')

@section('content')
<!-- Loading Overlay -->
<div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center;">
    <div class="text-center">
        <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="text-light mt-3">
            <h5 id="loadingText">Processing...</h5>
            <p id="loadingSubtext">Please wait</p>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="bi bi-bag text-primary"></i> Products Export
                </h1>
            </div>

            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                <strong>Note:</strong> Use filters to narrow down Shopify products. Export will include one row per variant with the required product + variant fields.
            </div>

            @if(session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif

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

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-funnel"></i> Filters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" id="productsFilterForm">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="tag" class="form-label">Tag</label>
                                <input type="text" class="form-control" id="tag" name="tag" value="{{ $filters['tag'] }}" placeholder="e.g., summer">
                            </div>

                            <div class="col-md-3">
                                <label for="sku" class="form-label">SKU Contains</label>
                                <input type="text" class="form-control" id="sku" name="sku" value="{{ $filters['sku'] }}" placeholder="e.g., ABC123">
                            </div>

                            <div class="col-md-3">
                                <label for="name" class="form-label">Name Contains</label>
                                <input type="text" class="form-control" id="name" name="name" value="{{ $filters['name'] }}" placeholder="e.g., Polo">
                            </div>

                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="all" {{ $filters['status'] == 'all' ? 'selected' : '' }}>All</option>
                                    <option value="active" {{ $filters['status'] == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="draft" {{ $filters['status'] == 'draft' ? 'selected' : '' }}>Draft</option>
                                    <option value="archived" {{ $filters['status'] == 'archived' ? 'selected' : '' }}>Archived</option>
                                </select>
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
                                    <button type="button" onclick="exportProducts()" class="btn btn-success" id="exportBtn">
                                        <i class="bi bi-download"></i> Export to CSV
                                    </button>
                                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-clockwise"></i> Reset Filters
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-end">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i>
                                        Preview shows first 20 products. Export fetches ALL pages.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            @if(!empty($products))
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Products Preview ({{ count($products) }} products shown)</h5>
                            <small class="text-muted">One row per product (variants exported separately)</small>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Product</th>
                                    <th>Status</th>
                                    <th>Vendor</th>
                                    <th>Type</th>
                                    <th>Tags</th>
                                    <th>Updated</th>
                                    <th>Variants</th>
                                    <th>URL</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $product)
                                    <tr>
                                        <td>
                                            <strong>{{ $product['title'] }}</strong>
                                            <br><small class="text-muted">ID: {{ $product['product_id'] }} | {{ $product['handle'] }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ strtoupper($product['status']) === 'ACTIVE' ? 'success' : (strtoupper($product['status']) === 'DRAFT' ? 'warning' : 'secondary') }}">
                                                {{ ucfirst(strtolower($product['status'] ?? 'unknown')) }}
                                            </span>
                                        </td>
                                        <td>{{ $product['vendor'] ?: 'N/A' }}</td>
                                        <td>{{ $product['type'] ?: 'N/A' }}</td>
                                        <td style="max-width: 280px;">
                                            <small>{{ $product['tags'] ?: '—' }}</small>
                                        </td>
                                        <td>
                                            <small>{{ $product['updated_at'] ? date('Y-m-d H:i:s', strtotime($product['updated_at'])) : '—' }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">{{ count($product['variants'] ?? []) }}</span>
                                        </td>
                                        <td>
                                            @if(!empty($product['url']))
                                                <a href="{{ $product['url'] }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No products found with current filters</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if(isset($pagination) && ($pagination['has_prev'] || $pagination['has_next']))
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Page {{ $pagination['current_page'] }} - Showing {{ $pagination['total_shown'] }} products</small>
                                </div>
                                <div class="btn-group" role="group">
                                    @if($pagination['has_prev'])
                                        <button type="button" class="btn btn-outline-primary" onclick="goToPage({{ $pagination['current_page'] - 1 }})">
                                            <i class="bi bi-chevron-left"></i> Previous
                                        </button>
                                    @endif
                                    @if($pagination['has_next'])
                                        <button type="button" class="btn btn-outline-primary" onclick="goToPage({{ $pagination['current_page'] + 1 }})">
                                            Next <i class="bi bi-chevron-right"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function exportProducts() {
    const form = document.getElementById('productsFilterForm');
    const formData = new FormData(form);

    const params = new URLSearchParams();
    for (const [key, value] of formData.entries()) {
        if (value !== '' && key !== 'preview' && key !== 'page') {
            params.append(key, value);
        }
    }

    const exportUrl = "{{ route('products.export') }}" + (params.toString() ? ('?' + params.toString()) : '');

    document.getElementById('loadingOverlay').style.display = 'flex';
    document.getElementById('loadingText').textContent = 'Exporting products...';
    document.getElementById('loadingSubtext').textContent = 'Fetching all pages from Shopify';

    window.location.href = exportUrl;

    // Hide overlay after a short delay (download handled by browser)
    setTimeout(() => {
        document.getElementById('loadingOverlay').style.display = 'none';
    }, 3000);
}

function goToPage(page) {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page);
    url.searchParams.set('preview', '1');
    window.location.href = url.toString();
}
</script>
@endsection
