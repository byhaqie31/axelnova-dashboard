<?php

namespace App\Models;

use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * The affiliate entity behind the referral programme — table `referral_partners`.
 * A THIRD authenticatable surface: it implements Authenticatable + HasApiTokens so
 * it can sign in to /partners on its own `referral` guard (config/auth.php), fully
 * isolated from the `users` guard behind /admin + /team. Never referred to as the
 * bare `partner` string — that's the RBAC role on User.
 */
class Referrer extends Authenticatable
{
    use HasApiTokens, HasFactory, RecordsActivity, SoftDeletes;

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

    /** The passcode hash never leaves the model. */
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'commission_pct' => 'integer',
            'agreed_terms' => 'boolean',
            'password' => 'hashed',       // assign the plaintext passcode; it hashes on save
            'last_login_at' => 'datetime',
        ];
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

    /**
     * Mint a login passcode: 8 random digits (CSPRNG), kept easy to read + key in.
     * Returned as a zero-padded string so leading zeros survive. Brute-force is held
     * off by the login throttle on /v1/partner/login, not passcode length.
     */
    public static function makePasscode(): string
    {
        return str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
