<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payslip row in the record-only payroll ledger (Phase 5). Gross is entered
 * as agreed, never computed — no statutory calculation lives in this repo.
 * `user_id` is whose payslip it is; `created_by` is the founder who recorded it.
 */
class PayrollEntry extends Model
{
    protected $fillable = ['user_id', 'period_label', 'gross_myr', 'paid_at', 'method', 'note', 'created_by'];

    protected $casts = [
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
}
