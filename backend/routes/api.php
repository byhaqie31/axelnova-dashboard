<?php

use App\Http\Controllers\Api\V1\Admin\ActivityController;
use App\Http\Controllers\Api\V1\Admin\AnalyticsController;
use App\Http\Controllers\Api\V1\Admin\AnnouncementsController;
use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\ClientsController;
use App\Http\Controllers\Api\V1\Admin\ExpensesController;
use App\Http\Controllers\Api\V1\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Api\V1\Admin\InquiriesController;
use App\Http\Controllers\Api\V1\Admin\InvoicesController;
use App\Http\Controllers\Api\V1\Admin\OrdersController;
use App\Http\Controllers\Api\V1\Admin\PaymentsController;
use App\Http\Controllers\Api\V1\Admin\PayrollController;
use App\Http\Controllers\Api\V1\Admin\ProjectsController;
use App\Http\Controllers\Api\V1\Admin\QuotationsController;
use App\Http\Controllers\Api\V1\Admin\ReferralPartnersController;
use App\Http\Controllers\Api\V1\Admin\ReferralsController;
use App\Http\Controllers\Api\V1\Admin\ServiceAddonsController;
use App\Http\Controllers\Api\V1\Admin\ServiceCategoriesController;
use App\Http\Controllers\Api\V1\Admin\ServicePackagesController;
use App\Http\Controllers\Api\V1\Admin\ServiceScopeFieldsController;
use App\Http\Controllers\Api\V1\Admin\TasksController;
use App\Http\Controllers\Api\V1\Admin\UsersController;
use App\Http\Controllers\Api\V1\Connector\CatalogController as ConnectorCatalogController;
use App\Http\Controllers\Api\V1\Connector\QuotationDraftController as ConnectorQuotationDraftController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\FeedbackController;
use App\Http\Controllers\Api\V1\InquiryController;
use App\Http\Controllers\Api\V1\LikesController;
use App\Http\Controllers\Api\V1\Partner\AuthController as PartnerAuthController;
use App\Http\Controllers\Api\V1\Partner\DashboardController as PartnerDashboardController;
use App\Http\Controllers\Api\V1\PublicProjectsController;
use App\Http\Controllers\Api\V1\PublicServicesController;
use App\Http\Controllers\Api\V1\PublicTestimonialsController;
use App\Http\Controllers\Api\V1\QuoteBuilderConfigController;
use App\Http\Controllers\Api\V1\QuoteRequestController;
use App\Http\Controllers\Api\V1\ReferralController;
use App\Http\Controllers\Api\V1\Team\AnnouncementsController as TeamAnnouncementsController;
use App\Http\Controllers\Api\V1\Team\AuthController as TeamAuthController;
use App\Http\Controllers\Api\V1\Team\PayrollController as TeamPayrollController;
use App\Http\Controllers\Api\V1\Team\TasksController as TeamTasksController;
use App\Http\Controllers\Api\V1\TrackingController;
use App\Http\Middleware\LogAdminActivity;
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

// Public — token-gated feedback page shell (unguessable token, read-only).
Route::get('/v1/feedback/{token}', [FeedbackController::class, 'showByToken'])->name('feedback.show');

// Public — the testimonial wall feed (published + consented only, cached 1h).
Route::get('/v1/testimonials', [PublicTestimonialsController::class, 'index'])->name('testimonials.index');

// Public — feedback submit: one write per token, tighter than the quote funnel
// (a feedback link reaches exactly one person). Production: 5/hour per IP.
$feedbackThrottle = app()->environment('production') ? 'throttle:5,60' : 'throttle:1000,1';
Route::middleware($feedbackThrottle)->group(function () {
    Route::post('/v1/feedback/{token}', [FeedbackController::class, 'submit'])
        ->name('feedback.submit');
});

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

    // Workspace "forgot password" — no self-service reset; a matching email
    // notifies the founder (services.admin.email) to reset it from Users.
    Route::post('/v1/team/forgot-password', [TeamAuthController::class, 'forgotPassword'])->name('team.forgot-password');

    // Partner portal login — the isolated `external` guard on external_accounts.
    // Same brute-force throttle; covers both partner types (referrer + investor),
    // active accounts only, with an issued passcode.
    Route::post('/v1/partner/login', [PartnerAuthController::class, 'login'])->name('partner.login');

    // Self-service passcode reset: a correct active email auto-emails a new passcode
    // (and notifies the founder). Same throttle bounds abuse.
    Route::post('/v1/partner/forgot-passcode', [PartnerAuthController::class, 'forgotPasscode'])->name('partner.forgot-passcode');
});

