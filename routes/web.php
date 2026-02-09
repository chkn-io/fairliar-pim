<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductsController;

// Authentication routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Registration routes (admin only)
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);

// Protected routes (require authentication)
Route::middleware('auth')->group(function () {
    // Home page
    Route::get('/', function () {
        return view('home');
    })->name('home');

    // Setup route
    Route::get('/setup', function () {
        return view('setup');
    })->name('setup');

    // Orders routes
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/export', [OrderController::class, 'export'])->name('orders.export');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    // Inventory routes
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/export', [InventoryController::class, 'export'])->name('inventory.export');

    // Products routes
    Route::get('/products', [ProductsController::class, 'index'])->name('products.index');
    Route::get('/products/export', [ProductsController::class, 'export'])->name('products.export');

    // Stock Sync routes
    
    Route::get('/stock-sync/export', [App\Http\Controllers\StockSyncController::class, 'export'])->name('stock-sync.export');
    Route::get('/stock-sync/clear-cache', [App\Http\Controllers\StockSyncController::class, 'clearCache'])->name('stock-sync.clear-cache');
    Route::post('/stock-sync/get-warehouse-stock', [App\Http\Controllers\StockSyncController::class, 'getWarehouseStock'])->name('stock-sync.get-warehouse-stock');
    Route::post('/stock-sync/get-warehouse-stock-batch', [App\Http\Controllers\StockSyncController::class, 'getWarehouseStockBatch'])->name('stock-sync.get-warehouse-stock-batch');
    Route::post('/stock-sync/get-warehouse-stock-by-sku', [App\Http\Controllers\StockSyncController::class, 'getWarehouseStockBySku'])->name('stock-sync.get-warehouse-stock-by-sku');
    Route::post('/stock-sync/toggle-pim-sync', [App\Http\Controllers\StockSyncController::class, 'togglePimSync'])->name('stock-sync.toggle-pim-sync');
    Route::post('/stock-sync/sync-stock', [App\Http\Controllers\StockSyncController::class, 'syncStock'])->name('stock-sync.sync-stock');
    
    // Warehouse sync endpoint (admin only)
    Route::middleware('admin')->post('/warehouse/sync', [App\Http\Controllers\StockSyncController::class, 'syncWarehouse'])->name('warehouse.sync');

    // Settings routes (admin only)
    Route::middleware('admin')->group(function () {
        Route::get('/settings/warehouse', [App\Http\Controllers\SettingsController::class, 'warehouse'])->name('settings.warehouse');
        Route::put('/settings/warehouse', [App\Http\Controllers\SettingsController::class, 'updateWarehouse'])->name('settings.warehouse.update');
        
        // Store API Keys settings
        Route::get('/settings/stores', [App\Http\Controllers\StoreSettingsController::class, 'index'])->name('settings.stores');
        Route::get('/settings/stores/{store}', [App\Http\Controllers\StoreSettingsController::class, 'show'])->name('settings.stores.show');
        Route::post('/settings/stores', [App\Http\Controllers\StoreSettingsController::class, 'store'])->name('settings.stores.store');
        Route::put('/settings/stores/{store}', [App\Http\Controllers\StoreSettingsController::class, 'update'])->name('settings.stores.update');
        Route::delete('/settings/stores/{store}', [App\Http\Controllers\StoreSettingsController::class, 'destroy'])->name('settings.stores.destroy');
        Route::post('/settings/stores/{store}/set-default', [App\Http\Controllers\StoreSettingsController::class, 'setDefault'])->name('settings.stores.set-default');
        Route::post('/settings/stores/{store}/toggle-active', [App\Http\Controllers\StoreSettingsController::class, 'toggleActive'])->name('settings.stores.toggle-active');
    });

    // Admin only routes for user management
    Route::middleware('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::get('/stock-sync', [App\Http\Controllers\StockSyncController::class, 'index'])->name('stock-sync.index');
    });
});
