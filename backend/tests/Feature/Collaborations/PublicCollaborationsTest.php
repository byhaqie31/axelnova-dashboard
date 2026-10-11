<?php

namespace Tests\Feature\Collaborations;

use App\Models\CollaborationPartner;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * /collaborations reads studio partners and the projects built with them. A
 * shared project is public only when ALL three hold: the partner is active,
 * the link's credit_public is on, and the project itself is active —
 * subcontracted work stays private unless the founder opts it in.
 */
class PublicCollaborationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The TR Labs seed migration ships a live row; start each case from an
        // empty table so fixtures own their slugs (TrlabsSeedTest covers the seed).
        DB::table('collaboration_partners')->delete();
    }

    private function project(string $slug, array $overrides = []): Project
    {
        return Project::create(array_merge([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'description' => "The {$slug} project.",
            'long_description' => "The {$slug} project, at length.",
            'status' => 'live',
            'tags' => [],
            'stack' => [],
            'active' => true,
        ], $overrides));
    }

    private function partner(array $overrides = []): CollaborationPartner
    {
        return CollaborationPartner::factory()->create($overrides);
    }

    public function test_index_lists_only_active_partners_in_sort_order(): void
    {
        $this->partner(['slug' => 'second', 'sort_order' => 2]);
        $this->partner(['slug' => 'first', 'sort_order' => 1]);
        $this->partner(['slug' => 'hidden', 'active' => false]);

        $res = $this->getJson('/api/v1/collaborations')->assertOk();

        $this->assertSame(['first', 'second'], array_column($res->json('data'), 'slug'));
    }

    public function test_show_returns_the_profile_with_only_publicly_credited_active_projects(): void
    {
        $partner = $this->partner(['slug' => 'trlabs', 'services' => ['Websites', 'Mobile apps']]);
        $partner->linkProject($this->project('cobuilt'), 'co_build', 'They designed, we built.');
        $partner->linkProject($this->project('subbed'), 'subcontract');
        $partner->linkProject($this->project('retired', ['active' => false]), 'co_build');

        $res = $this->getJson('/api/v1/collaborations/trlabs')->assertOk();

        $res->assertJsonPath('data.slug', 'trlabs')
            ->assertJsonPath('data.services', ['Websites', 'Mobile apps'])
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonPath('data.projects.0.slug', 'cobuilt')
            ->assertJsonPath('data.projects.0.collaboration.mode', 'co_build')
            ->assertJsonPath('data.projects.0.collaboration.role_note', 'They designed, we built.');
    }

    public function test_index_counts_only_public_projects(): void
    {
        $partner = $this->partner();
        $partner->linkProject($this->project('cobuilt'), 'co_build');
        $partner->linkProject($this->project('subbed'), 'subcontract');

        $this->getJson('/api/v1/collaborations')->assertJsonPath('data.0.projects_count', 1);
    }

    public function test_show_404s_for_an_inactive_deleted_or_unknown_partner(): void
    {
        $this->partner(['slug' => 'dormant', 'active' => false]);
        $this->partner(['slug' => 'former'])->delete();

        $this->getJson('/api/v1/collaborations/dormant')->assertNotFound();
        $this->getJson('/api/v1/collaborations/former')->assertNotFound();
        $this->getJson('/api/v1/collaborations/nobody')->assertNotFound();
    }

    public function test_credit_defaults_to_public_only_for_co_builds(): void
    {
        $partner = $this->partner();
        $partner->linkProject($this->project('a'), 'co_build');
        $partner->linkProject($this->project('b'), 'subcontract');
        $partner->linkProject($this->project('c'), 'referral');
        $partner->linkProject($this->project('d'), 'subcontract', creditPublic: true);

        $credits = $partner->projects()->pluck('collaboration_partner_project.credit_public', 'slug')->all();

        $this->assertEquals(['a' => 1, 'b' => 0, 'c' => 0, 'd' => 1], $credits);
    }

    public function test_an_unknown_mode_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->partner()->linkProject($this->project('x'), 'white_label');
    }

    public function test_project_detail_credits_public_partners_only(): void
    {
        $project = $this->project('shared');
        $this->partner(['slug' => 'trlabs', 'name' => 'TRLabs'])->linkProject($project, 'co_build');
        $this->partner(['slug' => 'quiet'])->linkProject($project, 'subcontract');
        $this->partner(['slug' => 'gone', 'active' => false])->linkProject($project, 'co_build');

        $res = $this->getJson('/api/v1/projects/shared')->assertOk();

        $res->assertJsonCount(1, 'data.partners')
            ->assertJsonPath('data.partners.0.slug', 'trlabs')
            ->assertJsonPath('data.partners.0.name', 'TRLabs')
            ->assertJsonPath('data.partners.0.mode', 'co_build');
    }

    public function test_project_listing_does_not_carry_partners(): void
    {
        $this->project('plain');

        $res = $this->getJson('/api/v1/projects')->assertOk();

        $this->assertArrayNotHasKey('partners', $res->json('data.0'));
    }
}
