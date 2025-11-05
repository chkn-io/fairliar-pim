@extends('layouts.app')

@section('title', 'Order Details - ' . $order['name'])

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-receipt text-primary"></i> Order {{ $order['name'] }}
            </h1>
            <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Orders
            </a>
        </div>

        <div class="row">
            <!-- Order Information -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Order Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td><strong>Order Number:</strong></td>
                                <td>{{ $order['name'] }}</td>
                            </tr>
                            <tr>
                                <td><strong>Order ID:</strong></td>
                                <td><code>{{ $order['id'] }}</code></td>
                            </tr>
                            <tr>
                                <td><strong>Created:</strong></td>
                                <td>{{ date('F j, Y g:i A', strtotime($order['created_at'])) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Total:</strong></td>
                                <td><strong class="text-success">{{ number_format($order['total_price'], 2) }} {{ $order['currency'] }}</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Shipping Address -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Shipping Address</h5>
                    </div>
                    <div class="card-body">
                        @if($order['shipping_address']['name'] || $order['shipping_address']['address1'])
                            <address class="mb-0">
                                @if($order['shipping_address']['name'])
                                    <strong>{{ $order['shipping_address']['name'] }}</strong><br>
                                @endif
                                @if($order['shipping_address']['address1'])
                                    {{ $order['shipping_address']['address1'] }}<br>
                                @endif
                                @if($order['shipping_address']['city'] || $order['shipping_address']['province'])
                                    {{ $order['shipping_address']['city'] }}{{ $order['shipping_address']['province'] ? ', ' . $order['shipping_address']['province'] : '' }}
                                    {{ $order['shipping_address']['zip'] }}<br>
                                @endif
                                @if($order['shipping_address']['country'])
                                    {{ $order['shipping_address']['country'] }}
                                @endif
                            </address>
                        @else
                            <p class="text-muted mb-0">No shipping address provided</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Line Items -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-box-seam"></i> Order Items</h5>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order['line_items'] as $item)
                        <tr>
                            <td>
                                <strong>{{ $item['name'] }}</strong>
                            </td>
                            <td>
                                @if($item['sku'])
                                    <code>{{ $item['sku'] }}</code>
                                @else
                                    <span class="text-muted">No SKU</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-primary">{{ $item['quantity'] }}</span>
                            </td>
                            <td>
                                {{ number_format($item['price'], 2) }} {{ $order['currency'] }}
                            </td>
                            <td>
                                <strong>{{ number_format($item['price'] * $item['quantity'], 2) }} {{ $order['currency'] }}</strong>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="4" class="text-end">Total:</th>
                            <th>{{ number_format($order['total_price'], 2) }} {{ $order['currency'] }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Fulfillment Orders -->
        @if(!empty($order['fulfillment_orders']))
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-truck"></i> Fulfillment Information</h5>
            </div>
            <div class="card-body">
                @foreach($order['fulfillment_orders'] as $fulfillment)
                <div class="border rounded p-3 mb-3">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Fulfillment Status</h6>
                            <span class="badge fs-6
                                @if($fulfillment['status'] == 'CLOSED') bg-success
                                @elseif($fulfillment['status'] == 'OPEN') bg-warning
                                @elseif($fulfillment['status'] == 'IN_PROGRESS') bg-info
                                @else bg-secondary
                                @endif
                            ">
                                {{ $fulfillment['status'] }}
                            </span>
                        </div>
                        <div class="col-md-6">
                            <h6>Assigned Location</h6>
                            <p class="mb-0">{{ $fulfillment['location'] ?: 'Not assigned' }}</p>
                        </div>
                    </div>
                    
                    @if(!empty($fulfillment['line_items']))
                    <hr>
                    <h6>Items in this fulfillment:</h6>
                    <ul class="list-unstyled mb-0">
                        @foreach($fulfillment['line_items'] as $item)
                        <li class="mb-1">
                            <strong>{{ $item['quantity'] }}x</strong> {{ $item['name'] }}
                            @if($item['sku'])
                                <small class="text-muted">({{ $item['sku'] }})</small>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @else
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No fulfillment information available for this order.
        </div>
        @endif
    </div>
</div>
@endsection