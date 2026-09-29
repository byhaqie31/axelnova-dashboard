<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Services\Blog\BlogPostInput;
use App\Services\Blog\BlogTaxonomy;

/**
 * The writing guide, built from config/blog.php — the one copy the admin editor
 * and Claude (MCP connector) both read, so the voice rules can't drift between
 * them. forEditor() is base() plus the categories/topics in use (the editor's
 * pickers); forConnector() adds what Claude needs to write a valid draft without
 * guessing (the same in-use lists, the section shape, image rules, and the two
 * drafting modes).
 */
class BlogGuide
{
    /** @return array{voice: list<string>, structure: list<string>, cta_defaults: array<string, string>, formats: list<string>} */
    public static function base(): array
    {
        return [
            'voice' => config('blog.voice', []),
            'structure' => config('blog.structure', []),
            'cta_defaults' => config('blog.cta_defaults', []),
            'formats' => BlogPost::FORMATS,
        ];
    }

    /** @return array<string, mixed> base() + the categories/tags in use (BlogTaxonomy) */
    public static function forEditor(): array
    {
        return [...self::base(), ...BlogTaxonomy::inUse()];
    }

    public static function forConnector(): array
    {
        return [
            ...self::base(),
            ...BlogTaxonomy::inUse(),
            'modes' => [
                'write' => 'Asked to write about a topic or brief: write it in the voice above, following the structure — the excerpt is the hook, then 2–4 practical sections and a key takeaway.',
                'structure_only' => 'Given the user\'s own text: keep their wording verbatim. Only arrange it (first heading or line → title, text before the first section heading → excerpt, each heading → a section) and fill what is missing (image alt text, format, category, tags, SEO). Rewrite only when asked, and list any change you made in your reply.',
            ],
            'excerpt_format' => 'Markdown, inline only: **bold** and *italic* (use sparingly — one or two emphases at most). No links, headings, lists or code; they are flattened to plain text. Cards and search snippets show it unformatted.',
            'section_shape' => [
                'id' => 'Stable s_xxxxxx id. Keep it when editing an existing section; omit it for a new one.',
                'heading' => 'Required, ≤ 120 characters. Becomes the H2 and the "On this page" anchor.',
                'body_md' => 'Markdown, ≤ 20000 characters. Bold, italic, links, ### subheadings, lists, > quotes, code. Raw HTML is stripped.',
                'image_url' => 'Optional in-section image (see image_rules).',
                'image_alt' => 'Required when image_url is set, ≤ 160 characters.',
                'quote' => 'Optional pull quote, ≤ 500 characters.',
                'quote_by' => 'Optional quote attribution, ≤ 80 characters.',
            ],
            'limits' => [
                'title' => 160,
                'excerpt' => 500,
                'sections' => 40,
                'tags' => 10,
                'tag_length' => 40,
                'category' => 60,
                'seo_title' => 70,
                'seo_description' => 160,
            ],
            'image_rules' => [
                'Only use image URLs the user supplied — never search for, guess, or invent one.',
                'A URL must start with https:// (or be a root-relative site path like /images/x.jpg).',
                'Always write alt text describing the image for someone who cannot see it.',
                'For images.unsplash.com links, sizing params like ?w=1600&q=80&auto=format are fine to add.',
            ],
            'cta_note' => 'Leave cta_* empty to use cta_defaults. Never repeat the closing call to action inside a section.',
            'url_rule' => BlogPostInput::URL_RULE,
        ];
    }
}
