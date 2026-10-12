<?php

namespace Tests\Feature\Pricing;

use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A service description is Markdown. The public API serves it as safe HTML —
 * bold, italic, paragraphs and lists only (CatalogMarkdown) — plus a plain-text
 * copy for SEO / OG / previews. Plain descriptions written before read the same.
 */
class ServiceDescriptionMarkdownTest extends TestCase
{
    use RefreshDatabase;

    private function service(string $description): array
    {
        ServiceCategory::forceCreate([
            'slug' => 'web', 'name' => 'Websites', 'icon' => 'i-lucide-globe', 'description' => $description, 'active' => true,
        ]);

        return $this->getJson('/api/v1/services')->assertOk()->json('data.0');
    }

    public function test_bold_italic_paragraphs_and_lists_render(): void
    {
        $service = $this->service("Sites built **for your brand**, not a *theme*.\n\nEvery site ships with:\n\n- SEO foundations\n- WhatsApp capture\n\n1. Design\n2. Build");

        $this->assertSame(
            "<p>Sites built <strong>for your brand</strong>, not a <em>theme</em>.</p>\n<p>Every site ships with:</p>\n<ul>\n<li>SEO foundations</li>\n<li>WhatsApp capture</li>\n</ul>\n<ol>\n<li>Design</li>\n<li>Build</li>\n</ol>",
            $service['description_html'],
        );
        $this->assertSame('Sites built for your brand, not a theme. Every site ships with: SEO foundations WhatsApp capture Design Build', $service['description_text']);
        // The admin editor round-trips the Markdown as written.
        $this->assertStringStartsWith('Sites built **for your brand**', $service['description']);
    }

    public function test_anything_else_collapses_to_text_and_nothing_scriptable_survives(): void
    {
        $service = $this->service("## Heading\n\nSee [our work](https://example.com) or [this](javascript:alert(1)).\n\n<script>alert(1)</script><img src=x onerror=alert(1)>\n\n`code`");

        $html = $service['description_html'];
        $this->assertStringNotContainsString('<h2', $html);
        $this->assertStringNotContainsString('<a', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('<p>Heading</p>', $html);
        $this->assertStringContainsString('See our work or this.', $html);
    }

    public function test_a_plain_description_reads_as_before(): void
    {
        $plain = 'From a focused one-page site to a fully custom, multi-language brand site. Built for SMEs, clinics and hotels (24/7 bookings, 11.11 sales).';

        $service = $this->service($plain);

        $this->assertSame("<p>{$plain}</p>", $service['description_html']);
        $this->assertSame($plain, $service['description_text']);
    }
}
