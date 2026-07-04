<?php

namespace Tests\Feature\Announcements;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The founder's cockpit surface for announcements: create, edit, and the
 * publish/draft toggle. The team's read-only feed is pinned separately in
 * TeamAnnouncementsTest. There is no delete endpoint — "unpublish" (the
 * `published` boolean flipped false) is the only retraction verb, which is
 * why its timestamp semantics are pinned here in detail.
 */
class AdminAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(?User $founder = null): array
    {
        $founder ??= User::factory()->founder()->create();
        $token = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_the_founder_can_create_a_draft_announcement(): void
    {
        $founder = User::factory()->founder()->create();

        $response = $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Office closed Monday',
            'body' => 'Public holiday — see you Tuesday.',
            'audience' => 'team',
        ], $this->adminHeaders($founder));

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Office closed Monday')
            ->assertJsonPath('data.audience', 'team')
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.created_by', $founder->id)
            ->assertJsonPath('data.created_by_name', $founder->name);
    }

    public function test_the_founder_can_create_and_publish_immediately(): void
    {
        $founder = User::factory()->founder()->create();

        $response = $this->postJson('/api/v1/admin/announcements', [
            'title' => 'New payroll cycle',
            'body' => 'Payroll now runs on the 28th of each month.',
            'audience' => 'all',
            'published' => true,
        ], $this->adminHeaders($founder));

        $response->assertCreated()
            ->assertJsonPath('data.audience', 'all');

        $this->assertNotNull($response->json('data.published_at'));
    }

    public function test_create_validates_required_fields_and_audience_enum(): void
    {
        $founder = User::factory()->founder()->create();

        $this->postJson('/api/v1/admin/announcements', [
            'title' => '',
            'body' => '',
            'audience' => 'everyone',
        ], $this->adminHeaders($founder))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'body', 'audience']);
    }

    public function test_publishing_a_draft_sets_published_at_for_the_first_time(): void
    {
        $founder = User::factory()->founder()->create();
        $announcement = Announcement::create([
            'title' => 'Draft notice',
            'body' => 'Not yet visible.',
            'audience' => 'team',
            'published_at' => null,
            'created_by' => $founder->id,
        ]);

        $response = $this->patchJson("/api/v1/admin/announcements/{$announcement->id}", [
            'published' => true,
        ], $this->adminHeaders($founder))->assertOk();

        $this->assertNotNull($response->json('data.published_at'));
        $this->assertNotNull($announcement->fresh()->published_at);
    }

    public function test_republishing_an_already_published_announcement_keeps_the_original_timestamp(): void
    {
        $founder = User::factory()->founder()->create();
        // Truncate to whole seconds — the `timestamp` column drops microseconds,
        // so comparing ISO strings with sub-second precision would false-fail.
        $publishedAt = now()->subDays(3)->startOfSecond();
        $announcement = Announcement::create([
            'title' => 'Old news',
            'body' => 'Already out.',
            'audience' => 'team',
            'published_at' => $publishedAt,
            'created_by' => $founder->id,
        ]);

        $this->patchJson("/api/v1/admin/announcements/{$announcement->id}", [
            'published' => true,
        ], $this->adminHeaders($founder))->assertOk();

        $this->assertSame(
            $publishedAt->toISOString(),
            $announcement->fresh()->published_at->toISOString()
        );
    }

    public function test_unpublishing_reverts_an_announcement_to_draft(): void
    {
        $founder = User::factory()->founder()->create();
        $announcement = Announcement::create([
            'title' => 'Was live',
            'body' => 'Being pulled back.',
            'audience' => 'team',
            'published_at' => now(),
            'created_by' => $founder->id,
        ]);

        $this->patchJson("/api/v1/admin/announcements/{$announcement->id}", [
            'published' => false,
        ], $this->adminHeaders($founder))
            ->assertOk()
            ->assertJsonPath('data.published_at', null);

        $this->assertNull($announcement->fresh()->published_at);
    }

    public function test_update_edits_title_body_and_audience_without_touching_publish_state(): void
    {
        $founder = User::factory()->founder()->create();
        $publishedAt = now()->subDay()->startOfSecond();
        $announcement = Announcement::create([
            'title' => 'Original title',
            'body' => 'Original body.',
            'audience' => 'team',
            'published_at' => $publishedAt,
            'created_by' => $founder->id,
        ]);

        $this->patchJson("/api/v1/admin/announcements/{$announcement->id}", [
            'title' => 'Retitled',
            'body' => 'Updated body copy.',
            'audience' => 'all',
        ], $this->adminHeaders($founder))
            ->assertOk()
            ->assertJsonPath('data.title', 'Retitled')
            ->assertJsonPath('data.body', 'Updated body copy.')
            ->assertJsonPath('data.audience', 'all');

        $this->assertSame($publishedAt->toISOString(), $announcement->fresh()->published_at->toISOString());
    }

    public function test_index_lists_newest_first_with_creator_name(): void
    {
        $founder = User::factory()->founder()->create();
        $first = Announcement::create([
            'title' => 'First', 'body' => 'a', 'audience' => 'team', 'created_by' => $founder->id,
        ]);
        $second = Announcement::create([
            'title' => 'Second', 'body' => 'b', 'audience' => 'team', 'created_by' => $founder->id,
        ]);

        $response = $this->getJson('/api/v1/admin/announcements', $this->adminHeaders($founder))->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$second->id, $first->id], $ids);
        $this->assertSame($founder->name, $response->json('data.0.created_by_name'));
    }

    public function test_creating_an_announcement_requires_a_cockpit_token(): void
    {
        $marketer = User::factory()->marketer()->create();
        $teamToken = $marketer->createToken('team-spa', ['workspace'])->plainTextToken;

        $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Should not land',
            'body' => 'Blocked.',
            'audience' => 'team',
        ], [
            'Authorization' => "Bearer {$teamToken}",
        ])->assertForbidden();
    }

    public function test_the_admin_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/announcements')->assertUnauthorized();
    }
}
