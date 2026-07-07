<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /** Cockpit tier — enters /admin (founder-only; the `partner` RBAC role was dropped). */
    public const COCKPIT_ROLES = ['founder'];

    /**
     * Workspace tier — everyone with an internal role (enters /team). Founder
     * deliberately stays here even though the cockpit already covers them: the
     * admin→team `teamSession` token exchange (Admin\AuthController@teamSession)
     * lets the founder preview the team workspace, and `role:workspace` gates
     * on this list.
     */
    public const WORKSPACE_ROLES = ['founder', 'marketer', 'engineer'];

    protected $fillable = [
        'name', 'email', 'password', 'role', 'availability', 'monthly_allowance_myr',
        // Teammate-filled profile (self-serve on /team/profile) — contact, bank, address.
        'phone', 'bank_name', 'bank_account_number', 'bank_account_holder',
        'address_line1', 'address_line2', 'city', 'postcode', 'state', 'country',
    ];

    /**
     * Profile fields a teammate must fill for their record to count as complete —
     * key => human label. `address_line2` is deliberately optional. Drives the
     * completeness flag on /team/me and the onboarding to-do on the team home.
     */
    public const PROFILE_REQUIRED = [
        'phone' => 'Phone number',
        'bank_name' => 'Bank name',
        'bank_account_number' => 'Bank account number',
        'bank_account_holder' => 'Account holder name',
        'address_line1' => 'Address',
        'city' => 'City',
        'postcode' => 'Postcode',
        'state' => 'State',
        'country' => 'Country',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'monthly_allowance_myr' => 'integer',
            'deactivated_at' => 'datetime',
        ];
    }

    /** 'cockpit' (founder) or 'workspace' (marketer/engineer). */
    public function tier(): string
    {
        return $this->isCockpit() ? 'cockpit' : 'workspace';
    }

    public function isCockpit(): bool
    {
        return in_array($this->role, self::COCKPIT_ROLES, true);
    }

    /** Any internal role — the /team workspace admits both tiers (founder…engineer). */
    public function isWorkspace(): bool
    {
        return in_array($this->role, self::WORKSPACE_ROLES, true);
    }

    public function isFounder(): bool
    {
        return $this->role === 'founder';
    }

    /** Set by the founder via /admin/users (Task 8) — a persistent lockout, not just a signed-out session. */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /** Human labels of the required profile fields still blank (empty = complete). */
    public function profileMissing(): array
    {
        return collect(self::PROFILE_REQUIRED)
            ->reject(fn (string $label, string $key) => filled($this->{$key}))
            ->values()
            ->all();
    }

    /** True once every required profile field is filled. */
    public function profileComplete(): bool
    {
        return $this->profileMissing() === [];
    }
}
