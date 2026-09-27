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
}
