<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A studio partner for the public /collaborations pages. `projects` (detail
 * only) and `projects_count` (listing only) cover PUBLIC shared work alone —
 * see CollaborationPartner::publicProjects().
 */
class CollaborationPartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'summary' => $this->summary,
            'story' => $this->story,
            'logo_url' => $this->logo_url,
            'symbol_url' => $this->symbol_url,
            'website_url' => $this->website_url,
            'founder_name' => $this->founder_name,
            'founder_role' => $this->founder_role,
            'location' => $this->location,
            'services' => $this->services ?? [],
            'partner_since' => $this->partner_since?->toDateString(),
            'projects_count' => $this->whenCounted('projects'),
            'projects' => CollaborationProjectResource::collection($this->whenLoaded('publicProjects')),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
