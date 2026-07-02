<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // The list endpoint stays lean; everything else (detail/store/update/send)
        // carries the full document + scope payload the builder needs.
        $listRoute = $request->routeIs('admin.quotations.index');

        return [
            'id' => $this->id,
            'reference_code' => $this->reference_code,
            'source' => $this->source,
            'client_id' => $this->client_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'package_key' => $this->package_key,
            'estimate_min_myr' => $this->estimate_min_myr,
            'estimate_max_myr' => $this->estimate_max_myr,
            'estimate_eta_value' => $this->estimate_eta_value,
            'estimate_eta_unit' => $this->estimate_eta_unit,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'viewed_at' => $this->viewed_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'updated_by' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy ? [
                'id' => $this->updatedBy->id,
                'name' => $this->updatedBy->name,
            ] : null),
            'order_id' => $this->whenLoaded('order', fn () => $this->order?->id),
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'referral_partner_id' => $this->referral_partner_id,
            'referrer' => $this->whenLoaded('referrer', fn () => $this->referrer ? [
                'name' => $this->referrer->name,
                'relationship_tier' => $this->referrer->relationship_tier,
                'commission_pct' => $this->referrer->commission_pct,
            ] : null),
            'public_token' => $this->when(! $listRoute, $this->public_token),
            'form_payload' => $this->when(! $listRoute, $this->form_payload),
            'document' => $this->when(! $listRoute, $this->document),
            'addons' => $this->whenLoaded('addons', fn () => $this->addons->map(fn ($a) => [
                'key' => $a->addon_key,
                'label' => $a->addon_label,
                'amount_myr' => $a->amount_myr,
            ])),
        ];
    }
}
