<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The one-off data migration that gives every ad-hoc-paid task (marked paid
 * before mark-paid wrote to the ledger) its Project collaboration payroll entry.
 * RefreshDatabase already ran it against an empty schema, so each test seeds the
 * shape it cares about and re-runs up() by hand — which also proves the
 * migration is idempotent.
 */
class BackfillAdHocTaskPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_09_26_000001_backfill_payroll_entries_for_ad_hoc_paid_tasks.php';

    private function migration(): object
    {
        return require database_path(self::MIGRATION);
    }

    public function test_it_records_a_settled_collaboration_entry_for_each_unlinked_paid_task(): void
    {
        $founder = User::factory()->founder()->create();
        $member = User::factory()->engineer()->create();
        $task = Task::factory()->assignedTo($member)->paid()->create([
            'created_by' => $founder->id,
            'pay_amount_myr' => 300,
            'paid_at' => Carbon::parse('2026-04-15 10:00:00'),
        ]);

        $this->migration()->up();

        $entry = $task->fresh()->payrollEntry;
        $this->assertNotNull($entry);
        $this->assertSame($member->id, $entry->user_id);
        $this->assertSame(PayrollEntry::KIND_ONE_TIME, $entry->kind);
        $this->assertSame('collaboration', $entry->one_time_type);
        $this->assertNull($entry->allowance_snapshot_myr);
        $this->assertSame(300, $entry->task_extras_myr);
        $this->assertSame(0, $entry->discretionary_myr);
        $this->assertSame(300, $entry->gross_myr);
        $this->assertSame('2026-04', $entry->period_label);            // the month it was paid
        $this->assertSame('2026-04-15 10:00:00', $entry->paid_at->toDateTimeString());
        $this->assertSame($founder->id, $entry->created_by);
        $this->assertStringContainsString("Task #{$task->id}", $entry->note);
        $this->assertFalse($entry->isLegacy());
    }

    public function test_it_leaves_every_other_task_alone(): void
    {
        $member = User::factory()->engineer()->create();
        $slip = PayrollEntry::factory()->settled()->create(['user_id' => $member->id]);

        $alreadyOnSlip = Task::factory()->assignedTo($member)->paid()->create(['payroll_entry_id' => $slip->id]);
        $stillPending = Task::factory()->assignedTo($member)->paymentPending()->create();
        $noPay = Task::factory()->assignedTo($member)->completed()->create(['pay_amount_myr' => null]);
        $unassigned = Task::factory()->pooled()->paid()->create();
        $deleted = Task::factory()->assignedTo($member)->paid()->create();
        $deleted->delete();

        $this->migration()->up();

        $this->assertSame(1, PayrollEntry::count()); // only the pre-existing slip
        $this->assertSame($slip->id, $alreadyOnSlip->fresh()->payroll_entry_id);
        $this->assertNull($stillPending->fresh()->payroll_entry_id);
        $this->assertNull($noPay->fresh()->payroll_entry_id);
        $this->assertNull($unassigned->fresh()->payroll_entry_id);
        $this->assertNull(Task::withTrashed()->find($deleted->id)->payroll_entry_id);
    }

    public function test_it_is_idempotent(): void
    {
        $member = User::factory()->engineer()->create();
        Task::factory()->assignedTo($member)->paid()->create();

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(1, PayrollEntry::count());
    }

    public function test_down_removes_only_the_rows_it_created(): void
    {
        $member = User::factory()->engineer()->create();
        $handRecorded = PayrollEntry::factory()->settled()->oneTime(500, 'collaboration')->create(['user_id' => $member->id]);
        $task = Task::factory()->assignedTo($member)->paid()->create();

        $migration = $this->migration();
        $migration->up();
        $this->assertNotNull($task->fresh()->payroll_entry_id);

        $migration->down();

        $this->assertNull($task->fresh()->payroll_entry_id);
        $this->assertSame('paid', $task->fresh()->status); // the task itself is untouched
        $this->assertSame(1, PayrollEntry::count());
        $this->assertNotNull(PayrollEntry::find($handRecorded->id));
    }
}
