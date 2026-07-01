<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Referral as seen from the /team workspace (the marketer manages the programme).
 * Carries the referrer/business contact + lifecycle status + tier so the marketer
 * can work leads, but OMITS every money field: the linked order, its final value,
 * and the derived commission amount. Those are cockpit/billing figures — the tier
 * percentage stays because it's programme configuration, not an amount.
 */
class ReferralTeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'referrer_name' => $this->referrer_name,
            'referrer_email' => $this->referrer_email,
            'referrer_phone' => $this->referrer_phone,
            'business_name' => $this->business_name,
            'business_contact_name' => $this->business_contact_name,
            'business_email' => $this->business_email,
            'business_phone' => $this->business_phone,
            'relationship_tier' => $this->relationship_tier,
            'commission_tier_pct' => $this->commission_tier_pct,
            'notes' => $this->notes,
            'status' => $this->status,
            'agreed_terms' => $this->agreed_terms,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
