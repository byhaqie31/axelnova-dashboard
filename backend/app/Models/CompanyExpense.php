<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company-spending row in the record-only ledger (renamed from
 * MarketingExpense — the tracker was always general company spend, not just
 * marketing). `entered_by` records who logged it. The cockpit
 * (Admin\ExpensesController) is the only surface that reads/writes this table.
 */
class CompanyExpense extends Model
{
    protected $fillable = ['entered_by', 'category', 'amount_myr', 'spent_at', 'note'];

    protected $casts = [
        'spent_at' => 'date',
    ];

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
