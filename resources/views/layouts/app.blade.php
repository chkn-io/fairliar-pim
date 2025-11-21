<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>{{ config('app.name', 'Laravel') }} - @yield('title', 'Shopify Orders')</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-color: #495057;
            --primary-light: #6c757d;
            --accent-color: #28a745;
            --accent-light: #34ce57;
            --muted-color: #868e96;
            --surface-color: #f8f9fa;
            --card-border: #e9ecef;
        }

        body {
            background-color: #ffffff;
            color: #495057;
        }

        .bg-primary {
            background-color: var(--primary-color) !important;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-light);
            border-color: var(--primary-light);
        }

        .btn-success {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .btn-success:hover {
            background-color: var(--accent-light);
            border-color: var(--accent-light);
        }

        .text-primary {
            color: var(--primary-color) !important;
        }

        .order-table {
            font-size: 0.875rem;
        }
        .address-cell {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .customer-cell {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .line-items-cell {
            max-width: 350px;
            min-width: 300px;
        }
        .transaction-cell {
            max-width: 200px;
            min-width: 150px;
        }
        .status-badge {
            font-size: 0.75rem;
        }
        .fulfillment-item {
            border-left: 3px solid #dee2e6;
            transition: border-color 0.2s;
        }
        .fulfillment-item:hover {
            border-left-color: var(--primary-color);
        }
        .pagination-controls {
            display: flex;
            justify-content: between;
            align-items: center;
            margin: 20px 0;
        }
        .table-responsive {
            border-radius: 0.375rem;
            border: 1px solid var(--card-border);
        }
        .navbar-brand {
            font-weight: 600;
        }
        .card {
            border: 1px solid var(--card-border);
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .card-header {
            background-color: var(--surface-color);
            border-bottom: 1px solid var(--card-border);
        }
        .table-dark {
            background-color: var(--primary-color);
        }
        .bg-light {
            background-color: var(--surface-color) !important;
        }
        .alert-info {
            background-color: #e7f3ff;
            border-color: #b8daff;
            color: #31708f;
        }
        .navbar {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <i class="bi bi-shop"></i> Shopify Order Manager
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav me-auto">
                    <a class="nav-link" href="{{ route('home') }}">
                        <i class="bi bi-house"></i> Home
                    </a>
                    <a class="nav-link" href="{{ route('orders.index') }}">
                        <i class="bi bi-list-ul"></i> Orders
                    </a>
                    <a class="nav-link" href="{{ route('inventory.index') }}">
                        <i class="bi bi-boxes"></i> Inventory
                    </a>
                    <a class="nav-link" href="{{ route('stock-sync.index') }}">
                        <i class="bi bi-arrow-repeat"></i> Stock Sync
                    </a>
                    @auth
                        @if(Auth::user()->isAdmin())
                            <a class="nav-link" href="{{ route('users.index') }}">
                                <i class="bi bi-people"></i> Users
                            </a>
                            <a class="nav-link" href="{{ route('users.create') }}">
                                <i class="bi bi-person-plus"></i> Add User
                            </a>
                            <a class="nav-link" href="{{ route('settings.warehouse') }}">
                                <i class="bi bi-gear"></i> Settings
                            </a>
                        @endif
                    @endauth
                </div>
                
                <div class="navbar-nav">
                    @auth
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> {{ Auth::user()->name }}
                                <span class="badge bg-secondary ms-1">{{ Auth::user()->getRoleDisplayName() }}</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><h6 class="dropdown-header">{{ Auth::user()->name }}</h6></li>
                                <li><small class="dropdown-item-text text-muted">{{ Auth::user()->email }}</small></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item">
                                            <i class="bi bi-box-arrow-right"></i> Sign Out
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @else
                        <a class="nav-link" href="{{ route('login') }}">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 px-lg-4">
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