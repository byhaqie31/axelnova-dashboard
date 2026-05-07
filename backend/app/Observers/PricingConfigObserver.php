<?php

namespace App\Observers;

use App\Models\PricingConfig;

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
}
