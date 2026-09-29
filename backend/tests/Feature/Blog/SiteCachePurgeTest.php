<?php

namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Publishing is instant on the public site: every change a visitor can see
 * (publish, unpublish, editing or deleting a live post) asks the Nuxt server to
 * drop its cached pages (POST /_cache/purge), once, after the response. Draft
 * edits change nothing public and don't purge; an unconfigured purge is a no-op
 * (dev / CI), and a failed one never fails the save — the 5-minute swr window
 * is the fallback.
 */
class SiteCachePurgeTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'http://axelnova-frontend:3000/_cache/purge';

    /** Flip to make the fake frontend fail (the first registered Http::fake stub wins, so it can't be re-faked). */
    private bool $frontendDown = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.site_cache.purge_url' => self::URL, 'services.site_cache.purge_token' => 'secret-token']);
        Http::fake([self::URL => fn () => $this->frontendDown ? Http::response('down', 502) : Http::response(['purged' => 3])]);
    }

    private function adminHeaders(): array
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    /** Setup rows are created without events, so only the request under test can purge. */
    private function blogPost(bool $published): BlogPost
    {
        return BlogPost::withoutEvents(fn () => $published
            ? BlogPost::factory()->published()->create()
            : BlogPost::factory()->create());
    }

    private function editPayload(BlogPost $post): array
    {
        return [
            'title' => $post->title.' (edited)',
            'slug' => $post->slug,
            'excerpt' => 'An intro.',
            'sections' => [['heading' => 'One', 'body_md' => 'Body.']],
        ];
    }

    private function assertPurgedOnce(): void
    {
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->url() === self::URL
            && $r->method() === 'POST'
            && $r->hasHeader('Authorization', 'Bearer secret-token'));
    }

    public function test_publishing_purges_once(): void
    {
        $post = $this->blogPost(published: false);

        $this->postJson("/api/v1/admin/blog/posts/{$post->id}/publish", [], $this->adminHeaders())->assertOk();

        $this->assertPurgedOnce();
    }

    public function test_unpublishing_purges(): void
    {
        $post = $this->blogPost(published: true);

        $this->postJson("/api/v1/admin/blog/posts/{$post->id}/unpublish", [], $this->adminHeaders())->assertOk();

        $this->assertPurgedOnce();
    }

    public function test_editing_a_live_post_purges(): void
    {
        $post = $this->blogPost(published: true);

        $this->putJson("/api/v1/admin/blog/posts/{$post->id}", $this->editPayload($post), $this->adminHeaders())->assertOk();

        $this->assertPurgedOnce();
    }

    public function test_deleting_a_live_post_purges(): void
    {
        $post = $this->blogPost(published: true);

        $this->deleteJson("/api/v1/admin/blog/posts/{$post->id}", [], $this->adminHeaders())->assertOk();

        $this->assertPurgedOnce();
    }

    public function test_draft_changes_do_not_purge(): void
    {
        $headers = $this->adminHeaders();
        $post = $this->blogPost(published: false);

        $this->putJson("/api/v1/admin/blog/posts/{$post->id}", $this->editPayload($post), $headers)->assertOk();
        $this->deleteJson("/api/v1/admin/blog/posts/{$post->id}", [], $headers)->assertOk();

        Http::assertNothingSent();
    }

    public function test_an_unconfigured_purge_is_a_no_op(): void
    {
        config(['services.site_cache.purge_url' => null, 'services.site_cache.purge_token' => null]);
        $post = $this->blogPost(published: false);

        $this->postJson("/api/v1/admin/blog/posts/{$post->id}/publish", [], $this->adminHeaders())->assertOk();

        Http::assertNothingSent();
    }

    public function test_a_failed_purge_is_logged_and_never_fails_the_save(): void
    {
        $this->frontendDown = true;
        Log::spy();
        $post = $this->blogPost(published: false);

        $this->postJson("/api/v1/admin/blog/posts/{$post->id}/publish", [], $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        Log::shouldHaveReceived('warning')->once();
    }
}
