<?php

namespace App\Models;

use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Referral extends Model
{
    use RecordsActivity, SoftDeletes;

    /**
     * Relationship tier → commission band (percent of final project value).
     * Single source of truth; the controller derives commission_tier_pct from this.
     */
    public const COMMISSION_TIERS = [
        'cold' => 5,
        'warm' => 10,
        'closed' => 15,
    ];

    /**
     * Per-referral payout ceiling (MYR). Whatever the tier, a single referral
     * never pays out more than this — the public programme copy advertises it,
     * so every derived commission figure must respect it.
     */
    public const COMMISSION_CAP_MYR = 1500;

    protected $fillable = [
        'referral_partner_id',
        'referrer_name',
        'referrer_email',
        'referrer_phone',
        'business_name',
        'business_contact_name',
        'business_email',
        'business_phone',
        'relationship_tier',
        'commission_tier_pct',
        'notes',
        'status',
        'agreed_terms',
        'linked_order_id',
        'commission_email_sent_at',
        'ip_address',
        'user_agent',
        'quotation_id',
        'commission_pct',
    ];

    protected function casts(): array
    {
        return [
            'commission_tier_pct' => 'integer',
            'agreed_terms' => 'boolean',
            'commission_email_sent_at' => 'datetime',
            'commission_pct' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'linked_order_id');
    }

    /** The normalized referrer this lead belongs to (null during transition). */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class, 'referral_partner_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** Confirmed rate if set, else the tier estimate. */
    public function effectivePct(): int
    {
        return (int) ($this->commission_pct ?? $this->commission_tier_pct);
    }

    /** The order this referral earns on — reached via its quotation anchor (falls back to legacy link). */
    public function orderViaQuotation(): ?Order
    {
        return $this->quotation?->order ?? $this->order;
    }

    /** Resolve the commission band for a relationship tier (defaults to the cold band). */
    public static function commissionPctFor(string $tier): int
    {
        return self::COMMISSION_TIERS[$tier] ?? self::COMMISSION_TIERS['cold'];
    }

    /** Apply the per-referral payout ceiling to a raw commission amount. */
    public static function capCommission(float $amount): float
    {
        return round(min($amount, self::COMMISSION_CAP_MYR), 2);
    }

    /**
     * The not-yet-collected slice of an order's commission. Both the contract
     * total and the collected-so-far portion are capped first, so once the cap
     * is reached nothing further accrues (capped-total minus capped-collected).
     */
    public static function pendingCommission(float $rate, float $contract, float $collected): float
    {
        return round(max(0, min($contract * $rate / 100, self::COMMISSION_CAP_MYR)
            - min($collected * $rate / 100, self::COMMISSION_CAP_MYR)), 2);
    }

    /**
     * Commission owed once converted — the linked order's final value times
     * this referral's tier, capped. Null until an order with a final amount is linked.
     */
    public function commissionAmount(): ?float
    {
        $final = (float) ($this->order?->final_amount_myr ?? 0);
        if (! $this->linked_order_id || $final <= 0) {
            return null;
        }

        return self::capCommission($final * $this->commission_tier_pct / 100);
    }
}
