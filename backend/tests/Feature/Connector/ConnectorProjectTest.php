<?php

namespace Tests\Feature\Connector;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The MCP connector's portfolio-projects surface (v6): LIST + read back any
 * project (Live or Hidden), create projects HIDDEN, and PARTIALLY update a
 * project while it is still Hidden. Going Live, Featured, ordering and delete
 * stay in the admin. (One token per test — the Sanctum guard caches the user
 * across requests within a test.)
 */
class ConnectorProjectTest extends TestCase
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
            'name' => 'Klinik Booking Portal',
            'description' => 'A booking portal for a three-branch clinic.',
            'long_description' => 'Patients book, staff confirm, the owner sees every branch in one place.',
            'status' => 'live',
            'url' => 'https://klinik.example.com',
            'tags' => ['Healthcare', 'Booking'],
            'stack' => ['Laravel', 'Nuxt'],
        ], $overrides);
    }

    private function project(array $overrides = []): Project
    {
        return Project::create(array_merge([
            'slug' => 'house-of-parfum',
            'name' => 'House of Parfum',
            'description' => 'E-commerce for a perfume house.',
            'long_description' => 'Long story.',
            'status' => 'wip',
            'tags' => ['E-commerce'],
            'stack' => ['Laravel'],
            'sort_order' => 0,
            'active' => true,
        ], $overrides));
    }

    // ── Access ──────────────────────────────────────────────────────────────

    public function test_no_token_is_unauthorised(): void
    {
        $this->getJson('/api/v1/connector/projects')->assertUnauthorized();
    }

    public function test_a_cockpit_token_is_rejected(): void
    {
        $token = $this->founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        $this->getJson('/api/v1/connector/projects', ['Authorization' => "Bearer {$token}"])->assertForbidden();
    }

    public function test_a_read_only_token_can_read_but_not_write(): void
    {
        $project = $this->project(['active' => false]);
        $headers = $this->tokenHeader(['connector:read']);

        $this->getJson('/api/v1/connector/projects', $headers)->assertOk();
        $this->getJson("/api/v1/connector/projects/{$project->id}", $headers)->assertOk();
        $this->postJson('/api/v1/connector/projects', $this->payload(), $headers)->assertForbidden();
        $this->putJson("/api/v1/connector/projects/{$project->id}", ['name' => 'X'], $headers)->assertForbidden();
    }

    // ── Read ────────────────────────────────────────────────────────────────

    public function test_list_returns_live_and_hidden_projects_with_visibility(): void
    {
        $this->project(['slug' => 'live-one', 'name' => 'Live One', 'active' => true]);
        $this->project(['slug' => 'hidden-one', 'name' => 'Hidden One', 'active' => false]);

        $res = $this->getJson('/api/v1/connector/projects', $this->tokenHeader())->assertOk();

        $rows = collect($res->json('data'))->keyBy('slug');
        $this->assertSame('live', $rows['live-one']['visibility']);
        $this->assertSame('hidden', $rows['hidden-one']['visibility']);
        $this->assertNull($rows['hidden-one']['public_url']);
        $this->assertStringEndsWith('/projects/live-one', $rows['live-one']['public_url']);
        $this->assertSame(2, $res->json('meta.total'));
    }

    public function test_list_filters_by_visibility_and_search(): void
    {
        $this->project(['slug' => 'live-one', 'name' => 'Live One', 'active' => true]);
        $this->project(['slug' => 'hidden-one', 'name' => 'Hidden One', 'active' => false]);
        $headers = $this->tokenHeader();

        $this->getJson('/api/v1/connector/projects?visibility=hidden', $headers)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'hidden-one');
        $this->getJson('/api/v1/connector/projects?q=live', $headers)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'live-one');
    }

    public function test_show_returns_the_full_record_with_urls(): void
    {
        $project = $this->project(['active' => false]);

        $this->getJson("/api/v1/connector/projects/{$project->id}", $this->tokenHeader())
            ->assertOk()
            ->assertJsonPath('data.slug', 'house-of-parfum')
            ->assertJsonPath('data.long_description', 'Long story.')
            ->assertJsonPath('data.visibility', 'hidden')
            ->assertJsonPath('data.public_url', null)
            ->assertJsonPath('data.admin_url', rtrim((string) config('services.frontend.url'), '/')."/admin/projects/{$project->id}");
    }

    public function test_show_unknown_id_is_404_with_a_hint(): void
    {
        $this->getJson('/api/v1/connector/projects/999', $this->tokenHeader())
            ->assertNotFound()
            ->assertJsonPath('message', 'No project with id 999. Use list_projects to find the right one.');
    }

    // ── Create ──────────────────────────────────────────────────────────────

    public function test_create_is_always_hidden_unfeatured_and_appended(): void
    {
        $this->project(['slug' => 'first', 'sort_order' => 3]);

        $res = $this->postJson('/api/v1/connector/projects', $this->payload([
            'active' => true,
            'featured' => true,
            'sort_order' => 0,
        ]), $this->tokenHeader())->assertCreated();

        $project = Project::findOrFail($res->json('data.id'));
        $this->assertFalse($project->active);
        $this->assertFalse($project->featured);
        $this->assertSame(4, $project->sort_order);
        $this->assertSame('klinik-booking-portal', $project->slug);
        $this->assertSame('live', $project->status);
        $this->assertSame(['Healthcare', 'Booking'], $project->tags);
        $res->assertJsonPath('data.visibility', 'hidden');

        $log = ActivityLog::where('action', 'project.created')->firstOrFail();
        $this->assertSame('mcp_connector', $log->changes['via']);
    }

    public function test_create_derives_a_unique_slug(): void
    {
        $this->project(['slug' => 'klinik-booking-portal']);

        $this->postJson('/api/v1/connector/projects', $this->payload(), $this->tokenHeader())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'klinik-booking-portal-2');
    }

    public function test_create_rejects_a_slug_already_taken(): void
    {
        $this->project(['slug' => 'taken']);

        $this->postJson('/api/v1/connector/projects', $this->payload(['slug' => 'taken']), $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_create_defaults_tags_and_stack_to_empty_lists(): void
    {
        $payload = $this->payload();
        unset($payload['tags'], $payload['stack']);

        $res = $this->postJson('/api/v1/connector/projects', $payload, $this->tokenHeader())->assertCreated();

        $this->assertSame([], Project::findOrFail($res->json('data.id'))->tags);
    }

    public function test_create_requires_the_content_fields(): void
    {
        $this->postJson('/api/v1/connector/projects', [], $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'description', 'long_description', 'status']);
    }

    public function test_create_rejects_a_script_cover_url(): void
    {
        $this->postJson('/api/v1/connector/projects', $this->payload(['cover_image_url' => 'javascript:alert(1)']), $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cover_image_url');
    }

    // ── Update ──────────────────────────────────────────────────────────────

    public function test_update_is_partial_on_a_hidden_project(): void
    {
        $project = $this->project(['active' => false]);

        $this->putJson("/api/v1/connector/projects/{$project->id}", [
            'description' => 'Sharper one-liner.',
            'stack' => ['Laravel', 'Nuxt', 'Redis'],
        ], $this->tokenHeader())->assertOk();

        $project->refresh();
        $this->assertSame('Sharper one-liner.', $project->description);
        $this->assertSame(['Laravel', 'Nuxt', 'Redis'], $project->stack);
        $this->assertSame('House of Parfum', $project->name);
        $this->assertSame('house-of-parfum', $project->slug);
        $this->assertFalse($project->active);
        $this->assertSame('mcp_connector', ActivityLog::where('action', 'project.updated')->firstOrFail()->changes['via']);
    }

    public function test_update_cannot_make_a_project_live(): void
    {
        $project = $this->project(['active' => false]);

        $this->putJson("/api/v1/connector/projects/{$project->id}", ['active' => true, 'featured' => true], $this->tokenHeader())
            ->assertOk();

        $project->refresh();
        $this->assertFalse($project->active);
        $this->assertFalse($project->featured);
    }

    public function test_update_is_refused_while_the_project_is_live(): void
    {
        $project = $this->project(['active' => true]);

        $this->putJson("/api/v1/connector/projects/{$project->id}", ['name' => 'Renamed'], $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('visibility');

        $this->assertSame('House of Parfum', $project->fresh()->name);
    }

    public function test_update_slug_must_stay_unique(): void
    {
        $this->project(['slug' => 'taken']);
        $project = $this->project(['slug' => 'mine', 'active' => false]);
        $headers = $this->tokenHeader();

        $this->putJson("/api/v1/connector/projects/{$project->id}", ['slug' => 'taken'], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->putJson("/api/v1/connector/projects/{$project->id}", ['slug' => 'mine'], $headers)
            ->assertOk();
    }
}
