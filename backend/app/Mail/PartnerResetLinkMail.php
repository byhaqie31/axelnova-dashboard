<?php

namespace App\Mail;

use App\Models\AccessToken;
use App\Models\ExternalAccount;
use App\Services\Auth\AccessLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Email 19 — a partner asked to reset their passcode (or the founder sent a
 * reset): a one-time "Choose a new passcode" link. Nothing changes until the
 * partner uses it, so a stranger who knows their email can't lock them out.
 */
class PartnerResetLinkMail extends KitMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ExternalAccount $account,
        public readonly string $resetUrl,
    ) {}

    public static function emailType(): string
    {
        return 'partner.reset_confirm';
    }

    public static function relatedProperty(): ?string
    {
        return 'account';
    }

    public function viewData(): array
    {
        $name = $this->account->displayName();

        return [
            'name' => $name !== '' ? $name : 'there',
            'resetUrl' => $this->resetUrl,
            'expiresIn' => app(AccessLinks::class)->lifetimeLabel(AccessToken::RESET),
        ];
    }
}
