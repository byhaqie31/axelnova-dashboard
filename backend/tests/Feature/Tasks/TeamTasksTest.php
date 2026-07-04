<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The workspace surface: the {pool, mine} feed, claiming from the pool, and the
 * team-owned status transitions (start / complete / release). Ownership is the
 * hard boundary — a member can never move someone else's task — and the forward
 * chain is enforced (no open → completed skip). NB: one authenticated user per
 * test — the sanctum guard caches the first bearer token's user across in-test
 * requests, so conflict scenarios pre-seed the other side's state via factories.
 */
class TeamTasksTest extends TestCase
{
    use RefreshDatabase;

    private function teamHeaders(User $user): array
    {
        $token = $user->createToken('team-spa', ['workspace'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_the_feed_splits_pool_and_mine(): void
    {
        $me = User::factory()->engineer()->create();
        $someoneElse = User::factory()->marketer()->create();

        $pooled = Task::factory()->pooled()->create(['title' => 'Pool task']);
        $mineOpen = Task::factory()->assignedTo($me)->create(['title' => 'Assigned to me, not started']);
        $mineDone = Task::factory()->assignedTo($me)->completed()->create(['title' => 'My finished one']);
        Task::factory()->assignedTo($someoneElse)->inProgress()->create(['title' => 'Not mine']);

        $response = $this->getJson('/api/v1/team/tasks', $this->teamHeaders($me))->assertOk();

        $poolIds = collect($response->json('pool'))->pluck('id');
        $mineIds = collect($response->json('mine'))->pluck('id');

        $this->assertEquals([$pooled->id], $poolIds->all());
        $this->assertEqualsCanonicalizing([$mineOpen->id, $mineDone->id], $mineIds->all());
    }

    public function test_an_assigned_but_open_task_is_not_in_the_pool(): void
    {
        $me = User::factory()->engineer()->create();
        $other = User::factory()->marketer()->create();
        Task::factory()->assignedTo($other)->create(['status' => 'open']);

        $response = $this->getJson('/api/v1/team/tasks', $this->teamHeaders($me))->assertOk();

        $this->assertSame([], $response->json('pool'));
        $this->assertSame([], $response->json('mine'));
    }

    public function test_claiming_a_pool_task_assigns_me_and_starts_it(): void
    {
        $me = User::factory()->engineer()->create();
        $task = Task::factory()->pooled()->create();

        $this->postJson("/api/v1/team/tasks/{$task->id}/claim", [], $this->teamHeaders($me))
            ->assertOk()
            ->assertJsonPath('data.assignee_id', $me->id)
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_claiming_an_already_claimed_task_conflicts(): void
    {
        $me = User::factory()->engineer()->create();
        $winner = User::factory()->marketer()->create();
        // The other teammate got there first (their claim already landed).
        $task = Task::factory()->assignedTo($winner)->inProgress()->create();

        $this->postJson("/api/v1/team/tasks/{$task->id}/claim", [], $this->teamHeaders($me))
            ->assertConflict();

        $this->assertSame($winner->id, $task->fresh()->assignee_id);
    }

    public function test_claiming_an_assigned_but_unstarted_task_conflicts(): void
    {
        $me = User::factory()->engineer()->create();
        $other = User::factory()->marketer()->create();
        // Admin-assigned to someone else, still open — not poachable.
        $task = Task::factory()->assignedTo($other)->create(['status' => 'open']);

        $this->postJson("/api/v1/team/tasks/{$task->id}/claim", [], $this->teamHeaders($me))
            ->assertConflict();
    }

    public function test_starting_an_admin_assigned_task(): void
    {
        $me = User::factory()->engineer()->create();
        $task = Task::factory()->assignedTo($me)->create(['status' => 'open']);

        $this->patchJson("/api/v1/team/tasks/{$task->id}/status", [
            'status' => 'in_progress',
        ], $this->teamHeaders($me))
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_completing_without_pay_lands_on_completed(): void
    {
        $me = User::factory()->engineer()->create();
        $task = Task::factory()->assignedTo($me)->inProgress()->create(['pay_amount_myr' => null]);

        $this->patchJson("/api/v1/team/tasks/{$task->id}/status", [
            'status' => 'completed',
            'note' => 'All done, deployed to prod.',
        ], $this->teamHeaders($me))
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.payment_state', 'none');

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_completing_with_pay_forks_to_payment_pending(): void
    {
        $me = User::factory()->engineer()->create();
        $task = Task::factory()->assignedTo($me)->inProgress()->withPay(200)->create();

        $this->patchJson("/api/v1/team/tasks/{$task->id}/status", [
            'status' => 'completed',
            'note' => 'Done.',
        ], $this->teamHeaders($me))
            ->assertOk()
            ->assertJsonPath('data.status', 'payment_pending')
            ->assertJsonPath('data.payment_state', 'pending');

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_completion_notes_append_as_timestamped_lines(): void
    {
        $me = User::factory()->engineer()->create(['name' => 'Aina']);
        $task = Task::factory()->assignedTo($me)->inProgress()->create(['notes' => '[2026-07-01 09:00] Aina: Started.']);

        $response = $this->patchJson("/api/v1/team/tasks/{$task->id}/status", [
            'status' => 'completed',
            'note' => 'Shipped it.',
        ], $this->teamHeaders($me))->assertOk();

        $notes = $response->json('data.notes');
        $this->assertStringStartsWith('[2026-07-01 09:00] Aina: Started.', $notes);
        $this->assertMatchesRegularExpression('/\n\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}\] Aina: Shipped it\.$/', $notes);
    }

    public function test_releasing_an_in_progress_task_returns_it_to_the_pool(): void
    {
        $me = User::factory()->engineer()->create();
        $task = Task::factory()->assignedTo($me)->inProgress()->create();

        $this->patchJson("/api/v1/team/tasks/{$task->id}/status", [
            'status' => 'open',
        ], $this->teamHeaders($me))
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.assignee_id', null);
    }

    public function test_the_forward_chain_cannot_be_skipped(): void
    {
        $me = User::factory()->engineer()->create();
        $task = Task::factory()->assignedTo($me)->create(['status' => 'open']);

        $this->patchJson("/api/v1/team/tasks/{$task->id}/status", [
            'status' => 'completed',
        ], $this->teamHeaders($me))
            ->assertUnprocessable();

        $this->assertSame('open', $task->fresh()->status);
    }

    public function test_a_completed_task_cannot_be_moved_by_the_team(): void
    {
        $me = User::factory()->engineer()->create();
        $task = Task::factory()->assignedTo($me)->completed()->create();

        $this->patchJson("/api/v1/team/tasks/{$task->id}/status", [
            'status' => 'in_progress',
        ], $this->teamHeaders($me))
            ->assertUnprocessable();
    }

    public function test_a_member_cannot_move_someone_elses_task(): void
    {
        $me = User::factory()->engineer()->create();
        $other = User::factory()->marketer()->create();
        $task = Task::factory()->assignedTo($other)->inProgress()->create();

        $this->patchJson("/api/v1/team/tasks/{$task->id}/status", [
            'status' => 'completed',
        ], $this->teamHeaders($me))
            ->assertForbidden();

        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_soft_deleted_tasks_vanish_from_the_feed_and_reject_claims(): void
    {
        $me = User::factory()->engineer()->create();
        $pooled = Task::factory()->pooled()->create();
        $mine = Task::factory()->assignedTo($me)->inProgress()->create();
        $pooled->delete();
        $mine->delete();

        $response = $this->getJson('/api/v1/team/tasks', $this->teamHeaders($me))->assertOk();
        $this->assertSame([], $response->json('pool'));
        $this->assertSame([], $response->json('mine'));

        // Route-model binding excludes trashed rows — a stale card 404s.
        $this->postJson("/api/v1/team/tasks/{$pooled->id}/claim", [], $this->teamHeaders($me))
            ->assertNotFound();
    }

    public function test_the_feed_requires_a_workspace_token(): void
    {
        $this->getJson('/api/v1/team/tasks')->assertUnauthorized();
    }
}
