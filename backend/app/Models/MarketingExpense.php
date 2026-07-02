<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A marketing-spend row in the record-only ledger (Phase 5). `entered_by`
 * doubles as the visibility scope: the marketer sees only their own rows,
 * the cockpit (founder + partner) sees all.
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
