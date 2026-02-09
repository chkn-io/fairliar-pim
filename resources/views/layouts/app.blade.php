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
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --accent-color: #10b981;
            --accent-light: #34d399;
            --sidebar-bg: #1f2937;
            --sidebar-hover: #374151;
            --topbar-bg: #ffffff;
            --surface-color: #f9fafb;
            --card-border: #e5e7eb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            overflow-x: hidden;
            max-width: 100%;
        }

        body {
            background-color: var(--surface-color);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
            max-width: 100%;
            position: relative;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: #fff;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 1.5rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }

        .sidebar-close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
        }

        .sidebar-header h4 {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-section {
            padding: 0.5rem 1.25rem;
            margin-top: 1.5rem;
        }

        .nav-section-title {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
            margin-bottom: 0.5rem;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.25rem;
            color: #d1d5db;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s;
            font-size: 0.95rem;
            margin: 0 0.5rem;
        }

        .nav-link:hover {
            background: var(--sidebar-hover);
            color: #fff;
            transform: translateX(2px);
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: #fff;
        }

        .nav-link i {
            font-size: 1.25rem;
            width: 24px;
            text-align: center;
        }

        .nav-submenu {
            padding-left: 1rem;
            margin-top: 0.25rem;
        }

        .nav-submenu .nav-link {
            font-size: 0.875rem;
            padding: 0.625rem 1rem;
        }

        .nav-submenu .nav-link i {
            font-size: 1rem;
        }

        .sidebar-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: auto;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
            line-height: 1.2;
        }

        .user-role {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        /* Main Content Area */
        .main-wrapper {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
            max-width: 100%;
        }

        /* Top Bar */
        .topbar {
            background: var(--topbar-bg);
            border-bottom: 1px solid var(--card-border);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-primary);
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        /* Content */
        .content-wrapper {
            flex: 1;
            padding: 2rem;
            overflow-x: hidden;
            max-width: 100%;
        }

        /* Fix Bootstrap row overflow */
        .content-wrapper > .row {
            margin-left: 0;
            margin-right: 0;
        }

        /* Cards */
        .card {
            border: 1px solid var(--card-border);
            border-radius: 0.75rem;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: box-shadow 0.2s;
        }

        .card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid var(--card-border);
            padding: 1.25rem 1.5rem;
            border-radius: 0.75rem 0.75rem 0 0;
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--accent-color), #059669);
            border: none;
        }

        .btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        /* Alerts */
        .alert {
            border-radius: 0.75rem;
            border: none;
            padding: 1rem 1.25rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Tables */
        .table {
            font-size: 0.9rem;
        }

        .table-responsive {
            border-radius: 0.75rem;
            border: 1px solid var(--card-border);
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-dark {
            background: var(--sidebar-bg);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            body {
                overflow-x: hidden;
            }

            .sidebar {
                position: fixed;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 1040;
            }

            .sidebar.active {
                transform: translateX(0);
                box-shadow: 2px 0 10px rgba(0, 0, 0, 0.3);
            }

            .sidebar-close {
                display: block !important;
            }

            .main-wrapper {
                margin-left: 0;
                width: 100%;
                max-width: 100vw;
                overflow-x: hidden;
            }

            .menu-toggle {
                display: block;
            }

            .page-title {
                font-size: 1.25rem;
            }

            .topbar {
                padding: 1rem;
                width: 100%;
            }

            .topbar-right {
                display: none;
            }

            .content-wrapper {
                padding: 1rem;
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
            }

            /* Mobile Container Fixes */
            .container-fluid {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
                max-width: 100%;
                overflow-x: hidden;
            }

            .row {
                margin-left: 0;
                margin-right: 0;
                max-width: 100%;
            }

            .row > * {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            /* Mobile Card Fixes */
            .card {
                border-radius: 0.5rem;
                margin-bottom: 1rem;
            }

            .card-body {
                padding: 1rem;
            }

            /* Mobile Form Fixes */
            .row.g-3 {
                gap: 0.75rem;
            }

            .col-md-1, .col-md-2, .col-md-3, .col-md-4, .col-md-6, .col-md-8, .col-md-10 {
                width: 100%;
                max-width: 100%;
                flex: 0 0 100%;
            }

            /* Mobile Button Group Fixes */
            .d-flex.align-items-end.gap-2,
            .d-flex.gap-2 {
                flex-wrap: wrap;
                width: 100%;
            }

            .d-flex.align-items-end.gap-2 .btn,
            .d-flex.gap-2 .btn {
                flex: 1 1 auto;
                min-width: 0;
            }

            .d-flex.gap-2 .form-select {
                min-width: 150px;
            }

            /* Mobile Table Fixes */
            .table-responsive {
                border-radius: 0.5rem;
                margin: 0 -0.5rem;
                border: none;
            }

            .table {
                font-size: 0.7rem;
                min-width: 1000px;
            }

            .order-table {
                min-width: 1200px;
            }

            .table th,
            .table td {
                padding: 0.5rem 0.25rem;
                white-space: nowrap;
            }

            /* Mobile Button Sizing */
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }

            /* Mobile Header Fixes */
            h1 {
                font-size: 1.5rem;
            }

            .d-flex.justify-content-between {
                flex-wrap: wrap;
                gap: 1rem;
            }

            /* Sidebar Overlay */
            .sidebar.active::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: -1;
            }
        }

        /* Dropdown Menu */
        .dropdown-menu {
            border-radius: 0.75rem;
            border: 1px solid var(--card-border);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Legacy styles for compatibility */
        .order-table { font-size: 0.875rem; }
        .address-cell { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .customer-cell { max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .line-items-cell { max-width: 350px; min-width: 300px; }
        .transaction-cell { max-width: 200px; min-width: 150px; }
        .status-badge { font-size: 0.75rem; }
        .pagination-controls { display: flex; justify-content: between; align-items: center; margin: 20px 0; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i class="bi bi-shop" style="font-size: 2rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                <h4 style="margin: 0;">Fairliar PIM</h4>
            </div>
            <button class="sidebar-close" id="sidebarClose" style="display: none;">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <div class="nav-item">
                    <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                        <i class="bi bi-house-door"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link {{ request()->routeIs('orders.*') ? 'active' : '' }}" href="{{ route('orders.index') }}">
                        <i class="bi bi-receipt"></i>
                        <span>Orders</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}">
                        <i class="bi bi-bag"></i>
                        <span>Products</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.index') }}">
                        <i class="bi bi-boxes"></i>
                        <span>Inventory</span>
                    </a>
                </div>
            </div>

            @auth
                @if(Auth::user()->isAdmin())
                    <div class="nav-section">
                        <div class="nav-section-title">Management</div>
                        <div class="nav-item">
                            <a class="nav-link {{ request()->routeIs('stock-sync.*') ? 'active' : '' }}" href="{{ route('stock-sync.index') }}">
                                <i class="bi bi-arrow-repeat"></i>
                                <span>Stock Sync</span>
                            </a>
                        </div>
                        <div class="nav-item">
                            <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                                <i class="bi bi-people"></i>
                                <span>Users</span>
                            </a>
                        </div>
                        <div class="nav-item">
                            <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="#" data-bs-toggle="collapse" data-bs-target="#settingsMenu" aria-expanded="{{ request()->routeIs('settings.*') ? 'true' : 'false' }}">
                                <i class="bi bi-gear"></i>
                                <span>Settings</span>
                                <i class="bi bi-chevron-down ms-auto" style="font-size: 0.75rem;"></i>
                            </a>
                            <div class="collapse {{ request()->routeIs('settings.*') ? 'show' : '' }}" id="settingsMenu">
                                <div class="nav-submenu">
                                    <a class="nav-link {{ request()->routeIs('settings.warehouse') ? 'active' : '' }}" href="{{ route('settings.warehouse') }}">
                                        <i class="bi bi-box-seam"></i>
                                        <span>Warehouse API</span>
                                    </a>
                                    <a class="nav-link {{ request()->routeIs('settings.stores*') ? 'active' : '' }}" href="{{ route('settings.stores') }}">
                                        <i class="bi bi-key"></i>
                                        <span>Store API Keys</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endauth
        </nav>

        @auth
            <div class="sidebar-footer">
                <div class="dropdown">
                    <div class="user-profile" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <div class="user-info">
                            <div class="user-name">{{ Auth::user()->name }}</div>
                            <div class="user-role">{{ Auth::user()->getRoleDisplayName() }}</div>
                        </div>
                        <i class="bi bi-three-dots-vertical"></i>
                    </div>
                    <ul class="dropdown-menu">
                        <li><h6 class="dropdown-header">{{ Auth::user()->email }}</h6></li>
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
            </div>
        @endauth
    </aside>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" id="menuToggle">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="page-title">@yield('page-title', 'Dashboard')</h1>
            </div>
            <div class="topbar-right">
                <span class="text-muted">{{ Auth::user()->name ?? 'Guest' }}</span>
            </div>
        </header>

        <!-- Content -->
        <main class="content-wrapper">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu toggle
        const sidebar = document.getElementById('sidebar');
        const menuToggle = document.getElementById('menuToggle');
        const sidebarClose = document.getElementById('sidebarClose');
        
        if (menuToggle) {
            menuToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('active');
            });
        }

        if (sidebarClose) {
            sidebarClose.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.remove('active');
            });
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Close sidebar when clicking any navigation link on mobile
        const navLinks = sidebar.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('active');
                }
            });
        });

        // Prevent clicks inside sidebar from closing it
        sidebar.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    </script>
    @yield('scripts')
</body>
</html>