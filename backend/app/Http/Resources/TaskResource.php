<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A task row. Shared by the admin cockpit list/detail (creator + assignee loaded)
 * and the team board (`{pool, mine}`). `payment_state` is the derived card badge
 * (none / pending / paid); `assignee_name` / `created_by_name` resolve only when
 * the relation is eager-loaded (all controllers here load both, so they're always
 * present, dropping to null when the relation itself is null).
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'assignee_id' => $this->assignee_id,
            'assignee_name' => $this->whenLoaded('assignee', fn () => $this->assignee?->name),
            'pay_amount_myr' => $this->pay_amount_myr,
            'payment_state' => $this->paymentState(),
            // Task 7 — non-null once a payslip has swept this bonus up. The admin
            // UI hides ad-hoc mark-paid for linked tasks (settle the slip instead);
            // the period label resolves only where `payrollEntry` is eager-loaded.
            'payroll_entry_id' => $this->payroll_entry_id,
            'payroll_period_label' => $this->whenLoaded('payrollEntry', fn () => $this->payrollEntry?->period_label),
            'duration_estimate' => $this->duration_estimate,
            'deadline' => $this->deadline?->toISOString(),
            'priority' => $this->priority,
            'status' => $this->status,
            'notes' => $this->notes,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
