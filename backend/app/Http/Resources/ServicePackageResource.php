<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicePackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_category_id' => $this->service_category_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'price_min_myr' => $this->price_min_myr,
            'price_max_myr' => $this->price_max_myr,
            'unit' => $this->unit,
            'duration_text' => $this->duration_text,
            'eta_value' => $this->eta_value,
            'eta_unit' => $this->eta_unit,
            'revisions' => $this->revisions,
            'featured' => $this->featured,
            'features' => $this->features,
            'cta' => $this->cta,
            'quote_key' => $this->quote_key,
            'sort_order' => $this->sort_order,
            'active' => $this->active,
            'category' => new ServiceCategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
