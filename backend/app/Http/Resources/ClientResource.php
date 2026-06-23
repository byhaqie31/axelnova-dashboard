<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'notes' => $this->notes,
            'tags' => $this->tags ?? [],
            'created_at' => $this->created_at?->toISOString(),

            // Counts ride along on the list (withCount); relations on the detail.
            'inquiries_count' => $this->whenCounted('inquiries'),
            'quotations_count' => $this->whenCounted('quotations'),
            'orders_count' => $this->whenCounted('orders'),

            // Slim activity rows for the customer detail — purpose-built so we
            // don't drag whole quotation/order documents into this view.
            'inquiries' => $this->whenLoaded('inquiries', fn () => $this->inquiries->map(fn ($i) => [
                'id' => $i->id,
                'status' => $i->status,
                'project_type' => $i->project_type,
                'quotation_id' => $i->quotation_id,
                'created_at' => $i->created_at?->toISOString(),
            ])),
            'quotations' => $this->whenLoaded('quotations', fn () => $this->quotations->map(fn ($q) => [
                'id' => $q->id,
                'reference_code' => $q->reference_code,
                'status' => $q->status,
                'estimate_max_myr' => $q->estimate_max_myr,
                'submitted_at' => $q->submitted_at?->toISOString(),
            ])),
            'orders' => $this->whenLoaded('orders', fn () => $this->orders->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'final_amount_myr' => $o->final_amount_myr,
                'created_at' => $o->created_at?->toISOString(),
            ])),
        ];
    }
}
