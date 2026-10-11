<?php

namespace App\Mail;

use App\Models\ExternalAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Email 26 — heads-up to the founder that a partner (referrer OR investor)
 * used self-service "forgot passcode" and a fresh passcode was auto-issued to
 * them. Carries NO passcode — it's just a notice. Queued like every email
 * (spec §5.7).
 */
class PartnerResetRequestedMail extends KitMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ExternalAccount $account) {}

    public static function emailType(): string
    {
        return 'partner.reset_alert';
    }

    public static function relatedProperty(): ?string
    {
        return 'account';
    }

    public function viewData(): array
    {
        $account = $this->account;
        $name = $account->displayName();

        return [
            'partnerName' => $name !== '' ? $name : $account->email,
            'rows' => [
                ['Partner', $name !== '' ? $name : '—'],
                ['Account type', ucfirst((string) $account->type)],
                ['Email', $account->email],
                ['Referral code', $account->isReferrer() ? $account->referralCode() : null],
            ],
        ];
    }
}
