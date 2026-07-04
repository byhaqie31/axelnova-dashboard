<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payslip row. Shared by the founder's full ledger (/v1/admin/payroll, with
 * `user`/`creator` loaded) and everyone's own-rows view (/v1/team/payslips,
 * where those relations stay unloaded and the names simply drop out).
 *
 * Exposes the itemised breakdown (allowance snapshot + task extras = gross) plus
 * a `legacy` flag: pre-Task-7 rows carry a hand-entered gross with no breakdown,
 * so the UI renders them gross-only. `settled` mirrors `paid_at`. Linked task
 * extras are included whenever the `tasks` relation is eager-loaded.
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
            'allowance_snapshot_myr' => $this->allowance_snapshot_myr,
            'task_extras_myr' => (int) $this->task_extras_myr,
            'gross_myr' => (int) $this->gross_myr,
            'legacy' => $this->isLegacy(),
            'settled' => $this->isSettled(),
            'paid_at' => $this->paid_at?->toISOString(),
            'method' => $this->method,
            'note' => $this->note,
            'tasks' => $this->whenLoaded('tasks', fn () => $this->tasks->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'pay_amount_myr' => $task->pay_amount_myr,
            ])->values()),
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
