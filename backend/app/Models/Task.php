<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A delegated unit of work (Task 5). `created_by` is the admin who authored it;
 * `assignee_id` is who's on it (null = the pick-up pool). The status enum is the
 * workflow spine, enforced in the controllers (App\Http\Controllers\Api\V1\{Admin,
 * Team}\TasksController) — the model just stores it. `pay_amount_myr` is the
 * OPTIONAL extra-on-top bonus; most tasks carry none, which is why payment is a
 * card badge, never a board column (see `paymentState`).
 */
class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'created_by',
        'assignee_id',
        'pay_amount_myr',
        'duration_estimate',
        'deadline',
        'priority',
        'status',
        'payroll_entry_id',
        'notes',
        'completed_at',
        'paid_at',
    ];

    protected $casts = [
        'pay_amount_myr' => 'integer',
        'payroll_entry_id' => 'integer',
        'deadline' => 'datetime',
        'completed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /** The admin who authored the task. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The team member working it (null = still in the pick-up pool). */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * The payslip that settles this task's extra bonus (null = not yet on any
     * payslip). Set at payslip generation; the per-task double-count guard is
     * that generation only ever picks up unlinked payment_pending tasks.
     */
    public function payrollEntry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class);
    }

    /**
     * The money lifecycle, collapsed to the three states the card badge renders:
     *   - 'none'    — no extra pay attached (`pay_amount_myr` is null). Most tasks.
     *   - 'paid'    — the bonus has been paid out (status 'paid').
     *   - 'pending' — a bonus is attached but not yet paid (any other status).
     *
     * Deliberately broader than "pending only once completed": a pooled task with
     * a bonus must advertise it so someone picks it up, so the amount is visible
     * from 'open' onward. The badge shows nothing for 'none' — payment is never a
     * board column because most work carries no extra pay.
     */
    public function paymentState(): string
    {
        if ($this->pay_amount_myr === null) {
            return 'none';
        }

        return $this->status === 'paid' ? 'paid' : 'pending';
    }
}
