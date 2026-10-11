<?php

namespace App\Mail;

use App\Models\AccessToken;
use App\Models\User;
use App\Services\Auth\AccessLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Email 20 — warm welcome sent to a teammate the moment the founder provisions
 * them on the Users screen, with a one-time "Set your password" link (Phase 2):
 * nobody else ever knows their password. Also re-sent when the founder resends
 * an expired set-up link.
 */
class TeamWelcomeMail extends KitMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $setupUrl,
    ) {}

    public static function emailType(): string
    {
        return 'team.welcome';
    }

    public static function relatedProperty(): ?string
    {
        return 'user';
    }

    public function viewData(): array
    {
        return [
            'name' => $this->user->name,
            'setupUrl' => $this->setupUrl,
            'expiresIn' => app(AccessLinks::class)->lifetimeLabel(AccessToken::SETUP),
        ];
    }
}
