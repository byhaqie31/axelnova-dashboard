<?php

namespace App\Mail;

use App\Models\AccessToken;
use App\Models\ExternalAccount;
use App\Services\Auth\AccessLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Email 18 — a partner's portal access is ready: a one-time "Set up your
 * access" link where they choose their own passcode (Phase 2). Sent when the
 * founder approves a referrer, and again when the founder re-sends set-up.
 * No passcode travels by email any more, so it's queued like every email.
 */
class PartnerPasscodeMail extends KitMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ExternalAccount $account,
        public readonly string $setupUrl,
    ) {}

    public static function emailType(): string
    {
        return 'partner.passcode';
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
            'setupUrl' => $this->setupUrl,
            'expiresIn' => app(AccessLinks::class)->lifetimeLabel(AccessToken::SETUP),
        ];
    }
}
