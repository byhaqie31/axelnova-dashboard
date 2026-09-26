<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogPostCardResource;
use App\Http\Resources\PublicBlogPostResource;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only blog feed for the storefront (see docs/global/BLOG.md). Only
 * `published` rows are ever served — drafts and soft-deleted posts 404. No
 * auth; the Nuxt pages sit behind a 5-minute swr cache on top of this.
 */
class PublicBlogController extends Controller
{
    /** Paginated cards, newest first, optional ?category=; plus the category set with counts. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = BlogPost::published()->orderByDesc('published_at')->orderByDesc('id');

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        $categories = BlogPost::published()
            ->whereNotNull('category')
            ->selectRaw('category as name, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('count')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'count' => (int) $row->count])
            ->values();

        return BlogPostCardResource::collection($query->paginate(12))
            ->additional(['categories' => $categories]);
    }

    /** The full article + up to 3 related posts (same category first, then newest). */
    public function show(string $slug): PublicBlogPostResource
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();

        $related = BlogPost::published()
            ->whereKeyNot($post->id)
            ->orderByRaw('CASE WHEN category = ? THEN 0 ELSE 1 END', [$post->category ?? ''])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        return (new PublicBlogPostResource($post))->withRelated($related);
    }

    /** Sitemap feed — every published slug with timestamps, unpaginated. */
    public function slugs(): JsonResponse
    {
        $rows = BlogPost::published()
            ->orderByDesc('published_at')
            ->get(['slug', 'published_at', 'updated_at'])
            ->map(fn (BlogPost $post) => [
                'slug' => $post->slug,
                'published_at' => $post->published_at?->toISOString(),
                'updated_at' => $post->updated_at?->toISOString(),
            ]);

        return response()->json(['data' => $rows]);
    }
}
