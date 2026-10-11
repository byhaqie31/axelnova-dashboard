<?php

namespace App\Mail;

use App\Models\AccessToken;
use App\Models\User;
use App\Services\Auth\AccessLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Email 21 — a one-time "Choose a new password" link for a teammate, whether
 * they asked from the team sign-in ("Forgot password") or the founder sent
 * it from the Users screen. Their current password keeps working until they
 * use the link (Phase 2).
 */
class TeamPasswordResetMail extends KitMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $resetUrl,
    ) {}

    public static function emailType(): string
    {
        return 'team.password_reset';
    }

    public static function relatedProperty(): ?string
    {
        return 'user';
    }

    public function viewData(): array
    {
        return [
            'name' => $this->user->name,
            'resetUrl' => $this->resetUrl,
            'expiresIn' => app(AccessLinks::class)->lifetimeLabel(AccessToken::RESET),
        ];
    }
}
