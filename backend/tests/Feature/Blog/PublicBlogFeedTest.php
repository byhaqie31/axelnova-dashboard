<?php

namespace Tests\Feature\Blog;

use App\Http\Controllers\Api\V1\PublicBlogController;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /v1/blog/feed — what the Nuxt /feed.xml (RSS) and /llms.txt routes read:
 * published posts only (drafts and soft-deleted rows excluded), newest first,
 * capped at 20, each as the full article (rendered section HTML + cover)
 * without the `related` cards.
 */
class PublicBlogFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_lists_published_posts_only_newest_first(): void
    {
        $old = BlogPost::factory()->published('2026-08-01 09:00:00')->create();
        $new = BlogPost::factory()->published('2026-09-01 09:00:00')->create();
        BlogPost::factory()->create(); // draft — hidden
        $gone = BlogPost::factory()->published('2026-09-15 09:00:00')->create();
        $gone->delete(); // soft-deleted — hidden

        $res = $this->getJson('/api/v1/blog/feed')->assertOk();

        $this->assertSame([$new->slug, $old->slug], array_column($res->json('data'), 'slug'));
    }

    public function test_feed_items_carry_the_rendered_article_and_cover_without_related(): void
    {
        $post = BlogPost::factory()->published('2026-09-01 09:00:00')->create([
            'excerpt' => 'WhatsApp is **convenient**.',
            'cover_image_url' => 'https://images.example.com/cover.jpg',
            'cover_image_alt' => 'A phone showing WhatsApp',
            'category' => 'Systems',
            'tags' => ['Customer portals', 'WhatsApp'],
            'sections' => BlogPost::normaliseSections([
                ['heading' => 'Notice what keeps repeating', 'body_md' => 'Do customers **ask** twice?'],
            ]),
        ]);
        BlogPost::factory()->published('2026-09-02 09:00:00')->create(['category' => 'Systems']);

        $res = $this->getJson('/api/v1/blog/feed')->assertOk();
        $item = collect($res->json('data'))->firstWhere('slug', $post->slug);

        $this->assertSame($post->title, $item['title']);
        // The excerpt is plain text (card shape) — feed readers show it as the summary.
        $this->assertSame('WhatsApp is convenient.', $item['excerpt']);
        $this->assertSame('https://images.example.com/cover.jpg', $item['cover_image_url']);
        $this->assertSame('A phone showing WhatsApp', $item['cover_image_alt']);
        $this->assertSame('Systems', $item['category']);
        $this->assertSame(['Customer portals', 'WhatsApp'], $item['tags']);
        // ISO 8601 in UTC, whatever the app timezone (the feed turns it into an RFC 822 pubDate).
        $this->assertSame($post->fresh()->published_at->toISOString(), $item['published_at']);
        $this->assertArrayHasKey('updated_at', $item);
        $this->assertArrayHasKey('reading_minutes', $item);
        $this->assertStringContainsString('<strong>ask</strong>', $item['sections'][0]['body_html']);
        $this->assertSame('Notice what keeps repeating', $item['sections'][0]['heading']);
        // The article page's related cards are not part of the feed shape.
        $this->assertArrayNotHasKey('related', $item);
    }

    public function test_feed_is_capped_at_twenty_newest(): void
    {
        $author = User::factory()->founder()->create();
        $limit = PublicBlogController::FEED_LIMIT;

        for ($i = 0; $i < $limit + 3; $i++) {
            BlogPost::factory()->published(now()->subDays($i)->toDateTimeString())->create([
                'slug' => "post-{$i}",
                'created_by' => $author->id,
            ]);
        }

        $res = $this->getJson('/api/v1/blog/feed')->assertOk();

        $slugs = array_column($res->json('data'), 'slug');
        $this->assertCount($limit, $slugs);
        $this->assertSame('post-0', $slugs[0]);
        $this->assertSame('post-'.($limit - 1), $slugs[$limit - 1]);
        $this->assertNotContains('post-'.$limit, $slugs);
    }
}
