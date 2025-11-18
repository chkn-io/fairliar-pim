@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row">
    <div class="col-12">
        <!-- Welcome Section -->
        <div class="card mb-4">
            <div class="card-body text-center py-5">
                <i class="bi bi-shop text-primary mb-3" style="font-size: 3rem;"></i>
                <h1 class="h3 mb-3">Welcome to Shopify Order Manager</h1>
                <p class="text-muted mb-4">Manage and export your Shopify orders with ease</p>
                
                <div class="d-flex justify-content-center gap-3">
                    <a href="{{ route('orders.index') }}" class="btn btn-primary">
                        <i class="bi bi-list-ul"></i> View Orders
                    </a>
                    @if(Auth::user()->isAdmin())
                        <a href="{{ route('users.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-people"></i> Manage Users
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row g-3">
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-bag-check text-success mb-3" style="font-size: 2rem;"></i>
                        <h5 class="card-title">Orders</h5>
                        <p class="text-muted">View and manage all your Shopify orders in one place</p>
                        <a href="{{ route('orders.index') }}" class="btn btn-outline-primary btn-sm">
                            Go to Orders
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-boxes text-info mb-3" style="font-size: 2rem;"></i>
                        <h5 class="card-title">Inventory</h5>
                        <p class="text-muted">Export inventory data by location with advanced filters</p>
                        <a href="{{ route('inventory.index') }}" class="btn btn-outline-primary btn-sm">
                            Export Inventory
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-download text-warning mb-3" style="font-size: 2rem;"></i>
                        <h5 class="card-title">Export</h5>
                        <p class="text-muted">Export filtered orders to CSV for analysis and reporting</p>
                        <a href="{{ route('orders.index') }}" class="btn btn-outline-primary btn-sm">
                            Export Data
                        </a>
                    </div>
                </div>
            </div>
            
            @if(Auth::user()->isAdmin())
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-people text-primary mb-3" style="font-size: 2rem;"></i>
                        <h5 class="card-title">Users</h5>
                        <p class="text-muted">Manage user accounts and permissions</p>
                        <a href="{{ route('users.index') }}" class="btn btn-outline-primary btn-sm">
                            Manage Users
                        </a>
                    </div>
                </div>
            </div>
            @else
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-funnel text-secondary mb-3" style="font-size: 2rem;"></i>
                        <h5 class="card-title">Filters</h5>
                        <p class="text-muted">Use advanced filters to find specific orders quickly</p>
                        <a href="{{ route('orders.index') }}" class="btn btn-outline-primary btn-sm">
                            Filter Orders
                        </a>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- User Info Card -->
        <div class="row mt-4">
            <div class="col-md-6 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-person-circle"></i> Your Account</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <strong>Name:</strong><br>
                                <span class="text-muted">{{ Auth::user()->name }}</span>
                            </div>
                            <div class="col-6">
                                <strong>Role:</strong><br>
                                <span class="badge bg-{{ Auth::user()->isAdmin() ? 'primary' : 'secondary' }}">
                                    {{ Auth::user()->getRoleDisplayName() }}
                                </span>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-12">
                                <strong>Email:</strong><br>
                                <span class="text-muted">{{ Auth::user()->email }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection