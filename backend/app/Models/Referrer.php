<?php

namespace App\Models;

use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * The affiliate entity behind the referral programme — table `referral_partners`.
 * As of Task 9 this is a plain PROFILE model: portal authentication moved to the
 * unified ExternalAccount (type 'referrer') on the isolated `external` guard, and
 * a referrer links to its account by `external_account_id`. The `password` column
 * remains physically for rollback safety but is no longer used for auth (nulled by
 * the link migration). Never referred to as the bare `partner` string — that's the
 * RBAC role on User.
 */
class Referrer extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $table = 'referral_partners';

    /**
     * Relationship tier → commission band (percent of collected order value).
     * Mirrors Referral::COMMISSION_TIERS — single source of truth for the bands.
     */
    public const COMMISSION_TIERS = [
        'cold' => 5,
        'warm' => 10,
        'closed' => 15,
    ];

    protected $fillable = [
        'external_account_id',
        'code',
        'name',
        'email',
        'phone',
        'relationship_tier',
        'commission_pct',
        'agreed_terms',
        'status',
        'password',
        'last_login_at',
    ];

    /** The legacy passcode hash never leaves the model. */
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'commission_pct' => 'integer',
            'agreed_terms' => 'boolean',
            'password' => 'hashed',       // legacy column; auth lives on the account now
            'last_login_at' => 'datetime',
        ];
    }

    /** The portal identity (email + passcode + status) — type 'referrer'. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ExternalAccount::class, 'external_account_id');
    }

    /** Every company this referrer has referred. */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referral_partner_id');
    }

    /** Resolve the commission band for a relationship tier (defaults to the cold band). */
    public static function commissionPctFor(string $tier): int
    {
        return self::COMMISSION_TIERS[$tier] ?? self::COMMISSION_TIERS['cold'];
    }

    /** Mint a neutral, collision-free ?ref code. */
    public static function makeUniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (static::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
