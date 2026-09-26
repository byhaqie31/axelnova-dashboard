<?php

namespace Tests\Feature\Tasks;

use App\Models\PayrollEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The founder's cockpit surface for tasks: CRUD + filters + mark-paid. The
 * state-machine rules the admin owns are pinned here — assignment never moves
 * status, mark-paid only fires when a completed task actually owes a bonus and
 * records that payout in payroll as a Project collaboration one-off.
 * (Team-side transitions live in TeamTasksTest.)
 */
class AdminTasksTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(?User $founder = null): array
    {
        $founder ??= User::factory()->founder()->create();
        $token = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_the_founder_can_create_a_task(): void
    {
        $founder = User::factory()->founder()->create();
        $assignee = User::factory()->engineer()->create();

        $response = $this->postJson('/api/v1/admin/tasks', [
            'title' => 'Ship the landing page revision',
            'description' => 'Hero copy + new pricing band.',
            'assignee_id' => $assignee->id,
            'pay_amount_myr' => 250,
            'duration_estimate' => '3 days',
            'deadline' => '2026-07-20 18:00:00',
            'priority' => 'high',
        ], $this->adminHeaders($founder));

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Ship the landing page revision')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.assignee_id', $assignee->id)
            ->assertJsonPath('data.assignee_name', $assignee->name)
            ->assertJsonPath('data.pay_amount_myr', 250)
            ->assertJsonPath('data.payment_state', 'pending')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.created_by', $founder->id);
    }

    public function test_a_task_without_pay_reports_payment_state_none(): void
    {
        $founder = User::factory()->founder()->create();

        $this->postJson('/api/v1/admin/tasks', ['title' => 'Allowance-covered chore'], $this->adminHeaders($founder))
            ->assertCreated()
            ->assertJsonPath('data.pay_amount_myr', null)
            ->assertJsonPath('data.payment_state', 'none');
    }

    public function test_the_list_filters_by_status_priority_assignee_and_title(): void
    {
        $founder = User::factory()->founder()->create();
        $engineer = User::factory()->engineer()->create();

        Task::factory()->create(['title' => 'Fix login redirect', 'status' => 'open', 'priority' => 'high', 'created_by' => $founder->id]);
        Task::factory()->assignedTo($engineer)->inProgress()->create(['title' => 'Write blog draft', 'priority' => 'low', 'created_by' => $founder->id]);
        Task::factory()->completed()->create(['title' => 'Archive old assets', 'priority' => 'medium', 'created_by' => $founder->id]);

        $headers = $this->adminHeaders($founder);

        $this->getJson('/api/v1/admin/tasks?status=in_progress', $headers)
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Write blog draft');

        $this->getJson('/api/v1/admin/tasks?priority=high', $headers)
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Fix login redirect');

        $this->getJson("/api/v1/admin/tasks?assignee_id={$engineer->id}", $headers)
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.assignee_id', $engineer->id);

        $this->getJson('/api/v1/admin/tasks?assignee_id=unassigned', $headers)
            ->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/admin/tasks?q=blog', $headers)
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Write blog draft');
    }

    public function test_assigning_via_update_keeps_the_status_untouched(): void
    {
        $founder = User::factory()->founder()->create();
        $engineer = User::factory()->engineer()->create();
        $task = Task::factory()->pooled()->create(['created_by' => $founder->id]);

        $this->patchJson("/api/v1/admin/tasks/{$task->id}", [
            'assignee_id' => $engineer->id,
        ], $this->adminHeaders($founder))
            ->assertOk()
            ->assertJsonPath('data.assignee_id', $engineer->id)
            ->assertJsonPath('data.status', 'open'); // the team member starts it, not the assignment
    }

    public function test_update_edits_shape_fields_and_can_unassign(): void
    {
        $founder = User::factory()->founder()->create();
        $engineer = User::factory()->engineer()->create();
        $task = Task::factory()->assignedTo($engineer)->create(['created_by' => $founder->id, 'priority' => 'low']);

        $this->patchJson("/api/v1/admin/tasks/{$task->id}", [
            'title' => 'Retitled',
            'assignee_id' => null,
            'pay_amount_myr' => 90,
            'priority' => 'high',
        ], $this->adminHeaders($founder))
            ->assertOk()
            ->assertJsonPath('data.title', 'Retitled')
            ->assertJsonPath('data.assignee_id', null)
            ->assertJsonPath('data.pay_amount_myr', 90)
            ->assertJsonPath('data.priority', 'high');
    }

    public function test_editing_an_in_progress_task_is_locked(): void
    {
        // Once a teammate has STARTED the task, its shape is frozen — the admin
        // can't retitle, re-pay, or unassign it out from under them (422). The
        // recall path is Delete, not an edit.
        $founder = User::factory()->founder()->create();
        $engineer = User::factory()->engineer()->create();
        $task = Task::factory()->assignedTo($engineer)->inProgress()->create(['created_by' => $founder->id, 'title' => 'Original']);

        $this->patchJson("/api/v1/admin/tasks/{$task->id}", [
            'title' => 'Retitled',
            'assignee_id' => null,
        ], $this->adminHeaders($founder))
            ->assertUnprocessable();

        // Nothing moved — title, assignee and status are all untouched.
        $this->assertSame('Original', $task->fresh()->title);
        $this->assertSame($engineer->id, $task->fresh()->assignee_id);
        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_editing_a_completed_or_paid_task_is_locked(): void
    {
        // Completed/payment_pending/paid tasks are historical records — their
        // shape (incl. assignee) stays frozen; editing is a 422.
        $founder = User::factory()->founder()->create();
        $engineer = User::factory()->engineer()->create();
        $task = Task::factory()->assignedTo($engineer)->completed()->create(['created_by' => $founder->id, 'title' => 'Done work']);

        $this->patchJson("/api/v1/admin/tasks/{$task->id}", [
            'title' => 'Tampered',
            'assignee_id' => null,
        ], $this->adminHeaders($founder))
            ->assertUnprocessable();

        $this->assertSame('Done work', $task->fresh()->title);
        $this->assertSame($engineer->id, $task->fresh()->assignee_id);
        $this->assertSame('completed', $task->fresh()->status);
    }

    public function test_mark_paid_from_payment_pending_stamps_paid_at(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->engineer()->create();
        $task = Task::factory()->assignedTo($member)->paymentPending()->create(['created_by' => $founder->id]);

        $this->postJson("/api/v1/admin/tasks/{$task->id}/mark-paid", [], $this->adminHeaders($founder))
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.payment_state', 'paid');

        $this->assertNotNull($task->fresh()->paid_at);
    }

    public function test_mark_paid_accepts_the_completed_with_pay_edge(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->engineer()->create();
        // A bonus attached AFTER a no-pay completion leaves status 'completed'.
        $task = Task::factory()->assignedTo($member)->completed()->withPay(120)->create(['created_by' => $founder->id]);

        $this->postJson("/api/v1/admin/tasks/{$task->id}/mark-paid", [], $this->adminHeaders($founder))
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_mark_paid_rejects_a_task_that_owes_nothing(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->engineer()->create();
        $headers = $this->adminHeaders($founder);

        $openWithPay = Task::factory()->assignedTo($member)->withPay(100)->create(['created_by' => $founder->id]);
        $completedNoPay = Task::factory()->assignedTo($member)->completed()->create(['created_by' => $founder->id, 'pay_amount_myr' => null]);

        $this->postJson("/api/v1/admin/tasks/{$openWithPay->id}/mark-paid", [], $headers)
            ->assertUnprocessable();

        $this->postJson("/api/v1/admin/tasks/{$completedNoPay->id}/mark-paid", [], $headers)
            ->assertUnprocessable();
    }

    public function test_mark_paid_records_a_collaboration_payroll_entry_for_the_assignee(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->engineer()->create();
        $task = Task::factory()->assignedTo($member)->paymentPending()->create([
            'created_by' => $founder->id,
            'pay_amount_myr' => 250,
        ]);

        $response = $this->postJson("/api/v1/admin/tasks/{$task->id}/mark-paid", [
            'paid_at' => '2026-03-10',
            'method' => 'duitnow',
            'note' => 'Landing page sprint',
        ], $this->adminHeaders($founder))
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.payment_state', 'paid')
            ->assertJsonPath('data.payroll_period_label', '2026-03');

        // The payment lands in the ledger as a settled Project collaboration
        // one-off for the assignee — gross = the task's pay, kept as a task extra.
        $entry = PayrollEntry::find($response->json('data.payroll_entry_id'));
        $this->assertNotNull($entry);
        $this->assertSame($member->id, $entry->user_id);
        $this->assertSame(PayrollEntry::KIND_ONE_TIME, $entry->kind);
        $this->assertSame('collaboration', $entry->one_time_type);
        $this->assertNull($entry->allowance_snapshot_myr);
        $this->assertSame(250, $entry->task_extras_myr);
        $this->assertSame(0, $entry->discretionary_myr);
        $this->assertSame(250, $entry->gross_myr);
        $this->assertSame('2026-03', $entry->period_label);
        $this->assertSame('2026-03-10', $entry->paid_at->toDateString());
        $this->assertSame('duitnow', $entry->method);
        $this->assertSame('Landing page sprint', $entry->note);
        $this->assertSame($founder->id, $entry->created_by);
        $this->assertTrue($entry->isSettled());
        $this->assertFalse($entry->isLegacy());

        // The task rides the entry: linked, paid, stamped with the same date.
        $fresh = $task->fresh();
        $this->assertSame($entry->id, $fresh->payroll_entry_id);
        $this->assertSame('2026-03-10', $fresh->paid_at->toDateString());
    }

    public function test_mark_paid_defaults_to_paid_now_in_the_current_month(): void
    {
        Carbon::setTestNow('2026-07-20 09:00:00');
        $founder = User::factory()->founder()->create();
        $member = User::factory()->engineer()->create();
        $task = Task::factory()->assignedTo($member)->paymentPending()->create(['created_by' => $founder->id]);

        $this->postJson("/api/v1/admin/tasks/{$task->id}/mark-paid", [], $this->adminHeaders($founder))
            ->assertOk()
            ->assertJsonPath('data.payroll_period_label', '2026-07');

        $entry = $task->fresh()->payrollEntry;
        $this->assertSame('2026-07-20 09:00:00', $entry->paid_at->toDateTimeString());
        $this->assertNull($entry->method);
        $this->assertNull($entry->note);
        Carbon::setTestNow();
    }

    public function test_mark_paid_counts_in_the_members_payroll_and_their_own_payments(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->engineer()->create();
        $task = Task::factory()->assignedTo($member)->paymentPending()->create([
            'created_by' => $founder->id,
            'pay_amount_myr' => 400,
        ]);

        $this->postJson("/api/v1/admin/tasks/{$task->id}/mark-paid", ['paid_at' => '2026-05-02'], $this->adminHeaders($founder))
            ->assertOk();

        // Founder's payroll detail for the member: paid + extras totals include it,
        // and the entry lists the task it settles.
        $detail = $this->getJson("/api/v1/admin/payroll/user/{$member->id}", $this->adminHeaders($founder))->assertOk();
        $this->assertSame(400, $detail->json('summary_by_year.2026.paid_total_myr'));
        $this->assertSame(400, $detail->json('summary_by_year.2026.extras_total_myr'));
        $this->assertSame(0, $detail->json('summary_by_year.2026.discretionary_total_myr'));
        $this->assertSame('collaboration', $detail->json('entries.0.one_time_type'));
        $this->assertSame($task->title, $detail->json('entries.0.tasks.0.title'));

        // The roster's year-to-date paid total includes it.
        $roster = $this->getJson('/api/v1/admin/payroll/roster?period_label=2026-06', $this->adminHeaders($founder))->assertOk();
        $this->assertSame(400, $roster->json('summary.paid_this_year_myr'));

        // The member sees it on their own Payments page, and it is no longer owed.
        $this->app['auth']->forgetGuards(); // drop the cached founder before switching tokens
        $teamToken = $member->createToken('team-spa', ['workspace'])->plainTextToken;
        $own = $this->getJson('/api/v1/team/payslips', ['Authorization' => "Bearer {$teamToken}"])->assertOk();
        $this->assertSame('collaboration', $own->json('payslips.data.0.one_time_type'));
        $this->assertSame(400, $own->json('payslips.data.0.gross_myr'));
        $this->assertSame(0, $own->json('pending_extras.total_myr'));
    }

    public function test_mark_paid_refuses_an_unassigned_task(): void
    {
        $founder = User::factory()->founder()->create();
        // Pooled yet owed a bonus — there is nobody to record the payment against.
        $task = Task::factory()->pooled()->completed()->withPay(100)->create(['created_by' => $founder->id]);

        $this->postJson("/api/v1/admin/tasks/{$task->id}/mark-paid", [], $this->adminHeaders($founder))
            ->assertUnprocessable();

        $this->assertSame('completed', $task->fresh()->status);
        $this->assertNull($task->fresh()->paid_at);
        $this->assertSame(0, PayrollEntry::count());
    }

    public function test_mark_paid_validates_the_paid_date(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->engineer()->create();
        $task = Task::factory()->assignedTo($member)->paymentPending()->create(['created_by' => $founder->id]);

        $this->postJson("/api/v1/admin/tasks/{$task->id}/mark-paid", ['paid_at' => 'not-a-date'], $this->adminHeaders($founder))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['paid_at']);

        $this->assertSame('payment_pending', $task->fresh()->status);
        $this->assertSame(0, PayrollEntry::count());
    }

    public function test_mark_paid_rejects_a_task_already_on_a_payslip(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->marketer()->create();

        // The bonus is frozen into a payslip's gross — ad-hoc mark-paid on top
        // would pay it twice (once here, again when the slip settles).
        $slip = PayrollEntry::factory()->create([
            'user_id' => $member->id,
            'period_label' => '2026-07',
        ]);
        $task = Task::factory()->assignedTo($member)->paymentPending()->create([
            'created_by' => $founder->id,
            'pay_amount_myr' => 250,
            'payroll_entry_id' => $slip->id,
        ]);

        $response = $this->postJson("/api/v1/admin/tasks/{$task->id}/mark-paid", [], $this->adminHeaders($founder))
            ->assertUnprocessable();

        $this->assertStringContainsString('2026-07', $response->json('message'));
        $this->assertSame('payment_pending', $task->fresh()->status);
        $this->assertNull($task->fresh()->paid_at);
    }

    public function test_mark_paid_requires_a_cockpit_token(): void
    {
        $marketer = User::factory()->marketer()->create();
        $task = Task::factory()->paymentPending()->create();

        $teamToken = $marketer->createToken('team-spa', ['workspace'])->plainTextToken;

        $this->postJson("/api/v1/admin/tasks/{$task->id}/mark-paid", [], [
            'Authorization' => "Bearer {$teamToken}",
        ])->assertForbidden();
    }

    public function test_delete_soft_deletes_the_task(): void
    {
        $founder = User::factory()->founder()->create();
        $task = Task::factory()->create(['created_by' => $founder->id]);

        $this->deleteJson("/api/v1/admin/tasks/{$task->id}", [], $this->adminHeaders($founder))
            ->assertOk();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_create_validates_the_enum_and_length_bounds(): void
    {
        $founder = User::factory()->founder()->create();

        $this->postJson('/api/v1/admin/tasks', [
            'title' => str_repeat('x', 201),
            'priority' => 'urgent',
            'pay_amount_myr' => 0,
        ], $this->adminHeaders($founder))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'priority', 'pay_amount_myr']);
    }

    public function test_a_task_cannot_be_assigned_to_a_deactivated_teammate(): void
    {
        // Task 8 lockout — a deactivated account can't sign in to work the
        // task, so both create and reassign must reject it (422).
        $deactivated = User::factory()->engineer()->create(['deactivated_at' => now()]);

        $this->postJson('/api/v1/admin/tasks', [
            'title' => 'Assigned to a ghost',
            'assignee_id' => $deactivated->id,
        ], $this->adminHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assignee_id']);

        $task = Task::factory()->create();

        $this->patchJson("/api/v1/admin/tasks/{$task->id}", [
            'assignee_id' => $deactivated->id,
        ], $this->adminHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assignee_id']);

        $this->assertNull($task->fresh()->assignee_id);
    }
}
