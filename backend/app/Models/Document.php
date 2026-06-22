<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A frozen, issued invoice or receipt. `payload` is the immutable DocumentData
 * snapshot; the PDF is rendered from it on demand (see DocumentController +
 * the Nitro renderer). Never recompute the payload from live order data.
 */
class Document extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'type',
        'number',
        'public_token',
        'payload',
        'amount_total',
        'amount_paid',
        'payment_ref',
        'payment_method',
        'status',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'amount_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    /** The public, token-gated PDF URL (frontend Nitro route). */
    protected $appends = ['pdf_path'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getPdfPathAttribute(): string
    {
        return "/api/documents/{$this->public_token}/pdf";
    }
}
