<?php

namespace Tests\Feature\Announcements;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The workspace read-only feed: published rows only, audience 'team' or
 * 'all', newest-published first. Drafts and 'partners'-only rows must never
 * leak here — those are the two hard exclusions this file pins.
 */
class TeamAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    private function teamHeaders(User $user): array
    {
        $token = $user->createToken('team-spa', ['workspace'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_the_feed_only_returns_published_team_and_all_audience_rows(): void
    {
        $founder = User::factory()->founder()->create();
        $me = User::factory()->engineer()->create();

        $draft = Announcement::create([
            'title' => 'Draft', 'body' => 'x', 'audience' => 'team',
            'published_at' => null, 'created_by' => $founder->id,
        ]);
        $partnersOnly = Announcement::create([
            'title' => 'Partners only', 'body' => 'x', 'audience' => 'partners',
            'published_at' => now(), 'created_by' => $founder->id,
        ]);
        $teamVisible = Announcement::create([
            'title' => 'Team notice', 'body' => 'x', 'audience' => 'team',
            'published_at' => now(), 'created_by' => $founder->id,
        ]);
        $everyoneVisible = Announcement::create([
            'title' => 'Everyone notice', 'body' => 'x', 'audience' => 'all',
            'published_at' => now(), 'created_by' => $founder->id,
        ]);

        $response = $this->getJson('/api/v1/team/announcements', $this->teamHeaders($me))->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($draft->id, $ids);
        $this->assertNotContains($partnersOnly->id, $ids);
        $this->assertContains($teamVisible->id, $ids);
        $this->assertContains($everyoneVisible->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_the_feed_orders_newest_published_first(): void
    {
        $founder = User::factory()->founder()->create();
        $me = User::factory()->engineer()->create();

        $older = Announcement::create([
            'title' => 'Older', 'body' => 'x', 'audience' => 'team',
            'published_at' => now()->subDays(2), 'created_by' => $founder->id,
        ]);
        $newer = Announcement::create([
            'title' => 'Newer', 'body' => 'x', 'audience' => 'team',
            'published_at' => now()->subHour(), 'created_by' => $founder->id,
        ]);

        $response = $this->getJson('/api/v1/team/announcements', $this->teamHeaders($me))->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$newer->id, $older->id], $ids);
    }

    public function test_a_future_dated_publish_time_does_not_show_yet(): void
    {
        $founder = User::factory()->founder()->create();
        $me = User::factory()->engineer()->create();

        $scheduled = Announcement::create([
            'title' => 'Scheduled', 'body' => 'x', 'audience' => 'team',
            'published_at' => now()->addDay(), 'created_by' => $founder->id,
        ]);

        $response = $this->getJson('/api/v1/team/announcements', $this->teamHeaders($me))->assertOk();

        $this->assertNotContains($scheduled->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_the_feed_requires_a_workspace_token(): void
    {
        $this->getJson('/api/v1/team/announcements')->assertUnauthorized();
    }
}
