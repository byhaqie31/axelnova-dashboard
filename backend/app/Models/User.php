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

    protected $fillable = ['name', 'email', 'password', 'role', 'availability', 'monthly_allowance_myr'];

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
}
