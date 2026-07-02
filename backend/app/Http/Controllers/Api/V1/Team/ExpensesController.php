<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Controllers\Controller;
use App\Http\Resources\MarketingExpenseResource;
use App\Models\MarketingExpense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Marketing spend from the /team workspace (Phase 5, record-only). Enter own +
 * see own, scoped hard to the token's user id — the marketer never sees the
 * full roll-up; that's the cockpit's Admin\ExpensesController.
 */
class ExpensesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = MarketingExpense::where('entered_by', $request->user()->id)
            ->latest('spent_at')
            ->latest('id');

        // Own-spend roll-up, over all their rows, not just the visible page.
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

        return new MarketingExpenseResource($expense);
    }
}
