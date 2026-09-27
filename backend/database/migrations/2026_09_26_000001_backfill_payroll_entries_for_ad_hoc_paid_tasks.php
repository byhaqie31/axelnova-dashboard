<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data backfill — give every task that was marked paid AD HOC (before
 * Admin\TasksController::markPaid wrote to the ledger) its payroll entry, so the
 * money it paid out counts in payroll like every other task payment.
 *
 * Selects tasks that are `paid`, carry a pay amount, have an assignee, are not
 * soft-deleted, and are NOT yet linked to any payroll entry (a linked task was
 * settled through a payslip and is already counted). For each one it inserts a
 * SETTLED one-time entry of type `collaboration` (Project collaboration):
 * gross = the task's pay as a task extra, discretionary 0, paid_at = the task's
 * own paid date (falling back to completed_at, then updated_at), period_label =
 * that date's YYYY-MM so it lands in the right year's totals — and links the
 * task to it. Nothing existing is edited or deleted.
 *
 * Idempotent: a second run finds no unlinked paid tasks and inserts nothing.
 * down() removes only the rows this migration created (tagged by NOTE_PREFIX)
 * and clears their task links; the tasks themselves stay `paid`.
 *
 * Deliberately query-builder only (no Eloquent models) so this migration stays
 * frozen as the app code evolves.
 */
return new class extends Migration
{
    private const NOTE_PREFIX = '[backfill] Task #';

    public function up(): void
    {
        $tasks = DB::table('tasks')
            ->whereNull('deleted_at')
            ->where('status', 'paid')
            ->whereNull('payroll_entry_id')
            ->whereNotNull('assignee_id')
            ->where('pay_amount_myr', '>=', 1)
            ->orderBy('id')
            ->get();

        foreach ($tasks as $task) {
            DB::transaction(function () use ($task) {
                $paidAt = Carbon::parse($task->paid_at ?? $task->completed_at ?? $task->updated_at ?? now());
                $now = now();

                $entryId = DB::table('payroll_entries')->insertGetId([
                    'user_id' => $task->assignee_id,
                    'kind' => 'one_time',
                    'period_label' => $paidAt->format('Y-m'),
                    'one_time_type' => 'collaboration',
                    'allowance_snapshot_myr' => null,
                    'task_extras_myr' => (int) $task->pay_amount_myr,
                    'discretionary_myr' => 0,
                    'gross_myr' => (int) $task->pay_amount_myr,
                    'paid_at' => $paidAt,
                    'method' => null,
                    'note' => self::NOTE_PREFIX.$task->id.' — paid ad hoc before payroll recording',
                    'created_by' => $task->created_by,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Re-guarded on the link being still null so a concurrent settle
                // (or a re-run racing this one) can never double-link.
                $linked = DB::table('tasks')
                    ->where('id', $task->id)
                    ->whereNull('payroll_entry_id')
                    ->update(['payroll_entry_id' => $entryId]);

                if ($linked === 0) {
                    DB::table('payroll_entries')->where('id', $entryId)->delete();
                }
            });
        }
    }

    public function down(): void
    {
        $ids = DB::table('payroll_entries')
            ->where('kind', 'one_time')
            ->where('one_time_type', 'collaboration')
            ->where('note', 'like', self::NOTE_PREFIX.'%')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('tasks')->whereIn('payroll_entry_id', $ids)->update(['payroll_entry_id' => null]);
        DB::table('payroll_entries')->whereIn('id', $ids)->delete();
    }
};
