<?php

namespace App\Observers;

use App\Models\ServiceAddon;
use Illuminate\Support\Facades\Cache;

class ServiceAddonObserver
{
    public function saved(ServiceAddon $addon): void
    {
        Cache::forget('quote_builder_config_v1');
    }

    public function deleted(ServiceAddon $addon): void
    {
        Cache::forget('quote_builder_config_v1');
    }
}
