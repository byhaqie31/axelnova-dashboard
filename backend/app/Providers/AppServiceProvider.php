<?php

namespace App\Providers;

use App\Console\Commands\MintConnectorToken;
use App\Models\Feedback;
use App\Models\Payment;
use App\Models\User;
use App\Observers\FeedbackObserver;
use App\Observers\PaymentObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        config([
            'app.admin_name' => env('ADMIN_NAME', 'Ahmad Baihaqie'),
            'app.calendly_url' => env('ADMIN_CALENDLY_URL', ''),
        ]);

        // The ledger's only writer of derived paid caches.
        Payment::observe(PaymentObserver::class);

        // Testimonial-wall cache invalidation (public_testimonials_v1).
        Feedback::observe(FeedbackObserver::class);

        // The global Sanctum cap (SANCTUM_EXPIRATION_MINUTES, 12h default) exists
        // so a leaked admin *login* token can't live forever — but it would also
        // kill the MCP connector's credential, which is long-lived by design.
        // Exempt exactly that token: its lifetime is its OWN expires_at, stamped
        // by `connector:token --days=N`. The exemption never widens validity
        // otherwise — an mcp-connector token without an explicit future expiry
        // still falls under the global cap.
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid): bool => $isValid
                || ($token->name === MintConnectorToken::TOKEN_NAME
                    && $token->tokenable instanceof User
                    && $token->expires_at !== null
                    && $token->expires_at->isFuture()),
        );

        // Founder-only capabilities (Phase 0). Each gate is the single source of
        // truth for one privileged action; controllers call Gate::authorize() at
        // the call site. `view-all-payroll` guards the payroll ledger (Phase 5):
        // reading the roll-up AND recording entries are both founder-only.
        Gate::define('manage-users', fn (User $user) => $user->isFounder());
        Gate::define('hard-delete', fn (User $user) => $user->isFounder());
        Gate::define('accept-quote', fn (User $user) => $user->isFounder());
        Gate::define('view-all-payroll', fn (User $user) => $user->isFounder());
    }
}
