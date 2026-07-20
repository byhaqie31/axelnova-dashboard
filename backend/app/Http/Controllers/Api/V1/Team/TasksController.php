<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Tasks — the workspace surface (Task 5). A team member sees exactly two sets:
 * the pick-up pool (open + unassigned) and their own tasks (any status). They
 * claim from the pool, move their own work through the state machine, and
 * complete with a note. Everything else is untouchable — never someone else's
 * task, never the admin edit surface, never mark-paid.
 *
 * Transitions owned here (Admin\TasksController documents the full machine):
 *   claim:   pool task → assignee = me, status open → in_progress (one gesture —
 *            picking up IS starting, matching the kanban's Available → In progress)
 *   status:  in_progress → completed (auto-forks to payment_pending when a bonus
 *            is attached; stamps completed_at; the completion note is required
 *            at the API edge by the frontend, appended here when given)
 *            in_progress → open (release/abandon — unassigns, back to the pool)
 *            open → in_progress (start an admin-assigned task)
 */
class TasksController extends Controller
{
    /** The kanban feed: `{pool, mine}` in one round-trip. */
    public function index(Request $request): JsonResponse
    {
        $pool = Task::with(['creator', 'assignee'])
            ->whereNull('assignee_id')
            ->where('status', 'open')
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->latest('id')
            ->get();

        $mine = Task::with(['creator', 'assignee'])
            ->where('assignee_id', $request->user()->id)
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->latest('id')
            ->get();

        return response()->json([
            'pool' => TaskResource::collection($pool),
            'mine' => TaskResource::collection($mine),
        ]);
    }

    /**
     * Claim a pool task — one gesture: assignee = me AND status → in_progress.
     * Guarded atomically (lockForUpdate) so two teammates tapping "Pick up"
     * simultaneously can't both win; the loser gets the same 409 as any stale
     * claim. Claiming a task that's assigned (or not open) is a 409 conflict.
     */
    public function claim(Request $request, Task $task): JsonResponse|TaskResource
    {
        $claimed = DB::transaction(function () use ($request, $task) {
            $fresh = Task::whereKey($task->id)->lockForUpdate()->first();

            if ($fresh->assignee_id !== null || $fresh->status !== 'open') {
                return false;
            }

            $fresh->update([
                'assignee_id' => $request->user()->id,
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            return true;
        });

        if (! $claimed) {
            return response()->json(['message' => 'This task is no longer available.'], 409);
        }

        return new TaskResource($task->fresh()->load(['creator', 'assignee']));
    }

    /**
     * Move my own task. Allowed edges only:
     *   open → in_progress          (start an admin-assigned task)
     *   in_progress → completed     (finish — forks to payment_pending with a bonus)
     *   in_progress → open          (release — unassigns me, back to the pool)
     * Anything else (skipping ahead, reopening a completed task, touching a paid
     * one) is a 422. Not-my-task is a 403 before any of that.
     */
    public function updateStatus(Request $request, Task $task): JsonResponse|TaskResource
    {
        if ($task->assignee_id !== $request->user()->id) {
            return response()->json(['message' => 'Not your task.'], 403);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'completed'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $allowed = match ($task->status) {
            'open' => ['in_progress'],
            'in_progress' => ['completed', 'open'],
            default => [],
        };

        if (! in_array($data['status'], $allowed, true)) {
            return response()->json([
                'message' => "Cannot move a task from '{$task->status}' to '{$data['status']}'.",
            ], 422);
        }

        $update = [];

        if ($data['status'] === 'open') {
            // Release: back to the pool, unassigned. Reset the pickup clock so a
            // re-claim reads fresh (the timeline shows the LATEST pickup, not the
            // abandoned one).
            $update['status'] = 'open';
            $update['assignee_id'] = null;
            $update['started_at'] = null;
        } elseif ($data['status'] === 'completed') {
            // Completing a task WITH a bonus forks to payment_pending automatically.
            $update['status'] = $task->pay_amount_myr !== null ? 'payment_pending' : 'completed';
            $update['completed_at'] = now();
        } else {
            // Starting an admin-assigned task — stamp the pickup moment.
            $update['status'] = 'in_progress';
            $update['started_at'] = now();
        }

        if (! empty($data['note'])) {
            $stamp = now()->format('Y-m-d H:i');
            $line = "[{$stamp}] {$request->user()->name}: {$data['note']}";
            $update['notes'] = $task->notes ? $task->notes."\n".$line : $line;
        }

        $task->update($update);

        return new TaskResource($task->fresh()->load(['creator', 'assignee']));
    }
}
