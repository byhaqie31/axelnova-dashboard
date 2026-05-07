<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_code' => $this->reference_code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'package_key' => $this->form_payload['package_key'] ?? null,
            'estimate_min_myr' => $this->estimate_min_myr,
            'estimate_max_myr' => $this->estimate_max_myr,
            'estimate_weeks' => $this->estimate_weeks,
            'breakdown' => $this->form_payload['breakdown'] ?? [],
            'status' => $this->status,
            'pdf_path' => $this->pdf_path,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'viewed_at' => $this->viewed_at?->toISOString(),
        ];
    }
}
