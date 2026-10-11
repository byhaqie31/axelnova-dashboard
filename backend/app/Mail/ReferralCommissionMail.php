<?php

namespace App\Mail;

use App\Models\Referral;
use App\Support\Mail\EmailFormat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Email 17 — sent from the admin commission section once a referral converts:
 * tells the referrer their estimated commission and asks them to reply with
 * the bank details we'll pay it into. Queued like every email (spec §5.7) —
 * its delivery shows in the referral's email history.
 */
class ReferralCommissionMail extends KitMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Referral $referral,
        public readonly float $commission,
    ) {}

    public static function emailType(): string
    {
        return 'referral.commission';
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
            'ratePct' => $this->referral->effectivePct(),
            'cap' => EmailFormat::money(Referral::COMMISSION_CAP_MYR),
            'commission' => EmailFormat::money($this->commission),
        ];
    }
}
