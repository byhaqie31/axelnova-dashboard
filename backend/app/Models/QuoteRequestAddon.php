<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteRequestAddon extends Model
{
    protected $fillable = ['quote_request_id', 'addon_key', 'addon_label', 'amount_myr'];

    protected function casts(): array
    {
        return ['amount_myr' => 'decimal:2'];
    }

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }
}
