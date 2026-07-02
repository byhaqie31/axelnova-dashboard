<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payslip row. Shared by the founder's full ledger (/v1/admin/payroll, with
 * `user`/`creator` loaded) and everyone's own-rows view (/v1/team/payslips,
 * where those relations stay unloaded and the names simply drop out).
 */
class PayrollEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'period_label' => $this->period_label,
            'gross_myr' => $this->gross_myr,
            'paid_at' => $this->paid_at?->toISOString(),
            'method' => $this->method,
            'note' => $this->note,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
