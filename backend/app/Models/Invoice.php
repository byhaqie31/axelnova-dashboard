<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A frozen, issued invoice (deposit / partial / final). `payload` is the
 * immutable DocumentData snapshot; the PDF renders from it on demand. Never
 * recompute the payload from live order data.
 */
class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'invoice_number',
        'public_token',
        'type',
        'payload',
        'amount_total',
        'amount_paid',
        'payment_ref',
        'payment_method',
        'status',
        'issued_at',
        'due_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'amount_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /** The public, token-gated PDF URL (frontend Nitro route). */
    protected $appends = ['pdf_path'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    /** Payments allocated to this invoice (succeeded + refunds), the ledger view. */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getPdfPathAttribute(): string
    {
        return "/documents/{$this->public_token}/pdf";
    }

    /**
     * Live paid total from the ledger — succeeded payments allocated here, net of
     * refunds. Distinct from the observer-maintained `amount_paid` cache column;
     * use this for display where the ledger is the intended source.
     */
    protected function amountPaidMyr(): Attribute
    {
        return Attribute::get(
            fn () => number_format((float) $this->payments()->succeeded()->sum('amount_myr'), 2, '.', '')
        );
    }
}
