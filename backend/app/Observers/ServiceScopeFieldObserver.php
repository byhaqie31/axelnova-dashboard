<?php

namespace App\Observers;

use App\Models\ServiceScopeField;
use Illuminate\Support\Facades\Cache;

class ServiceScopeFieldObserver
{
    public function saved(ServiceScopeField $field): void
    {
        Cache::forget('quote_builder_config_v1');
    }

    public function deleted(ServiceScopeField $field): void
    {
        Cache::forget('quote_builder_config_v1');
    }
}
