<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollEntryResource;
use App\Models\PayrollEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The payroll ledger (Phase 5) — founder-only on both ends via the
 * `view-all-payroll` gate: a partner reaches these routes (cockpit tier) but is
 * stopped here (403) and reads their own payslips at /v1/team/payslips like
 * everyone else. Record-only: gross is entered as agreed, never computed — no
 * EPF/SOCSO/EIS/PCB in this repo (plan Section 3).
 */
class PayrollController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('view-all-payroll');

        $query = PayrollEntry::with(['user', 'creator'])->latest()->latest('id');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return PayrollEntryResource::collection($query->paginate(20));
    }

    public function store(Request $request): PayrollEntryResource
    {
        Gate::authorize('view-all-payroll');

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'period_label' => ['required', 'string', 'max:40'],
            'gross_myr' => ['required', 'integer', 'min:1'],
            'paid_at' => ['nullable', 'date'],
            'method' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $entry = PayrollEntry::create([...$data, 'created_by' => $request->user()->id]);

        return new PayrollEntryResource($entry->load(['user', 'creator']));
    }
}
