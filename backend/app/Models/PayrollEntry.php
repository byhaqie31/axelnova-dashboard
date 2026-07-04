<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A payslip row in the in-system payroll ledger (Task 7). Each slip itemises one
 * member's comp for one period as `allowance_snapshot_myr` + `task_extras_myr`,
 * with `gross_myr` kept as the TOTAL so legacy consumers stay valid. `paid_at` is
 * the sole settlement marker (no status column). `user_id` is whose payslip it
 * is; `created_by` is the founder who generated it.
 *
 * The settled payslip IS the team-comp expense record — there is no separate
 * finance/expenses/P&L module in this repo (only `marketing_expenses` and the
 * client-revenue `payments` ledger), so nothing double-counts. Statutory maths
 * (EPF/SOCSO/EIS/PCB) stays out of scope — amounts are recorded as agreed.
 */
class PayrollEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'period_label',
        'allowance_snapshot_myr',
        'task_extras_myr',
        'gross_myr',
        'paid_at',
        'method',
        'note',
        'created_by',
    ];

    protected $casts = [
        'allowance_snapshot_myr' => 'integer',
        'task_extras_myr' => 'integer',
        'gross_myr' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The task extras this slip settles (linked at generation, flipped to paid at settle). */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * True for a pre-Task-7 row: no allowance snapshot and no task extras, yet a
     * gross was recorded by hand. The UI renders these gross-only, without a
     * breakdown, so history reads faithfully.
     */
    public function isLegacy(): bool
    {
        return $this->allowance_snapshot_myr === null
            && (int) $this->task_extras_myr === 0
            && (int) $this->gross_myr > 0;
    }

    /** Settled once `paid_at` is stamped. */
    public function isSettled(): bool
    {
        return $this->paid_at !== null;
    }
}
