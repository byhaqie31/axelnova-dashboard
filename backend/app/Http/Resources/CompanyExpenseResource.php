<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A company-spending row for the cockpit's full roll-up (Admin\ExpensesController,
 * with `enteredBy` loaded). Renamed from MarketingExpenseResource alongside the
 * marketing_expenses → company_expenses table rename.
 */
class CompanyExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'amount_myr' => $this->amount_myr,
            'spent_at' => $this->spent_at?->toDateString(),
            'note' => $this->note,
            'entered_by' => $this->entered_by,
            'entered_by_name' => $this->whenLoaded('enteredBy', fn () => $this->enteredBy?->name),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
