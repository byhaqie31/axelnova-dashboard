<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InquiryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detailRoute = $request->routeIs('admin.inquiries.show');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'project_type' => $this->project_type,
            'budget_hint' => $this->budget_hint,
            'timeline_hint' => $this->timeline_hint,
            'message' => $this->when($detailRoute, $this->message),
            'source' => $this->source,
            'status' => $this->status,
            'quotation_id' => $this->quotation_id,
            'quotation_reference' => $this->whenLoaded('quotation', fn () => $this->quotation?->reference_code),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
