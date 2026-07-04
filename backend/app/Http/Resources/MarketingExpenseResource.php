<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A marketing-spend row for the cockpit's full roll-up (Admin\ExpensesController,
 * with `enteredBy` loaded). The team's own-rows view was removed in Task 4 of
 * the portal restructure — this is now the only surface that renders it.
 */
class MarketingExpenseResource extends JsonResource
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
