<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralResource extends JsonResource
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
            'linked_order_id' => $this->linked_order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'order_final_amount_myr' => $this->whenLoaded('order', fn () => $this->order?->final_amount_myr),
            'commission_amount_myr' => $this->whenLoaded('order', fn () => $this->commissionAmount()),
            'commission_email_sent_at' => $this->commission_email_sent_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
