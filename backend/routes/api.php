<?php

use App\Http\Controllers\Api\V1\Admin\ActivityController;
use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\ClientsController;
use App\Http\Controllers\Api\V1\Admin\InquiriesController;
use App\Http\Controllers\Api\V1\Admin\InvoicesController;
use App\Http\Controllers\Api\V1\Admin\OrdersController;
use App\Http\Controllers\Api\V1\Admin\PaymentsController;
use App\Http\Controllers\Api\V1\Admin\ProjectsController;
use App\Http\Controllers\Api\V1\Admin\QuotationsController;
use App\Http\Controllers\Api\V1\Admin\ReferralsController;
use App\Http\Controllers\Api\V1\Admin\ServiceAddonsController;
use App\Http\Controllers\Api\V1\Admin\ServiceCategoriesController;
use App\Http\Controllers\Api\V1\Admin\ServiceScopeFieldsController;
use App\Http\Controllers\Api\V1\Admin\ServicePackagesController;
use App\Http\Controllers\Api\V1\Admin\UsersController;
use App\Http\Controllers\Api\V1\Team\AuthController as TeamAuthController;
use App\Http\Controllers\Api\V1\Team\InquiriesController as TeamInquiriesController;
use App\Http\Controllers\Api\V1\Team\ReferralsController as TeamReferralsController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Middleware\LogAdminActivity;
use App\Http\Controllers\Api\V1\InquiryController;
use App\Http\Controllers\Api\V1\PublicProjectsController;
use App\Http\Controllers\Api\V1\PublicServicesController;
use App\Http\Controllers\Api\V1\QuoteBuilderConfigController;
use App\Http\Controllers\Api\V1\QuoteRequestController;
use App\Http\Controllers\Api\V1\LikesController;
use App\Http\Controllers\Api\V1\ReferralController;
use App\Http\Controllers\Api\V1\TrackingController;
use App\Http\Controllers\Api\V1\Admin\AnalyticsController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

// Public — pricing config (cached 1 hour)
Route::get('/v1/quote-builder/config', [QuoteBuilderConfigController::class, 'show'])
    ->name('quote-builder.config');

// Public — service catalogue + portfolio projects (CMS-managed, served read-only).
Route::get('/v1/services', [PublicServicesController::class, 'index'])->name('services.index');
Route::get('/v1/projects', [PublicProjectsController::class, 'index'])->name('projects.index');
Route::get('/v1/projects/{slug}', [PublicProjectsController::class, 'show'])->name('projects.show');

// Public — token-gated quotation document data for the PDF renderer (unguessable token).
Route::get('/v1/documents/{token}', [DocumentController::class, 'show'])->name('documents.show');

// Public — submit quote / referral.
// Production: 8/hour per IP (spam protection). Non-prod: very high so dev/staging can test freely.
// NB: Laravel's simple `throttle:N,M` keys the bucket on domain+IP, NOT the path — every
// route inside one throttle group shares a single per-IP counter. Inquiries therefore get
// their own group below so heavy quote traffic can't starve the lightweight intake form.
$quoteThrottle = app()->environment('production') ? 'throttle:8,60' : 'throttle:1000,1';
Route::middleware($quoteThrottle)->group(function () {
    Route::post('/v1/quote-requests', [QuoteRequestController::class, 'store'])
        ->name('quote-requests.store');

    // Partner referrals — same env-aware policy.
    Route::post('/v1/referrals', [ReferralController::class, 'store'])
        ->name('referrals.store');
});

// Public — project inquiries: lightweight intake (the admin builds the priced quote),
// so a more forgiving ceiling than quotes. Production: 20/hour per IP.
$inquiryThrottle = app()->environment('production') ? 'throttle:20,60' : 'throttle:1000,1';
Route::middleware($inquiryThrottle)->group(function () {
    Route::post('/v1/inquiries', [InquiryController::class, 'store'])
        ->name('inquiries.store');
});

// Public — analytics page-view beacon. High ceiling (normal browsing bursts),
// stateless, fire-and-forget. Server hashes the IP; obvious bots are dropped.
$trackThrottle = app()->environment('production') ? 'throttle:120,1' : 'throttle:100000,1';
Route::middleware($trackThrottle)->group(function () {
    Route::post('/v1/track/page-view', [TrackingController::class, 'pageView'])
        ->name('track.page-view');

    // Anonymous like toggle for a project / service package.
    Route::post('/v1/likes/{type}/{id}', [LikesController::class, 'toggle'])
        ->whereNumber('id')
        ->name('likes.toggle');
});

