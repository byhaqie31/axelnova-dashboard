<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminBlogPostResource;
use App\Models\BlogPost;
use App\Models\PageView;
use App\Support\BlogMarkdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The blog CMS (founder-only via the /v1/admin cockpit group; see BLOG.md).
 * Drafts may be incomplete — `publish` is the completeness gate and stamps
 * `published_at` on the FIRST publish only. Slugs are generated from the title
 * when blank and always normalised + de-duplicated (BlogPost::uniqueSlug); a
 * published post's slug stays editable (the UI warns that the old link dies).
 * Markdown is stored as-is; rendering happens on read, and `render` runs the
 * same renderer for the editor's preview without saving anything.
 */
class BlogPostsController extends Controller
{
    /** Absolute http(s) URL or a root-relative path — keeps javascript:/data: out of <img src>. */
    private const URL_RULE = 'regex:#^(https?://|/[^/])#';

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'in:draft,published'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = BlogPost::query()->orderByDesc('updated_at')->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.$request->string('q')->toString().'%');
        }

        $page = $query->paginate(20);

        // One grouped query for this page's view counts (page_views is keyed by path).
        $paths = $page->getCollection()->map(fn (BlogPost $post) => "/blog/{$post->slug}")->all();
        $views = $paths
            ? PageView::whereIn('path', $paths)->selectRaw('path, COUNT(*) as c')->groupBy('path')->pluck('c', 'path')
            : collect();
        $page->getCollection()->each(fn (BlogPost $post) => $post->views = (int) ($views["/blog/{$post->slug}"] ?? 0));

        return AdminBlogPostResource::collection($page);
    }

    public function store(Request $request): AdminBlogPostResource
    {
        $data = $this->validated($request);

        $post = BlogPost::create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $post->logActivity('blog_post.created', ['title' => $post->title]);

        return new AdminBlogPostResource($post);
    }

    public function show(BlogPost $blogPost): AdminBlogPostResource
    {
        return new AdminBlogPostResource($blogPost);
    }

    public function update(Request $request, BlogPost $blogPost): AdminBlogPostResource
    {
        $data = $this->validated($request, $blogPost);

        $blogPost->update([...$data, 'updated_by' => $request->user()->id]);
        $blogPost->logActivity('blog_post.updated', ['title' => $blogPost->title]);

        return new AdminBlogPostResource($blogPost->fresh());
    }

    /** The completeness gate: title, excerpt, and ≥ 1 section with a heading + body. */
    public function publish(Request $request, BlogPost $blogPost): AdminBlogPostResource
    {
        $errors = [];

        if (trim((string) $blogPost->title) === '') {
            $errors['title'] = ['Give the post a title before publishing.'];
        }
        if (trim((string) $blogPost->excerpt) === '') {
            $errors['excerpt'] = ['Write a short introduction before publishing.'];
        }

        $complete = collect($blogPost->sections ?? [])->contains(
            fn ($s) => trim((string) ($s['heading'] ?? '')) !== '' && trim((string) ($s['body_md'] ?? '')) !== ''
        );
        if (! $complete) {
            $errors['sections'] = ['Add at least one section with a heading and some content.'];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $blogPost->update([
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => $blogPost->published_at ?? now(), // first publish only
            'updated_by' => $request->user()->id,
        ]);
        $blogPost->logActivity('blog_post.published', ['title' => $blogPost->title]);

        return new AdminBlogPostResource($blogPost->fresh());
    }

    /** Back to draft; published_at is kept so a re-publish doesn't re-date the post. */
    public function unpublish(Request $request, BlogPost $blogPost): AdminBlogPostResource
    {
        $blogPost->update(['status' => BlogPost::STATUS_DRAFT, 'updated_by' => $request->user()->id]);
        $blogPost->logActivity('blog_post.unpublished', ['title' => $blogPost->title]);

        return new AdminBlogPostResource($blogPost->fresh());
    }

    public function destroy(BlogPost $blogPost): JsonResponse
    {
        $blogPost->logActivity('blog_post.deleted', ['title' => $blogPost->title]);
        $blogPost->delete();

        return response()->json(['message' => 'Post deleted.']);
    }

    /** Preview helper — the public renderer over unsaved form state. Saves nothing. */
    public function render(Request $request): JsonResponse
    {
        $data = $request->validate([
            'excerpt' => ['nullable', 'string', 'max:500'],
            ...$this->sectionRules(),
        ]);

        $post = new BlogPost(['sections' => BlogPost::normaliseSections($data['sections'] ?? [])]);

        return response()->json([
            'sections' => $post->renderedSections(),
            'toc' => $post->toc(),
            'reading_minutes' => BlogMarkdown::readingMinutes((string) ($data['excerpt'] ?? ''), $post->sections),
        ]);
    }

    /** Shared create/update validation + the derived fields (slug, sections, tags, reading time). */
    private function validated(Request $request, ?BlogPost $existing = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:120'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            ...$this->sectionRules(),
            'cover_image_url' => ['nullable', 'string', 'max:500', self::URL_RULE],
            'cover_image_alt' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:60'],
            'format' => ['nullable', 'string', Rule::in(BlogPost::FORMATS)],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:40'],
            'cta_heading' => ['nullable', 'string', 'max:120'],
            'cta_body' => ['nullable', 'string', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'cta_url' => ['nullable', 'string', 'max:500', self::URL_RULE],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
        ]);

        $sections = BlogPost::normaliseSections($data['sections'] ?? []);
        $slugBase = trim((string) ($data['slug'] ?? '')) !== '' ? $data['slug'] : $data['title'];

        return [
            ...$data,
            'slug' => BlogPost::uniqueSlug($slugBase, $existing?->id),
            'format' => $data['format'] ?? 'article',
            'excerpt' => trim((string) ($data['excerpt'] ?? '')),
            'sections' => $sections,
            'tags' => array_values(array_filter(array_map('trim', $data['tags'] ?? []), fn ($t) => $t !== '')),
            'reading_minutes' => BlogMarkdown::readingMinutes((string) ($data['excerpt'] ?? ''), $sections),
        ];
    }

    private function sectionRules(): array
    {
        return [
            'sections' => ['nullable', 'array', 'max:40'],
            'sections.*.id' => ['nullable', 'string', 'max:12'],
            'sections.*.heading' => ['required', 'string', 'max:120'],
            'sections.*.body_md' => ['nullable', 'string', 'max:20000'],
            'sections.*.image_url' => ['nullable', 'string', 'max:500', self::URL_RULE],
            'sections.*.image_alt' => ['nullable', 'string', 'max:160'],
            'sections.*.quote' => ['nullable', 'string', 'max:500'],
            'sections.*.quote_by' => ['nullable', 'string', 'max:80'],
        ];
    }
}
