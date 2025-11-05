<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;

// Redirect root to orders
Route::get('/', function () {
    return redirect()->route('orders.index');
});

// Setup route
Route::get('/setup', function () {
    return view('setup');
})->name('setup');

// Orders routes
Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/export', [OrderController::class, 'export'])->name('orders.export');
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
