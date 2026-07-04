<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The investor profile (table `investors`) — the second partner kind. A plain
 * profile model: authentication lives on the linked ExternalAccount (type
 * 'investor'). No content model yet — documents/reports are premium empty
 * states for now (admin investor CRUD is future work).
 */
class Investor extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_account_id',
        'name',
        'company',
        'notes',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ExternalAccount::class, 'external_account_id');
    }
}
