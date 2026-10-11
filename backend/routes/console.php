<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Scheduled tasks — run by the single `scheduler` container
 * (`php artisan schedule:work`, docker-compose*.yml). Times are app time
 * (Asia/Kuala_Lumpur). Every task is withoutOverlapping().
 */
Schedule::command('quotations:expire-overdue')->hourly()->withoutOverlapping();
