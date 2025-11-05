<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - @yield('title', 'Shopify Orders')</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    
    <style>
        .order-table {
            font-size: 0.875rem;
        }
        .address-cell {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .line-items-cell {
            max-width: 350px;
            min-width: 300px;
        }
        .status-badge {
            font-size: 0.75rem;
        }
        .fulfillment-item {
            border-left: 3px solid #dee2e6;
            transition: border-color 0.2s;
        }
        .fulfillment-item:hover {
            border-left-color: #0d6efd;
        }
        .pagination-controls {
            display: flex;
            justify-content: between;
            align-items: center;
            margin: 20px 0;
        }
        .table-responsive {
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
        }
        .navbar-brand {
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="{{ route('orders.index') }}">
                <i class="bi bi-shop"></i> Shopify Order Exporter
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="{{ route('orders.index') }}">
                    <i class="bi bi-list-ul"></i> Orders
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @yield('scripts')
</body>
</html>