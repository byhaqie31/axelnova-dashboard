<?php

namespace App\Observers;

use App\Models\PricingConfig;
use App\Support\SiteCache;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps one active config, drops the cached copies on any change, and purges
 * the Nuxt page cache (SiteCache) — the /services estimator prices from it.
 */
class PricingConfigObserver
{
    public function __construct(private SiteCache $site) {}

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
        Cache::forget('pricing_config_active_v1');

        if ($config->wasRecentlyCreated || $config->wasChanged()) {
            $this->site->purge();
        }
    }

    public function deleted(PricingConfig $config): void
    {
        Cache::forget('quote_builder_config_v1');
        Cache::forget('pricing_config_active_v1');
        $this->site->purge();
    }
}
