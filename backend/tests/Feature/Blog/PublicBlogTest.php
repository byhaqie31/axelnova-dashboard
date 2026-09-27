<?php

namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The storefront's read-only blog feed: published posts only (drafts and
 * soft-deleted rows 404), newest first, category filter + category counts,
 * rendered sections + toc + related on show, and the unpaginated slugs feed
 * the frontend sitemap reads.
 */
class PublicBlogTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_returns_published_posts_newest_first_with_format_and_topic_sets(): void
    {
        $old = BlogPost::factory()->published('2026-08-01 09:00:00')->create(['category' => 'Systems', 'format' => 'guide', 'tags' => ['Websites', 'AI']]);
        $new = BlogPost::factory()->published('2026-09-01 09:00:00')->create(['category' => 'UI/UX', 'format' => 'article', 'tags' => ['ai']]);
        BlogPost::factory()->create(['category' => 'Systems', 'format' => 'news']); // draft — hidden

        $res = $this->getJson('/api/v1/blog/posts')->assertOk();

        $this->assertSame([$new->slug, $old->slug], array_column($res->json('data'), 'slug'));
        $this->assertArrayNotHasKey('sections', $res->json('data.0'));
        $this->assertSame(2, $res->json('meta.total'));
        // Formats present among published posts, most used first then alphabetical.
        $this->assertSame([['value' => 'article', 'count' => 1], ['value' => 'guide', 'count' => 1]], $res->json('formats'));
        // Topics = category ∪ tags, de-duplicated case-insensitively, most used first then alphabetical.
        $this->assertSame([
            ['name' => 'AI', 'count' => 2],
            ['name' => 'Systems', 'count' => 1],
            ['name' => 'UI/UX', 'count' => 1],
            ['name' => 'Websites', 'count' => 1],
        ], $res->json('topics'));
    }

    public function test_list_filters_by_format_and_by_topic(): void
    {
        $guide = BlogPost::factory()->published()->create(['format' => 'guide', 'category' => 'Systems', 'tags' => ['Websites']]);
        $article = BlogPost::factory()->published()->create(['format' => 'article', 'category' => 'UI/UX', 'tags' => ['ai']]);

        $res = $this->getJson('/api/v1/blog/posts?format=guide')->assertOk();
        $this->assertSame([$guide->slug], array_column($res->json('data'), 'slug'));

        // A topic matches the category OR a tag, case-insensitively.
        $res = $this->getJson('/api/v1/blog/posts?topic=websites')->assertOk();
        $this->assertSame([$guide->slug], array_column($res->json('data'), 'slug'));
        $res = $this->getJson('/api/v1/blog/posts?topic=UI%2FUX')->assertOk();
        $this->assertSame([$article->slug], array_column($res->json('data'), 'slug'));
        $res = $this->getJson('/api/v1/blog/posts?topic=AI&format=guide')->assertOk();
        $this->assertSame([], $res->json('data'));

        // An unknown format is a validation error, not an empty page.
        $this->getJson('/api/v1/blog/posts?format=poem')->assertUnprocessable();
    }

    public function test_show_returns_rendered_sections_toc_and_related(): void
    {
        $post = BlogPost::factory()->published('2026-09-01 09:00:00')->create([
            'category' => 'Systems',
            'sections' => BlogPost::normaliseSections([
                ['heading' => 'Notice what keeps repeating', 'body_md' => 'Do customers **ask** twice?', 'quote' => 'Keep it calm', 'quote_by' => 'Me'],
            ]),
            'cta_heading' => 'Talk it through',
        ]);
        $same = BlogPost::factory()->published('2026-09-02 09:00:00')->create(['category' => 'Systems']);
        $other = BlogPost::factory()->published('2026-09-03 09:00:00')->create(['category' => 'UI/UX']);

        $res = $this->getJson("/api/v1/blog/posts/{$post->slug}")->assertOk();

        $this->assertStringContainsString('<strong>ask</strong>', $res->json('data.sections.0.body_html'));
        $this->assertSame('notice-what-keeps-repeating', $res->json('data.sections.0.anchor'));
        $this->assertSame('Keep it calm', $res->json('data.sections.0.quote'));
        $this->assertSame([['id' => 'notice-what-keeps-repeating', 'heading' => 'Notice what keeps repeating']], $res->json('data.toc'));
        $this->assertSame('Talk it through', $res->json('data.cta_heading'));
        // Related: same category first, then newest; never itself.
        $this->assertSame([$same->slug, $other->slug], array_column($res->json('data.related'), 'slug'));
    }

    public function test_a_draft_or_deleted_post_is_404(): void
    {
        $draft = BlogPost::factory()->create();
        $gone = BlogPost::factory()->published()->create();
        $gone->delete();

        $this->getJson("/api/v1/blog/posts/{$draft->slug}")->assertNotFound();
        $this->getJson("/api/v1/blog/posts/{$gone->slug}")->assertNotFound();
        $this->getJson('/api/v1/blog/posts/nope')->assertNotFound();
    }

    public function test_format_is_exposed_on_cards_and_the_article(): void
    {
        $post = BlogPost::factory()->published()->create(['format' => 'guide']);

        $this->getJson('/api/v1/blog/posts')->assertOk()->assertJsonPath('data.0.format', 'guide');
        $this->getJson("/api/v1/blog/posts/{$post->slug}")->assertOk()->assertJsonPath('data.format', 'guide');
    }

    public function test_slugs_feed_lists_published_only(): void
    {
        $pub = BlogPost::factory()->published()->create();
        BlogPost::factory()->create();

        $res = $this->getJson('/api/v1/blog/slugs')->assertOk();

        $this->assertSame([$pub->slug], array_column($res->json('data'), 'slug'));
        $this->assertArrayHasKey('updated_at', $res->json('data.0'));
    }

    public function test_the_closing_cta_falls_back_to_the_shared_defaults(): void
    {
        $default = BlogPost::factory()->published()->create();
        $custom = BlogPost::factory()->published()->create(['cta_heading' => 'Planning a portal?', 'cta_url' => '/quote']);

        $this->getJson("/api/v1/blog/posts/{$default->slug}")->assertOk()
            ->assertJsonPath('data.cta_heading', config('blog.cta_defaults.heading'))
            ->assertJsonPath('data.cta_body', config('blog.cta_defaults.body'))
            ->assertJsonPath('data.cta_label', config('blog.cta_defaults.label'))
            ->assertJsonPath('data.cta_url', config('blog.cta_defaults.url'));

        // Each field falls back on its own — a custom heading keeps the default body.
        $this->getJson("/api/v1/blog/posts/{$custom->slug}")->assertOk()
            ->assertJsonPath('data.cta_heading', 'Planning a portal?')
            ->assertJsonPath('data.cta_url', '/quote')
            ->assertJsonPath('data.cta_body', config('blog.cta_defaults.body'));
    }
}
