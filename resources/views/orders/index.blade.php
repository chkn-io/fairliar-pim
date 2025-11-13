@extends('layouts.app')

@section('title', 'Orders')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-bag-check text-primary"></i> Shopify Orders
            </h1>
            <div class="d-flex gap-2">
                <!-- Sorting Controls -->
                <form method="GET" class="d-flex gap-2" id="sortForm">
                    @foreach(request()->except(['sort_by', 'sort_order']) as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <select name="sort_by" class="form-select form-select-sm" onchange="document.getElementById('sortForm').submit()">
                        <option value="ORDER_NUMBER" {{ $sorting['sort_by'] == 'ORDER_NUMBER' ? 'selected' : '' }}>Order Number</option>
                        <option value="CREATED_AT" {{ $sorting['sort_by'] == 'CREATED_AT' ? 'selected' : '' }}>Date Created</option>
                        <option value="UPDATED_AT" {{ $sorting['sort_by'] == 'UPDATED_AT' ? 'selected' : '' }}>Date Updated</option>
                        <option value="TOTAL_PRICE" {{ $sorting['sort_by'] == 'TOTAL_PRICE' ? 'selected' : '' }}>Total Price</option>
                    </select>
                    <select name="sort_order" class="form-select form-select-sm" onchange="document.getElementById('sortForm').submit()">
                        <option value="desc" {{ $sorting['sort_order'] == 'desc' ? 'selected' : '' }}>
                            @if($sorting['sort_by'] == 'ORDER_NUMBER') Highest First (#1606, #1605...)
                            @elseif($sorting['sort_by'] == 'CREATED_AT' || $sorting['sort_by'] == 'UPDATED_AT') Newest First
                            @elseif($sorting['sort_by'] == 'TOTAL_PRICE') Highest First
                            @else Z-A @endif
                        </option>
                        <option value="asc" {{ $sorting['sort_order'] == 'asc' ? 'selected' : '' }}>
                            @if($sorting['sort_by'] == 'ORDER_NUMBER') Lowest First (#1001, #1002...)
                            @elseif($sorting['sort_by'] == 'CREATED_AT' || $sorting['sort_by'] == 'UPDATED_AT') Oldest First
                            @elseif($sorting['sort_by'] == 'TOTAL_PRICE') Lowest First
                            @else A-Z @endif
                        </option>
                    </select>
                </form>
                
                <!-- Per Page Controls -->
                <form method="GET" class="d-flex gap-2">
                    @foreach(request()->except('per_page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <select name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10 per page</option>
                        <option value="20" {{ $perPage == 20 ? 'selected' : '' }}>20 per page</option>
                        <option value="30" {{ $perPage == 30 ? 'selected' : '' }}>30 per page</option>
                        <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 per page</option>
                    </select>
                </form>
            </div>
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

        @if(empty($orders))
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i> No orders found.
                <br><small>Make sure your Shopify store domain is configured in the .env file, or try adjusting your filters.</small>
            </div>
        @else
            <div class="alert alert-info alert-dismissible fade show">
                <i class="bi bi-info-circle"></i> 
                <strong>Note:</strong> Now showing all orders from the last 30 days by default, sorted by order number (highest first). 
                Use filters to narrow down results or search for specific orders.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            
            <!-- Advanced Search and Filter -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-funnel"></i> Filters & Search</h5>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                            <i class="bi bi-gear"></i> Advanced
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" id="filterForm">
                        <!-- Quick Filters Row -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label for="text_search" class="form-label">Order Search</label>
                                <input type="text" class="form-control" id="text_search" name="text_search" 
                                       value="{{ $filters['text_search'] }}" 
                                       placeholder="Order number, customer...">
                            </div>
                            <div class="col-md-2">
                                <label for="fulfillment_status" class="form-label">Fulfillment</label>
                                <select name="fulfillment_status" class="form-select">
                                    <option value="">All Orders</option>
                                    <option value="unfulfilled" {{ $filters['fulfillment_status'] == 'unfulfilled' ? 'selected' : '' }}>Unfulfilled</option>
                                    <option value="partial" {{ $filters['fulfillment_status'] == 'partial' ? 'selected' : '' }}>Partial</option>
                                    <option value="fulfilled" {{ $filters['fulfillment_status'] == 'fulfilled' ? 'selected' : '' }}>Fulfilled</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="financial_status" class="form-label">Payment</label>
                                <select name="financial_status" class="form-select">
                                    <option value="">All</option>
                                    <option value="paid" {{ $filters['financial_status'] == 'paid' ? 'selected' : '' }}>Paid</option>
                                    <option value="pending" {{ $filters['financial_status'] == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="refunded" {{ $filters['financial_status'] == 'refunded' ? 'selected' : '' }}>Refunded</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="{{ $filters['date_from'] }}">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="{{ $filters['date_to'] }}">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Advanced Filters (Collapsible) -->
                        <div class="collapse {{ $filters['custom_query'] ? 'show' : '' }}" id="advancedFilters">
                            <hr>
                            <div class="row g-3">
                                <div class="col-md-10">
                                    <label for="custom_query" class="form-label">Custom GraphQL Query</label>
                                    <input type="text" class="form-control" id="custom_query" name="custom_query" 
                                           value="{{ $filters['custom_query'] }}" 
                                           placeholder="e.g., fulfillment_status:unfulfilled AND created_at:>2024-01-01">
                                    <div class="form-text">
                                        Examples: <code>tag:urgent</code>, <code>customer.email:john@example.com</code>, <code>created_at:>2024-01-01</code>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-clockwise"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden fields for pagination and sorting -->
                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                        <input type="hidden" name="sort_by" value="{{ $sorting['sort_by'] }}">
                        <input type="hidden" name="sort_order" value="{{ $sorting['sort_order'] }}">
                    </form>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Orders ({{ count($orders) }} shown)</h5>
                        <div class="d-flex gap-2 align-items-center">
                            <small class="text-muted">Query: {{ $currentQuery }}</small>
                            <form method="GET" action="{{ route('orders.export') }}" style="display: inline;">
                                @foreach(request()->query() as $key => $value)
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endforeach
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="bi bi-download"></i> Export CSV
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover order-table mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Order</th>
                                <th>Date</th>
                                <th>Customer Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Shipping Address</th>
                                <th>Items & Fulfillment</th>
                                <th>Transaction</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)
                            <tr>
                                <td>
                                    <strong>{{ $order['name'] }}</strong>
                                    <br><small class="text-muted">{{ substr($order['id'], -8) }}</small>
                                </td>
                                <td>
                                    <small>{{ date('M j, Y', strtotime($order['created_at'])) }}</small>
                                    <br><small class="text-muted">{{ date('g:i A', strtotime($order['created_at'])) }}</small>
                                </td>
                                <td>
                                    <div class="customer-cell">
                                        {{ $order['shipping_address']['name'] ?: 'N/A' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="customer-cell" title="{{ $order['customer']['email'] }}">
                                        {{ $order['customer']['email'] ?: 'N/A' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="customer-cell">
                                        {{ $order['shipping_address']['phone'] ?: 'N/A' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="address-cell" title="{{ $order['shipping_address']['address1'] }}, {{ $order['shipping_address']['city'] }}, {{ $order['shipping_address']['province'] }}, {{ $order['shipping_address']['country'] }} {{ $order['shipping_address']['zip'] }}">
                                        @if($order['shipping_address']['address1'])
                                            {{ $order['shipping_address']['address1'] }}<br>
                                            <small class="text-muted">
                                                {{ $order['shipping_address']['city'] }}{{ $order['shipping_address']['province'] ? ', ' . $order['shipping_address']['province'] : '' }}
                                                {{ $order['shipping_address']['country'] }}
                                            </small>
                                        @else
                                            <small class="text-muted">No address</small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="line-items-cell">
                                        @foreach(array_slice($order['line_items'], 0, 3) as $item)
                                            <div class="mb-2 p-2 bg-light rounded">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <strong>{{ $item['quantity'] }}x</strong> {{ $item['name'] }}
                                                        @if($item['sku'])
                                                            <br><small class="text-muted">SKU: {{ $item['sku'] }}</small>
                                                        @endif
                                                        <br><small class="text-primary">{{ number_format($item['price'], 2) }} each</small>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="mb-1">
                                                            <span class="badge status-badge
                                                                @if($item['fulfillment_status'] == 'CLOSED') bg-success
                                                                @elseif($item['fulfillment_status'] == 'OPEN') bg-warning
                                                                @elseif($item['fulfillment_status'] == 'IN_PROGRESS') bg-info
                                                                @else bg-secondary
                                                                @endif
                                                            ">
                                                                {{ $item['fulfillment_status'] }}
                                                            </span>
                                                        </div>
                                                        @if($item['fulfillment_location'] && $item['fulfillment_location'] !== 'Not assigned')
                                                            <small class="text-muted d-block">
                                                                <i class="bi bi-geo-alt"></i> {{ $item['fulfillment_location'] }}
                                                            </small>
                                                        @else
                                                            <small class="text-warning d-block">
                                                                <i class="bi bi-exclamation-triangle"></i> Not assigned
                                                            </small>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                        
                                        @if(count($order['line_items']) > 3)
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        onclick="showAllItems('{{ md5($order['id']) }}')"
                                                        data-order-name="{{ $order['name'] }}">
                                                    <i class="bi bi-eye"></i> Show All {{ count($order['line_items']) }} Items
                                                </button>
                                            </div>
                                        @endif
                                        
                                        <!-- Hidden data for modal -->
                                        <script type="application/json" id="items-data-{{ md5($order['id']) }}">
                                            {!! json_encode($order['line_items']) !!}
                                        </script>
                                    </div>
                                </td>
                                <td>
                                    <div class="transaction-cell">
                                        @if(!empty($order['transactions']))
                                            @foreach($order['transactions'] as $transaction)
                                                <div class="mb-1">
                                                    <small class="text-muted">
                                                        <strong>{{ $transaction['kind'] }}</strong> - {{ $transaction['gateway'] }}
                                                        <br>
                                                        {{ number_format($transaction['amount'], 2) }} {{ $transaction['currency'] }}
                                                        @if($transaction['processed_at'])
                                                            <br><span class="text-muted">{{ date('M j, g:i A', strtotime($transaction['processed_at'])) }}</span>
                                                        @endif
                                                    </small>
                                                </div>
                                            @endforeach
                                        @else
                                            <small class="text-muted">No transactions</small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <strong>{{ number_format($order['total_price'], 2) }} {{ $order['currency'] }}</strong>
                                </td>
                                
                                <td>
                                    <div class="btn-group-vertical btn-group-sm">
                                        @php
                                            // Extract numeric ID from GraphQL ID format like "gid://shopify/Order/6935755849900"
                                            preg_match('/\/(\d+)$/', $order['id'], $matches);
                                            $numericOrderId = $matches[1] ?? '';
                                            $shopifyAdminUrl = $numericOrderId ? 'https://admin.shopify.com/store/' . explode('.', config('shopify.store_domain'))[0] . '/orders/' . $numericOrderId : '#';
                                        @endphp
                                        <a href="{{ $shopifyAdminUrl }}" 
                                           target="_blank"
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="card-footer">
                    <div class="pagination-controls">
                        <div class="d-flex gap-2">
                            @if(isset($pageInfo['hasPreviousPage']) && $pageInfo['hasPreviousPage'])
                                <a href="{{ request()->fullUrlWithQuery(['before' => $pageInfo['startCursor'], 'after' => null]) }}" 
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </a>
                            @endif
                            
                            @if(isset($pageInfo['hasNextPage']) && $pageInfo['hasNextPage'])
                                <a href="{{ request()->fullUrlWithQuery(['after' => $pageInfo['endCursor'], 'before' => null]) }}" 
                                   class="btn btn-outline-primary btn-sm">
                                    Next <i class="bi bi-chevron-right"></i>
                                </a>
                            @endif
                        </div>
                        
                        <div class="text-muted">
                            <small>
                                @if(isset($pageInfo['hasNextPage']) && $pageInfo['hasNextPage'])
                                    More results available
                                @else
                                    End of results
                                @endif
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Modal for showing all items -->
<div class="modal fade" id="allItemsModal" tabindex="-1" aria-labelledby="allItemsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="allItemsModalLabel">All Items</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="allItemsContainer">
                    <!-- Items will be loaded here by JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
// Auto-refresh every 5 minutes
setTimeout(function() {
    location.reload();
}, 300000);

// Function to show all items in modal
function showAllItems(orderHash) {
    // Get the items data from the hidden script tag
    const itemsData = JSON.parse(document.getElementById('items-data-' + orderHash).textContent);
    
    // Get the order name from the button
    const button = document.querySelector(`[onclick="showAllItems('${orderHash}')"]`);
    const orderName = button.getAttribute('data-order-name');
    
    // Set modal title
    document.getElementById('allItemsModalLabel').textContent = `All Items - Order ${orderName}`;
    
    // Build items HTML
    let itemsHtml = '';
    itemsData.forEach(function(item) {
        let statusClass = '';
        switch(item.fulfillment_status) {
            case 'CLOSED':
                statusClass = 'bg-success';
                break;
            case 'OPEN':
                statusClass = 'bg-warning';
                break;
            case 'IN_PROGRESS':
                statusClass = 'bg-info';
                break;
            default:
                statusClass = 'bg-secondary';
        }
        
        const locationHtml = item.fulfillment_location && item.fulfillment_location !== 'Not assigned' 
            ? `<small class="text-muted d-block"><i class="bi bi-geo-alt"></i> ${item.fulfillment_location}</small>`
            : `<small class="text-warning d-block"><i class="bi bi-exclamation-triangle"></i> Not assigned</small>`;
            
        itemsHtml += `
            <div class="mb-3 p-3 bg-light rounded">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <strong>${item.quantity}x</strong> ${item.name}
                        ${item.sku ? `<br><small class="text-muted">SKU: ${item.sku}</small>` : ''}
                        <br><small class="text-primary">${parseFloat(item.price).toFixed(2)} each</small>
                        <br><small class="text-success">Total: ${(parseFloat(item.price) * item.quantity).toFixed(2)}</small>
                    </div>
                    <div class="text-end">
                        <div class="mb-1">
                            <span class="badge status-badge ${statusClass}">
                                ${item.fulfillment_status}
                            </span>
                        </div>
                        ${locationHtml}
                    </div>
                </div>
            </div>
        `;
    });
    
    // Set items in modal
    document.getElementById('allItemsContainer').innerHTML = itemsHtml;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('allItemsModal'));
    modal.show();
}
</script>
@endsection