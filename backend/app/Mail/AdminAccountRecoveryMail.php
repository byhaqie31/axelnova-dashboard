<?php

namespace App\Mail;

use App\Models\AccessToken;
use App\Models\User;
use App\Services\Auth\AccessLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/** Email 32 — the founder's own account recovery: a one-time admin reset link (Phase 2). */
class AdminAccountRecoveryMail extends KitMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $resetUrl,
    ) {}

    public static function emailType(): string
    {
        return 'admin.account_recovery';
    }

    public static function relatedProperty(): ?string
    {
        return 'user';
    }

    public function viewData(): array
    {
        return [
            'resetUrl' => $this->resetUrl,
            'expiresIn' => app(AccessLinks::class)->lifetimeLabel(AccessToken::RESET),
        ];
    }
}
