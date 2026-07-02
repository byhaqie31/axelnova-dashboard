<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A marketing-spend row. Shared by the cockpit's full roll-up (with `enteredBy`
 * loaded) and the marketer's own-rows view (relation unloaded — their own name
 * would be noise).
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
