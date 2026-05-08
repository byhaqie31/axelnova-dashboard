<?php

namespace App\Observers;

use App\Models\PricingConfig;
use Illuminate\Support\Facades\Cache;

class PricingConfigObserver
{
    public function saving(PricingConfig $config): void
    {
        if ($config->active) {
            PricingConfig::where('id', '!=', $config->id ?? 0)
                ->where('active', true)
                ->update(['active' => false]);
        }
    }

    public function saved(PricingConfig $config): void
    {
        Cache::forget('quote_builder_config_v1');
    }

    public function deleted(PricingConfig $config): void
    {
        Cache::forget('quote_builder_config_v1');
    }
}
