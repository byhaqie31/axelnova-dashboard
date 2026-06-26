<?php

namespace App\Providers;

use App\Models\Payment;
use App\Observers\PaymentObserver;
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
    }
}
