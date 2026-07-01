<?php

namespace App\Providers;

use App\Models\Payment;
use App\Models\User;
use App\Observers\PaymentObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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

        // Founder-only capabilities (Phase 0). Each gate is the single source of
        // truth for one privileged action; controllers call Gate::authorize() at
        // the call site. `view-all-payroll` is defined now but has no call site
        // until the payroll ledger lands (Phase 5).
        Gate::define('manage-users', fn (User $user) => $user->isFounder());
        Gate::define('hard-delete', fn (User $user) => $user->isFounder());
        Gate::define('accept-quote', fn (User $user) => $user->isFounder());
        Gate::define('view-all-payroll', fn (User $user) => $user->isFounder());
    }
}
