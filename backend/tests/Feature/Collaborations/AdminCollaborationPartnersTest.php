<?php

namespace Tests\Feature\Collaborations;

use App\Models\CollaborationPartner;
use App\Models\Inquiry;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Partners › Collaborations in the admin (docs/global/COLLABORATIONS.md §Admin):
 * the partner list, its page, profile edits and the shared-project links.
 * The public visibility rule itself is covered by PublicCollaborationsTest.
 */
class AdminCollaborationPartnersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The TRLabs data migration seeds a live partner; start each test empty.
        DB::table('collaboration_partners')->delete();
    }

    private function headers(): array
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
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

    private function enquiry(CollaborationPartner $partner, string $status): Inquiry
    {
        return Inquiry::create([
            'name' => 'Nadia Karim', 'email' => 'nadia@example.com', 'message' => 'Hire them via you.',
            'source' => 'web', 'origin' => 'partner', 'collaboration_partner_id' => $partner->id, 'status' => $status,
        ]);
    }

    public function test_the_list_shows_each_partner_with_its_counts(): void
    {
        $partner = CollaborationPartner::factory()->create(['name' => 'Studio X']);
        $partner->linkProject($this->project('alpha'), 'co_build');
        $partner->linkProject($this->project('beta'), 'subcontract');
        $this->enquiry($partner, 'new');
        $this->enquiry($partner, 'reviewing');
        $this->enquiry($partner, 'archived');
        Inquiry::create([
            'name' => 'Studio Y', 'email' => 'y@example.com', 'message' => 'Partner with us?',
            'source' => 'web', 'origin' => 'collaboration', 'status' => 'new',
        ]);

        $res = $this->getJson('/api/v1/admin/collaboration-partners', $this->headers())->assertOk();

        $res->assertJsonPath('data.0.name', 'Studio X')
            ->assertJsonPath('data.0.projects_total', 2)
            ->assertJsonPath('data.0.projects_public', 1)
            ->assertJsonPath('data.0.enquiries_open', 2)
            ->assertJsonPath('data.0.enquiries_new', 1)
            ->assertJsonPath('applications_new', 1);
    }

    public function test_a_hidden_partner_is_listed_too(): void
    {
        CollaborationPartner::factory()->create(['active' => false]);

        $this->getJson('/api/v1/admin/collaboration-partners', $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.active', false);
    }

    public function test_the_partner_page_explains_why_a_project_is_not_public(): void
    {
        $partner = CollaborationPartner::factory()->create();
        $partner->linkProject($this->project('alpha'), 'co_build', 'Them: design · Us: backend');
        $partner->linkProject($this->project('beta'), 'subcontract');
        $partner->linkProject($this->project('gamma', ['active' => false]), 'co_build');

        $res = $this->getJson("/api/v1/admin/collaboration-partners/{$partner->id}", $this->headers())->assertOk();

        $projects = collect($res->json('data.projects'))->keyBy('slug');
        $this->assertTrue($projects['alpha']['public']);
        $this->assertNull($projects['alpha']['hidden_reason']);
        $this->assertSame('Them: design · Us: backend', $projects['alpha']['role_note']);
        $this->assertSame('credit_off', $projects['beta']['hidden_reason']);
        $this->assertSame('project_inactive', $projects['gamma']['hidden_reason']);
        $res->assertJsonPath('data.slug', $partner->slug)
            ->assertJsonPath('data.projects_public', 1);
    }

    public function test_every_project_reads_as_hidden_while_the_partner_is_hidden(): void
    {
        $partner = CollaborationPartner::factory()->create(['active' => false]);
        $partner->linkProject($this->project('alpha'), 'co_build');

        $this->getJson("/api/v1/admin/collaboration-partners/{$partner->id}", $this->headers())
            ->assertOk()
            ->assertJsonPath('data.projects.0.public', false)
            ->assertJsonPath('data.projects.0.hidden_reason', 'partner_hidden');
    }

    public function test_the_profile_can_be_edited_and_the_edit_is_logged(): void
    {
        $partner = CollaborationPartner::factory()->create(['tagline' => 'Old line']);

        $this->patchJson("/api/v1/admin/collaboration-partners/{$partner->id}", [
            'tagline' => 'Product studio from KL',
            'services' => ['Websites', 'AI products'],
            'logo_url' => '/logos/studio.svg',
            'partner_since' => '2026-03-01',
            'active' => false,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.tagline', 'Product studio from KL')
            ->assertJsonPath('data.active', false);

        $partner->refresh();
        $this->assertSame(['Websites', 'AI products'], $partner->services);
        $this->assertFalse($partner->active);
        $this->assertDatabaseHas('activity_log', [
            'action' => 'collaboration_partner.updated',
            'subject_type' => 'CollaborationPartner',
            'subject_id' => $partner->id,
        ]);
    }

    public function test_the_slug_can_never_change(): void
    {
        $partner = CollaborationPartner::factory()->create(['slug' => 'trlabs']);

        $this->patchJson("/api/v1/admin/collaboration-partners/{$partner->id}", ['slug' => 'other'], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->assertSame('trlabs', $partner->fresh()->slug);
    }

    public function test_profile_edits_are_validated(): void
    {
        $partner = CollaborationPartner::factory()->create();

        $this->patchJson("/api/v1/admin/collaboration-partners/{$partner->id}", [
            'summary' => str_repeat('a', 201),
            'logo_url' => 'https://cdn.example.com/logo.svg',
            'symbol_url' => '//evil.example.com/mark.svg',
            'website_url' => 'not a url',
            'services' => 'Websites',
            'partner_since' => 'someday',
        ], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['summary', 'logo_url', 'symbol_url', 'website_url', 'services', 'partner_since']);
    }

    public function test_linking_a_project_follows_the_credit_defaults(): void
    {
        $partner = CollaborationPartner::factory()->create();
        $alpha = $this->project('alpha');
        $beta = $this->project('beta');

        $this->postJson("/api/v1/admin/collaboration-partners/{$partner->id}/projects", [
            'project_id' => $alpha->id, 'mode' => 'co_build', 'role_note' => 'Them: mobile app',
        ], $this->headers())->assertCreated();
        $this->postJson("/api/v1/admin/collaboration-partners/{$partner->id}/projects", [
            'project_id' => $beta->id, 'mode' => 'subcontract',
        ], $this->headers())->assertCreated();

        $links = $partner->projects()->get()->keyBy('slug');
        $this->assertTrue((bool) $links['alpha']->pivot->credit_public, 'co-builds credit publicly by default');
        $this->assertFalse((bool) $links['beta']->pivot->credit_public, 'subcontracts stay private unless switched on');
        $this->assertDatabaseHas('activity_log', ['action' => 'collaboration_partner.project_linked', 'subject_id' => $partner->id]);
    }

    public function test_linking_the_same_project_twice_is_refused(): void
    {
        $partner = CollaborationPartner::factory()->create();
        $alpha = $this->project('alpha');
        $partner->linkProject($alpha, 'co_build', 'Original note');

        $this->postJson("/api/v1/admin/collaboration-partners/{$partner->id}/projects", [
            'project_id' => $alpha->id, 'mode' => 'referral',
        ], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');

        $this->assertSame('co_build', $partner->projects()->first()->pivot->mode);
    }

    public function test_linking_rejects_an_unknown_mode_or_project(): void
    {
        $partner = CollaborationPartner::factory()->create();

        $this->postJson("/api/v1/admin/collaboration-partners/{$partner->id}/projects", [
            'project_id' => 999999, 'mode' => 'merger',
        ], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id', 'mode']);
    }

    public function test_a_link_can_be_edited(): void
    {
        $partner = CollaborationPartner::factory()->create();
        $beta = $this->project('beta');
        $partner->linkProject($beta, 'subcontract');

        $this->patchJson("/api/v1/admin/collaboration-partners/{$partner->id}/projects/{$beta->id}", [
            'mode' => 'co_build', 'role_note' => 'Joint build', 'credit_public' => true,
        ], $this->headers())->assertOk();

        $pivot = $partner->projects()->first()->pivot;
        $this->assertSame('co_build', $pivot->mode);
        $this->assertSame('Joint build', $pivot->role_note);
        $this->assertTrue((bool) $pivot->credit_public);
    }

    public function test_unlinking_keeps_the_project(): void
    {
        $partner = CollaborationPartner::factory()->create();
        $alpha = $this->project('alpha');
        $partner->linkProject($alpha, 'co_build');

        $this->deleteJson("/api/v1/admin/collaboration-partners/{$partner->id}/projects/{$alpha->id}", [], $this->headers())
            ->assertOk();

        $this->assertSame(0, $partner->projects()->count());
        $this->assertNotNull($alpha->fresh());
        $this->assertDatabaseHas('activity_log', ['action' => 'collaboration_partner.project_unlinked', 'subject_id' => $partner->id]);
    }

    public function test_editing_or_unlinking_a_project_that_is_not_linked_is_a_404(): void
    {
        $partner = CollaborationPartner::factory()->create();
        $stranger = $this->project('stranger');

        $this->patchJson("/api/v1/admin/collaboration-partners/{$partner->id}/projects/{$stranger->id}", ['mode' => 'co_build'], $this->headers())
            ->assertNotFound();
        $this->deleteJson("/api/v1/admin/collaboration-partners/{$partner->id}/projects/{$stranger->id}", [], $this->headers())
            ->assertNotFound();
    }

    public function test_a_deleted_partner_is_a_404(): void
    {
        $partner = CollaborationPartner::factory()->create();
        $partner->delete();

        $this->getJson("/api/v1/admin/collaboration-partners/{$partner->id}", $this->headers())->assertNotFound();
    }

    public function test_a_workspace_token_cannot_reach_it(): void
    {
        $token = User::factory()->marketer()->create()->createToken('team-spa', ['workspace'])->plainTextToken;

        $this->getJson('/api/v1/admin/collaboration-partners', ['Authorization' => "Bearer {$token}"])
            ->assertForbidden();
    }
}
