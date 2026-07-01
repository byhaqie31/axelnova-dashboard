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
    ];

    protected function casts(): array
    {
        return [
            'commission_tier_pct' => 'integer',
            'agreed_terms' => 'boolean',
            'commission_email_sent_at' => 'datetime',
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

    /** Resolve the commission band for a relationship tier (defaults to the cold band). */
    public static function commissionPctFor(string $tier): int
    {
        return self::COMMISSION_TIERS[$tier] ?? self::COMMISSION_TIERS['cold'];
    }

    /**
     * Commission owed once converted — the linked order's final value times
     * this referral's tier. Null until an order with a final amount is linked.
     */
    public function commissionAmount(): ?float
    {
        $final = (float) ($this->order?->final_amount_myr ?? 0);
        if (! $this->linked_order_id || $final <= 0) {
            return null;
        }

        return round($final * $this->commission_tier_pct / 100, 2);
    }
}
