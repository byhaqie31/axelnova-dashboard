<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Assert;

/**
 * Kit fidelity: our Blade emails must reproduce the owner's design kit
 * (docs/global/email-kit/, copied byte-for-byte to tests/Fixtures/email-kit/
 * because the dev container mounts ./backend only).
 *
 * Comparison ignores what can't matter in a mail client — formatting
 * whitespace between tags, entity spelling (&rarr; vs →) and authoring
 * comments — and keeps everything that can: tags, attributes, inline
 * styles, text, and the MSO conditional comments.
 */
final class KitHtml
{
    /** The kit's text wordmark — replaced by the PNG logo header (spec §5.13). */
    private const KIT_WORDMARK = '<p style="margin: 0; font-size: 15px; line-height: 22px; font-weight: 600; letter-spacing: -0.01em; color: #14161A;">Axel Nova Ventures</p>';

    public static function kit(string $file): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/email-kit/'.$file));
    }

    public static function normalize(string $html): string
    {
        $html = (string) preg_replace('/<!--(?!\[if).*?-->/s', '', $html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = (string) preg_replace('/[ \t\r\n]+/', ' ', $html);
        $html = (string) preg_replace('/>\s+</', '><', $html);

        return trim($html);
    }

    /** The normalized kit file as our renderer must produce it: wordmark → logo header. */
    public static function expected(string $file): string
    {
        $kit = self::normalize(self::kit($file));
        $wordmark = self::normalize(self::KIT_WORDMARK);
        $logo = self::normalize(Blade::render('<x-email::blocks.logo-header />'));

        Assert::assertSame(1, substr_count($kit, $wordmark), "{$file}: kit wordmark not found exactly once");

        return str_replace($wordmark, $logo, $kit);
    }

    /** Line-per-tag diff aid: assertSame on one huge line hides where they differ. */
    public static function assertMatchesKit(string $file, string $rendered, ?string $expected = null): void
    {
        $split = fn (string $s): string => str_replace('><', ">\n<", $s);

        Assert::assertSame(
            $split($expected ?? self::expected($file)),
            $split(self::normalize($rendered)),
            "{$file}: rendered email differs from the design kit",
        );
    }
}
