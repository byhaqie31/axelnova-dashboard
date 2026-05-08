<?php

namespace App\Observers;

use App\Models\ServicePackage;
use Illuminate\Support\Facades\Cache;

class ServicePackageObserver
{
    public function saved(ServicePackage $package): void
    {
        Cache::forget('quote_builder_config_v1');
    }

    public function deleted(ServicePackage $package): void
    {
        Cache::forget('quote_builder_config_v1');
    }
}
