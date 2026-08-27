<?php

namespace App\Providers;

use App\Console\Commands\MintConnectorToken;
use App\Models\Feedback;
use App\Models\Payment;
use App\Models\PersonalAccessToken as AppPersonalAccessToken;
use App\Models\User;
use App\Observers\FeedbackObserver;
use App\Observers\PaymentObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\IpUtils;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        config([
            'app.admin_name' => env('ADMIN_NAME', 'Ahmad Baihaqie'),
            'app.calendly_url' => env('ADMIN_CALENDLY_URL', ''),
        ]);

        $this->configureRateLimiting();

        // Throttled last_used_at stamping — otherwise every authenticated
        // request performs an UPDATE on personal_access_tokens.
        Sanctum::usePersonalAccessTokenModel(AppPersonalAccessToken::class);

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

    /**
     * The global `api` limiter — a floor under EVERY /api route (wired by
     * `throttleApi()` in bootstrap/app.php). It is not the primary control:
     * the tight per-route throttles (login 10/min, quotes 8/hour, inquiries
     * 20/hour) still run on top. This only stops unbounded automated hammering
     * of endpoints that have no throttle group of their own.
     *
     * Keyed on the client IP, which is only trustworthy because trustProxies()
     * now trusts the real proxy hops instead of '*'.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $ip = $request->ip();

            // Internal traffic: the Nuxt SSR server calls this API from inside
            // the docker network, so EVERY public visitor to a server-rendered
            // page shares that single source IP. A per-visitor ceiling here
            // would throttle the whole marketing site during a traffic spike —
            // it still gets a (much higher) ceiling so a runaway loop or a
            // compromised container can't hammer the database unbounded.
            if ($ip === null || IpUtils::isPrivateIp($ip)) {
                return Limit::perMinute(3000)->by('api:internal:'.$ip);
            }

            // Public floor. Generous for a human driving the admin portal
            // (a page load is a handful of requests); restrictive for a script.
            return Limit::perMinute(300)->by('api:ip:'.$ip);
        });
    }
}
