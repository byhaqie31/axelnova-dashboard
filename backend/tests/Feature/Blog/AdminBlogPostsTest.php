<?php

namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use App\Models\PageView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The blog CMS (cockpit-only): drafts may be incomplete, `publish` is the
 * completeness gate and stamps published_at once, slugs are generated /
 * normalised / de-duplicated, the list filters and carries page-view counts,
 * delete is soft and hides the post publicly, and `render` previews without
 * saving. Workspace tokens are rejected like every other /v1/admin route.
 */
class AdminBlogPostsTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(?User $founder = null): array
    {
        $founder ??= User::factory()->founder()->create();
        $token = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Still Managing Enquiries Through WhatsApp?',
            'excerpt' => 'WhatsApp is convenient. Most customers already know how to use it.',
            'sections' => [
                ['heading' => 'Notice what keeps repeating', 'body_md' => 'Do customers ask for the same updates?'],
            ],
            'category' => 'Systems',
            'tags' => ['portal', 'whatsapp'],
        ], $overrides);
    }

    public function test_create_generates_the_slug_and_reading_time_and_starts_as_draft(): void
    {
        $res = $this->postJson('/api/v1/admin/blog/posts', $this->payload(), $this->adminHeaders())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'still-managing-enquiries-through-whatsapp')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.reading_minutes', 1)
            ->assertJsonPath('data.sections.0.heading', 'Notice what keeps repeating');

        $this->assertMatchesRegularExpression('/^s_[a-z0-9]{6}$/', $res->json('data.sections.0.id'));
    }

    public function test_format_defaults_to_article_and_accepts_known_values(): void
    {
        $headers = $this->adminHeaders();

        $id = $this->postJson('/api/v1/admin/blog/posts', $this->payload(), $headers)
            ->assertCreated()
            ->assertJsonPath('data.format', 'article')
            ->json('data.id');

        $this->putJson("/api/v1/admin/blog/posts/{$id}", $this->payload(['format' => 'guide']), $headers)
            ->assertOk()
            ->assertJsonPath('data.format', 'guide');

        $this->putJson("/api/v1/admin/blog/posts/{$id}", $this->payload(['format' => 'poem']), $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['format']);
    }

    public function test_create_normalises_a_custom_slug_and_suffixes_a_collision(): void
    {
        BlogPost::factory()->create(['slug' => 'my-post']);

        $this->postJson('/api/v1/admin/blog/posts', $this->payload(['slug' => ' My Post! ']), $this->adminHeaders())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'my-post-2');
    }

    public function test_validation_limits(): void
    {
        $this->postJson('/api/v1/admin/blog/posts', $this->payload([
            'title' => str_repeat('x', 161),
            'seo_title' => str_repeat('x', 71),
            'tags' => array_fill(0, 11, 't'),
            'cover_image_url' => 'javascript:alert(1)',
            'sections' => [['heading' => '', 'body_md' => 'x', 'image_url' => 'data:x']],
        ]), $this->adminHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'seo_title', 'tags', 'cover_image_url', 'sections.0.heading', 'sections.0.image_url']);
    }

    public function test_publish_gates_on_completeness_then_stamps_published_at_once(): void
    {
        $headers = $this->adminHeaders();

        // A draft may be incomplete.
        $id = $this->postJson('/api/v1/admin/blog/posts', $this->payload(['excerpt' => '', 'sections' => []]), $headers)
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/v1/admin/blog/posts/{$id}/publish", [], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['excerpt', 'sections']);

        $this->putJson("/api/v1/admin/blog/posts/{$id}", $this->payload(), $headers)->assertOk();

        $first = $this->postJson("/api/v1/admin/blog/posts/{$id}/publish", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->json('data.published_at');
        $this->assertNotNull($first);

        $this->postJson("/api/v1/admin/blog/posts/{$id}/unpublish", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', $first);

        // Re-publishing keeps the original date.
        $this->postJson("/api/v1/admin/blog/posts/{$id}/publish", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.published_at', $first);
    }

    public function test_update_can_change_a_published_slug(): void
    {
        $post = BlogPost::factory()->published()->create(['slug' => 'old-slug']);

        $this->putJson("/api/v1/admin/blog/posts/{$post->id}", $this->payload(['slug' => 'new-slug']), $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.slug', 'new-slug')
            ->assertJsonPath('data.status', 'published');

        $this->getJson('/api/v1/blog/posts/old-slug')->assertNotFound();
        $this->getJson('/api/v1/blog/posts/new-slug')->assertOk();
    }

    public function test_index_filters_and_counts_views(): void
    {
        $headers = $this->adminHeaders();
        $pub = BlogPost::factory()->published()->create(['title' => 'Portal timing', 'slug' => 'portal-timing']);
        BlogPost::factory()->create(['title' => 'Draft idea']);
        PageView::create(['path' => '/blog/portal-timing', 'ip_hash' => 'a', 'user_agent' => 'x', 'referrer' => null, 'viewed_at' => now()]);
        PageView::create(['path' => '/blog/portal-timing', 'ip_hash' => 'b', 'user_agent' => 'x', 'referrer' => null, 'viewed_at' => now()]);

        $res = $this->getJson('/api/v1/admin/blog/posts?status=published', $headers)->assertOk();
        $this->assertSame([$pub->id], array_column($res->json('data'), 'id'));
        $this->assertSame(2, $res->json('data.0.views'));

        $this->getJson('/api/v1/admin/blog/posts?q=draft', $headers)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_delete_soft_deletes_and_hides_publicly(): void
    {
        $post = BlogPost::factory()->published()->create();

        $this->deleteJson("/api/v1/admin/blog/posts/{$post->id}", [], $this->adminHeaders())->assertOk();

        $this->assertSoftDeleted('blog_posts', ['id' => $post->id]);
        $this->getJson("/api/v1/blog/posts/{$post->slug}")->assertNotFound();
    }

    public function test_render_previews_without_saving(): void
    {
        $res = $this->postJson('/api/v1/admin/blog/render', [
            'excerpt' => 'Intro',
            'sections' => [['heading' => 'Keep the human part', 'body_md' => 'A portal **keeps** people.']],
        ], $this->adminHeaders())->assertOk();

        $this->assertStringContainsString('<strong>keeps</strong>', $res->json('sections.0.body_html'));
        $this->assertSame('keep-the-human-part', $res->json('toc.0.id'));
        $this->assertSame(1, $res->json('reading_minutes'));
        $this->assertSame(0, BlogPost::count());
    }

    public function test_admin_blog_rejects_a_workspace_token(): void
    {
        $token = User::factory()->marketer()->create()->createToken('team-spa', ['workspace'])->plainTextToken;

        $this->getJson('/api/v1/admin/blog/posts', ['Authorization' => "Bearer {$token}"])->assertForbidden();
    }
}
