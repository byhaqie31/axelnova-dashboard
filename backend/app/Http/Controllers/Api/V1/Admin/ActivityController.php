<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The audit feed — the append-only activity_log, newest first. Cockpit-visible
 * (founder + partner) via the route group; no per-action gate.
 */
class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::query()->with('actor:id,name')->latest('created_at')->latest('id');

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->query('subject_type'));
        }
        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->integer('actor_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(30)->through(fn (ActivityLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'changes' => $log->changes,
            'actor' => $log->actor ? ['id' => $log->actor->id, 'name' => $log->actor->name] : null,
            'created_at' => $log->created_at,
        ]);

        return response()->json($logs);
    }
}
