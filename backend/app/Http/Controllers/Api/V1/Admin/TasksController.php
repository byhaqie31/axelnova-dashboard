<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
 * 'paid' + paid_at, and only from payment_pending (or the completed-with-bonus edge).
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
     * The one exception is unassigning: clearing `assignee_id` on an in_progress
     * task mirrors the team's own "release" edge (Team\TasksController::updateStatus)
     * — it isn't just orphaning the task in place, it sends it back to the pool
     * (status → open) so it's claimable again. A task past that point (completed,
     * payment_pending, paid) is a historical record of who did the work, so
     * unassigning it is rejected outright rather than silently dropping the trail.
     */
    public function update(Request $request, Task $task): JsonResponse|TaskResource
    {
        $data = $this->validatePayload($request, creating: false);

        if (array_key_exists('assignee_id', $data) && $data['assignee_id'] === null) {
            if (in_array($task->status, ['completed', 'payment_pending', 'paid'], true)) {
                return response()->json([
                    'message' => 'This task is already past assignment — completed/paid work stays linked to who did it.',
                ], 422);
            }

            if ($task->status === 'in_progress') {
                $data['status'] = 'open';
            }
        }

        $task->update($data);

        return new TaskResource($task->fresh()->load(['creator', 'assignee', 'payrollEntry']));
    }

    /**
     * Release the extra-pay bonus. Only valid once the work is done and money is
     * owed: from payment_pending (the normal path) or the completed-with-bonus
     * edge (a bonus added after a no-pay completion). Stamps paid_at.
     *
     * A task already LINKED to a payslip is off-limits here (422): its bonus is
     * frozen into that slip's gross, so settling the payslip is the only payout
     * path — ad-hoc mark-paid on top would double-pay the extra (Task 7 guard).
     */
    public function markPaid(Task $task): JsonResponse|TaskResource
    {
        if ($task->payroll_entry_id !== null) {
            $period = $task->payrollEntry?->period_label ?? 'a payslip';

            return response()->json([
                'message' => "This task's bonus is on payslip {$period} — settle the payslip instead.",
            ], 422);
        }

        $owesPayment = $task->status === 'payment_pending'
            || ($task->status === 'completed' && $task->pay_amount_myr !== null);

        if (! $owesPayment) {
            return response()->json([
                'message' => 'Only a completed task with an unpaid bonus can be marked paid.',
            ], 422);
        }

        // The checks above cover the common cases with a friendly message, but
        // the actual write is re-guarded as a single conditional UPDATE so two
        // concurrent mark-paid calls (or one racing a payslip generation that
        // links payroll_entry_id) can't both win — only the first commits; a
        // loser affects 0 rows and gets a 422 instead of silently double-paying.
        $affected = Task::whereKey($task->id)
            ->whereNull('payroll_entry_id')
            ->where(function ($query) {
                $query->where('status', 'payment_pending')
                    ->orWhere(function ($query) {
                        $query->where('status', 'completed')->whereNotNull('pay_amount_myr');
                    });
            })
            ->update([
                'status' => 'paid',
                'paid_at' => now(),
                'completed_at' => $task->completed_at ?? now(),
            ]);

        if ($affected === 0) {
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