// Admin cockpit — Sanctum SPA (stateful via cookie + CSRF), cockpit tier only
// (founder). Workspace roles (marketer/engineer) get 403 here and use
// /v1/team/* instead (Phase 3b).
Route::middleware([
    EnsureFrontendRequestsAreStateful::class,
    'auth:sanctum',
    'abilities:cockpit',
    'role:cockpit',
    LogAdminActivity::class,
])
    ->prefix('v1/admin')
    ->name('admin.')
    ->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');

        // Admin → Team jump: exchange the cockpit session for a workspace token
        // (same account) so the portal link signs in directly. Audited per call.
        Route::post('/team-session', [AuthController::class, 'teamSession'])->name('team-session');

        // Team provisioning — founder-only (Gate: manage-users, enforced in-controller).
        Route::get('/users', [UsersController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UsersController::class, 'show'])->name('users.show');
        Route::post('/users', [UsersController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UsersController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/deactivate', [UsersController::class, 'deactivate'])->name('users.deactivate');
        Route::post('/users/{user}/reactivate', [UsersController::class, 'reactivate'])->name('users.reactivate');

        // Customers (clients) — typeahead for the builder + the Customers spine
        Route::get('/clients', [ClientsController::class, 'index'])->name('clients.index');
        Route::post('/clients', [ClientsController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}', [ClientsController::class, 'show'])->name('clients.show');
        Route::put('/clients/{client}', [ClientsController::class, 'update'])->name('clients.update');

        // Analytics overview (traffic / engagement) + revenue attribution
        Route::get('/analytics/overview', [AnalyticsController::class, 'overview'])->name('analytics.overview');
        Route::get('/analytics/attribution', [AnalyticsController::class, 'attribution'])->name('analytics.attribution');

        // Activity feed — the audit trail (founder, per the cockpit group)
        Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');

        // Payroll ledger (Task 7) — founder-only via the view-all-payroll gate
        // in-controller; everyone else reads their own payslips at
        // /v1/team/payslips. `store` GENERATES a monthly payslip (allowance
        // snapshot + Σ pending task extras); `one-time` records an ad-hoc bonus /
        // payout outside the monthly cycle; `preview` is the generation dry-run;
        // `settle` stamps paid_at + flips the linked task extras to paid.
        // `/preview` and `/one-time` must precede the {payrollEntry} bind.
        Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::get('/payroll/roster', [PayrollController::class, 'roster'])->name('payroll.roster');
        Route::get('/payroll/user/{user}', [PayrollController::class, 'userDetail'])->name('payroll.user');
        Route::get('/payroll/preview', [PayrollController::class, 'preview'])->name('payroll.preview');
        Route::post('/payroll', [PayrollController::class, 'store'])->name('payroll.store');
        Route::post('/payroll/one-time', [PayrollController::class, 'storeOneTime'])->name('payroll.one-time');
        Route::post('/payroll/{payrollEntry}/settle', [PayrollController::class, 'settle'])->name('payroll.settle');

        // Company-spending ledger (record-only) — general company spend, not
        // just marketing (renamed from /marketing-expenses). Founder-only.
        Route::get('/expenses', [ExpensesController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpensesController::class, 'store'])->name('expenses.store');

        // Tasks (Task 5) — author, assign or leave in the pool, track the
        // lifecycle, mark the extra-pay bonus paid. The team works its tasks
        // from /v1/team/tasks; the state machine lives in the two controllers.
        Route::get('/tasks', [TasksController::class, 'index'])->name('tasks.index');
        Route::post('/tasks', [TasksController::class, 'store'])->name('tasks.store');
        Route::get('/tasks/{task}', [TasksController::class, 'show'])->name('tasks.show');
        Route::patch('/tasks/{task}', [TasksController::class, 'update'])->name('tasks.update');
        Route::post('/tasks/{task}/mark-paid', [TasksController::class, 'markPaid'])->name('tasks.mark-paid');
        Route::delete('/tasks/{task}', [TasksController::class, 'destroy'])->name('tasks.destroy');

        // Announcements (Task 6) — post/edit company notices. No delete: the
        // team's read-only feed lives at /v1/team/announcements; "unpublish"
        // (published toggle → false) reverts a row to draft instead.
        Route::get('/announcements', [AnnouncementsController::class, 'index'])->name('announcements.index');
        Route::post('/announcements', [AnnouncementsController::class, 'store'])->name('announcements.store');
        Route::patch('/announcements/{announcement}', [AnnouncementsController::class, 'update'])->name('announcements.update');

        Route::get('/quotations', [QuotationsController::class, 'index'])->name('quotations.index');
        Route::post('/quotations', [QuotationsController::class, 'store'])->name('quotations.store');
        Route::post('/quotations/preview', [QuotationsController::class, 'preview'])->name('quotations.preview');
        Route::post('/quotations/seed-document', [QuotationsController::class, 'seedDocument'])->name('quotations.seed-document');
        Route::get('/quotations/{quotation}', [QuotationsController::class, 'show'])->name('quotations.show');
        Route::put('/quotations/{quotation}', [QuotationsController::class, 'update'])->name('quotations.update');
        Route::post('/quotations/{quotation}/status', [QuotationsController::class, 'updateStatus'])->name('quotations.status');
        Route::post('/quotations/{quotation}/expiry', [QuotationsController::class, 'setExpiry'])->name('quotations.expiry');
        Route::post('/quotations/{quotation}/send', [QuotationsController::class, 'send'])->name('quotations.send');
        Route::post('/quotations/{quotation}/accept', [QuotationsController::class, 'accept'])->name('quotations.accept');
        // Correct a mis-matched client — re-point (+ refresh snapshot). Not gated by
        // status: bad records may already be sent/accepted.
        Route::post('/quotations/{quotation}/client', [QuotationsController::class, 'updateClient'])->name('quotations.client');
        // Soft-delete (portal-only, never via the connector). Blocked with a 409
        // when an order is attached — the order is the money record.
        Route::delete('/quotations/{quotation}', [QuotationsController::class, 'destroy'])->name('quotations.destroy');

        Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
        // Money roll-up for the dashboard — must precede the {order} wildcard.
        Route::get('/orders/stats', [OrdersController::class, 'stats'])->name('orders.stats');
        Route::get('/orders/{order}', [OrdersController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/status', [OrdersController::class, 'updateStatus'])->name('orders.status');
        Route::post('/orders/{order}/schedule', [OrdersController::class, 'updateSchedule'])->name('orders.schedule');
        // Correct a mis-matched client — re-point order.client_id (cascades to the
        // source quotation); frozen invoices/receipts stay untouched.
        Route::post('/orders/{order}/client', [OrdersController::class, 'updateClient'])->name('orders.client');
        // Issue an invoice/receipt for the order (freezes a document snapshot).
        Route::post('/orders/{order}/documents', [OrdersController::class, 'issueDocument'])->name('orders.documents.issue');
        // Live preview of the would-be invoice document (no persist).
        Route::post('/orders/{order}/documents/preview', [OrdersController::class, 'previewDocument'])->name('orders.documents.preview');

        // Invoices — cross-order list + detail (the standalone Invoices module).
        Route::get('/invoices', [InvoicesController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [InvoicesController::class, 'show'])->name('invoices.show');
        // In-place re-edit (re-freezes the payload, same AXNI number; amounts
        // lock once payments exist) and the client email send (queued).
        Route::put('/invoices/{invoice}', [InvoicesController::class, 'update'])->name('invoices.update');
        Route::post('/invoices/{invoice}/send', [InvoicesController::class, 'send'])->name('invoices.send');

        // Payments — the money ledger. Record/refund/issue-receipt flow through here.
        Route::get('/payments', [PaymentsController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [PaymentsController::class, 'show'])->name('payments.show');
        Route::post('/orders/{order}/payments', [PaymentsController::class, 'store'])->name('orders.payments.store');
        Route::post('/payments/{payment}/refund', [PaymentsController::class, 'refund'])->name('payments.refund');
        Route::get('/payments/{payment}/receipt/preview', [PaymentsController::class, 'receiptPreview'])->name('payments.receipt.preview');
        Route::post('/payments/{payment}/receipt', [PaymentsController::class, 'issueReceipt'])->name('payments.receipt');
        Route::patch('/payments/{payment}/allocation', [PaymentsController::class, 'allocate'])->name('payments.allocation');

        // Partner referrals
        Route::get('/referrals', [ReferralsController::class, 'index'])->name('referrals.index');
        Route::get('/referrals/{referral}', [ReferralsController::class, 'show'])->name('referrals.show');
        Route::post('/referrals/{referral}/status', [ReferralsController::class, 'updateStatus'])->name('referrals.status');
        Route::post('/referrals/{referral}/link-order', [ReferralsController::class, 'linkOrder'])->name('referrals.link-order');
        Route::post('/referrals/{referral}/commission-email', [ReferralsController::class, 'sendCommissionEmail'])->name('referrals.commission-email');
        Route::post('/referrals/{referral}/tie-quotation', [ReferralsController::class, 'tieQuotation'])->name('referrals.tie-quotation');
        Route::post('/referrals/{referral}/untie-quotation', [ReferralsController::class, 'untieQuotation'])->name('referrals.untie-quotation');

        // Referral partners (the affiliate accounts). Approve issues + emails the
        // first passcode; reset-passcode regenerates it. The passcode is never
        // returned here — only emailed. No self-service reset exists.
        Route::get('/referral-partners', [ReferralPartnersController::class, 'index'])->name('referral-partners.index');
        Route::get('/referral-partners/{referralPartner}', [ReferralPartnersController::class, 'show'])->name('referral-partners.show');
        Route::post('/referral-partners/{referralPartner}/approve', [ReferralPartnersController::class, 'approve'])->name('referral-partners.approve');
        Route::post('/referral-partners/{referralPartner}/reset-passcode', [ReferralPartnersController::class, 'resetPasscode'])->name('referral-partners.reset-passcode');

        // Feedback & reviews — request-from-client / log-offline create modes,
        // moderation, and the publish gate (consent required, nothing auto-publishes).
        Route::get('/feedback', [AdminFeedbackController::class, 'index'])->name('feedback.index');
        Route::post('/feedback', [AdminFeedbackController::class, 'store'])->name('feedback.store');
        Route::get('/feedback/{feedback}', [AdminFeedbackController::class, 'show'])->name('feedback.show');
        Route::put('/feedback/{feedback}', [AdminFeedbackController::class, 'update'])->name('feedback.update');
        Route::post('/feedback/{feedback}/status', [AdminFeedbackController::class, 'updateStatus'])->name('feedback.status');
        Route::delete('/feedback/{feedback}', [AdminFeedbackController::class, 'destroy'])->name('feedback.destroy');

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

// Team workspace — Sanctum, workspace tier (three internal roles: founder/
// marketer/engineer). The team no longer touches admin-owned operational data
// (Task 4 of the portal restructure dropped inquiry triage, the referral
// programme, and marketing spend entry — those stay cockpit-only). What's
// left is personal: your own session/profile, your own payslips, and
// tasks/calendar/announcements.
Route::middleware([
    EnsureFrontendRequestsAreStateful::class,
    'auth:sanctum',
    'abilities:workspace',
    'role:workspace',
])
    ->prefix('v1/team')
    ->name('team.')
    ->group(function () {
        Route::post('/logout', [TeamAuthController::class, 'logout'])->name('logout');
        Route::get('/me', [TeamAuthController::class, 'me'])->name('me');
        Route::patch('/me', [TeamAuthController::class, 'updateMe'])->name('me.update');

        // Own payslips (Phase 5) — every internal role reads only their own rows;
        // the founder's full ledger lives at /v1/admin/payroll.
        Route::get('/payslips', [TeamPayrollController::class, 'index'])->name('payslips.index');

        // Tasks (Task 5) — the kanban + calendar feed ({pool, mine}), claiming
        // from the pool, and moving your OWN tasks through the state machine.
        // Admin-owned edits (shape/pay/mark-paid/delete) stay on /v1/admin/tasks.
        Route::get('/tasks', [TeamTasksController::class, 'index'])->name('tasks.index');
        Route::post('/tasks/{task}/claim', [TeamTasksController::class, 'claim'])->name('tasks.claim');
        Route::patch('/tasks/{task}/status', [TeamTasksController::class, 'updateStatus'])->name('tasks.status');

        // Announcements (Task 6) — read-only feed: published + audience in
        // ('team', 'all'). 'partners' rows are for a later phase (the partner
        // portal) and are deliberately excluded here.
        Route::get('/announcements', [TeamAnnouncementsController::class, 'index'])->name('announcements.index');

        // Marketer surface — read-only mirror of the cockpit traffic overview
        // (same controller, no duplicated logic). Founder passes too (previews
        // the workspace via the admin→team session exchange); engineers 403.
        Route::get('/analytics/overview', [AnalyticsController::class, 'overview'])
            ->middleware('role:founder,marketer')->name('analytics.overview');
    });

// Partner portal — the third, isolated surface, shared by BOTH partner kinds
// (referrer + investor) since Task 9. Pure bearer tokens on the `external` guard
// (ExternalAccount / external_accounts), deliberately WITHOUT the stateful-cookie
// middleware the cockpit/workspace use. An ExternalAccount token authenticates
// only here: the `sanctum` guard (provider = users) behind /v1/admin and /v1/team
// rejects it, and a User token is rejected here. Everything is scoped to the
// token's own data; type-specific endpoints add `partner.type:{referrer|investor}`.
Route::middleware(['auth:external', 'abilities:partner'])
    ->prefix('v1/partner')
    ->name('partner.')
    ->group(function () {
        Route::post('/logout', [PartnerAuthController::class, 'logout'])->name('logout');
        Route::get('/me', [PartnerAuthController::class, 'me'])->name('me');

        // Referrer-only: own leads + derived earnings + the ?ref link, and the
        // context-aware "refer another". An investor token 403s here.
        Route::middleware('partner.type:referrer')->group(function () {
            Route::get('/dashboard', [PartnerDashboardController::class, 'index'])->name('dashboard');
            Route::post('/referrals', [PartnerDashboardController::class, 'storeReferral'])->name('referrals.store');
        });

        // Investor-only content endpoints (documents / reports) are deliberately
        // absent — those surfaces are premium empty states until an investor
        // content model exists (admin investor CRUD is future work).
    });

// MCP connector — a fourth, deliberately NARROW surface for the remote MCP server
// (mcp.axelnova.tech), which lets Claude drive the quotation pipeline from a client
// brief. Pure Sanctum bearer tokens minted with connector abilities ONLY
// (connector:read / connector:draft) — never `cockpit`, so a connector token is
// rejected by every /v1/admin route (abilities:cockpit), and an admin cockpit
// token is rejected here. Access model (v3): READ everything (list + read-back any
// non-deleted quotation), WRITE with a lifecycle gate (create a DRAFT, update any
// PRE-SEND quotation), DESTROY never (delete is portal-only, by hand). It still
// CANNOT change status, send/accept a quote, or touch orders/clients/payments.
// Routes deny by default; each ability opens exactly its own endpoints, throttled
// (authenticated, but never unbounded).
Route::middleware('auth:sanctum')
    ->prefix('v1/connector')
    ->name('connector.')
    ->group(function () {
        // Read surface — catalog + list + read-back of ANY non-deleted quotation.
        // 60/min: comfortably above real chat usage, a backstop against a loop.
        Route::middleware(['abilities:connector:read', 'throttle:60,1'])->group(function () {
            Route::get('/catalog', [ConnectorCatalogController::class, 'index'])->name('catalog');
            Route::get('/quotations', [ConnectorQuotationDraftController::class, 'index'])->name('quotations.index');
            Route::get('/quotations/{reference_code}', [ConnectorQuotationDraftController::class, 'show'])->name('quotations.show');
        });

        // Write surface — create a DRAFT, or update a PRE-SEND quotation. Tighter
        // 30/min bound (writes re-price + re-seed, so they're heavier than reads).
        Route::middleware(['abilities:connector:draft', 'throttle:30,1'])->group(function () {
            Route::post('/quotations/draft', [ConnectorQuotationDraftController::class, 'store'])->name('quotations.draft');
            Route::put('/quotations/{reference_code}', [ConnectorQuotationDraftController::class, 'update'])->name('quotations.update');
        });
    });
