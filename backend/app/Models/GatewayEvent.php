<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Raw inbound webhook record + idempotency gate. A redelivered event (matched on
 * the unique `event_id`) must not create or transition a payment twice. Wired up
 * in the gateway phases; the model exists now so the ledger contract is complete.
 */
class GatewayEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
