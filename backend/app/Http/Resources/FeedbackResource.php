<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedbackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_code' => $this->reference_code,
            'order_id' => $this->order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'client_id' => $this->client_id,
            'name' => $this->name,
            'email' => $this->email,
            'project_label' => $this->project_label,
            'overall' => $this->overall,
            'rating_design' => $this->rating_design,
            'rating_communication' => $this->rating_communication,
            'rating_delivery' => $this->rating_delivery,
            'rating_value' => $this->rating_value,
            'average_rating' => $this->average_rating,
            'nps' => $this->nps,
            'nps_bucket' => $this->nps_bucket,
            'praise' => $this->praise,
            'improve' => $this->improve,
            'publish_consent' => $this->publish_consent,
            'attribution_name' => $this->attribution_name,
            'attribution_role' => $this->attribution_role,
            'status' => $this->status,
            'source' => $this->source,
            'featured' => $this->featured,
            'sort_order' => $this->sort_order,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
