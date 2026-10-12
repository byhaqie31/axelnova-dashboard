<?php

namespace App\Support;

/**
 * A service (category) description is Markdown — written in the admin's rich
 * editor or by the MCP connector. The public site gets it two ways:
 *
 *  - descriptionHtml(): **bold**, *italic*, paragraph breaks and bullet /
 *    numbered lists, through the same safe converter as the blog (raw HTML
 *    stripped, unsafe links dropped), then cut to that allowlist — headings
 *    become plain paragraphs, links and code collapse to their text — so it
 *    stays an intro, never a second page body. Safe for v-html.
 *  - plainText(): no markers, for SEO / OG descriptions and anywhere that
 *    can't show formatting.
 *
 * Plain-text descriptions written before this read exactly as before.
 */
final class CatalogMarkdown
{
    private const DESCRIPTION_TAGS = ['p', 'strong', 'em', 'br', 'ul', 'ol', 'li'];

    public static function descriptionHtml(string $markdown): string
    {
        // A heading becomes a plain paragraph, so its text keeps its own line
        // instead of running into the next one once the tag is stripped.
        $html = (string) preg_replace('#<(/?)h[1-6]\b[^>]*>#i', '<$1p>', BlogMarkdown::toHtml($markdown));

        return trim(strip_tags($html, self::DESCRIPTION_TAGS));
    }

    public static function plainText(string $markdown): string
    {
        return BlogMarkdown::plainText($markdown);
    }
}
