<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'long_description' => $this->long_description,
            'status' => $this->status,
            'url' => $this->url,
            'repo' => $this->repo,
            'tags' => $this->tags ?? [],
            'stack' => $this->stack ?? [],
            'featured' => $this->featured,
            'sort_order' => $this->sort_order,
            'cover_image_url' => $this->cover_image_url,
            'active' => $this->active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
