<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The workspace analytics endpoint — the marketer's read-only mirror of the
 * cockpit traffic overview. Gated `role:founder,marketer` inside /v1/team:
 * engineers hold a valid workspace token but must be refused.
 */
class TeamAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function teamToken(User $user): array
    {
        $token = $user->createToken('team-spa', ['workspace'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_a_marketer_can_read_the_analytics_overview(): void
    {
        $marketer = User::factory()->marketer()->create();

        $this->getJson('/api/v1/team/analytics/overview?range=7d', $this->teamToken($marketer))
            ->assertOk()
            ->assertJsonStructure([
                'range',
                'views' => ['total', 'unique', 'series'],
                'topPaths',
                'topReferrers',
                'topLikedProjects',
            ]);
    }

    public function test_the_founder_can_read_the_analytics_overview(): void
    {
        $founder = User::factory()->create(['role' => 'founder']);

        $this->getJson('/api/v1/team/analytics/overview', $this->teamToken($founder))
            ->assertOk();
    }

    public function test_an_engineer_is_forbidden(): void
    {
        $engineer = User::factory()->engineer()->create();

        $this->getJson('/api/v1/team/analytics/overview', $this->teamToken($engineer))
            ->assertForbidden();
    }
}
