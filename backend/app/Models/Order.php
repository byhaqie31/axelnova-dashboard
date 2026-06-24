<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number',
        'quotation_id',
        'client_id',
        'value_min_myr',
        'value_max_myr',
        'final_amount_myr',
        'deposit_pct',
        'amount_paid_myr',
        'status',
        'started_at',
        'delivered_at',
        'completed_at',
        'due_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'value_min_myr' => 'decimal:2',
            'value_max_myr' => 'decimal:2',
            'final_amount_myr' => 'decimal:2',
            'deposit_pct' => 'integer',
            'amount_paid_myr' => 'decimal:2',
            'started_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'due_at' => 'date',
        ];
    }

    /** Agreed total still owed — what "Pending" sums on the dashboard. */
    protected function remainingMyr(): Attribute
    {
        return Attribute::get(fn () => max(0, (float) $this->final_amount_myr - (float) $this->amount_paid_myr));
    }

    /** Deposit due up front, derived from the carried deposit percentage. */
    protected function depositDueMyr(): Attribute
    {
        return Attribute::get(fn () => round((float) $this->final_amount_myr * ((int) ($this->deposit_pct ?? 0)) / 100, 2));
    }

    /** unpaid → deposit_paid → paid, derived from how much has landed. */
    protected function paymentStatus(): Attribute
    {
        return Attribute::get(function () {
            $paid = (float) $this->amount_paid_myr;
            $total = (float) $this->final_amount_myr;
            if ($paid <= 0) return 'unpaid';
            if ($total > 0 && $paid >= $total) return 'paid';
            return 'deposit_paid';
        });
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Issued invoices (deposit / partial / final), frozen snapshots. */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('issued_at');
    }

    /** Issued receipts (settled payments), frozen snapshots. */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class)->latest('issued_at');
    }

    /** Legacy combined documents (pre invoices/receipts split). */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->latest('issued_at');
    }
}
