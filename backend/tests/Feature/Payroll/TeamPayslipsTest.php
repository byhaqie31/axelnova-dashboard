<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The workspace "Payslips" surface (Task 7). Every internal role reads ONLY their
 * own payslips (with the allowance/extras/gross breakdown) plus a separate
 * `pending_extras` block — completed-with-pay tasks not yet on any slip, so the
 * member sees money owed a future payslip will settle.
 */
class TeamPayslipsTest extends TestCase
{
    use RefreshDatabase;

    private function teamHeaders(User $user): array
    {
        $token = $user->createToken('team-spa', ['workspace'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_a_member_sees_only_their_own_payslips_with_breakdown(): void
    {
        $me = User::factory()->engineer()->create(['monthly_allowance_myr' => 4000]);
        $someoneElse = User::factory()->marketer()->create();

        $mine = PayrollEntry::factory()->create([
            'user_id' => $me->id,
            'period_label' => '2026-07',
            'allowance_snapshot_myr' => 4000,
            'task_extras_myr' => 150,
            'gross_myr' => 4150,
        ]);
        PayrollEntry::factory()->create(['user_id' => $someoneElse->id]);

        $response = $this->getJson('/api/v1/team/payslips', $this->teamHeaders($me))->assertOk();

        $rows = $response->json('payslips.data');
        $this->assertCount(1, $rows);
        $this->assertSame($mine->id, $rows[0]['id']);
        $this->assertSame(4000, $rows[0]['allowance_snapshot_myr']);
        $this->assertSame(150, $rows[0]['task_extras_myr']);
        $this->assertSame(4150, $rows[0]['gross_myr']);
    }

    public function test_pending_extras_lists_unlinked_completed_with_pay_tasks(): void
    {
        $me = User::factory()->engineer()->create();
        $other = User::factory()->marketer()->create();

        Task::factory()->assignedTo($me)->paymentPending()->create(['pay_amount_myr' => 250, 'title' => 'Onboarding sequence']);
        Task::factory()->assignedTo($me)->paymentPending()->create(['pay_amount_myr' => 80, 'title' => 'Alt-text audit']);
        // Excluded: in-progress (not owed), already linked, and someone else's.
        Task::factory()->assignedTo($me)->inProgress()->withPay(500)->create();
        $slip = PayrollEntry::factory()->create(['user_id' => $me->id]);
        Task::factory()->assignedTo($me)->paymentPending()->create(['pay_amount_myr' => 999, 'payroll_entry_id' => $slip->id]);
        Task::factory()->assignedTo($other)->paymentPending()->create(['pay_amount_myr' => 300]);

        $response = $this->getJson('/api/v1/team/payslips', $this->teamHeaders($me))->assertOk();

        $this->assertCount(2, $response->json('pending_extras.tasks'));
        $this->assertSame(330, $response->json('pending_extras.total_myr'));
    }

    public function test_team_payslips_requires_a_workspace_token(): void
    {
        $this->getJson('/api/v1/team/payslips')->assertUnauthorized();
    }
}
