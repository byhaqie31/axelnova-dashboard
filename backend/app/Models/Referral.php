<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Referral extends Model
{
    use SoftDeletes;

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
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'commission_tier_pct' => 'integer',
            'agreed_terms' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'linked_order_id');
    }

    /** Resolve the commission band for a relationship tier (defaults to the cold band). */
    public static function commissionPctFor(string $tier): int
    {
        return self::COMMISSION_TIERS[$tier] ?? self::COMMISSION_TIERS['cold'];
    }
}
