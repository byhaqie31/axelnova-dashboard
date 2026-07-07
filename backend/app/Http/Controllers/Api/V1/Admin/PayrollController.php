<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollEntryResource;
use App\Models\PayrollEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The payroll ledger (Task 7) — founder-only on both ends via the
 * `view-all-payroll` gate. Everyone else reads their own payslips at
 * /v1/team/payslips.
 *
 * A payslip = the member's allowance SNAPSHOT + Σ of their payment_pending task
 * extras for the period. Generation freezes the allowance (so a later raise never
 * rewrites history) and LINKS the settled task extras (per-task double-count
 * guard); a UNIQUE (user_id, period_label) index is the per-period guard.
 * Settling stamps `paid_at` and flips the linked tasks to `paid`. The settled
 * payslip IS the team-comp expense record — no separate expense insert exists
 * (there's no general finance/P&L module here), so nothing double-counts.
 */
class PayrollController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('view-all-payroll');

        $query = PayrollEntry::with(['user', 'creator', 'tasks'])->latest()->latest('id');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return PayrollEntryResource::collection($query->paginate(20));
    }

    /**
     * A dry-run for the generation UI: what a payslip for this member would carry
     * right now — the allowance on file plus the count/sum of their unlinked
     * payment_pending task extras. Pass `period_label` too and `period_taken`
     * reports whether that slip already exists, so the UI can warn before the
     * generate call 422s (the unique-period guard stays enforced at generation).
     */
    public function preview(Request $request): JsonResponse
    {
        Gate::authorize('view-all-payroll');

        $data = $request->validate([
            // Active accounts only — a deactivated teammate (Task 8 lockout)
            // must not get new payslips; mirrors store()'s constraint so the
            // preview never green-lights a generation that would 422.
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deactivated_at')],
            'period_label' => ['nullable', 'string', 'max:40'],
        ], [
            'user_id.exists' => 'That teammate is deactivated (or doesn\'t exist) — reactivate them on the Users page first.',
        ]);

        $user = User::findOrFail($data['user_id']);
        $tasks = $this->pendingExtras($user->id)->get();
        $extras = (int) $tasks->sum('pay_amount_myr');

        $periodTaken = isset($data['period_label']) && $data['period_label'] !== ''
            ? PayrollEntry::where('user_id', $user->id)->where('period_label', $data['period_label'])->exists()
            : null;

        return response()->json([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'monthly_allowance_myr' => $user->monthly_allowance_myr,
            'pending_extras_count' => $tasks->count(),
            'pending_extras_myr' => $extras,
            'projected_gross_myr' => (int) ($user->monthly_allowance_myr ?? 0) + $extras,
            'period_taken' => $periodTaken,
        ]);
    }

    /**
     * The person-first roster: one row per teammate with their standing allowance,
     * the count/sum of their pending task extras, the projected gross, and whether
     * this period's slip already exists — so the founder can decide allowances and
     * generate per individual from a single screen. Deactivated teammates are
     * included (tagged) so their payslip history stays reachable, but generation
     * still refuses them (see store()). Batched: two aggregate queries feed every
     * row, no per-user N+1.
     */
    public function roster(Request $request): JsonResponse
    {
        Gate::authorize('view-all-payroll');

        $data = $request->validate([
            'period_label' => ['nullable', 'string', 'max:40'],
        ]);
        $period = isset($data['period_label']) && $data['period_label'] !== '' ? $data['period_label'] : null;

        $users = User::query()->orderByRaw('deactivated_at IS NOT NULL')->orderBy('name')->get();

        // Pending extras per assignee — one grouped query for the whole roster.
        $extras = Task::query()
            ->where('status', 'payment_pending')
            ->whereNull('payroll_entry_id')
            ->whereNotNull('pay_amount_myr')
            ->selectRaw('assignee_id, COUNT(*) as cnt, COALESCE(SUM(pay_amount_myr), 0) as total')
            ->groupBy('assignee_id')
            ->get()
            ->keyBy('assignee_id');

        // Teammates who already have a slip for this period — one query.
        $taken = $period
            ? PayrollEntry::where('period_label', $period)->pluck('user_id')->flip()
            : collect();

        $rows = $users->map(function (User $u) use ($extras, $taken, $period) {
            $ex = $extras->get($u->id);
            $sum = (int) ($ex->total ?? 0);
            $allowance = $u->monthly_allowance_myr;

            return [
                'user_id' => $u->id,
                'name' => $u->name,
                'role' => $u->role,
                'deactivated' => $u->deactivated_at !== null,
                'monthly_allowance_myr' => $allowance,
                'pending_extras_count' => (int) ($ex->cnt ?? 0),
                'pending_extras_myr' => $sum,
                'projected_gross_myr' => (int) ($allowance ?? 0) + $sum,
                'period_taken' => $period ? $taken->has($u->id) : null,
            ];
        })->values();

        // Dashboard summary — this-period figures over ACTIVE teammates, plus the
        // year-to-date paid total for the period's year.
        $active = $rows->reject(fn (array $r) => $r['deactivated']);
        $year = $period ? substr($period, 0, 4) : null;
        $paidThisYear = $year
            ? (int) PayrollEntry::whereNotNull('paid_at')->where('period_label', 'like', $year.'-%')->sum('gross_myr')
            : 0;

        return response()->json([
            'period_label' => $period,
            'summary' => [
                'projected_total_myr' => (int) $active->sum('projected_gross_myr'),
                'generated_count' => $active->where('period_taken', true)->count(),
                'pending_count' => $active->filter(fn (array $r) => $r['period_taken'] === false && $r['projected_gross_myr'] >= 1)->count(),
                'paid_this_year_myr' => $paidThisYear,
                'year' => $year ? (int) $year : null,
                'headcount' => $active->count(),
            ],
            'data' => $rows,
        ]);
    }

    /**
     * One teammate's full payroll history + per-year aggregates — the payroll
     * detail page (/admin/payroll/[user]). Founder-only. Newest slip first;
     * `summary_by_year` powers the yearly-totals tiles + year selector.
     */
    public function userDetail(User $user): JsonResponse
    {
        Gate::authorize('view-all-payroll');

        $entries = PayrollEntry::with(['tasks', 'creator'])
            ->where('user_id', $user->id)
            ->orderByDesc('period_label')
            ->orderByDesc('id')
            ->get();

        $byYear = $entries
            ->groupBy(fn (PayrollEntry $e) => substr($e->period_label, 0, 4))
            ->map(fn ($group) => [
                'count' => $group->count(),
                'gross_total_myr' => (int) $group->sum('gross_myr'),
                'paid_total_myr' => (int) $group->filter(fn (PayrollEntry $e) => $e->paid_at !== null)->sum('gross_myr'),
                'pending_total_myr' => (int) $group->filter(fn (PayrollEntry $e) => $e->paid_at === null)->sum('gross_myr'),
                'allowance_total_myr' => (int) $group->sum('allowance_snapshot_myr'),
                'extras_total_myr' => (int) $group->sum('task_extras_myr'),
            ]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'deactivated' => $user->deactivated_at !== null,
                'monthly_allowance_myr' => $user->monthly_allowance_myr,
            ],
            'years' => $byYear->keys()->map(fn ($y) => (int) $y)->sortDesc()->values(),
            'summary_by_year' => $byYear,
            'entries' => PayrollEntryResource::collection($entries)->resolve(),
        ]);
    }

    /**
     * Generate a payslip. Snapshots the member's allowance (null stays null —
     * "no allowance on file" is distinct from an explicit 0, but counts as 0 in
     * the sum), collects their unlinked payment_pending task extras under a lock,
     * links them, and stores gross = allowance(0-if-null) + extras. Refuses a
     * duplicate period (422) and an empty slip — no allowance and no extras (422).
     */
    public function store(Request $request): PayrollEntryResource|JsonResponse
    {
        Gate::authorize('view-all-payroll');

        $data = $request->validate([
            // Active accounts only — see preview()'s matching constraint.
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deactivated_at')],
            'period_label' => ['required', 'string', 'max:40'],
            'method' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'user_id.exists' => 'That teammate is deactivated (or doesn\'t exist) — reactivate them on the Users page first.',
        ]);

        $user = User::findOrFail($data['user_id']);

        $result = DB::transaction(function () use ($data, $user, $request) {
            // One payslip per member per period (backed by the unique index).
            $exists = PayrollEntry::where('user_id', $user->id)
                ->where('period_label', $data['period_label'])
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                return ['error' => "A payslip for {$user->name} already exists for {$data['period_label']}."];
            }

            // Lock the member's unlinked payment_pending tasks (mirrors Task 5's
            // claim pattern) so two concurrent generations can't both grab them.
            $tasks = $this->pendingExtras($user->id)->lockForUpdate()->get();
            $extras = (int) $tasks->sum('pay_amount_myr');

            $allowance = $user->monthly_allowance_myr; // as-is: null stays null
            $gross = (int) ($allowance ?? 0) + $extras;

            if ($gross < 1) {
                return ['error' => "Nothing to pay {$user->name}: no allowance on file and no pending task extras."];
            }

            $entry = PayrollEntry::create([
                'user_id' => $user->id,
                'period_label' => $data['period_label'],
                'allowance_snapshot_myr' => $allowance,
                'task_extras_myr' => $extras,
                'gross_myr' => $gross,
                'method' => $data['method'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            // Link the extras to this slip — they're settled when it settles.
            if ($tasks->isNotEmpty()) {
                Task::whereIn('id', $tasks->pluck('id'))->update(['payroll_entry_id' => $entry->id]);
            }

            return ['entry' => $entry];
        });

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        return new PayrollEntryResource($result['entry']->load(['user', 'creator', 'tasks']));
    }

    /**
     * Settle a payslip — stamp `paid_at` (+ method) and flip its linked, still-
     * pending task extras to `paid`. Idempotent guard: settling an already-settled
     * slip is a 422 (no double-flip). The guard runs INSIDE the transaction on a
     * lockForUpdate re-read, so two concurrent settles can't both pass it.
     */
    public function settle(Request $request, PayrollEntry $payrollEntry): PayrollEntryResource|JsonResponse
    {
        Gate::authorize('view-all-payroll');

        $data = $request->validate([
            'method' => ['nullable', 'string', 'max:40'],
        ]);

        $settled = DB::transaction(function () use ($payrollEntry, $data) {
            $fresh = PayrollEntry::whereKey($payrollEntry->id)->lockForUpdate()->first();

            if ($fresh->isSettled()) {
                return false;
            }

            $fresh->update([
                'paid_at' => now(),
                'method' => $data['method'] ?? $fresh->method,
            ]);

            // Only the linked extras that haven't been settled by another route.
            $fresh->tasks()
                ->where('status', 'payment_pending')
                ->update(['status' => 'paid', 'paid_at' => now()]);

            return true;
        });

        if (! $settled) {
            return response()->json(['message' => 'This payslip is already settled.'], 422);
        }

        return new PayrollEntryResource($payrollEntry->fresh()->load(['user', 'creator', 'tasks']));
    }

    /** A member's unlinked, payment_pending task extras — the generation pool. */
    private function pendingExtras(int $userId)
    {
        return Task::where('assignee_id', $userId)
            ->where('status', 'payment_pending')
            ->whereNull('payroll_entry_id')
            ->whereNotNull('pay_amount_myr');
    }
}
