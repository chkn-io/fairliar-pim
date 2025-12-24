@extends('layouts.app')

@section('title', 'Setup Instructions')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="bi bi-gear"></i> Shopify Order Exporter Setup</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Welcome to the Shopify Order Exporter! Please follow these steps to configure your application.
                </div>

                <h5>1. Configure Your Shopify Store</h5>
                <p>Update your <code>.env</code> file with your Shopify store information:</p>
                <pre class="bg-light p-3 rounded"><code>SHOPIFY_API_KEY=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
SHOPIFY_STORE_DOMAIN=your-store.myshopify.com
SHOPIFY_API_VERSION=2025-10</code></pre>

                <h5>2. Install Dependencies</h5>
                <p>Make sure you have installed all required PHP packages:</p>
                <pre class="bg-light p-3 rounded"><code>composer install</code></pre>

                <h5>3. Features</h5>
                <ul>
                    <li><strong>Order Listing:</strong> View all orders with pagination</li>
                    <li><strong>Search & Filter:</strong> Use Shopify's query syntax to filter orders</li>
                    <li><strong>Order Details:</strong> View complete order information including line items and fulfillment status</li>
                    <li><strong>Responsive Design:</strong> Works on desktop and mobile devices</li>
                </ul>

                <h5>4. GraphQL Query Examples</h5>
                <p>Use these query examples in the search box:</p>
                <ul>
                    <li><code>fulfillment_status:unfulfilled</code> - Show unfulfilled orders</li>
                    <li><code>financial_status:paid</code> - Show paid orders</li>
                    <li><code>created_at:>2025-10-01</code> - Orders created after a specific date</li>
                    <li><code>tag:urgent</code> - Orders with specific tags</li>
                </ul>

                <div class="text-center mt-4">
                    <a href="{{ route('orders.index') }}" class="btn btn-primary btn-lg">
                        <i class="bi bi-arrow-right"></i> View Orders
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection