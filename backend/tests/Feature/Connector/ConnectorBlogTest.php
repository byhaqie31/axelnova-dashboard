<?php

namespace Tests\Feature\Connector;

use App\Models\ActivityLog;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The MCP connector's blog surface (v4): read the guide, LIST + read back any
 * non-deleted post, create DRAFTS, and PARTIALLY update a post while it is a
 * draft. The universal connector:read / connector:draft abilities gate it;
 * nothing here can publish, unpublish, delete, or touch a published post.
 * (One token per test — the Sanctum guard caches the user across requests
 * within a test.)
 */
class ConnectorBlogTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->founder = User::factory()->founder()->create();
    }

    /** @param  list<string>  $abilities */
    private function tokenHeader(array $abilities = ['connector:read', 'connector:draft']): array
    {
        $token = $this->founder->createToken('mcp-connector', $abilities)->plainTextToken;

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
            'format' => 'guide',
            'category' => 'Systems',
            'tags' => ['portal', 'whatsapp'],
        ], $overrides);
    }

    // ── Access ──────────────────────────────────────────────────────────────

    public function test_no_token_is_unauthorised(): void
    {
        $this->getJson('/api/v1/connector/blog/posts')->assertUnauthorized();
    }

    public function test_a_cockpit_token_is_rejected(): void
    {
        $token = $this->founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        $this->getJson('/api/v1/connector/blog/guide', ['Authorization' => "Bearer {$token}"])->assertForbidden();
    }

    public function test_a_read_only_token_can_read_but_not_write(): void
    {
        $post = BlogPost::factory()->create();
        $headers = $this->tokenHeader(['connector:read']);

        $this->getJson('/api/v1/connector/blog/guide', $headers)->assertOk();
        $this->getJson('/api/v1/connector/blog/posts', $headers)->assertOk();
        $this->getJson("/api/v1/connector/blog/posts/{$post->id}", $headers)->assertOk();
        $this->postJson('/api/v1/connector/blog/posts', $this->payload(), $headers)->assertForbidden();
        $this->putJson("/api/v1/connector/blog/posts/{$post->id}", ['title' => 'X'], $headers)->assertForbidden();
    }

    public function test_a_connector_token_cannot_reach_admin_publish(): void
    {
        $post = BlogPost::factory()->create();

        $this->postJson("/api/v1/admin/blog/posts/{$post->id}/publish", [], $this->tokenHeader())->assertForbidden();
        $this->assertSame(BlogPost::STATUS_DRAFT, $post->fresh()->status);
    }

    // ── Guide ───────────────────────────────────────────────────────────────

    public function test_guide_serves_config_formats_and_the_categories_and_tags_in_use(): void
    {
        BlogPost::factory()->create(['category' => 'Systems', 'tags' => ['portal', 'AI']]);
        BlogPost::factory()->published()->create(['category' => 'UI/UX', 'tags' => ['portal']]);
        BlogPost::factory()->create(['category' => 'Deleted cat', 'tags' => ['gone']])->delete();

        $this->getJson('/api/v1/connector/blog/guide', $this->tokenHeader(['connector:read']))
            ->assertOk()
            ->assertJsonPath('voice', config('blog.voice'))
            ->assertJsonPath('structure', config('blog.structure'))
            ->assertJsonPath('cta_defaults', config('blog.cta_defaults'))
            ->assertJsonPath('formats', BlogPost::FORMATS)
            ->assertJsonPath('categories', ['Systems', 'UI/UX'])
            ->assertJsonPath('tags', ['AI', 'portal'])
            ->assertJsonStructure(['modes' => ['write', 'structure_only'], 'section_shape', 'image_rules', 'limits']);
    }

    public function test_create_and_update_snap_category_and_tags_to_the_spelling_already_in_use(): void
    {
        BlogPost::factory()->create(['category' => 'Systems', 'tags' => ['Cloudflare', 'AI']]);

        $id = $this->postJson('/api/v1/connector/blog/posts', $this->payload(['category' => 'systems', 'tags' => ['cloudflare', 'ai', 'Brand New']]), $this->tokenHeader())
            ->assertCreated()
            ->assertJsonPath('data.category', 'Systems')
            ->assertJsonPath('data.tags', ['Cloudflare', 'AI', 'Brand New'])
            ->json('data.id');

        $this->putJson("/api/v1/connector/blog/posts/{$id}", ['category' => 'SYSTEMS', 'tags' => ['brand new', 'CLOUDFLARE']], $this->tokenHeader())
            ->assertOk()
            ->assertJsonPath('data.category', 'Systems')
            ->assertJsonPath('data.tags', ['brand new', 'Cloudflare']);
    }

    // ── List / show ─────────────────────────────────────────────────────────

    public function test_list_is_slim_newest_first_filterable_and_hides_deleted_posts(): void
    {
        $draft = BlogPost::factory()->create(['title' => 'Portal checklist', 'updated_at' => now()->subDay()]);
        $live = BlogPost::factory()->published()->create(['title' => 'Why UX audits matter', 'updated_at' => now()]);
        BlogPost::factory()->create(['title' => 'Deleted post'])->delete();

        $res = $this->getJson('/api/v1/connector/blog/posts', $this->tokenHeader(['connector:read']))->assertOk();

        $this->assertSame([$live->id, $draft->id], array_column($res->json('data'), 'id'));
        $this->assertSame(2, $res->json('meta.total'));
        $this->assertArrayNotHasKey('sections', $res->json('data.0'));
        $this->assertStringEndsWith("/admin/blog/{$live->id}", $res->json('data.0.admin_url'));
        $this->assertStringEndsWith("/blog/{$live->slug}", $res->json('data.0.public_url'));
        $this->assertNull($res->json('data.1.public_url'));

        $this->assertSame([$draft->id], array_column(
            $this->getJson('/api/v1/connector/blog/posts?status=draft', $this->tokenHeader(['connector:read']))->json('data'), 'id'));
        $this->assertSame([$live->id], array_column(
            $this->getJson('/api/v1/connector/blog/posts?q=audits', $this->tokenHeader(['connector:read']))->json('data'), 'id'));
    }

    public function test_list_caps_per_page_at_25(): void
    {
        $this->getJson('/api/v1/connector/blog/posts?per_page=100', $this->tokenHeader(['connector:read']))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 25);
    }

    public function test_show_returns_the_markdown_record_and_404s_on_missing_or_deleted(): void
    {
        $post = BlogPost::factory()->create();
        $deleted = BlogPost::factory()->create();
        $deleted->delete();
        $headers = $this->tokenHeader(['connector:read']);

        $this->getJson("/api/v1/connector/blog/posts/{$post->id}", $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $post->id)
            ->assertJsonPath('data.sections.0.body_md', $post->sections[0]['body_md'])
            ->assertJsonPath('data.public_url', null);

        $this->getJson("/api/v1/connector/blog/posts/{$deleted->id}", $headers)
            ->assertNotFound()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'list_blog_posts'));
        $this->getJson('/api/v1/connector/blog/posts/999999', $headers)->assertNotFound();
    }

    // ── Create ──────────────────────────────────────────────────────────────

    public function test_create_is_always_a_draft_owned_by_the_founder_and_logged_as_connector(): void
    {
        $res = $this->postJson('/api/v1/connector/blog/posts', $this->payload([
            'status' => 'published',
            'published_at' => '2026-01-01 00:00:00',
        ]), $this->tokenHeader())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.slug', 'still-managing-enquiries-through-whatsapp')
            ->assertJsonPath('data.format', 'guide');

        $post = BlogPost::findOrFail($res->json('data.id'));
        $this->assertSame($this->founder->id, $post->created_by);
        $this->assertStringEndsWith("/admin/blog/{$post->id}", $res->json('data.admin_url'));

        $log = ActivityLog::where('action', 'blog_post.created')->where('subject_id', $post->id)->firstOrFail();
        $this->assertSame('mcp_connector', $log->changes['via']);
    }

    public function test_create_requires_alt_text_for_the_cover_and_section_images(): void
    {
        $headers = $this->tokenHeader();

        $this->postJson('/api/v1/connector/blog/posts', $this->payload([
            'cover_image_url' => 'https://images.unsplash.com/photo-1?w=1600',
        ]), $headers)->assertUnprocessable()->assertJsonValidationErrors(['cover_image_alt']);

        $this->postJson('/api/v1/connector/blog/posts', $this->payload([
            'sections' => [['heading' => 'One', 'body_md' => 'x', 'image_url' => 'https://images.unsplash.com/photo-2']],
        ]), $headers)->assertUnprocessable()->assertJsonValidationErrors(['sections.0.image_alt']);

        $this->postJson('/api/v1/connector/blog/posts', $this->payload([
            'cover_image_url' => 'javascript:alert(1)',
            'cover_image_alt' => 'x',
        ]), $headers)->assertUnprocessable()->assertJsonValidationErrors(['cover_image_url']);

        $this->assertSame(0, BlogPost::count());
    }

    public function test_create_de_duplicates_the_slug(): void
    {
        BlogPost::factory()->create(['slug' => 'still-managing-enquiries-through-whatsapp']);

        $this->postJson('/api/v1/connector/blog/posts', $this->payload(), $this->tokenHeader())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'still-managing-enquiries-through-whatsapp-2');
    }

    // ── Update ──────────────────────────────────────────────────────────────

    public function test_update_is_partial_and_keeps_the_slug_when_only_the_title_changes(): void
    {
        $post = BlogPost::factory()->create(['title' => 'Old title', 'slug' => 'old-title', 'category' => 'Systems']);
        $before = $post->fresh();

        $this->putJson("/api/v1/connector/blog/posts/{$post->id}", [
            'title' => 'A sharper title',
            'cover_image_url' => 'https://images.unsplash.com/photo-3?w=1600&q=80&auto=format',
            'cover_image_alt' => 'A laptop on a wooden desk',
        ], $this->tokenHeader())
            ->assertOk()
            ->assertJsonPath('data.title', 'A sharper title')
            ->assertJsonPath('data.slug', 'old-title')
            ->assertJsonPath('data.cover_image_alt', 'A laptop on a wooden desk')
            ->assertJsonPath('data.category', 'Systems')
            ->assertJsonPath('data.excerpt', $before->excerpt)
            ->assertJsonPath('data.sections', $before->sections)
            ->assertJsonPath('data.status', 'draft');

        $log = ActivityLog::where('action', 'blog_post.updated')->where('subject_id', $post->id)->firstOrFail();
        $this->assertSame('mcp_connector', $log->changes['via']);
    }

    public function test_update_checks_cover_alt_against_the_merged_post(): void
    {
        $post = BlogPost::factory()->create(['cover_image_url' => 'https://images.unsplash.com/photo-1', 'cover_image_alt' => 'A desk']);
        $headers = $this->tokenHeader();

        // A new URL with the stored alt is fine…
        $this->putJson("/api/v1/connector/blog/posts/{$post->id}", ['cover_image_url' => 'https://images.unsplash.com/photo-9'], $headers)
            ->assertOk()
            ->assertJsonPath('data.cover_image_url', 'https://images.unsplash.com/photo-9');

        // …but clearing the alt while an image remains is not.
        $this->putJson("/api/v1/connector/blog/posts/{$post->id}", ['cover_image_alt' => null], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cover_image_alt']);
    }

    public function test_update_rejects_a_blank_title(): void
    {
        $post = BlogPost::factory()->create();

        $this->putJson("/api/v1/connector/blog/posts/{$post->id}", ['title' => ''], $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_update_replaces_sections_keeping_sent_ids_and_minting_new_ones(): void
    {
        $post = BlogPost::factory()->create();
        $keptId = $post->sections[0]['id'];

        $res = $this->putJson("/api/v1/connector/blog/posts/{$post->id}", [
            'sections' => [
                ['id' => $keptId, 'heading' => 'Rewritten', 'body_md' => str_repeat('word ', 400)],
                ['heading' => 'Brand new', 'body_md' => 'Fresh'],
            ],
        ], $this->tokenHeader())->assertOk();

        $this->assertSame($keptId, $res->json('data.sections.0.id'));
        $this->assertSame('Rewritten', $res->json('data.sections.0.heading'));
        $this->assertMatchesRegularExpression('/^s_[a-z0-9]{6}$/', $res->json('data.sections.1.id'));
        $this->assertNotSame($keptId, $res->json('data.sections.1.id'));
        $this->assertCount(2, $res->json('data.sections'));
        $this->assertGreaterThanOrEqual(3, $res->json('data.reading_minutes'));
    }

    public function test_update_of_a_published_post_is_refused_and_leaves_it_untouched(): void
    {
        $post = BlogPost::factory()->published()->create(['title' => 'Live post']);

        $this->putJson("/api/v1/connector/blog/posts/{$post->id}", ['title' => 'Sneaky edit'], $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertSame('Live post', $post->fresh()->title);
    }

    public function test_update_cannot_publish_or_404s_on_a_deleted_post(): void
    {
        $post = BlogPost::factory()->create();
        $deleted = BlogPost::factory()->create();
        $deleted->delete();
        $headers = $this->tokenHeader();

        $this->putJson("/api/v1/connector/blog/posts/{$post->id}", ['status' => 'published', 'published_at' => now()->toISOString()], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null);

        $this->putJson("/api/v1/connector/blog/posts/{$deleted->id}", ['title' => 'Back from the dead'], $headers)->assertNotFound();
    }
}
