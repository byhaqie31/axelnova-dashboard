<?php

namespace App\Observers;

use App\Models\ServiceCategory;
use Illuminate\Support\Facades\Cache;

class ServiceCategoryObserver
{
    public function saved(ServiceCategory $category): void
    {
        Cache::forget('quote_builder_config_v1');
    }

    public function deleted(ServiceCategory $category): void
    {
        Cache::forget('quote_builder_config_v1');
    }
}
