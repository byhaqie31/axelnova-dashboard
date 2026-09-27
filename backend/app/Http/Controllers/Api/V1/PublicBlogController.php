<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogPostCardResource;
use App\Http\Resources\PublicBlogPostResource;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Read-only blog feed for the storefront (see docs/global/BLOG.md). Only
 * `published` rows are ever served — drafts and soft-deleted posts 404. No
 * auth; the Nuxt pages sit behind a 5-minute swr cache on top of this.
 */
class PublicBlogController extends Controller
{
    /**
     * Paginated cards, newest first. Two optional filters — ?format= (one of
     * BlogPost::FORMATS) and ?topic= (matches the category OR a tag,
     * case-insensitively) — plus the `formats` and `topics` sets with counts
     * that drive the index page's pills and dropdown. Topics are the union of
     * every published post's category and tags, de-duplicated by case.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'format' => ['nullable', 'string', Rule::in(BlogPost::FORMATS)],
            'topic' => ['nullable', 'string', 'max:60'],
        ]);

        $query = BlogPost::published()->orderByDesc('published_at')->orderByDesc('id');

        if (! empty($data['format'])) {
            $query->where('format', $data['format']);
        }
        if (! empty($data['topic'])) {
            $topic = mb_strtolower(trim($data['topic']));
            // Tags live in a JSON array; LOWER(JSON) + a quoted-string LIKE keeps
            // the match case-insensitive and exact to one tag (no substrings).
            $query->where(function ($q) use ($topic) {
                $q->whereRaw('LOWER(category) = ?', [$topic])
                    ->orWhereRaw('LOWER(tags) LIKE ?', ['%'.json_encode($topic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'%']);
            });
        }

        $formats = BlogPost::published()
            ->selectRaw('format as value, COUNT(*) as count')
            ->groupBy('format')
            ->orderByDesc('count')
            ->orderBy('value')
            ->get()
            ->map(fn ($row) => ['value' => $row->value, 'count' => (int) $row->count])
            ->values();

        // Small table, JSON tags — aggregate in PHP rather than fight MySQL JSON.
        $topicCounts = [];
        $topicLabels = [];
        foreach (BlogPost::published()->get(['category', 'tags']) as $post) {
            $seen = [];
            foreach (array_filter([$post->category, ...($post->tags ?? [])]) as $name) {
                $key = mb_strtolower(trim((string) $name));
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $topicCounts[$key] = ($topicCounts[$key] ?? 0) + 1;
                $topicLabels[$key] ??= trim((string) $name);
            }
        }
        $topics = collect($topicCounts)
            ->map(fn (int $count, string $key) => ['name' => $topicLabels[$key], 'count' => $count])
            ->sortBy([['count', 'desc'], ['name', 'asc']])
            ->values();

        return BlogPostCardResource::collection($query->paginate(12))
            ->additional(['formats' => $formats, 'topics' => $topics]);
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
