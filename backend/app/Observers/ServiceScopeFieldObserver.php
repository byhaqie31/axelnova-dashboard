<?php

namespace App\Observers;

use App\Models\ServiceScopeField;
use App\Support\SiteCache;
use Illuminate\Support\Facades\Cache;

/**
 * Drops the cached quote-builder config and purges the Nuxt page cache
 * (SiteCache) — the /services estimator prices from this config, and its
 * cached pages carry a copy.
 */
class ServiceScopeFieldObserver
{
    public function __construct(private SiteCache $site) {}

    public function saved(ServiceScopeField $field): void
    {
        Cache::forget('quote_builder_config_v1');

        if ($field->wasRecentlyCreated || $field->wasChanged()) {
            $this->site->purge();
        }
    }

    public function deleted(ServiceScopeField $field): void
    {
        Cache::forget('quote_builder_config_v1');
        $this->site->purge();
    }
}
