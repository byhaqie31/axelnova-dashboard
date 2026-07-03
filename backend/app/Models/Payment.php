<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single money movement — the ledger's atomic unit. Money in is a `payment`;
 * money out is a `refund` row with a negative `amount_myr` pointing back at its
 * original via `parent_payment_id`, so `SUM(amount_myr)` nets out and history is
 * preserved. Only `succeeded` rows count toward the order / invoice paid caches,
 * which PaymentObserver is the sole writer of.
 */
class Payment extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        // Log every ledger movement as it lands. Auth::id() resolves to the acting
        // admin on manual entries and to null on a gateway/webhook write — so
        // gateway payments enter the audit trail with actor_id = null for free.
        static::created(function (Payment $payment) {
            $action = $payment->type === PaymentType::Refund ? 'payment.refunded' : 'payment.recorded';
            $payment->logActivity($action, [
                'amount_myr' => (float) $payment->amount_myr,
                'gateway' => $payment->gateway->value,
                'method' => $payment->method->value,
                'order_id' => $payment->order_id,
            ]);
        });
    }

    protected $casts = [
        'type' => PaymentType::class,
        'gateway' => PaymentGateway::class,
        'method' => PaymentMethod::class,
        'status' => PaymentStatus::class,
        'amount_myr' => 'decimal:2',
        'fee_myr' => 'decimal:2',
        'net_myr' => 'decimal:2',
        'gateway_payload' => 'array',
        'paid_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    /** The original payment this refund reverses (null for ordinary payments). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'parent_payment_id');
    }

    /** Refund rows reversing this payment (negative amounts). */
    public function refunds(): HasMany
    {
        return $this->hasMany(Payment::class, 'parent_payment_id');
    }

    /** The admin who keyed a manual entry (null for gateway-driven rows). */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeSucceeded(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Succeeded);
    }
}
