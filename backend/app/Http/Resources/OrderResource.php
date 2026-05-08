<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'quotation_id' => $this->quotation_id,
            'client_id' => $this->client_id,
            'reference_code' => $this->whenLoaded('quotation', fn () => $this->quotation?->reference_code),
            'package_key' => $this->whenLoaded('quotation', fn () => $this->quotation?->package_key),
            'estimate_eta_value' => $this->whenLoaded('quotation', fn () => $this->quotation?->estimate_eta_value),
            'estimate_eta_unit' => $this->whenLoaded('quotation', fn () => $this->quotation?->estimate_eta_unit),
            'submitted_at' => $this->whenLoaded('quotation', fn () => $this->quotation?->submitted_at?->toISOString()),
            'name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'email' => $this->whenLoaded('client', fn () => $this->client?->email),
            'phone' => $this->whenLoaded('client', fn () => $this->client?->phone),
            'company' => $this->whenLoaded('client', fn () => $this->client?->company),
            'value_min_myr' => $this->value_min_myr,
            'value_max_myr' => $this->value_max_myr,
            'status' => $this->status,
            'started_at' => $this->started_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
