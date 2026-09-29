<?php

namespace App\Http\Resources;

use App\Support\BlogMarkdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The index-card / related-card shape — everything a listing needs, no body. */
class BlogPostCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            // Plain text — the stored intro is Markdown (inline bold/italic), and
            // cards / the SEO fallback can't show formatting. Articles also get
            // excerpt_html (PublicBlogPostResource).
            'excerpt' => BlogMarkdown::plainText((string) $this->excerpt),
            'cover_image_url' => $this->cover_image_url,
            'cover_image_alt' => $this->cover_image_alt,
            'category' => $this->category,
            'format' => $this->format ?? 'article',
            'tags' => $this->tags ?? [],
            'reading_minutes' => (int) $this->reading_minutes,
            'published_at' => $this->published_at?->toISOString(),
        ];
    }
}
