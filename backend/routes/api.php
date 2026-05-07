<?php

use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\OrdersController;
use App\Http\Controllers\Api\V1\Admin\ProjectsController;
use App\Http\Controllers\Api\V1\Admin\QuotationsController;
use App\Http\Controllers\Api\V1\Admin\ServiceCategoriesController;
use App\Http\Controllers\Api\V1\Admin\ServicePackagesController;
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
        Route::post('/quotations/{quotation}/accept', [QuotationsController::class, 'accept'])->name('quotations.accept');

        Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrdersController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/status', [OrdersController::class, 'updateStatus'])->name('orders.status');

        // CMS — Service categories
        Route::get('/service-categories', [ServiceCategoriesController::class, 'index'])->name('service-categories.index');
        Route::post('/service-categories', [ServiceCategoriesController::class, 'store'])->name('service-categories.store');
        Route::get('/service-categories/{serviceCategory}', [ServiceCategoriesController::class, 'show'])->name('service-categories.show');
        Route::put('/service-categories/{serviceCategory}', [ServiceCategoriesController::class, 'update'])->name('service-categories.update');
        Route::delete('/service-categories/{serviceCategory}', [ServiceCategoriesController::class, 'destroy'])->name('service-categories.destroy');

        // CMS — Service packages
        Route::get('/service-packages', [ServicePackagesController::class, 'index'])->name('service-packages.index');
        Route::post('/service-packages', [ServicePackagesController::class, 'store'])->name('service-packages.store');
        Route::get('/service-packages/{servicePackage}', [ServicePackagesController::class, 'show'])->name('service-packages.show');
        Route::put('/service-packages/{servicePackage}', [ServicePackagesController::class, 'update'])->name('service-packages.update');
        Route::delete('/service-packages/{servicePackage}', [ServicePackagesController::class, 'destroy'])->name('service-packages.destroy');

        // CMS — Projects
        Route::get('/projects', [ProjectsController::class, 'index'])->name('projects.index');
        Route::post('/projects', [ProjectsController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}', [ProjectsController::class, 'show'])->name('projects.show');
        Route::put('/projects/{project}', [ProjectsController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [ProjectsController::class, 'destroy'])->name('projects.destroy');
    });
