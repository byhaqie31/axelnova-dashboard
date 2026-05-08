<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detailRoute = $request->routeIs('admin.quotations.show');

        return [
            'id' => $this->id,
            'reference_code' => $this->reference_code,
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
            'order_id' => $this->whenLoaded('order', fn () => $this->order?->id),
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'form_payload' => $this->when($detailRoute, $this->form_payload),
            'addons' => $this->whenLoaded('addons', fn () => $this->addons->map(fn ($a) => [
                'key' => $a->addon_key,
                'label' => $a->addon_label,
                'amount_myr' => $a->amount_myr,
            ])),
        ];
    }
}
