<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The founder's cockpit over payroll (Task 7): generate a payslip (allowance
 * snapshot + Σ pending task extras, linking those tasks), preview the dry-run,
 * and settle (stamp paid_at + flip the linked extras to paid). The two guards are
 * pinned here — one payslip per period, and generation only ever picks up
 * unlinked payment_pending tasks so extras never double-count.
 */
class AdminPayrollTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(?User $founder = null): array
    {
        $founder ??= User::factory()->founder()->create();
        $token = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_generation_snapshots_allowance_sums_and_links_extras(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->marketer()->create(['monthly_allowance_myr' => 3000]);

        // Two eligible extras (payment_pending + unlinked).
        $extraA = Task::factory()->assignedTo($member)->paymentPending()->create(['pay_amount_myr' => 250]);
        $extraB = Task::factory()->assignedTo($member)->paymentPending()->create(['pay_amount_myr' => 100]);

        // Ignored: in-progress-with-pay, ad-hoc-already-paid, already-linked to another slip.
        $inProgress = Task::factory()->assignedTo($member)->inProgress()->withPay(500)->create();
        $adHocPaid = Task::factory()->assignedTo($member)->paid()->create();
        $otherSlip = PayrollEntry::factory()->create(['user_id' => $member->id]);
        $alreadyLinked = Task::factory()->assignedTo($member)->paymentPending()->create([
            'pay_amount_myr' => 999,
            'payroll_entry_id' => $otherSlip->id,
        ]);

        $response = $this->postJson('/api/v1/admin/payroll', [
            'user_id' => $member->id,
            'period_label' => '2026-07',
        ], $this->adminHeaders($founder));

        $response->assertCreated()
            ->assertJsonPath('data.allowance_snapshot_myr', 3000)
            ->assertJsonPath('data.task_extras_myr', 350)
            ->assertJsonPath('data.gross_myr', 3350)
            ->assertJsonPath('data.legacy', false)
            ->assertJsonPath('data.settled', false);

        $entryId = $response->json('data.id');

        // The two eligible extras are now linked; nothing else moved.
        $this->assertSame($entryId, $extraA->fresh()->payroll_entry_id);
        $this->assertSame($entryId, $extraB->fresh()->payroll_entry_id);
        $this->assertNull($inProgress->fresh()->payroll_entry_id);
        $this->assertNull($adHocPaid->fresh()->payroll_entry_id);
        $this->assertSame($otherSlip->id, $alreadyLinked->fresh()->payroll_entry_id);
        // Linking does not settle — extras stay payment_pending until the slip settles.
        $this->assertSame('payment_pending', $extraA->fresh()->status);
    }

    public function test_generation_for_a_null_allowance_member_keeps_the_snapshot_null(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->engineer()->create(['monthly_allowance_myr' => null]);
        Task::factory()->assignedTo($member)->paymentPending()->create(['pay_amount_myr' => 200]);

        $this->postJson('/api/v1/admin/payroll', [
            'user_id' => $member->id,
            'period_label' => '2026-07',
        ], $this->adminHeaders($founder))
            ->assertCreated()
            ->assertJsonPath('data.allowance_snapshot_myr', null)
            ->assertJsonPath('data.task_extras_myr', 200)
            ->assertJsonPath('data.gross_myr', 200);
    }

    public function test_generation_rejects_a_duplicate_period(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->marketer()->create(['monthly_allowance_myr' => 3000]);
        $headers = $this->adminHeaders($founder);

        $this->postJson('/api/v1/admin/payroll', ['user_id' => $member->id, 'period_label' => '2026-07'], $headers)
            ->assertCreated();

        $this->postJson('/api/v1/admin/payroll', ['user_id' => $member->id, 'period_label' => '2026-07'], $headers)
            ->assertStatus(422);

        $this->assertSame(1, PayrollEntry::where('user_id', $member->id)->count());
    }

    public function test_generation_refuses_an_empty_slip(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->engineer()->create(['monthly_allowance_myr' => null]);

        $this->postJson('/api/v1/admin/payroll', [
            'user_id' => $member->id,
            'period_label' => '2026-07',
        ], $this->adminHeaders($founder))
            ->assertStatus(422);

        $this->assertSame(0, PayrollEntry::count());
    }

    public function test_generation_and_preview_refuse_a_deactivated_teammate(): void
    {
        // Task 8 lockout — no new payslips for a deactivated account. Preview
        // mirrors store so the UI never green-lights a doomed generation.
        $founder = User::factory()->founder()->create();
        $member = User::factory()->engineer()->create([
            'monthly_allowance_myr' => 3000,
            'deactivated_at' => now(),
        ]);
        $headers = $this->adminHeaders($founder);

        $this->postJson('/api/v1/admin/payroll', [
            'user_id' => $member->id,
            'period_label' => '2026-07',
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);

        $this->getJson("/api/v1/admin/payroll/preview?user_id={$member->id}", $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);

        $this->assertSame(0, PayrollEntry::count());
    }

    public function test_preview_reports_allowance_and_pending_extras(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->marketer()->create(['monthly_allowance_myr' => 3000]);
        Task::factory()->assignedTo($member)->paymentPending()->create(['pay_amount_myr' => 250]);
        Task::factory()->assignedTo($member)->paymentPending()->create(['pay_amount_myr' => 100]);
        // An in-progress bonus is not yet owed — excluded from the preview.
        Task::factory()->assignedTo($member)->inProgress()->withPay(500)->create();

        $this->getJson("/api/v1/admin/payroll/preview?user_id={$member->id}", $this->adminHeaders($founder))
            ->assertOk()
            ->assertJsonPath('monthly_allowance_myr', 3000)
            ->assertJsonPath('pending_extras_count', 2)
            ->assertJsonPath('pending_extras_myr', 350)
            ->assertJsonPath('projected_gross_myr', 3350)
            ->assertJsonPath('period_taken', null); // no period sent
    }

    public function test_preview_flags_an_already_taken_period(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->marketer()->create(['monthly_allowance_myr' => 3000]);
        PayrollEntry::factory()->create(['user_id' => $member->id, 'period_label' => '2026-07']);
        $headers = $this->adminHeaders($founder);

        $this->getJson("/api/v1/admin/payroll/preview?user_id={$member->id}&period_label=2026-07", $headers)
            ->assertOk()
            ->assertJsonPath('period_taken', true);

        $this->getJson("/api/v1/admin/payroll/preview?user_id={$member->id}&period_label=2026-08", $headers)
            ->assertOk()
            ->assertJsonPath('period_taken', false);
    }

    public function test_roster_lists_each_teammate_with_allowance_extras_and_period_status(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->marketer()->create(['name' => 'Aisyah', 'monthly_allowance_myr' => 2500]);
        Task::factory()->assignedTo($member)->paymentPending()->create(['pay_amount_myr' => 200]);
        Task::factory()->assignedTo($member)->paymentPending()->create(['pay_amount_myr' => 50]);
        // An in-progress bonus isn't owed yet — excluded from the extras sum.
        Task::factory()->assignedTo($member)->inProgress()->withPay(999)->create();
        PayrollEntry::factory()->create(['user_id' => $member->id, 'period_label' => '2026-07']);

        $res = $this->getJson('/api/v1/admin/payroll/roster?period_label=2026-07', $this->adminHeaders($founder))
            ->assertOk()
            ->assertJsonPath('period_label', '2026-07');

        $rows = collect($res->json('data'));
        $row = $rows->firstWhere('user_id', $member->id);
        $this->assertSame(2500, $row['monthly_allowance_myr']);
        $this->assertSame(2, $row['pending_extras_count']);
        $this->assertSame(250, $row['pending_extras_myr']);
        $this->assertSame(2750, $row['projected_gross_myr']);
        $this->assertTrue($row['period_taken']);
        $this->assertFalse($row['deactivated']);
        // The founder is on the roster too (own allowance/extras, here none).
        $this->assertNotNull($rows->firstWhere('user_id', $founder->id));
    }

    public function test_roster_tags_a_deactivated_teammate_and_orders_them_last(): void
    {
        $founder = User::factory()->founder()->create();
        $active = User::factory()->marketer()->create(['name' => 'Aaa Active']);
        $gone = User::factory()->marketer()->create(['name' => 'Bbb Gone', 'deactivated_at' => now()]);

        $rows = collect($this->getJson('/api/v1/admin/payroll/roster', $this->adminHeaders($founder))
            ->assertOk()->json('data'));

        $goneRow = $rows->firstWhere('user_id', $gone->id);
        $this->assertTrue($goneRow['deactivated']);
        $this->assertNull($goneRow['period_taken']); // no period sent → null

        $ids = $rows->pluck('user_id')->all();
        $this->assertGreaterThan(array_search($active->id, $ids), array_search($gone->id, $ids));
    }

    public function test_roster_returns_a_dashboard_summary(): void
    {
        $founder = User::factory()->founder()->create();
        $a = User::factory()->marketer()->create(['monthly_allowance_myr' => 2000]);
        $b = User::factory()->engineer()->create(['monthly_allowance_myr' => 1500]);
        // `a` already has this period's slip; two paid slips this year. Pin
        // created_by so the factory doesn't spawn phantom creator users (which
        // would inflate the headcount).
        PayrollEntry::factory()->create(['user_id' => $a->id, 'created_by' => $founder->id, 'period_label' => '2026-07', 'gross_myr' => 2000, 'paid_at' => now()]);
        PayrollEntry::factory()->create(['user_id' => $a->id, 'created_by' => $founder->id, 'period_label' => '2026-06', 'gross_myr' => 2000, 'paid_at' => now()]);

        $summary = $this->getJson('/api/v1/admin/payroll/roster?period_label=2026-07', $this->adminHeaders($founder))
            ->assertOk()->json('summary');

        $this->assertSame(3500, $summary['projected_total_myr']); // 2000 + 1500 (founder 0)
        $this->assertSame(1, $summary['generated_count']);        // a is taken
        $this->assertSame(1, $summary['pending_count']);          // b eligible; founder has nothing to pay
        $this->assertSame(4000, $summary['paid_this_year_myr']);  // two paid 2000 slips in 2026
        $this->assertSame(2026, $summary['year']);
        $this->assertSame(3, $summary['headcount']);
    }

    public function test_user_detail_returns_history_and_per_year_aggregates(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->marketer()->create();
        PayrollEntry::factory()->create(['user_id' => $member->id, 'period_label' => '2026-07', 'allowance_snapshot_myr' => 2000, 'task_extras_myr' => 250, 'gross_myr' => 2250, 'paid_at' => now()]);
        PayrollEntry::factory()->create(['user_id' => $member->id, 'period_label' => '2026-06', 'allowance_snapshot_myr' => 2000, 'task_extras_myr' => 0, 'gross_myr' => 2000, 'paid_at' => null]);
        PayrollEntry::factory()->create(['user_id' => $member->id, 'period_label' => '2025-12', 'allowance_snapshot_myr' => 1800, 'task_extras_myr' => 0, 'gross_myr' => 1800, 'paid_at' => now()]);

        $res = $this->getJson("/api/v1/admin/payroll/user/{$member->id}", $this->adminHeaders($founder))->assertOk();

        $res->assertJsonPath('user.id', $member->id);
        $this->assertSame([2026, 2025], $res->json('years'));
        $this->assertSame(4250, $res->json('summary_by_year.2026.gross_total_myr'));
        $this->assertSame(2250, $res->json('summary_by_year.2026.paid_total_myr'));
        $this->assertSame(2000, $res->json('summary_by_year.2026.pending_total_myr'));
        $this->assertSame(250, $res->json('summary_by_year.2026.extras_total_myr'));
        $this->assertSame(1800, $res->json('summary_by_year.2025.gross_total_myr'));
        $this->assertCount(3, $res->json('entries'));
        $this->assertSame('2026-07', $res->json('entries.0.period_label')); // newest first
    }

    public function test_settle_stamps_paid_at_and_flips_linked_tasks(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->marketer()->create(['monthly_allowance_myr' => 3000]);
        $extra = Task::factory()->assignedTo($member)->paymentPending()->create(['pay_amount_myr' => 250]);

        $entryId = $this->postJson('/api/v1/admin/payroll', [
            'user_id' => $member->id,
            'period_label' => '2026-07',
        ], $this->adminHeaders($founder))->json('data.id');

        $this->postJson("/api/v1/admin/payroll/{$entryId}/settle", [
            'method' => 'duitnow',
        ], $this->adminHeaders($founder))
            ->assertOk()
            ->assertJsonPath('data.settled', true)
            ->assertJsonPath('data.method', 'duitnow');

        $this->assertNotNull(PayrollEntry::find($entryId)->paid_at);
        $this->assertSame('paid', $extra->fresh()->status);
        $this->assertNotNull($extra->fresh()->paid_at);
    }

    public function test_settle_is_idempotent_guarded(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->marketer()->create(['monthly_allowance_myr' => 3000]);
        $entry = PayrollEntry::factory()->settled()->create(['user_id' => $member->id]);

        $this->postJson("/api/v1/admin/payroll/{$entry->id}/settle", [], $this->adminHeaders($founder))
            ->assertStatus(422);
    }

    public function test_settle_does_not_touch_unlinked_tasks(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->marketer()->create(['monthly_allowance_myr' => 3000]);

        $linked = Task::factory()->assignedTo($member)->paymentPending()->create(['pay_amount_myr' => 250]);
        $entryId = $this->postJson('/api/v1/admin/payroll', [
            'user_id' => $member->id,
            'period_label' => '2026-07',
        ], $this->adminHeaders($founder))->json('data.id');

        // A separate pending extra that never made it onto the slip.
        $unlinked = Task::factory()->assignedTo($member)->paymentPending()->create(['pay_amount_myr' => 80]);

        $this->postJson("/api/v1/admin/payroll/{$entryId}/settle", [], $this->adminHeaders($founder))
            ->assertOk();

        $this->assertSame('paid', $linked->fresh()->status);
        $this->assertSame('payment_pending', $unlinked->fresh()->status);
        $this->assertNull($unlinked->fresh()->payroll_entry_id);
    }

    public function test_the_legacy_flag_marks_pre_task_7_rows(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->marketer()->create(['monthly_allowance_myr' => 3000]);

        $legacy = PayrollEntry::factory()->legacy(3500)->create(['user_id' => $member->id, 'period_label' => 'Jun 2026']);
        $modern = PayrollEntry::factory()->create(['user_id' => $member->id, 'period_label' => '2026-07']);

        $response = $this->getJson('/api/v1/admin/payroll', $this->adminHeaders($founder))->assertOk();

        $rows = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($rows[$legacy->id]['legacy']);
        $this->assertSame(3500, $rows[$legacy->id]['gross_myr']);
        $this->assertFalse($rows[$modern->id]['legacy']);
    }

    public function test_admin_payroll_endpoints_reject_a_workspace_token(): void
    {
        $marketer = User::factory()->marketer()->create();
        $token = $marketer->createToken('team-spa', ['workspace'])->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}"];
        $entry = PayrollEntry::factory()->create(['user_id' => $marketer->id]);

        $this->getJson('/api/v1/admin/payroll', $headers)->assertForbidden();
        $this->getJson('/api/v1/admin/payroll/roster', $headers)->assertForbidden();
        $this->getJson("/api/v1/admin/payroll/user/{$marketer->id}", $headers)->assertForbidden();
        $this->getJson("/api/v1/admin/payroll/preview?user_id={$marketer->id}", $headers)->assertForbidden();
        $this->postJson('/api/v1/admin/payroll', ['user_id' => $marketer->id, 'period_label' => '2026-07'], $headers)->assertForbidden();
        $this->postJson("/api/v1/admin/payroll/{$entry->id}/settle", [], $headers)->assertForbidden();
    }
}
