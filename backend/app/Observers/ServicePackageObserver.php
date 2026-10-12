<?php

namespace App\Observers;

use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Support\SiteCache;
use Illuminate\Support\Facades\Cache;

/**
 * Every save drops the cached quote-builder config. A save a visitor could see
 * — a package that is or was effectively Live (it AND its service on), so
 * hiding / showing it or moving it between services counts — also purges the
 * Nuxt page cache (SiteCache). Edits to hidden drafts (the MCP's) don't.
 */
class ServicePackageObserver
{
    public function __construct(private SiteCache $site) {}

    public function saved(ServicePackage $package): void
    {
        Cache::forget('quote_builder_config_v1');

        if (! $package->wasRecentlyCreated && ! $package->wasChanged()) {
            return;
        }

        // `saved` fires before the originals are synced, so getOriginal() is the
        // pre-save row. Services are looked up by id: a loaded `category`
        // relation can still be the old one after a move.
        $isLive = self::liveIn($package->active, $package->service_category_id);
        $wasLive = self::liveIn($package->getOriginal('active'), $package->getOriginal('service_category_id'));

        if ($isLive || $wasLive) {
            $this->site->purge();
        }
    }

    public function deleted(ServicePackage $package): void
    {
        Cache::forget('quote_builder_config_v1');

        if (self::liveIn($package->active, $package->service_category_id)) {
            $this->site->purge();
        }
    }

    private static function liveIn(mixed $active, mixed $categoryId): bool
    {
        return (bool) $active
            && $categoryId
            && (bool) ServiceCategory::whereKey($categoryId)->value('active');
    }
}
