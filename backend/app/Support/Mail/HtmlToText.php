<?php

namespace App\Support\Mail;

/**
 * Derives an email's plain-text part from its rendered HTML, so every email
 * goes out multipart without hand-written text templates. Tuned for the kit's
 * table layout: cells become two-space columns, rows and paragraphs become
 * blank-line-separated blocks, buttons read "Label → https://…".
 */
final class HtmlToText
{
    public static function convert(string $html): string
    {
        $html = (string) preg_replace('#<(head|style|script)\b.*?</\1>#is', '', $html);
        // The hidden inbox preheader would otherwise lead the text part.
        $html = (string) preg_replace('#<div[^>]*display:\s*none[^>]*>.*?</div>#is', '', $html);
        $html = (string) preg_replace('#<!--.*?-->#s', '', $html);

        $html = (string) preg_replace_callback('#<a\b[^>]*href="([^"]*)"[^>]*>(.*?)</a>#is', function (array $m): string {
            $label = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $label = trim(str_replace("\u{00A0}", ' ', $label));
            $href = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (str_starts_with($href, 'mailto:') || $label === $href || $href === '') {
                return htmlspecialchars($label);
            }

            return htmlspecialchars("{$label} {$href}");
        }, $html);

        $html = (string) preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = (string) preg_replace('#</(p|h[1-6]|tr|li|div|table)>#i', "\n\n", $html);
        $html = (string) preg_replace('#</td>#i', '  ', $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);

        $lines = array_map(
            fn (string $line): string => trim((string) preg_replace('/[ \t]{3,}/', '  ', $line)),
            explode("\n", $text),
        );

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }
}
