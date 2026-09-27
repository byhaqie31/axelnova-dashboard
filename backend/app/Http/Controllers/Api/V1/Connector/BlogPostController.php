<?php

namespace App\Http\Controllers\Api\V1\Connector;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminBlogPostResource;
use App\Models\BlogPost;
use App\Services\Blog\BlogPostInput;
use App\Support\BlogGuide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The MCP connector's blog surface (connector v4; see docs/global/MCP-CONNECTOR.md).
 * Same access model as quotations:
 *   • READ everything  — the writing guide, a slim list, and the full Markdown
 *     record of ANY non-deleted post (draft or published).
 *   • WRITE drafts only — create always lands `draft`; update is PARTIAL (only
 *     the fields sent change) and refused once a post is published, because a
 *     published post's edits would go live without the founder's preview.
 *   • NEVER publish, unpublish, or delete — those stay in the admin, by hand.
 *
 * Saves go through the same BlogPostInput as the admin editor. Unlike the
 * editor, an image here must carry alt text (checked against the merged post
 * for the cover, so swapping a URL keeps the stored alt).
 */
class BlogPostController extends Controller
{
    public function guide(): JsonResponse
    {
        return response()->json(BlogGuide::forConnector());
    }

    /** Slim rows, newest updated first. status / q (title) filters; per_page default 10, capped 25. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:draft,published'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $perPage = min(25, max(1, (int) ($data['per_page'] ?? 10)));

        $query = BlogPost::query()->orderByDesc('updated_at')->orderByDesc('id');
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['q'])) {
            $query->where('title', 'like', '%'.$data['q'].'%');
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (BlogPost $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status,
                'format' => $post->format,
                'category' => $post->category,
                'excerpt' => Str::limit((string) $post->excerpt, 160),
                'updated_at' => $post->updated_at?->toISOString(),
                'published_at' => $post->published_at?->toISOString(),
                ...$this->urls($post),
            ])->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->record($this->find($id))]);
    }

    /** Always a DRAFT — status / published_at aren't in the rule set, so a sent value is ignored. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(BlogPostInput::rules(requireAlt: true));
        $this->assertCoverAlt($data);

        $post = BlogPost::create([
            ...BlogPostInput::derive($data),
            'status' => BlogPost::STATUS_DRAFT,
            'published_at' => null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $post->logActivity('blog_post.created', ['title' => $post->title, 'via' => 'mcp_connector']);

        return response()->json(['data' => $this->record($post->fresh())], 201);
    }

    /** Partial update of a DRAFT. `sections`, when sent, replaces the whole list (ids kept). */
    public function update(Request $request, int $id): JsonResponse
    {
        $post = $this->find($id);

        if ($post->isPublished()) {
            throw ValidationException::withMessages([
                'status' => ['This post is published, so changes would go live immediately. Ask the founder to unpublish it in the admin first, or create a new draft instead.'],
            ]);
        }

        $data = $request->validate(BlogPostInput::rules(partial: true, requireAlt: true));
        $this->assertCoverAlt($data, $post);

        $post->update([...BlogPostInput::derive($data, $post, partial: true), 'updated_by' => $request->user()->id]);
        $post->logActivity('blog_post.updated', ['title' => $post->title, 'via' => 'mcp_connector']);

        return response()->json(['data' => $this->record($post->fresh())]);
    }

    private function find(int $id): BlogPost
    {
        return BlogPost::find($id)
            ?? abort(404, "No blog post with id {$id}. Use list_blog_posts to find the right one.");
    }

    /** A cover image needs alt text — judged on the post as it will be after this write. */
    private function assertCoverAlt(array $data, ?BlogPost $existing = null): void
    {
        $url = array_key_exists('cover_image_url', $data) ? $data['cover_image_url'] : $existing?->cover_image_url;
        $alt = array_key_exists('cover_image_alt', $data) ? $data['cover_image_alt'] : $existing?->cover_image_alt;

        if (filled($url) && blank($alt)) {
            throw ValidationException::withMessages([
                'cover_image_alt' => ['Add alt text describing the cover image.'],
            ]);
        }
    }

    private function record(BlogPost $post): array
    {
        return [...(new AdminBlogPostResource($post))->resolve(), ...$this->urls($post)];
    }

    /** @return array{admin_url: string, public_url: string|null} */
    private function urls(BlogPost $post): array
    {
        $base = rtrim((string) config('services.frontend.url'), '/');

        return [
            'admin_url' => "{$base}/admin/blog/{$post->id}",
            'public_url' => $post->isPublished() ? "{$base}/blog/{$post->slug}" : null,
        ];
    }
}