// Admin — login (public, throttled to deter brute-force)
$loginThrottle = app()->environment('production') ? 'throttle:10,1' : 'throttle:1000,1';
Route::middleware($loginThrottle)->group(function () {
    Route::post('/v1/admin/login', [AuthController::class, 'login'])->name('admin.login');

    // Workspace login — same brute-force throttle. Admits every internal role
    // (marketer/engineer land here since they get 403 on the cockpit login).
    Route::post('/v1/team/login', [TeamAuthController::class, 'login'])->name('team.login');
});

// Admin cockpit — Sanctum SPA (stateful via cookie + CSRF), cockpit tier only
// (founder + partner). Workspace roles (marketer/engineer) get 403 here and use
// /v1/team/* instead (Phase 3b).
Route::middleware([
    EnsureFrontendRequestsAreStateful::class,
    'auth:sanctum',
    'role:cockpit',
    LogAdminActivity::class,
])
    ->prefix('v1/admin')
    ->name('admin.')
    ->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');

        // Team provisioning — founder-only (Gate: manage-users, enforced in-controller).
        Route::get('/users', [UsersController::class, 'index'])->name('users.index');
        Route::post('/users', [UsersController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UsersController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/deactivate', [UsersController::class, 'deactivate'])->name('users.deactivate');

        // Customers (clients) — typeahead for the builder + the Customers spine
        Route::get('/clients', [ClientsController::class, 'index'])->name('clients.index');
        Route::post('/clients', [ClientsController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}', [ClientsController::class, 'show'])->name('clients.show');
        Route::put('/clients/{client}', [ClientsController::class, 'update'])->name('clients.update');

        // Analytics overview (traffic / engagement) + revenue attribution
        Route::get('/analytics/overview', [AnalyticsController::class, 'overview'])->name('analytics.overview');
        Route::get('/analytics/attribution', [AnalyticsController::class, 'attribution'])->name('analytics.attribution');

        // Activity feed — the audit trail (founder + partner, per the cockpit group)
        Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');

        Route::get('/quotations', [QuotationsController::class, 'index'])->name('quotations.index');
        Route::post('/quotations', [QuotationsController::class, 'store'])->name('quotations.store');
        Route::post('/quotations/preview', [QuotationsController::class, 'preview'])->name('quotations.preview');
        Route::get('/quotations/{quotation}', [QuotationsController::class, 'show'])->name('quotations.show');
        Route::put('/quotations/{quotation}', [QuotationsController::class, 'update'])->name('quotations.update');
        Route::post('/quotations/{quotation}/status', [QuotationsController::class, 'updateStatus'])->name('quotations.status');
        Route::post('/quotations/{quotation}/expiry', [QuotationsController::class, 'setExpiry'])->name('quotations.expiry');
        Route::post('/quotations/{quotation}/send', [QuotationsController::class, 'send'])->name('quotations.send');
        Route::post('/quotations/{quotation}/accept', [QuotationsController::class, 'accept'])->name('quotations.accept');

        Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
        // Money roll-up for the dashboard — must precede the {order} wildcard.
        Route::get('/orders/stats', [OrdersController::class, 'stats'])->name('orders.stats');
        Route::get('/orders/{order}', [OrdersController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/status', [OrdersController::class, 'updateStatus'])->name('orders.status');
        Route::post('/orders/{order}/schedule', [OrdersController::class, 'updateSchedule'])->name('orders.schedule');
        // Issue an invoice/receipt for the order (freezes a document snapshot).
        Route::post('/orders/{order}/documents', [OrdersController::class, 'issueDocument'])->name('orders.documents.issue');
        // Live preview of the would-be invoice document (no persist).
        Route::post('/orders/{order}/documents/preview', [OrdersController::class, 'previewDocument'])->name('orders.documents.preview');

        // Invoices — cross-order list + detail (the standalone Invoices module).
        Route::get('/invoices', [InvoicesController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [InvoicesController::class, 'show'])->name('invoices.show');

        // Payments — the money ledger. Record/refund/issue-receipt flow through here.
        Route::get('/payments', [PaymentsController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [PaymentsController::class, 'show'])->name('payments.show');
        Route::post('/orders/{order}/payments', [PaymentsController::class, 'store'])->name('orders.payments.store');
        Route::post('/payments/{payment}/refund', [PaymentsController::class, 'refund'])->name('payments.refund');
        Route::get('/payments/{payment}/receipt/preview', [PaymentsController::class, 'receiptPreview'])->name('payments.receipt.preview');
        Route::post('/payments/{payment}/receipt', [PaymentsController::class, 'issueReceipt'])->name('payments.receipt');

        // Partner referrals
        Route::get('/referrals', [ReferralsController::class, 'index'])->name('referrals.index');
        Route::get('/referrals/{referral}', [ReferralsController::class, 'show'])->name('referrals.show');
        Route::post('/referrals/{referral}/status', [ReferralsController::class, 'updateStatus'])->name('referrals.status');
        Route::post('/referrals/{referral}/link-order', [ReferralsController::class, 'linkOrder'])->name('referrals.link-order');
        Route::post('/referrals/{referral}/commission-email', [ReferralsController::class, 'sendCommissionEmail'])->name('referrals.commission-email');

        // Project inquiries
        Route::get('/inquiries', [InquiriesController::class, 'index'])->name('inquiries.index');
        Route::get('/inquiries/{inquiry}', [InquiriesController::class, 'show'])->name('inquiries.show');
        Route::post('/inquiries/{inquiry}/status', [InquiriesController::class, 'updateStatus'])->name('inquiries.status');
        Route::post('/inquiries/{inquiry}/quotation', [InquiriesController::class, 'linkQuotation'])->name('inquiries.quotation.link');
        Route::delete('/inquiries/{inquiry}/quotation', [InquiriesController::class, 'unlinkQuotation'])->name('inquiries.quotation.unlink');

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

        // CMS — Service add-ons (quote builder extras)
        Route::get('/service-addons', [ServiceAddonsController::class, 'index'])->name('service-addons.index');
        Route::post('/service-addons', [ServiceAddonsController::class, 'store'])->name('service-addons.store');
        Route::get('/service-addons/{serviceAddon}', [ServiceAddonsController::class, 'show'])->name('service-addons.show');
        Route::put('/service-addons/{serviceAddon}', [ServiceAddonsController::class, 'update'])->name('service-addons.update');
        Route::delete('/service-addons/{serviceAddon}', [ServiceAddonsController::class, 'destroy'])->name('service-addons.destroy');

        // CMS — Scope fields (quote builder per-category inputs + pricing)
        Route::get('/service-scope-fields', [ServiceScopeFieldsController::class, 'index'])->name('service-scope-fields.index');
        Route::post('/service-scope-fields', [ServiceScopeFieldsController::class, 'store'])->name('service-scope-fields.store');
        Route::get('/service-scope-fields/{serviceScopeField}', [ServiceScopeFieldsController::class, 'show'])->name('service-scope-fields.show');
        Route::put('/service-scope-fields/{serviceScopeField}', [ServiceScopeFieldsController::class, 'update'])->name('service-scope-fields.update');
        Route::delete('/service-scope-fields/{serviceScopeField}', [ServiceScopeFieldsController::class, 'destroy'])->name('service-scope-fields.destroy');

        // CMS — Projects
        Route::get('/projects', [ProjectsController::class, 'index'])->name('projects.index');
        Route::post('/projects', [ProjectsController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}', [ProjectsController::class, 'show'])->name('projects.show');
        Route::put('/projects/{project}', [ProjectsController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [ProjectsController::class, 'destroy'])->name('projects.destroy');
    });

// Team workspace — Sanctum, workspace tier (all four internal roles). Scoped to
// the surfaces staff own: inquiry triage + the referral programme. Every payload
// here is a *_Team resource that omits money — the cockpit keeps the financials.
// Cockpit-only work (quotations, orders, billing, users) is simply absent, so a
// marketer/engineer token can only ever reach what's mounted below.
Route::middleware([
    EnsureFrontendRequestsAreStateful::class,
    'auth:sanctum',
    'role:workspace',
])
    ->prefix('v1/team')
    ->name('team.')
    ->group(function () {
        Route::post('/logout', [TeamAuthController::class, 'logout'])->name('logout');
        Route::get('/me', [TeamAuthController::class, 'me'])->name('me');

        // Project inquiries — triage + respond (all workspace roles).
        Route::get('/inquiries', [TeamInquiriesController::class, 'index'])->name('inquiries.index');
        Route::get('/inquiries/{inquiry}', [TeamInquiriesController::class, 'show'])->name('inquiries.show');
        Route::post('/inquiries/{inquiry}/status', [TeamInquiriesController::class, 'updateStatus'])->name('inquiries.status');

        // Referral programme — the marketer owns it; engineers are excluded here
        // (they can enter /team, but not this surface). founder/partner keep access.
        Route::middleware('role:founder,partner,marketer')->group(function () {
            Route::get('/referrals', [TeamReferralsController::class, 'index'])->name('referrals.index');
            Route::get('/referrals/{referral}', [TeamReferralsController::class, 'show'])->name('referrals.show');
            Route::post('/referrals/{referral}/status', [TeamReferralsController::class, 'updateStatus'])->name('referrals.status');
        });
    });
