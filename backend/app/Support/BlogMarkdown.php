<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Markdown → safe HTML for blog sections, plus the word-count / reading-time
 * maths. Uses the CommonMark converter Laravel ships (GFM flavour) with raw
 * HTML STRIPPED and unsafe links (javascript:/data:/vbscript:) dropped, so
 * nothing scriptable can reach the public page via v-html.
 */
final class BlogMarkdown
{
    public const WORDS_PER_MINUTE = 200;

    public static function toHtml(string $markdown): string
    {
        return trim(Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ]));
    }

    /**
     * The introduction: inline **bold** / *italic* and paragraph breaks only.
     * Rendered through the same safe converter, then cut down to an allowlist —
     * links, code, headings, lists collapse to their plain text — so the intro
     * stays a styled paragraph, never a second article body.
     */
    public static function introHtml(string $markdown): string
    {
        return trim(strip_tags(self::toHtml($markdown), ['p', 'strong', 'em', 'br']));
    }

    /**
     * Markdown → plain text for surfaces that can't show formatting (cards, the
     * SEO/OG description fallback, the connector list): markers dropped,
     * entities decoded, whitespace collapsed.
     */
    public static function plainText(string $markdown): string
    {
        $text = html_entity_decode(strip_tags(self::toHtml($markdown)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    public static function wordCount(string $markdown): int
    {
        $text = html_entity_decode(strip_tags(self::toHtml($markdown)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * Excerpt + every section body at 200 wpm, never less than 1 minute.
     *
     * @param  array<int, array{body_md?: string|null}>  $sections
     */
    public static function readingMinutes(string $excerpt, array $sections): int
    {
        $words = self::wordCount($excerpt);
        foreach ($sections as $section) {
            $words += self::wordCount((string) ($section['body_md'] ?? ''));
        }

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }
}
