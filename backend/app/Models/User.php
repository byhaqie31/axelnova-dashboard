<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /** Cockpit tier — enters /admin (founder + trusted partner). */
    public const COCKPIT_ROLES = ['founder', 'partner'];

    /** Workspace tier — everyone with an internal role (enters /team). */
    public const WORKSPACE_ROLES = ['founder', 'partner', 'marketer', 'engineer'];

    protected $fillable = ['name', 'email', 'password', 'role'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** 'cockpit' (founder/partner) or 'workspace' (everyone else). */
    public function tier(): string
    {
        return $this->isCockpit() ? 'cockpit' : 'workspace';
    }

    public function isCockpit(): bool
    {
        return in_array($this->role, self::COCKPIT_ROLES, true);
    }

    public function isFounder(): bool
    {
        return $this->role === 'founder';
    }
}
