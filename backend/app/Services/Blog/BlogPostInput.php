<?php

namespace App\Services\Blog;

use App\Models\BlogPost;
use App\Support\BlogMarkdown;
use Illuminate\Validation\Rule;

/**
 * The ONE blog write shape, shared by both writers — the admin editor
 * (Admin\BlogPostsController, full replace) and the MCP connector
 * (Connector\BlogPostController, partial update). rules() validates, derive()
 * turns validated input into columns (slug, normalised sections, tags, format,
 * reading time).
 *
 * Partial mode only touches the keys that were sent: the slug is recomputed
 * only when `slug` itself is sent (a title edit never moves a URL), and the
 * reading time is recomputed from the MERGED excerpt + sections.
 */
class BlogPostInput
{
    /** Absolute http(s) URL or a root-relative path — keeps javascript:/data: out of <img src>. */
    public const URL_RULE = 'regex:#^(https?://|/[^/])#';

    /**
     * @param  bool  $partial  every top-level field becomes `sometimes` (the title stays required when sent)
     * @param  bool  $requireAlt  a section image needs alt text (the connector; the editor keeps it optional)
     */
    public static function rules(bool $partial = false, bool $requireAlt = false): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:120'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            ...self::sectionRules($requireAlt),
            'cover_image_url' => ['nullable', 'string', 'max:500', self::URL_RULE],
            'cover_image_alt' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:60'],
            'format' => ['nullable', 'string', Rule::in(BlogPost::FORMATS)],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:40'],
            'cta_heading' => ['nullable', 'string', 'max:120'],
            'cta_body' => ['nullable', 'string', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'cta_url' => ['nullable', 'string', 'max:500', self::URL_RULE],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
        ];

        if ($partial) {
            foreach ($rules as $field => $fieldRules) {
                if (! str_contains($field, '.')) {
                    $rules[$field] = ['sometimes', ...$fieldRules];
                }
            }
        }

        return $rules;
    }

    public static function sectionRules(bool $requireAlt = false): array
    {
        return [
            'sections' => ['nullable', 'array', 'max:40'],
            'sections.*.id' => ['nullable', 'string', 'max:12'],
            'sections.*.heading' => ['required', 'string', 'max:120'],
            'sections.*.body_md' => ['nullable', 'string', 'max:20000'],
            'sections.*.image_url' => ['nullable', 'string', 'max:500', self::URL_RULE],
            'sections.*.image_alt' => [
                ...($requireAlt ? ['required_with:sections.*.image_url'] : []),
                'nullable', 'string', 'max:160',
            ],
            'sections.*.quote' => ['nullable', 'string', 'max:500'],
            'sections.*.quote_by' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * Validated input → columns. Full mode (the editor) always derives every
     * field; partial mode (the connector) derives only what was sent, merging
     * with `$existing` where a derived value depends on unsent fields.
     */
    public static function derive(array $data, ?BlogPost $existing = null, bool $partial = false): array
    {
        $out = $data;
        $has = fn (string $key) => ! $partial || array_key_exists($key, $data);

        if ($has('sections')) {
            $out['sections'] = BlogPost::normaliseSections($data['sections'] ?? []);
        }
        if ($has('excerpt')) {
            $out['excerpt'] = trim((string) ($data['excerpt'] ?? ''));
        }
        if ($has('tags')) {
            $out['tags'] = BlogTaxonomy::unique($data['tags'] ?? [], sort: false);
        }
        if ($has('format')) {
            $out['format'] = $data['format'] ?? 'article';
        }
        if ($has('slug')) {
            $title = $data['title'] ?? $existing?->title ?? '';
            $slugBase = trim((string) ($data['slug'] ?? '')) !== '' ? $data['slug'] : $title;
            $out['slug'] = BlogPost::uniqueSlug($slugBase, $existing?->id);
        }
        if ($has('excerpt') || $has('sections')) {
            $out['reading_minutes'] = BlogMarkdown::readingMinutes(
                $out['excerpt'] ?? (string) ($existing?->excerpt ?? ''),
                $out['sections'] ?? ($existing?->sections ?? []),
            );
        }

        return $out;
    }
}
