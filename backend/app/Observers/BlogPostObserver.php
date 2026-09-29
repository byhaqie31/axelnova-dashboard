<?php

namespace App\Observers;

use App\Models\BlogPost;
use App\Support\SiteCache;

/**
 * Keeps the public blog instant: any change a visitor could see purges the
 * Nuxt page cache (SiteCache). That is a save that publishes, unpublishes, or
 * touches a post that is or was live, and deleting / restoring a live post.
 * Draft-only edits change nothing public and don't purge.
 */
class BlogPostObserver
{
    public function __construct(private SiteCache $cache) {}

    public function saved(BlogPost $post): void
    {
        // `saved` fires before the originals are synced, so getOriginal() is
        // still the pre-save status — an unpublish (published → draft) counts.
        $touchesLive = $post->isPublished() || $post->getOriginal('status') === BlogPost::STATUS_PUBLISHED;

        if ($touchesLive && ($post->wasRecentlyCreated || $post->wasChanged())) {
            $this->cache->purge();
        }
    }

    public function deleted(BlogPost $post): void
    {
        if ($post->isPublished()) {
            $this->cache->purge();
        }
    }

    public function restored(BlogPost $post): void
    {
        $this->deleted($post);
    }
}
