<?php

namespace App\Mail;

use App\Models\Referral;
use App\Support\Mail\EmailFormat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/** Email 15 — thanks to the person who made a referral (not the passcode email). */
class ReferralReceivedMail extends KitMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Referral $referral) {}

    public static function emailType(): string
    {
        return 'referral.received';
    }

    public static function relatedProperty(): ?string
    {
        return 'referral';
    }

    public function viewData(): array
    {
        return [
            'name' => $this->referral->referrer_name,
            'businessName' => $this->referral->business_name,
            'tierPct' => $this->referral->commission_tier_pct,
            'cap' => EmailFormat::money(Referral::COMMISSION_CAP_MYR),
        ];
    }
}
