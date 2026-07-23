<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyExpenseResource;
use App\Models\CompanyExpense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Company-spending ledger, cockpit view (record-only): the founder sees every
 * row and may enter their own. Renamed from the "marketing expenses" ledger —
 * the tracker was always general company spend. This is the only entry point
 * for the ledger; the workspace does not touch financial data.
 */
class ExpensesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CompanyExpense::with('enteredBy')->latest('spent_at')->latest('id');

        if ($request->filled('category')) {
            $query->where('category', 'like', "%{$request->category}%");
        }

        // Roll-up over the current filter, not just the visible page.
        $total = (int) (clone $query)->sum('amount_myr');

        return CompanyExpenseResource::collection($query->paginate(20))
            ->additional(['totals' => ['amount_myr' => $total]]);
    }

    public function store(Request $request): CompanyExpenseResource
    {
        $expense = CompanyExpense::create([
            ...$request->validate([
                'category' => ['required', 'string', 'max:60'],
                'amount_myr' => ['required', 'integer', 'min:1'],
                'spent_at' => ['required', 'date'],
                'note' => ['nullable', 'string', 'max:2000'],
            ]),
            'entered_by' => $request->user()->id,
        ]);

        return new CompanyExpenseResource($expense->load('enteredBy'));
    }
}
