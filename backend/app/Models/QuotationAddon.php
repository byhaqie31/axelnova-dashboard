<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationAddon extends Model
{
    protected $fillable = ['quotation_id', 'addon_key', 'addon_label', 'amount_myr'];

    protected function casts(): array
    {
        return ['amount_myr' => 'decimal:2'];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
