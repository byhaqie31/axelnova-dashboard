<?php

use App\Http\Controllers\Api\V1\Admin\LeadsController;
use App\Http\Controllers\Api\V1\QuoteBuilderConfigController;
use App\Http\Controllers\Api\V1\QuoteRequestController;
use Illuminate\Support\Facades\Route;

// Public — pricing config (cached 1 hour)
Route::get('/v1/quote-builder/config', [QuoteBuilderConfigController::class, 'show'])
    ->name('quote-builder.config');

// Public — submit quote (3 per IP per hour)
Route::middleware('throttle:3,60')->group(function () {
    Route::post('/v1/quote-requests', [QuoteRequestController::class, 'store'])
        ->name('quote-requests.store');
});

// Admin — Sanctum + role:admin
Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('v1/admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/leads', [LeadsController::class, 'index'])->name('leads.index');
        Route::get('/leads/{lead}', [LeadsController::class, 'show'])->name('leads.show');
        Route::post('/leads/{lead}/status', [LeadsController::class, 'updateStatus'])->name('leads.status');
        Route::post('/leads/{lead}/convert', [LeadsController::class, 'convert'])->name('leads.convert');
    });
