<?php

namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use App\Models\User;
use App\Support\BlogMarkdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The introduction (`excerpt`) is stored as Markdown limited to inline **bold**
 * and *italic*. The article renders it through a tight allowlist (strong/em/p/br
 * only); every plain-text surface — cards, the SEO/OG fallback, the connector's
 * slim list — gets the markers stripped. The admin editor keeps the raw Markdown.
 */
class BlogExcerptFormattingTest extends TestCase
{
    use RefreshDatabase;

    private const EXCERPT = 'WhatsApp is **convenient**. Most customers *already* know it.';

    public function test_intro_html_keeps_only_bold_italic_and_paragraphs(): void
    {
        $html = BlogMarkdown::introHtml("Hi **there** and *you*.\n\nSecond [link](https://evil.test) `code` <u>under</u> <script>x</script>");

        $this->assertStringContainsString('<strong>there</strong>', $html);
        $this->assertStringContainsString('<em>you</em>', $html);
        $this->assertSame(2, substr_count($html, '<p>'), 'paragraph breaks survive');
        foreach (['<a', 'href', '<code', '<u>', '<script'] as $banned) {
            $this->assertStringNotContainsString($banned, $html);
        }
        $this->assertStringContainsString('link', $html, 'a stripped link keeps its text');
    }

    public function test_plain_text_drops_the_markers(): void
    {
        $this->assertSame(
            'WhatsApp is convenient. Most customers already know it.',
            BlogMarkdown::plainText(self::EXCERPT),
        );
        $this->assertSame('Tom & Jerry', BlogMarkdown::plainText('Tom & **Jerry**'), 'entities decode back to text');
    }

    public function test_public_article_serves_html_intro_and_plain_excerpt(): void
    {
        $post = BlogPost::factory()->published()->create(['excerpt' => self::EXCERPT]);

        $this->getJson("/api/v1/blog/posts/{$post->slug}")
            ->assertOk()
            ->assertJsonPath('data.excerpt', 'WhatsApp is convenient. Most customers already know it.')
            ->assertJsonPath('data.excerpt_html', '<p>WhatsApp is <strong>convenient</strong>. Most customers <em>already</em> know it.</p>');
    }

    public function test_listing_cards_get_the_plain_excerpt(): void
    {
        BlogPost::factory()->published()->create(['excerpt' => self::EXCERPT]);

        $this->getJson('/api/v1/blog/posts')
            ->assertOk()
            ->assertJsonPath('data.0.excerpt', 'WhatsApp is convenient. Most customers already know it.');
    }

    public function test_admin_keeps_raw_markdown_and_preview_returns_intro_html(): void
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}"];
        $post = BlogPost::factory()->create(['excerpt' => self::EXCERPT]);

        $this->getJson("/api/v1/admin/blog/posts/{$post->id}", $headers)
            ->assertOk()
            ->assertJsonPath('data.excerpt', self::EXCERPT);

        $this->postJson('/api/v1/admin/blog/render', [
            'excerpt' => self::EXCERPT,
            'sections' => [['heading' => 'One', 'body_md' => 'Body text.']],
        ], $headers)
            ->assertOk()
            ->assertJsonPath('excerpt_html', '<p>WhatsApp is <strong>convenient</strong>. Most customers <em>already</em> know it.</p>');
    }

    public function test_connector_list_shows_the_plain_excerpt(): void
    {
        $founder = User::factory()->founder()->create();
        $token = $founder->createToken('mcp-connector', ['connector:read'])->plainTextToken;
        BlogPost::factory()->create(['excerpt' => self::EXCERPT]);

        $this->getJson('/api/v1/connector/blog/posts', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.0.excerpt', 'WhatsApp is convenient. Most customers already know it.');
    }
}
