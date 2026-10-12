<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * One issued access link (set-up or reset) — see App\Services\Auth\AccessLinks.
 * The plaintext token is never stored; `token_hash` is its SHA-256.
 */
class AccessToken extends Model
{
    public const SETUP = 'setup';

    public const RESET = 'reset';

    protected $fillable = ['account_type', 'account_id', 'purpose', 'surface', 'token_hash', 'expires_at', 'used_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /** The user or external account this link belongs to (null once deleted). */
    protected function account(): Attribute
    {
        return Attribute::get(fn () => match ($this->account_type) {
            'user' => User::find($this->account_id),
            'external_account' => ExternalAccount::find($this->account_id),
            default => null,
        });
    }
}
