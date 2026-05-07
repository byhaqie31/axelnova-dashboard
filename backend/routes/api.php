<?php

use App\Http\Controllers\Api\V1\Admin\LeadsController;
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

// Admin — Sanctum SPA (stateful via cookie + CSRF) + role:admin
Route::middleware([
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        'auth:sanctum',
        'role:admin',
    ])
    ->prefix('v1/admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/leads', [LeadsController::class, 'index'])->name('leads.index');
        Route::get('/leads/{lead}', [LeadsController::class, 'show'])->name('leads.show');
        Route::post('/leads/{lead}/status', [LeadsController::class, 'updateStatus'])->name('leads.status');
        Route::post('/leads/{lead}/convert', [LeadsController::class, 'convert'])->name('leads.convert');
    });
