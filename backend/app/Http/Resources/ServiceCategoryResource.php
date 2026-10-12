<?php

namespace App\Http\Resources;

use App\Support\CatalogMarkdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'icon' => $this->icon,
            // Markdown as written (the admin editor round-trips it), plus the two
            // public renderings — see CatalogMarkdown.
            'description' => $this->description,
            'description_html' => CatalogMarkdown::descriptionHtml((string) $this->description),
            'description_text' => CatalogMarkdown::plainText((string) $this->description),
            'sort_order' => $this->sort_order,
            'active' => $this->active,
            'is_default' => (bool) $this->is_default,
            'packages' => ServicePackageResource::collection($this->whenLoaded('packages')),
            'packages_count' => $this->whenCounted('packages'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
