<?php
namespace App\Services\Referrals;

use App\Models\Inquiry;
use App\Models\Quotation;
use App\Models\Referral;
use App\Models\Referrer;

class ReferralAttributionService
{
    /**
     * Anchor a quotation to the referrer its inquiry came from. Stamps the partner
     * on the quotation and creates a DRAFT referral (or ties an existing claim for
     * the same partner + company — the dedup step). No-op when the inquiry isn't
     * attributed or the partner isn't active.
     */
    public function attribute(Quotation $quotation, Inquiry $inquiry): void
    {
        $partnerId = $inquiry->referral_partner_id;
        if (! $partnerId) {
            return;
        }
        $partner = Referrer::where('id', $partnerId)->where('status', 'active')->first();
        if (! $partner) {
            return;
        }

        $quotation->forceFill(['referral_partner_id' => $partnerId])->saveQuietly();

        // Dedup: reuse an untied claim from this partner for the same company.
        $referral = Referral::where('referral_partner_id', $partnerId)
            ->whereNull('quotation_id')
            ->whereNotIn('status', ['converted', 'rejected'])
            ->where(function ($q) use ($inquiry) {
                $q->where('business_email', $inquiry->email)
                    ->orWhere('business_name', $inquiry->company);
            })
            ->first();

        if (! $referral) {
            $referral = new Referral([
                'referral_partner_id' => $partnerId,
                'referrer_name' => $partner->name,
                'referrer_email' => $partner->email,
                'referrer_phone' => $partner->phone,
                'business_name' => $inquiry->company ?: $inquiry->name,
                'business_contact_name' => $inquiry->name,
                'business_email' => $inquiry->email,
                'business_phone' => $inquiry->phone,
                'relationship_tier' => $partner->relationship_tier,
                'commission_tier_pct' => Referrer::commissionPctFor($partner->relationship_tier),
                'agreed_terms' => true,
            ]);
        }

        $referral->quotation_id = $quotation->id;
        $referral->status = 'draft';
        $referral->save();
        $referral->logActivity('referral.drafted', ['quotation_id' => $quotation->id]);
    }
}
