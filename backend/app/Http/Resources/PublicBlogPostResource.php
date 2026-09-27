<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The full article for /blog/{slug}: card fields + sections rendered to safe
 * HTML (App\Support\BlogMarkdown via BlogPost::renderedSections) + toc + the
 * closing CTA (empty fields filled from config/blog.php) + SEO overrides + up to 3 related cards (set by the controller
 * through withRelated()). Raw Markdown never leaves the admin API.
 */
class PublicBlogPostResource extends BlogPostCardResource
{
    private ?Collection $related = null;

    public function withRelated(Collection $related): static
    {
        $this->related = $related;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'sections' => array_map(fn (array $s) => [
                'id' => $s['id'],
                'anchor' => $s['anchor'],
                'heading' => $s['heading'],
                'body_html' => $s['body_html'],
                'image_url' => $s['image_url'],
                'image_alt' => $s['image_alt'],
                'quote' => $s['quote'],
                'quote_by' => $s['quote_by'],
            ], $this->renderedSections()),
            'toc' => $this->toc(),
            // Empty fields fall back to the shared defaults (config/blog.php), one by one.
            'cta_heading' => $this->cta_heading ?: config('blog.cta_defaults.heading'),
            'cta_body' => $this->cta_body ?: config('blog.cta_defaults.body'),
            'cta_label' => $this->cta_label ?: config('blog.cta_defaults.label'),
            'cta_url' => $this->cta_url ?: config('blog.cta_defaults.url'),
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'updated_at' => $this->updated_at?->toISOString(),
            'related' => BlogPostCardResource::collection($this->related ?? collect())->resolve(),
        ];
    }
}
