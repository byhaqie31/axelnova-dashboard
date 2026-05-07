<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detailRoute = $request->routeIs('admin.quotations.show')
            || $request->routeIs('admin.orders.show');

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
            'status' => $this->status,
            'project_status' => $this->project_status,
            'project_started_at' => $this->project_started_at?->toISOString(),
            'project_delivered_at' => $this->project_delivered_at?->toISOString(),
            'project_completed_at' => $this->project_completed_at?->toISOString(),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'viewed_at' => $this->viewed_at?->toISOString(),
            'form_payload' => $this->when($detailRoute, $this->form_payload),
            'addons' => $this->whenLoaded('addons', fn () => $this->addons->map(fn ($a) => [
                'key' => $a->addon_key,
                'label' => $a->addon_label,
                'amount_myr' => $a->amount_myr,
            ])),
            'pdf_url' => $this->when(
                $this->pdf_path,
                fn () => Storage::disk('r2')->temporaryUrl($this->pdf_path, now()->addHour())
            ),
        ];
    }
}
