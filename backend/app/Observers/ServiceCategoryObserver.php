<?php

namespace App\Observers;

use App\Models\ServiceCategory;
use App\Support\SiteCache;
use Illuminate\Support\Facades\Cache;

/**
 * Every save drops the cached quote-builder config. A save a visitor could see
 * — a category that is or was Live, so hiding / showing it counts — also
 * purges the Nuxt page cache (SiteCache): /services, /services/{slug}, the
 * /services estimator and the site footer all list Live categories. Hidden
 * drafts (the MCP's) change nothing public and don't purge.
 */
class ServiceCategoryObserver
{
    public function __construct(private SiteCache $site) {}

    public function saved(ServiceCategory $category): void
    {
        Cache::forget('quote_builder_config_v1');

        // `saved` fires before the originals are synced, so getOriginal() is
        // still the pre-save flag — a hide (Live → Hidden) counts.
        $touchesLive = $category->active || $category->getOriginal('active');

        if ($touchesLive && ($category->wasRecentlyCreated || $category->wasChanged())) {
            $this->site->purge();
        }
    }

    public function deleted(ServiceCategory $category): void
    {
        Cache::forget('quote_builder_config_v1');

        if ($category->active) {
            $this->site->purge();
        }
    }
}
