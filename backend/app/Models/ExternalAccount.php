<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * The unified partner-portal identity (table `external_accounts`) — the third
 * authenticatable surface, isolated on the `external` guard (config/auth.php).
 * Discriminated by `type` (referrer | investor); the matching PROFILE lives in
 * referral_partners (Referrer) or investors (Investor), each linking back by FK.
 *
 * A token minted here carries the `partner` ability and authenticates ONLY under
 * /v1/partner/* — the `sanctum` guard (provider = users) behind /admin + /team
 * rejects it, and a User token is rejected here.
 */
class ExternalAccount extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'type',
        'email',
        'password',
        'status',
        'last_login_at',
    ];

    /** The passcode hash never leaves the model. */
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',        // assign the plaintext passcode; it hashes on save
            'last_login_at' => 'datetime',
        ];
    }

    /** The referrer profile, when type === 'referrer'. */
    public function referrer(): HasOne
    {
        return $this->hasOne(Referrer::class, 'external_account_id');
    }

    /** The investor profile, when type === 'investor'. */
    public function investor(): HasOne
    {
        return $this->hasOne(Investor::class, 'external_account_id');
    }

    /**
     * Mint a login passcode: 8 random digits (CSPRNG), zero-padded so leading
     * zeros survive. Brute-force is held off by the login throttle, not length.
     */
    public static function makePasscode(): string
    {
        return str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
    }

    public function isReferrer(): bool
    {
        return $this->type === 'referrer';
    }

    public function isInvestor(): bool
    {
        return $this->type === 'investor';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** The profile display name for whichever type this account is. */
    public function displayName(): string
    {
        return $this->isReferrer()
            ? (string) ($this->referrer?->name ?? '')
            : (string) ($this->investor?->name ?? '');
    }

    /** Referrer link code, or null for investor accounts. */
    public function referralCode(): ?string
    {
        return $this->isReferrer() ? $this->referrer?->code : null;
    }
}
