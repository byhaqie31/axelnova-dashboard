<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MarketingExpenseResource;
use App\Models\MarketingExpense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Marketing-spend ledger, cockpit view (Phase 5, record-only): founder +
 * partner see every row and may enter their own. The marketer's enter/see-own
 * surface is Team\ExpensesController — same table, scoped query.
 */
class ExpensesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = MarketingExpense::with('enteredBy')->latest('spent_at')->latest('id');

        if ($request->filled('category')) {
            $query->where('category', 'like', "%{$request->category}%");
        }

        // Roll-up over the current filter, not just the visible page.
        $total = (int) (clone $query)->sum('amount_myr');

        return MarketingExpenseResource::collection($query->paginate(20))
            ->additional(['totals' => ['amount_myr' => $total]]);
    }

    public function store(Request $request): MarketingExpenseResource
    {
        $expense = MarketingExpense::create([
            ...$request->validate([
                'category' => ['required', 'string', 'max:60'],
                'amount_myr' => ['required', 'integer', 'min:1'],
                'spent_at' => ['required', 'date'],
                'note' => ['nullable', 'string', 'max:2000'],
            ]),
            'entered_by' => $request->user()->id,
        ]);

        return new MarketingExpenseResource($expense->load('enteredBy'));
    }
}
