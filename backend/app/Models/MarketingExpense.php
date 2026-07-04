<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A marketing-spend row in the record-only ledger (Phase 5). `entered_by`
 * records who logged it. The cockpit (Admin\ExpensesController) is the only
 * surface that reads/writes this table — the team's own-rows view was removed
 * in Task 4 of the portal restructure.
 */
class MarketingExpense extends Model
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
