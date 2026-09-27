<?php

namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use App\Support\BlogMarkdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The blog's model-level rules: Markdown renders to SAFE HTML (raw HTML and
 * javascript: links can never reach the public page), reading time is
 * excerpt + sections at 200 wpm (never 0), slugs are unique across all rows
 * including soft-deleted ones (the DB unique index is absolute), and TOC
 * anchors stay unique within a post.
 */
class BlogPostModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_render_converts_markdown_to_html(): void
    {
        $html = BlogMarkdown::toHtml("Do customers **ask** for the same updates?\n\n- one\n- two");

        $this->assertStringContainsString('<strong>ask</strong>', $html);
        $this->assertStringContainsString('<ul>', $html);
    }

    public function test_render_strips_raw_html_and_unsafe_links(): void
    {
        // A line that STARTS with <script> is a whole CommonMark HTML block, so it
        // vanishes as one unit; the inline <img> and javascript: link sit in a
        // normal paragraph and must be neutralised individually.
        $html = BlogMarkdown::toHtml("<script>alert(1)</script>\n\nHello [x](javascript:alert(1)) <img src=x onerror=alert(1)>");

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('Hello', $html);
    }

    public function test_reading_minutes_counts_excerpt_and_sections_at_200_wpm(): void
    {
        $words = implode(' ', array_fill(0, 350, 'word'));

        $minutes = BlogMarkdown::readingMinutes('short intro', [
            ['heading' => 'A', 'body_md' => $words],
            ['heading' => 'B', 'body_md' => '**bold** and _italic_'],
        ]);

        $this->assertSame(2, $minutes); // 2 + 350 + 3 = 355 words → ceil(1.775)
        $this->assertSame(1, BlogMarkdown::readingMinutes('', [])); // never 0
    }

    public function test_unique_slug_suffixes_on_collision_including_soft_deleted(): void
    {
        $first = BlogPost::factory()->create(['slug' => 'customer-portal']);
        $deleted = BlogPost::factory()->create(['slug' => 'customer-portal-2']);
        $deleted->delete();

        $this->assertSame('customer-portal-3', BlogPost::uniqueSlug('Customer Portal!'));
        // Self is ignored, so re-saving a post keeps its own slug.
        $this->assertSame('customer-portal', BlogPost::uniqueSlug('customer portal', $first->id));
    }

    public function test_toc_ids_are_unique_within_a_post(): void
    {
        $post = BlogPost::factory()->create(['sections' => BlogPost::normaliseSections([
            ['heading' => 'Keep the human part', 'body_md' => 'a'],
            ['heading' => 'Keep the human part', 'body_md' => 'b'],
        ])]);

        $this->assertSame([
            ['id' => 'keep-the-human-part', 'heading' => 'Keep the human part'],
            ['id' => 'keep-the-human-part-2', 'heading' => 'Keep the human part'],
        ], $post->toc());

        $rendered = $post->renderedSections();
        $this->assertStringContainsString('<p>a</p>', $rendered[0]['body_html']);
        $this->assertSame('keep-the-human-part-2', $rendered[1]['anchor']);
    }

    public function test_normalise_sections_assigns_ids_and_nulls_empties(): void
    {
        $sections = BlogPost::normaliseSections([
            ['heading' => ' Notice ', 'body_md' => 'x', 'image_url' => '', 'quote' => '  '],
        ]);

        $this->assertMatchesRegularExpression('/^s_[a-z0-9]{6}$/', $sections[0]['id']);
        $this->assertSame('Notice', $sections[0]['heading']);
        $this->assertNull($sections[0]['image_url']);
        $this->assertNull($sections[0]['quote']);
        $this->assertArrayHasKey('quote_by', $sections[0]);
    }

    public function test_normalise_sections_keeps_a_valid_existing_id(): void
    {
        $sections = BlogPost::normaliseSections([['id' => 's_ab12cd', 'heading' => 'H', 'body_md' => 'x']]);

        $this->assertSame('s_ab12cd', $sections[0]['id']);
    }
}
