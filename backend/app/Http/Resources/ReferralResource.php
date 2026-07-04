<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // New-model anchor: the order reached via the tied quotation (legacy linked
        // order as fallback). Only computed when `quotation` is eager-loaded (detail
        // + partner views) so the list endpoint stays N+1-free.
        $anchor = $this->relationLoaded('quotation') ? $this->orderViaQuotation() : null;
        $rate = $this->effectivePct();
        $collected = (float) ($anchor?->amount_paid_myr ?? 0);
        $contract = (float) ($anchor?->final_amount_myr ?? 0);

        return [
            'id' => $this->id,
            'referral_partner_id' => $this->referral_partner_id,
            'quotation_id' => $this->quotation_id,
            'referrer_name' => $this->referrer_name,
            'referrer_email' => $this->referrer_email,
            'referrer_phone' => $this->referrer_phone,
            'business_name' => $this->business_name,
            'business_contact_name' => $this->business_contact_name,
            'business_email' => $this->business_email,
            'business_phone' => $this->business_phone,
            'relationship_tier' => $this->relationship_tier,
            'commission_tier_pct' => $this->commission_tier_pct,
            'commission_pct' => $this->commission_pct,
            'effective_pct' => $this->effectivePct(),
            'notes' => $this->notes,
            'status' => $this->status,
            'agreed_terms' => $this->agreed_terms,
            'linked_order_id' => $this->linked_order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'order_final_amount_myr' => $this->whenLoaded('order', fn () => $this->order?->final_amount_myr),
            'commission_amount_myr' => $this->whenLoaded('order', fn () => $this->commissionAmount()),
            'quotation_reference' => $this->quotation?->reference_code,
            // Anchor order (via quotation, legacy fallback) + derived commission at the
            // effective rate. earned counts only once converted (deposit collected).
            'anchor_order_id' => $anchor?->id,
            'anchor_order_number' => $anchor?->order_number,
            'contract_myr' => $anchor ? $contract : null,
            'collected_myr' => $anchor ? $collected : null,
            'earned_myr' => ($this->status === 'converted' && $anchor) ? round($collected * $rate / 100, 2) : null,
            'estimated_myr' => $anchor ? round(max(0, $contract - $collected) * $rate / 100, 2) : null,
            'commission_email_sent_at' => $this->commission_email_sent_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
