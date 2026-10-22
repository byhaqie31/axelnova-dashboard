<?php

namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use App\Services\Blog\BlogPostInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * The shared blog write logic (admin editor + MCP connector). Full derive is
 * the editor's full-replace save; partial derive only touches the keys sent —
 * a title change never moves the URL, and reading time is recomputed from the
 * MERGED excerpt + sections.
 */
class BlogPostInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_derive_fills_every_derived_field(): void
    {
        $out = BlogPostInput::derive(['title' => 'Hello World', 'sections' => [['heading' => 'One', 'body_md' => 'x']]]);

        $this->assertSame('hello-world', $out['slug']);
        $this->assertSame('article', $out['format']);
        $this->assertSame('', $out['excerpt']);
        $this->assertSame([], $out['tags']);
        $this->assertSame(1, $out['reading_minutes']);
        $this->assertMatchesRegularExpression('/^s_[a-z0-9]{6}$/', $out['sections'][0]['id']);
    }

    public function test_partial_title_change_keeps_the_slug(): void
    {
        $post = BlogPost::factory()->create(['title' => 'Old title', 'slug' => 'old-title']);

        $out = BlogPostInput::derive(['title' => 'A brand new title'], $post, partial: true);

        $this->assertSame(['title' => 'A brand new title'], $out);
    }

    public function test_partial_slug_is_recomputed_only_when_sent_and_ignores_its_own_row(): void
    {
        $post = BlogPost::factory()->create(['title' => 'Old title', 'slug' => 'old-title']);
        BlogPost::factory()->create(['slug' => 'taken']);

        $this->assertSame('old-title', BlogPostInput::derive(['slug' => 'old-title'], $post, partial: true)['slug']);
        $this->assertSame('taken-2', BlogPostInput::derive(['slug' => 'taken'], $post, partial: true)['slug']);
        // A blank slug means "from the (merged) title".
        $this->assertSame('old-title', BlogPostInput::derive(['slug' => null], $post, partial: true)['slug']);
    }

    public function test_partial_reading_time_uses_the_merged_excerpt_and_sections(): void
    {
        $post = BlogPost::factory()->create([
            'excerpt' => str_repeat('word ', 400), // 2 minutes on its own
            'sections' => BlogPost::normaliseSections([['heading' => 'A', 'body_md' => 'short']]),
            'reading_minutes' => 2,
        ]);

        $out = BlogPostInput::derive(['sections' => [['heading' => 'B', 'body_md' => str_repeat('word ', 200)]]], $post, partial: true);

        $this->assertSame(3, $out['reading_minutes']); // 400 kept excerpt words + 200 new section words
        $this->assertArrayNotHasKey('excerpt', $out);
        $this->assertArrayNotHasKey('slug', $out);
    }

    public function test_partial_rules_skip_absent_fields_but_reject_a_blank_title(): void
    {
        $rules = BlogPostInput::rules(partial: true);

        $this->assertTrue(Validator::make(['cover_image_alt' => 'A desk'], $rules)->passes());
        $this->assertTrue(Validator::make(['title' => ''], $rules)->fails());
    }

    public function test_require_alt_demands_alt_text_for_a_section_image(): void
    {
        $rules = BlogPostInput::rules(requireAlt: true);
        $section = ['heading' => 'One', 'image_url' => 'https://images.unsplash.com/photo-1'];

        $this->assertTrue(Validator::make(['title' => 'T', 'sections' => [$section]], $rules)->fails());
        $this->assertTrue(Validator::make(['title' => 'T', 'sections' => [[...$section, 'image_alt' => 'A desk']]], $rules)->passes());
        // The editor's rules stay as they were — alt optional.
        $this->assertTrue(Validator::make(['title' => 'T', 'sections' => [$section]], BlogPostInput::rules())->passes());
    }

    public function test_tags_are_trimmed_and_de_duplicated_case_insensitively_keeping_the_first(): void
    {
        $out = BlogPostInput::derive(['title' => 'T', 'tags' => [' Cloudflare ', 'cloudflare', '', 'AI']]);

        $this->assertSame(['Cloudflare', 'AI'], $out['tags']);
    }

    // ── FAQs ────────────────────────────────────────────────────────────────

    public function test_faqs_are_trimmed_stripped_of_tags_and_blank_rows_dropped(): void
    {
        $out = BlogPostInput::derive(['title' => 'T', 'faqs' => [
            ['question' => '  How long does it take?  ', 'answer' => "Two to <b>four</b> weeks.\n"],
            ['question' => '', 'answer' => ''],
            ['question' => null, 'answer' => null],
        ]]);

        $this->assertSame([['question' => 'How long does it take?', 'answer' => 'Two to four weeks.']], $out['faqs']);
        // Full mode (the editor) always derives the list — absent means none.
        $this->assertSame([], BlogPostInput::derive(['title' => 'T'])['faqs']);
    }

    public function test_partial_derive_leaves_faqs_alone_unless_sent(): void
    {
        $post = BlogPost::factory()->create(['faqs' => [['question' => 'Q?', 'answer' => 'A.']]]);

        $this->assertArrayNotHasKey('faqs', BlogPostInput::derive(['title' => 'New'], $post, partial: true));
        $this->assertSame([], BlogPostInput::derive(['faqs' => []], $post, partial: true)['faqs']);
        $this->assertSame([], BlogPostInput::derive(['faqs' => null], $post, partial: true)['faqs']);
        $this->assertSame(
            [['question' => 'Q2?', 'answer' => 'A2.']],
            BlogPostInput::derive(['faqs' => [['question' => 'Q2?', 'answer' => 'A2.']]], $post, partial: true)['faqs'],
        );
    }

    public function test_faq_rules_cap_the_count_and_lengths_and_need_both_halves(): void
    {
        $rules = BlogPostInput::rules();
        $faq = fn (array $o = []) => array_merge(['question' => 'Q?', 'answer' => 'A.'], $o);
        $passes = fn (array $faqs) => Validator::make(['title' => 'T', 'faqs' => $faqs], $rules)->passes();

        $this->assertTrue($passes(array_fill(0, 8, $faq())));
        $this->assertFalse($passes(array_fill(0, 9, $faq())));
        $this->assertFalse($passes([$faq(['question' => str_repeat('q', 201)])]));
        $this->assertFalse($passes([$faq(['answer' => str_repeat('a', 1201)])]));
        // One half on its own is a mistake; a fully blank row is fine (derive drops it).
        $this->assertFalse($passes([$faq(['answer' => null])]));
        $this->assertFalse($passes([$faq(['question' => null])]));
        $this->assertTrue($passes([['question' => null, 'answer' => null]]));
        // Partial mode: absent is fine, sent is still checked.
        $this->assertTrue(Validator::make(['title' => 'T'], BlogPostInput::rules(partial: true))->passes());
        $this->assertTrue(Validator::make(['faqs' => [$faq(['answer' => null])]], BlogPostInput::rules(partial: true))->fails());
    }
}
