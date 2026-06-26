<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A frozen, issued receipt confirming a settled payment. Belongs to the invoice
 * it settles (`invoice_id`) plus its order. `payload` is the immutable
 * DocumentData snapshot the PDF renders from.
 */
class Receipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'invoice_id',
        'payment_id',
        'receipt_number',
        'public_token',
        'payload',
        'amount',
        'payment_ref',
        'payment_method',
        'status',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'amount' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    /** The public, token-gated PDF URL (frontend Nitro route). */
    protected $appends = ['pdf_path'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** The payment that produced this receipt (1 payment : 1 receipt). */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function getPdfPathAttribute(): string
    {
        return "/documents/{$this->public_token}/pdf";
    }
}
