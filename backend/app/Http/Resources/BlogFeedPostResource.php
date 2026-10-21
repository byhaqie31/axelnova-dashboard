<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * One post in GET /v1/blog/feed — the article shape (PublicBlogPostResource:
 * rendered sections, cover, tags, category, plain excerpt, dates, reading
 * time) without `related`, which only the article page needs and would cost a
 * query per post across the twenty the feed carries. Consumed by the Nuxt
 * /feed.xml (RSS 2.0) and /llms.txt routes — see docs/global/SEO-AND-DISCOVERY.md.
 */
class BlogFeedPostResource extends PublicBlogPostResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        unset($data['related']);

        return $data;
    }
}
