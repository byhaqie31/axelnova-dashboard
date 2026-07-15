<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * One-time payroll entries — the founder records an ad-hoc payment (a signing /
 * festive / spot bonus, and/or a batch of pending task extras paid immediately)
 * outside the monthly cycle. These share the payroll_entries table with the
 * monthly run under `kind = one_time`, so history and year totals stay in one
 * place, but they are NOT period-guarded and never masquerade as the monthly slip.
 */
class OneTimePayrollTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(?User $founder = null): array
    {
        $founder ??= User::factory()->founder()->create();
        $token = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_records_a_settled_discretionary_bonus(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->engineer()->create(['monthly_allowance_myr' => 3000]);

        $this->postJson('/api/v1/admin/payroll/one-time', [
            'user_id' => $member->id,
            'one_time_type' => 'signing',
            'discretionary_myr' => 1500,
            'note' => 'Joining Q3',
            'mark_paid' => true,
            'paid_at' => '2026-03-10',
            'method' => 'duitnow',
        ], $this->adminHeaders($founder))
            ->assertCreated()
            ->assertJsonPath('data.kind', 'one_time')
            ->assertJsonPath('data.one_time_type', 'signing')
            ->assertJsonPath('data.allowance_snapshot_myr', null)
            ->assertJsonPath('data.task_extras_myr', 0)
            ->assertJsonPath('data.discretionary_myr', 1500)
            ->assertJsonPath('data.gross_myr', 1500)
            ->assertJsonPath('data.legacy', false)   // must NOT be misflagged legacy
            ->assertJsonPath('data.settled', true)
            ->assertJsonPath('data.method', 'duitnow')
            ->assertJsonPath('data.period_label', '2026-03'); // month of the paid date

        // The member's standing allowance is untouched — a bonus is not salary.
        $this->assertSame(3000, $member->fresh()->monthly_allowance_myr);
    }

    public function test_can_be_drafted_as_pending_then_settled(): void
    {
        Carbon::setTestNow('2026-07-20 09:00:00');
        $founder = User::factory()->founder()->create();
        $member = User::factory()->engineer()->create();

        $id = $this->postJson('/api/v1/admin/payroll/one-time', [
            'user_id' => $member->id,
            'one_time_type' => 'performance',
            'discretionary_myr' => 800,
            'mark_paid' => false,
        ], $this->adminHeaders($founder))
            ->assertCreated()
            ->assertJsonPath('data.settled', false)
            ->assertJsonPath('data.paid_at', null)
            ->assertJsonPath('data.period_label', '2026-07') // month of "now"
            ->json('data.id');

        // Settles through the normal payslip Settle action.
        $this->postJson("/api/v1/admin/payroll/{$id}/settle", ['method' => 'cash'], $this->adminHeaders($founder))
            ->assertOk()
            ->assertJsonPath('data.settled', true);

        $this->assertNotNull(PayrollEntry::find($id)->paid_at);
        Carbon::setTestNow();
    }

    public function test_can_sweep_pending_task_extras_and_settles_them_when_paid(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->marketer()->create(['monthly_allowance_myr' => 3000]);

        $extraA = Task::factory()->assignedTo($member)->paymentPending()->create(['pay_amount_myr' => 200]);
        $extraB = Task::factory()->assignedTo($member)->paymentPending()->create(['pay_amount_myr' => 50]);

        $id = $this->postJson('/api/v1/admin/payroll/one-time', [
            'user_id' => $member->id,
            'one_time_type' => 'spot',
            'discretionary_myr' => 100,
            'include_pending_tasks' => true,
            'mark_paid' => true,
        ], $this->adminHeaders($founder))
            ->assertCreated()
            ->assertJsonPath('data.task_extras_myr', 250)
            ->assertJsonPath('data.discretionary_myr', 100)
            ->assertJsonPath('data.gross_myr', 350) // 100 + 250
            ->json('data.id');

        // The extras are linked to THIS one-off and settled (paid now).
        $this->assertSame($id, $extraA->fresh()->payroll_entry_id);
        $this->assertSame($id, $extraB->fresh()->payroll_entry_id);
        $this->assertSame('paid', $extraA->fresh()->status);
        $this->assertNotNull($extraA->fresh()->paid_at);

        // A later monthly run for the same member won't re-grab them (no double-pay).
        $this->postJson('/api/v1/admin/payroll', [
            'user_id' => $member->id, 'period_label' => '2026-07',
        ], $this->adminHeaders($founder))
            ->assertCreated()
            ->assertJsonPath('data.task_extras_myr', 0)   // extras already claimed
            ->assertJsonPath('data.gross_myr', 3000);     // allowance only
    }

    public function test_pending_one_time_links_tasks_but_leaves_them_unpaid_until_settle(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->marketer()->create();
        $extra = Task::factory()->assignedTo($member)->paymentPending()->create(['pay_amount_myr' => 150]);

        $id = $this->postJson('/api/v1/admin/payroll/one-time', [
            'user_id' => $member->id,
            'one_time_type' => 'other',
            'include_pending_tasks' => true,
            'mark_paid' => false,
        ], $this->adminHeaders($founder))
            ->assertCreated()
            ->assertJsonPath('data.gross_myr', 150)
            ->json('data.id');

        // Linked but not yet paid.
        $this->assertSame($id, $extra->fresh()->payroll_entry_id);
        $this->assertSame('payment_pending', $extra->fresh()->status);

        // Settling the one-off flips the linked extra to paid.
        $this->postJson("/api/v1/admin/payroll/{$id}/settle", [], $this->adminHeaders($founder))->assertOk();
        $this->assertSame('paid', $extra->fresh()->status);
    }

    public function test_refuses_an_empty_record(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->engineer()->create();

        $this->postJson('/api/v1/admin/payroll/one-time', [
            'user_id' => $member->id,
            'one_time_type' => 'signing',
            'discretionary_myr' => 0,
        ], $this->adminHeaders($founder))
            ->assertStatus(422);

        $this->assertSame(0, PayrollEntry::count());
    }

    public function test_rejects_an_unknown_type(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->engineer()->create();

        $this->postJson('/api/v1/admin/payroll/one-time', [
            'user_id' => $member->id,
            'one_time_type' => 'thirteenth-month',
            'discretionary_myr' => 500,
        ], $this->adminHeaders($founder))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['one_time_type']);
    }

    public function test_does_not_claim_the_monthly_period(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->marketer()->create(['monthly_allowance_myr' => 2500]);
        $headers = $this->adminHeaders($founder);

        // Two one-offs in the same month — both allowed (no period guard).
        foreach (['signing', 'festive'] as $type) {
            $this->postJson('/api/v1/admin/payroll/one-time', [
                'user_id' => $member->id, 'one_time_type' => $type,
                'discretionary_myr' => 500, 'paid_at' => '2026-07-05',
            ], $headers)->assertCreated();
        }

        // The monthly slip for 2026-07 is still available — one-offs didn't take it.
        $this->getJson('/api/v1/admin/payroll/roster?period_label=2026-07', $headers)
            ->assertOk();
        $roster = $this->getJson('/api/v1/admin/payroll/roster?period_label=2026-07', $headers)->json('data');
        $row = collect($roster)->firstWhere('user_id', $member->id);
        $this->assertFalse($row['period_taken']); // monthly still open

        $this->getJson("/api/v1/admin/payroll/preview?user_id={$member->id}&period_label=2026-07", $headers)
            ->assertOk()
            ->assertJsonPath('period_taken', false);

        // And the monthly generation itself succeeds.
        $this->postJson('/api/v1/admin/payroll', ['user_id' => $member->id, 'period_label' => '2026-07'], $headers)
            ->assertCreated();

        $this->assertSame(3, PayrollEntry::where('user_id', $member->id)->count()); // 2 one-off + 1 monthly
    }

    public function test_is_allowed_for_a_deactivated_teammate(): void
    {
        // Unlike the monthly run, a one-off is allowed for a deactivated teammate —
        // final / severance payouts are exactly this.
        $founder = User::factory()->founder()->create();
        $member = User::factory()->engineer()->create(['deactivated_at' => now()]);

        $this->postJson('/api/v1/admin/payroll/one-time', [
            'user_id' => $member->id,
            'one_time_type' => 'other',
            'discretionary_myr' => 2000,
            'note' => 'Final settlement',
        ], $this->adminHeaders($founder))
            ->assertCreated()
            ->assertJsonPath('data.gross_myr', 2000);
    }

    public function test_one_offs_count_in_year_totals_with_a_discretionary_line(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->marketer()->create();
        // A paid monthly slip + a paid one-off, both in 2026.
        PayrollEntry::factory()->settled()->create([
            'user_id' => $member->id, 'period_label' => '2026-06',
            'allowance_snapshot_myr' => 2000, 'task_extras_myr' => 0, 'gross_myr' => 2000,
        ]);
        PayrollEntry::factory()->settled()->oneTime(1500)->create([
            'user_id' => $member->id, 'period_label' => '2026-06',
        ]);

        $res = $this->getJson("/api/v1/admin/payroll/user/{$member->id}", $this->adminHeaders($founder))->assertOk();

        $this->assertSame(3500, $res->json('summary_by_year.2026.gross_total_myr')); // 2000 + 1500
        $this->assertSame(3500, $res->json('summary_by_year.2026.paid_total_myr'));
        $this->assertSame(1500, $res->json('summary_by_year.2026.discretionary_total_myr'));
        $this->assertSame(2000, $res->json('summary_by_year.2026.allowance_total_myr'));
    }

    public function test_a_member_sees_their_own_one_off(): void
    {
        $member = User::factory()->engineer()->create();
        PayrollEntry::factory()->settled()->oneTime(900, 'festive')->create(['user_id' => $member->id]);

        $token = $member->createToken('team-spa', ['workspace'])->plainTextToken;

        $res = $this->getJson('/api/v1/team/payslips', ['Authorization' => "Bearer {$token}"])->assertOk();
        $this->assertSame('one_time', $res->json('payslips.data.0.kind'));
        $this->assertSame('festive', $res->json('payslips.data.0.one_time_type'));
        $this->assertSame(900, $res->json('payslips.data.0.gross_myr'));
    }

    public function test_one_time_endpoint_rejects_a_workspace_token(): void
    {
        $member = User::factory()->marketer()->create();
        $token = $member->createToken('team-spa', ['workspace'])->plainTextToken;

        $this->postJson('/api/v1/admin/payroll/one-time', [
            'user_id' => $member->id, 'one_time_type' => 'signing', 'discretionary_myr' => 500,
        ], ['Authorization' => "Bearer {$token}"])->assertForbidden();
    }
}
