<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A payslip row in the in-system payroll ledger (Task 7). Each slip itemises one
 * member's comp as `allowance_snapshot_myr` + `task_extras_myr` +
 * `discretionary_myr`, with `gross_myr` kept as the TOTAL so legacy consumers stay
 * valid. `paid_at` is the sole settlement marker (no status column). `user_id` is
 * whose payslip it is; `created_by` is the founder who generated it.
 *
 * A slip is one of two `kind`s:
 *   - `monthly`   the recurring run — allowance snapshot + task extras. Guarded to
 *                 one per member per period (the generated `monthly_period` unique
 *                 index). `discretionary_myr` is 0, `one_time_type` null.
 *   - `one_time`  an ad-hoc record — a signing/festive/spot bonus (a
 *                 `discretionary_myr` amount) and/or a batch of task extras paid
 *                 immediately, outside the monthly cycle. Not period-guarded;
 *                 `allowance_snapshot_myr` is null, `one_time_type` labels it. It
 *                 still carries a YYYY-MM `period_label` (the payment's month) so
 *                 year-to-date rollups bucket it correctly.
 *
 * The settled payslip IS the team-comp expense record — there is no separate
 * finance/expenses/P&L module in this repo (only `marketing_expenses` and the
 * client-revenue `payments` ledger), so nothing double-counts. Statutory maths
 * (EPF/SOCSO/EIS/PCB) stays out of scope — amounts are recorded as agreed.
 */
class PayrollEntry extends Model
{
    use HasFactory;

    public const KIND_MONTHLY = 'monthly';

    public const KIND_ONE_TIME = 'one_time';

    /** Reason labels a one-off can carry — validated at the controller. */
    public const ONE_TIME_TYPES = ['signing', 'festive', 'performance', 'spot', 'other'];

    protected $fillable = [
        'user_id',
        'kind',
        'period_label',
        'one_time_type',
        'allowance_snapshot_myr',
        'task_extras_myr',
        'discretionary_myr',
        'gross_myr',
        'paid_at',
        'method',
        'note',
        'created_by',
    ];

    protected $casts = [
        'allowance_snapshot_myr' => 'integer',
        'task_extras_myr' => 'integer',
        'discretionary_myr' => 'integer',
        'gross_myr' => 'integer',
        'paid_at' => 'datetime',
    ];

    protected $attributes = [
        'kind' => self::KIND_MONTHLY,
        'discretionary_myr' => 0,
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
     * True for a pre-Task-7 row: a monthly slip with no allowance snapshot and no
     * task extras, yet a gross recorded by hand. The UI renders these gross-only,
     * without a breakdown. Gated on `monthly` so a discretionary one-off — which
     * also has a null allowance and zero task extras — is never misflagged.
     */
    public function isLegacy(): bool
    {
        return $this->kind === self::KIND_MONTHLY
            && $this->allowance_snapshot_myr === null
            && (int) $this->task_extras_myr === 0
            && (int) $this->discretionary_myr === 0
            && (int) $this->gross_myr > 0;
    }

    /** A one-off record rather than the recurring monthly run. */
    public function isOneTime(): bool
    {
        return $this->kind === self::KIND_ONE_TIME;
    }

    /** Settled once `paid_at` is stamped. */
    public function isSettled(): bool
    {
        return $this->paid_at !== null;
    }
}
