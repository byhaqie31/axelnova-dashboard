<?php

namespace App\Providers;

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
    }
}
