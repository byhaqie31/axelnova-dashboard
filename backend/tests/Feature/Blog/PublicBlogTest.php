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

    public function test_list_returns_published_posts_newest_first_with_categories(): void
    {
        $old = BlogPost::factory()->published('2026-08-01 09:00:00')->create(['category' => 'Systems']);
        $new = BlogPost::factory()->published('2026-09-01 09:00:00')->create(['category' => 'UI/UX']);
        BlogPost::factory()->create(['category' => 'Systems']); // draft — hidden

        $res = $this->getJson('/api/v1/blog/posts')->assertOk();

        $this->assertSame([$new->slug, $old->slug], array_column($res->json('data'), 'slug'));
        $this->assertArrayNotHasKey('sections', $res->json('data.0'));
        $this->assertSame([['name' => 'Systems', 'count' => 1], ['name' => 'UI/UX', 'count' => 1]], $res->json('categories'));
        $this->assertSame(2, $res->json('meta.total'));
    }

    public function test_list_filters_by_category(): void
    {
        BlogPost::factory()->published()->create(['category' => 'Systems']);
        $ux = BlogPost::factory()->published()->create(['category' => 'UI/UX']);

        $res = $this->getJson('/api/v1/blog/posts?category=UI%2FUX')->assertOk();

        $this->assertSame([$ux->slug], array_column($res->json('data'), 'slug'));
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

    public function test_slugs_feed_lists_published_only(): void
    {
        $pub = BlogPost::factory()->published()->create();
        BlogPost::factory()->create();

        $res = $this->getJson('/api/v1/blog/slugs')->assertOk();

        $this->assertSame([$pub->slug], array_column($res->json('data'), 'slug'));
        $this->assertArrayHasKey('updated_at', $res->json('data.0'));
    }
}
