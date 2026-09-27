<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\PayrollEntry;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Tasks — the founder's cockpit surface (Task 5). Author, assign (or leave in the
 * pool), track the lifecycle, and mark the extra-pay bonus paid. Founder-only via
 * the /v1/admin route group (role:cockpit). The workflow state machine is enforced
 * here and in the mirror Team\TasksController — the model just stores the enum.
 *
 *   open ─► in_progress ─► completed            (no bonus)
 *                       └► payment_pending ─► paid   (bonus attached)
 *
 * Assignment never changes status: assigning a pooled task keeps it 'open' so the
 * team member is the one who starts it. The one exception runs the other way —
 * unassigning an in_progress task drops it back to 'open' (see `update()`), the
 * admin-side mirror of the team's own release edge. Only `mark-paid` writes
 * 'paid' + paid_at, and only from payment_pending (or the completed-with-bonus
 * edge) — and it records the payout in payroll as a Project collaboration
 * one-off for the assignee, so an ad-hoc task payment never bypasses the ledger.
 */
class TasksController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'completed', 'payment_pending', 'paid'])],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high'])],
        ]);

        $query = Task::with(['creator', 'assignee', 'payrollEntry'])->latest()->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority'));
        }
        // assignee_id=0 (or the literal 'unassigned') filters the pick-up pool.
        if ($request->filled('assignee_id')) {
            $assignee = $request->string('assignee_id')->toString();
            if ($assignee === '0' || $assignee === 'unassigned') {
                $query->whereNull('assignee_id');
            } else {
                $query->where('assignee_id', (int) $assignee);
            }
        }
        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.$request->string('q').'%');
        }

        return TaskResource::collection($query->paginate(20));
    }

    public function store(Request $request): TaskResource
    {
        $data = $this->validatePayload($request, creating: true);

        $task = Task::create([
            ...$data,
            'created_by' => $request->user()->id,
            'status' => 'open',
        ]);

        return new TaskResource($task->load(['creator', 'assignee', 'payrollEntry']));
    }

    public function show(Task $task): TaskResource
    {
        return new TaskResource($task->load(['creator', 'assignee', 'payrollEntry']));
    }

    /**
     * Edit the task's shape — title/description/assignee/pay/duration/deadline/
     * priority. Deliberately NOT status: assignment keeps the current status (the
     * team member starts it), and the lifecycle only advances through the team
     * transitions + mark-paid.
     *
     * Editable only while the task is still `open` (pooled, or assigned but not
     * yet started). The moment a teammate STARTS it — `in_progress`, and every
     * state after (completed, payment_pending, paid) — the shape is frozen (422)
     * so the admin can't change scope/pay/assignment out from under the person
     * doing (or who already did) the work. The recall path for an in-progress
     * task is Delete, not an edit.
     */
    public function update(Request $request, Task $task): JsonResponse|TaskResource
    {
        if ($task->status !== 'open') {
            return response()->json([
                'message' => 'This task is in progress (or beyond) — its details are locked. Delete it to pull it back.',
            ], 422);
        }

        $data = $this->validatePayload($request, creating: false);

        $task->update($data);

        return new TaskResource($task->fresh()->load(['creator', 'assignee', 'payrollEntry']));
    }

    /**
     * Release the extra-pay bonus AND record it in payroll. Only valid once the
     * work is done and money is owed: from payment_pending (the normal path) or
     * the completed-with-bonus edge (a bonus added after a no-pay completion).
     *
     * The payout is written to the ledger as a SETTLED one-time payroll entry of
     * type `collaboration` (Project collaboration) for the assignee — gross = the
     * task's pay, kept as a task extra, discretionary 0 — so an ad-hoc task
     * payment counts in the roster's year-to-date total, the member's payroll
     * detail and their own Payments page exactly like a payslip-settled one.
     * Optional body: `paid_at` (default now; also the entry's period month),
     * `method`, `note`. The task is linked to the entry and flipped to paid in
     * the same transaction.
     *
     * A task already LINKED to a payslip is off-limits here (422): its bonus is
     * frozen into that slip's gross, so settling the payslip is the only payout
     * path — ad-hoc mark-paid on top would double-pay the extra (Task 7 guard).
     * An unassigned task is refused too (422): there is nobody to pay.
     */
    public function markPaid(Request $request, Task $task): JsonResponse|TaskResource
    {
        $data = $request->validate([
            'paid_at' => ['nullable', 'date'],
            'method' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($task->payroll_entry_id !== null) {
            $period = $task->payrollEntry?->period_label ?? 'a payslip';

            return response()->json([
                'message' => "This task's bonus is on payslip {$period} — settle the payslip instead.",
            ], 422);
        }

        $owesPayment = (int) $task->pay_amount_myr >= 1
            && ($task->status === 'payment_pending' || $task->status === 'completed');

        if (! $owesPayment) {
            return response()->json([
                'message' => 'Only a completed task with an unpaid bonus can be marked paid.',
            ], 422);
        }

        if ($task->assignee_id === null) {
            return response()->json([
                'message' => 'Assign the task to a teammate first — the payment is recorded to their payroll.',
            ], 422);
        }

        $paidAt = isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now();

        $entry = DB::transaction(function () use ($task, $data, $paidAt, $request) {
            // The checks above cover the common cases with a friendly message, but
            // the actual write is re-guarded as a single conditional UPDATE so two
            // concurrent mark-paid calls (or one racing a payslip generation that
            // links payroll_entry_id) can't both win — only the first commits; a
            // loser affects 0 rows and gets a 422 instead of silently double-paying.
            $affected = Task::whereKey($task->id)
                ->whereNull('payroll_entry_id')
                ->whereNotNull('pay_amount_myr')
                ->whereIn('status', ['payment_pending', 'completed'])
                ->update([
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                    'completed_at' => $task->completed_at ?? $paidAt,
                ]);

            if ($affected === 0) {
                return null;
            }

            // period_label = the payment's month, so year-to-date rollups bucket
            // it correctly; the UI labels it by one_time_type, not this.
            $entry = PayrollEntry::create([
                'user_id' => $task->assignee_id,
                'kind' => PayrollEntry::KIND_ONE_TIME,
                'period_label' => $paidAt->format('Y-m'),
                'one_time_type' => PayrollEntry::TYPE_COLLABORATION,
                'allowance_snapshot_myr' => null,
                'task_extras_myr' => (int) $task->pay_amount_myr,
                'discretionary_myr' => 0,
                'gross_myr' => (int) $task->pay_amount_myr,
                'paid_at' => $paidAt,
                'method' => $data['method'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            // Link the task to the entry that settles it (the per-task guard the
            // payslip paths rely on: a linked task is never swept up again).
            Task::whereKey($task->id)->update(['payroll_entry_id' => $entry->id]);

            return $entry;
        });

        if ($entry === null) {
            return response()->json([
                'message' => 'This task changed state just now — refresh and try again.',
            ], 422);
        }

        return new TaskResource($task->fresh()->load(['creator', 'assignee', 'payrollEntry']));
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Shared create/update validation. On create, title is required and priority
     * defaults to medium; on update every field is `sometimes` so a PATCH can
     * touch one field. `assignee_id` accepts null to unassign (back to the pool).
     */
    private function validatePayload(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'title' => [$required, 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Active accounts only — a deactivated teammate (Task 8 lockout)
            // can't sign in to work the task, so assigning to them is a mistake.
            'assignee_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deactivated_at'),
            ],
            'pay_amount_myr' => ['nullable', 'integer', 'min:1'],
            'duration_estimate' => ['nullable', 'string', 'max:60'],
            'deadline' => ['nullable', 'date'],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'assignee_id.exists' => 'That teammate is deactivated (or doesn\'t exist) — reactivate them on the Users page first.',
        ]);
    }
}
