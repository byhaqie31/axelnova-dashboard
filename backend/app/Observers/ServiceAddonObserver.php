<?php

namespace App\Observers;

use App\Models\ServiceAddon;
use App\Support\SiteCache;
use Illuminate\Support\Facades\Cache;

/**
 * Drops the cached quote-builder config and purges the Nuxt page cache
 * (SiteCache) — the /services estimator prices from this config, and its
 * cached pages carry a copy.
 */
class ServiceAddonObserver
{
    public function __construct(private SiteCache $site) {}

    public function saved(ServiceAddon $addon): void
    {
        Cache::forget('quote_builder_config_v1');

        if ($addon->wasRecentlyCreated || $addon->wasChanged()) {
            $this->site->purge();
        }
    }

    public function deleted(ServiceAddon $addon): void
    {
        Cache::forget('quote_builder_config_v1');
        $this->site->purge();
    }
}
