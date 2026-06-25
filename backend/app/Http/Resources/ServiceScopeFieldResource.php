<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceScopeFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_category_id' => $this->service_category_id,
            'field_key' => $this->field_key,
            'label' => $this->label,
            'type' => $this->type,
            // Raw array ([] = all packages). The builder reads the engine-normalised
            // 'all' from the cached config; the admin editor reads this raw shape.
            'applies_to' => $this->applies_to ?? [],
            'config' => $this->config ?? [],
            'sort_order' => $this->sort_order,
            'active' => $this->active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
