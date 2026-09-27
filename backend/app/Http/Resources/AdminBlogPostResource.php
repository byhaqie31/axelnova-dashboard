<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The editable record for /admin/blog — raw Markdown sections (never HTML),
 * status + published_at, and `views` (page_views count) when the list
 * controller has attached it.
 */
class AdminBlogPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'sections' => $this->sections ?? [],
            'cover_image_url' => $this->cover_image_url,
            'cover_image_alt' => $this->cover_image_alt,
            'category' => $this->category,
            'format' => $this->format ?? 'article',
            'tags' => $this->tags ?? [],
            'cta_heading' => $this->cta_heading,
            'cta_body' => $this->cta_body,
            'cta_label' => $this->cta_label,
            'cta_url' => $this->cta_url,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'reading_minutes' => (int) $this->reading_minutes,
            'status' => $this->status,
            'published_at' => $this->published_at?->toISOString(),
            'views' => $this->when(isset($this->views), fn () => (int) $this->views),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
