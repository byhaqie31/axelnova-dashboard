<?php

use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\OrdersController;
use App\Http\Controllers\Api\V1\Admin\QuotationsController;
use App\Http\Controllers\Api\V1\QuoteBuilderConfigController;
use App\Http\Controllers\Api\V1\QuoteRequestController;
use Illuminate\Support\Facades\Route;

// Public — pricing config (cached 1 hour)
Route::get('/v1/quote-builder/config', [QuoteBuilderConfigController::class, 'show'])
    ->name('quote-builder.config');

// Public — submit quote
// Production: 3/hour per IP (spam protection). Non-prod: very high so dev/staging can test freely.
$quoteThrottle = app()->environment('production') ? 'throttle:3,60' : 'throttle:1000,1';
Route::middleware($quoteThrottle)->group(function () {
    Route::post('/v1/quote-requests', [QuoteRequestController::class, 'store'])
        ->name('quote-requests.store');
});

// Admin — login (public, throttled to deter brute-force)
$loginThrottle = app()->environment('production') ? 'throttle:10,1' : 'throttle:1000,1';
Route::middleware($loginThrottle)->group(function () {
    Route::post('/v1/admin/login', [AuthController::class, 'login'])->name('admin.login');
});

// Admin — Sanctum SPA (stateful via cookie + CSRF) + role:admin
Route::middleware([
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        'auth:sanctum',
        'role:admin',
    ])
    ->prefix('v1/admin')
    ->name('admin.')
    ->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');

        Route::get('/quotations', [QuotationsController::class, 'index'])->name('quotations.index');
        Route::get('/quotations/{quotation}', [QuotationsController::class, 'show'])->name('quotations.show');
        Route::post('/quotations/{quotation}/status', [QuotationsController::class, 'updateStatus'])->name('quotations.status');
        Route::post('/quotations/{quotation}/convert', [QuotationsController::class, 'convert'])->name('quotations.convert');

        Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrdersController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/project-status', [OrdersController::class, 'updateProjectStatus'])->name('orders.project-status');
    });
