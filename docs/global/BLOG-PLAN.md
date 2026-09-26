# Blog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A database-backed blog the founder writes and publishes from `/admin/blog`, rendered on the public site at `/blog` and `/blog/{slug}`, with no code change per article.

**Architecture:** One `blog_posts` table with scalar columns plus a `sections` JSON column (ordered list of heading + Markdown body + optional image / quote). Markdown is the storage format; the backend renders it to safe HTML with the CommonMark converter Laravel already ships (`Str::markdown`, `html_input => 'strip'`, `allow_unsafe_links => false`). The public Nuxt pages only ever `v-html` backend-rendered output. The admin editor uses Nuxt UI's `UEditor` in `content-type="markdown"` mode and previews through the same backend renderer.

**Tech Stack:** Laravel 11 (PHP 8.4, Sanctum, PHPUnit on MySQL), Nuxt 4 (Vue 3 + @nuxt/ui 4.9 with the bundled Tiptap editor, @nuxtjs/sitemap 7). Everything runs in Docker (`axelnova-backend-dev`, `axelnova-frontend-dev`).

**Spec:** [BLOG-DESIGN.md](./BLOG-DESIGN.md)

## Global Constraints

- **Branch:** all work stacks on `feat/task-payment-in-payroll` (PR #50). **Commits on request only** — never commit or push without the founder's explicit go-ahead for that commit.
- **DATABASE PROTECTION:** never run `migrate:fresh` / `migrate:refresh` / `migrate:reset` / `migrate:rollback` / `db:wipe` / `docker compose down -v` / `TRUNCATE` / `DROP` against the dev or prod DB. Additive `php artisan migrate` is allowed. PHPUnit is safe (`phpunit.xml` forces `axelnova_dashboard_test`).
- **New migration** is dated `2026_09_26_000002_create_blog_posts_table.php` (after `2026_09_26_000001_backfill_payroll_entries_for_ad_hoc_paid_tasks.php`).
- **Zero new dependencies** — Markdown editing comes from `@nuxt/ui`'s `UEditor` (`@tiptap/markdown` is already bundled), rendering from `Illuminate\Support\Str::markdown`.
- **Column limits (validation mirrors them):** title ≤ 160, slug ≤ 120, excerpt ≤ 500, section heading ≤ 120, section `body_md` ≤ 20 000, image/CTA URLs ≤ 500, `cover_image_alt`/`image_alt` ≤ 160, category ≤ 60, ≤ 10 tags of ≤ 40, `cta_heading` ≤ 120, `cta_body` ≤ 500, `cta_label` ≤ 60, `seo_title` ≤ 70, `seo_description` ≤ 160, quote ≤ 500, `quote_by` ≤ 80.
- **URL fields** accept an absolute `http(s)://` URL or a root-relative path (`regex:#^(https?://|/[^/])#`), matching `projects.cover_image_url`.
- **Reading time** = `max(1, ceil(words / 200))` over excerpt + all section bodies, Markdown stripped.
- **Publish gate:** title, excerpt, and ≥ 1 section with non-blank heading + body. Drafts may be incomplete.
- **Frontend conventions:** CSS variables only (never hardcode hex; never bind layout backgrounds to `colorMode`), `<UIcon name="i-lucide-…">`, `useScrollReveal('.reveal')` for public reveal, `useApiBase()` for public fetches, `useAdminAuth().apiFetch` for admin. Verify light + dark. New public pages live under `pages/public/…` (the `stripPublicPrefix` hook maps them to `/blog`).
- **Navbar order:** `Home · About · Company · Blog · Projects · Services · Partners · Contact` in both `layouts/public.vue` and `components/public/HeroEpoch.vue` (they must stay in sync).
- **Docs:** every `.md` under `docs/`, ALL-CAPS-WITH-HYPHENS names.

## Review Focus

1. **A published post whose slug is edited** — the old URL 404s (no redirect table). The editor must warn; the backend must still accept the change. Pinned in Task 3 (`test_update_can_change_a_published_slug`).
2. **Markdown containing raw HTML or `javascript:` links** — must never reach the public HTML. Pinned in Task 1 (`test_render_strips_raw_html_and_unsafe_links`).
3. **Two sections with the same heading** — TOC anchors must stay unique or the second link jumps to the first section. Pinned in Task 1 (`test_toc_ids_are_unique_within_a_post`).
4. **A draft requested by slug publicly, and a soft-deleted post** — both must 404, not leak. Pinned in Task 2 (`test_a_draft_or_deleted_post_is_404`).
5. **Title collisions on create** — two posts titled the same must get distinct slugs, including against a soft-deleted post holding the slug (DB unique index is absolute). Pinned in Task 1 (`test_unique_slug_suffixes_on_collision_including_soft_deleted`).

---

## File Structure

```
backend/
  database/migrations/2026_09_26_000002_create_blog_posts_table.php   (new)
  database/factories/BlogPostFactory.php                               (new)
  app/Support/BlogMarkdown.php            (new)  toHtml(), wordCount(), readingMinutes()
  app/Models/BlogPost.php                 (new)  casts, scopes, uniqueSlug(), toc(), renderedSections(), normaliseSections()
  app/Http/Resources/BlogPostCardResource.php     (new)  public card fields
  app/Http/Resources/PublicBlogPostResource.php   (new)  card + rendered sections + toc + cta + seo + related
  app/Http/Resources/AdminBlogPostResource.php    (new)  editable fields + status + views
  app/Http/Controllers/Api/V1/PublicBlogController.php       (new)  index, show, slugs
  app/Http/Controllers/Api/V1/Admin/BlogPostsController.php  (new)  index, store, show, update, publish, unpublish, destroy, render
  routes/api.php                          (mod)  public + admin blog routes
  tests/Feature/Blog/BlogPostModelTest.php     (new)
  tests/Feature/Blog/PublicBlogTest.php        (new)
  tests/Feature/Blog/AdminBlogPostsTest.php    (new)
frontend/
  nuxt.config.ts                          (mod)  routeRules /blog swr, sitemap.sources
  server/api/__sitemap__/urls.ts          (new)  published post URLs for the sitemap
  app/assets/css/main.css                 (mod)  .blog-prose + .blog-toc styles
  app/data/blog.ts                        (new)  types, template, markdown import parser, reading time, voice guide, slugify
  app/data/adminNav.ts                    (mod)  Catalog → Blog
  app/layouts/public.vue                  (mod)  Blog after Company
  app/components/public/HeroEpoch.vue     (mod)  Blog after Company (mirror)
  app/components/public/BlogCard.vue      (new)
  app/components/public/BlogToc.vue       (new)
  app/components/public/BlogArticle.vue   (new)  shared by /blog/[slug] and the admin preview
  app/components/admin/BlogSectionEditor.vue  (new)  one section card (heading + UEditor + image/quote + move/remove)
  app/pages/public/blog/index.vue         (new)
  app/pages/public/blog/[slug].vue        (new)
  app/pages/admin/blog/index.vue          (new)
  app/pages/admin/blog/[id].vue           (new)  new + edit + preview
docs/
  global/BLOG.md                          (new)  reference + "writing your next post"
  global/ARCHITECTURE.md, global/PLATFORM-OVERVIEW.md, frontend/ADMIN-COMPONENTS.md,
  frontend/PUBLIC-COMPONENTS.md, frontend/UI-STANDARDS.md                 (mod)
```

Test command (inside the container, MySQL test DB):

```bash
docker compose -f docker-compose.dev.yml exec -T backend vendor/bin/phpunit tests/Feature/Blog
docker compose -f docker-compose.dev.yml exec -T backend vendor/bin/pint --test <files>
docker compose -f docker-compose.dev.yml exec -T frontend npx eslint <files>
docker compose -f docker-compose.dev.yml exec -T frontend npm run typecheck
```

---

### Task 1: Schema, model, Markdown renderer

**Files:**
- Create: `backend/database/migrations/2026_09_26_000002_create_blog_posts_table.php`
- Create: `backend/database/factories/BlogPostFactory.php`
- Create: `backend/app/Support/BlogMarkdown.php`
- Create: `backend/app/Models/BlogPost.php`
- Test: `backend/tests/Feature/Blog/BlogPostModelTest.php`

**Interfaces:**
- Produces: `BlogMarkdown::toHtml(string $md): string`, `BlogMarkdown::wordCount(string $md): int`, `BlogMarkdown::readingMinutes(string $excerpt, array $sections): int`
- Produces: `BlogPost` model — `const STATUS_DRAFT = 'draft'`, `STATUS_PUBLISHED = 'published'`; `scopePublished()`; `isPublished(): bool`; `static uniqueSlug(string $base, ?int $ignoreId = null): string`; `static normaliseSections(array $raw): array`; `toc(): array` (`[{id, heading}]`); `renderedSections(): array` (sections + `body_html`); `creator()` relation; `RecordsActivity` trait.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// backend/tests/Feature/Blog/BlogPostModelTest.php
namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use App\Support\BlogMarkdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
        $html = BlogMarkdown::toHtml("<script>alert(1)</script>Hello [x](javascript:alert(1)) <img src=x onerror=alert(1)>");
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
        $this->assertSame(2, $minutes);                 // 355 words → ceil(1.775)
        $this->assertSame(1, BlogMarkdown::readingMinutes('', []));   // never 0
    }

    public function test_unique_slug_suffixes_on_collision_including_soft_deleted(): void
    {
        BlogPost::factory()->create(['slug' => 'customer-portal']);
        $deleted = BlogPost::factory()->create(['slug' => 'customer-portal-2']);
        $deleted->delete();

        $this->assertSame('customer-portal-3', BlogPost::uniqueSlug('Customer Portal!'));
        $this->assertSame('customer-portal', BlogPost::uniqueSlug('customer portal', BlogPost::first()->id)); // self is ignored
    }

    public function test_toc_ids_are_unique_within_a_post(): void
    {
        $post = BlogPost::factory()->create(['sections' => BlogPost::normaliseSections([
            ['heading' => 'Keep the human part', 'body_md' => 'a'],
            ['heading' => 'Keep the human part', 'body_md' => 'b'],
        ])]);

        $this->assertSame(
            [['id' => 'keep-the-human-part', 'heading' => 'Keep the human part'], ['id' => 'keep-the-human-part-2', 'heading' => 'Keep the human part']],
            $post->toc(),
        );
        $this->assertStringContainsString('<p>a</p>', $post->renderedSections()[0]['body_html']);
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
}
```

- [ ] **Step 2: Run to verify failure** — `vendor/bin/phpunit tests/Feature/Blog/BlogPostModelTest.php` → errors: class `App\Support\BlogMarkdown` / `App\Models\BlogPost` not found.

- [ ] **Step 3: Migration**

```php
<?php
// 2026_09_26_000002_create_blog_posts_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The blog (see docs/global/BLOG.md). One row per article; `sections` is the
 * ordered list of {id, heading, body_md, image_url, image_alt, quote, quote_by}
 * — Markdown is the storage format, rendered to safe HTML on read by
 * App\Support\BlogMarkdown. Images are URLs only. Soft-deletes; the slug unique
 * index is absolute (includes deleted rows), so BlogPost::uniqueSlug checks
 * withTrashed().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 160);
            $table->text('excerpt');
            $table->json('sections');
            $table->string('cover_image_url', 500)->nullable();
            $table->string('cover_image_alt', 160)->nullable();
            $table->string('category', 60)->nullable();
            $table->json('tags');
            $table->string('cta_heading', 120)->nullable();
            $table->text('cta_body')->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 160)->nullable();
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
```

- [ ] **Step 4: `BlogMarkdown` support class**

```php
<?php
namespace App\Support;

use Illuminate\Support\Str;

/** Markdown → safe HTML for blog sections, plus the word-count / reading-time maths. */
final class BlogMarkdown
{
    public const WORDS_PER_MINUTE = 200;

    /** GFM render with raw HTML stripped and unsafe (javascript:/data:) links dropped. */
    public static function toHtml(string $markdown): string
    {
        return trim(Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ]));
    }

    public static function wordCount(string $markdown): int
    {
        $text = html_entity_decode(strip_tags(self::toHtml($markdown)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY));
    }

    /** @param array<int, array{body_md?: string|null}> $sections */
    public static function readingMinutes(string $excerpt, array $sections): int
    {
        $words = self::wordCount($excerpt);
        foreach ($sections as $section) {
            $words += self::wordCount((string) ($section['body_md'] ?? ''));
        }

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }
}
```

- [ ] **Step 5: Model**

```php
<?php
namespace App\Models;

use App\Support\BlogMarkdown;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'slug', 'title', 'excerpt', 'sections', 'cover_image_url', 'cover_image_alt', 'category', 'tags',
        'cta_heading', 'cta_body', 'cta_label', 'cta_url', 'seo_title', 'seo_description',
        'reading_minutes', 'status', 'published_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'sections' => 'array',
        'tags' => 'array',
        'reading_minutes' => 'integer',
        'published_at' => 'datetime',
    ];

    protected $attributes = ['status' => self::STATUS_DRAFT, 'tags' => '[]', 'sections' => '[]'];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)->whereNotNull('published_at');
    }

    public function isPublished(): bool { return $this->status === self::STATUS_PUBLISHED; }

    /** Slug from a title, unique across ALL rows (the unique index counts soft-deleted ones). */
    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $root = Str::limit(Str::slug($base), 110, '') ?: 'post';
        $candidate = $root;
        for ($n = 2; static::withTrashed()->where('slug', $candidate)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $n++) {
            $candidate = "{$root}-{$n}";
        }

        return $candidate;
    }

    /** Trim strings, null empties, give every section a stable id. */
    public static function normaliseSections(array $raw): array
    {
        $clean = fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null;

        return array_values(array_map(fn (array $s) => [
            'id' => is_string($s['id'] ?? null) && preg_match('/^s_[a-z0-9]{6}$/', $s['id']) ? $s['id'] : 's_'.Str::lower(Str::random(6)),
            'heading' => $clean($s['heading'] ?? null) ?? '',
            'body_md' => is_string($s['body_md'] ?? null) ? trim($s['body_md']) : '',
            'image_url' => $clean($s['image_url'] ?? null),
            'image_alt' => $clean($s['image_alt'] ?? null),
            'quote' => $clean($s['quote'] ?? null),
            'quote_by' => $clean($s['quote_by'] ?? null),
        ], $raw));
    }

    /** @return array<int, array{id: string, heading: string}> anchors, de-duplicated within the post */
    public function toc(): array
    {
        $seen = [];
        $out = [];
        foreach ($this->sections ?? [] as $section) {
            $root = Str::slug($section['heading'] ?? '') ?: 'section';
            $id = $root;
            for ($n = 2; isset($seen[$id]); $n++) { $id = "{$root}-{$n}"; }
            $seen[$id] = true;
            $out[] = ['id' => $id, 'heading' => $section['heading'] ?? ''];
        }

        return $out;
    }

    /** Sections with `body_html` rendered and the TOC anchor id attached as `anchor`. */
    public function renderedSections(): array
    {
        $toc = $this->toc();

        return array_values(array_map(fn (array $section, int $i) => [
            ...$section,
            'anchor' => $toc[$i]['id'],
            'body_html' => BlogMarkdown::toHtml($section['body_md'] ?? ''),
        ], $this->sections ?? [], array_keys($this->sections ?? [])));
    }
}
```

Note: `Str::random(6)` may include uppercase — `Str::lower` keeps the id regex happy.

- [ ] **Step 6: Factory**

```php
<?php
namespace Database\Factories;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BlogPost> */
class BlogPostFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        return [
            'slug' => \Illuminate\Support\Str::slug($title),
            'title' => rtrim($title, '.'),
            'excerpt' => fake()->paragraph(2),
            'sections' => BlogPost::normaliseSections([
                ['heading' => 'Notice what keeps repeating', 'body_md' => fake()->paragraph(3)],
                ['heading' => 'Keep the human part', 'body_md' => fake()->paragraph(3)],
            ]),
            'cover_image_url' => null,
            'cover_image_alt' => null,
            'category' => 'Systems',
            'tags' => ['portal', 'whatsapp'],
            'reading_minutes' => 2,
            'status' => BlogPost::STATUS_DRAFT,
            'published_at' => null,
            'created_by' => User::factory()->founder(),
        ];
    }

    public function published(?string $at = null): static
    {
        return $this->state(['status' => BlogPost::STATUS_PUBLISHED, 'published_at' => $at ?? now()]);
    }
}
```

- [ ] **Step 7: Run the tests** — all 6 pass. Run Pint on the four new files.

---

### Task 2: Public API — list, show, slugs

**Files:**
- Create: `backend/app/Http/Resources/BlogPostCardResource.php`
- Create: `backend/app/Http/Resources/PublicBlogPostResource.php`
- Create: `backend/app/Http/Controllers/Api/V1/PublicBlogController.php`
- Modify: `backend/routes/api.php` (public block after the projects routes, ~line 57)
- Test: `backend/tests/Feature/Blog/PublicBlogTest.php`

**Interfaces:**
- Consumes: `BlogPost` (Task 1) — `scopePublished()`, `renderedSections()`, `toc()`.
- Produces: `GET /v1/blog/posts?category=&page=` → `{data: card[], links, meta, categories: [{name, count}]}`; `GET /v1/blog/posts/{slug}` → `{data: full}`; `GET /v1/blog/slugs` → `{data: [{slug, published_at, updated_at}]}` (sitemap feed).
- Card = `{slug, title, excerpt, cover_image_url, cover_image_alt, category, tags, reading_minutes, published_at}`. Full = card + `{sections: [{id, anchor, heading, body_html, image_url, image_alt, quote, quote_by}], toc, cta_heading, cta_body, cta_label, cta_url, seo_title, seo_description, updated_at, related: card[]}`.

- [ ] **Step 1: Failing tests**

```php
<?php
namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBlogTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_returns_published_posts_newest_first_with_categories(): void
    {
        $old = BlogPost::factory()->published('2026-08-01 09:00:00')->create(['category' => 'Systems']);
        $new = BlogPost::factory()->published('2026-09-01 09:00:00')->create(['category' => 'UI/UX']);
        BlogPost::factory()->create(['category' => 'Systems']); // draft — hidden

        $res = $this->getJson('/api/v1/blog/posts')->assertOk();
        $this->assertSame([$new->slug, $old->slug], array_column($res->json('data'), 'slug'));
        $this->assertArrayNotHasKey('sections', $res->json('data.0'));
        $this->assertSame([['name' => 'Systems', 'count' => 1], ['name' => 'UI/UX', 'count' => 1]], $res->json('categories'));
        $this->assertSame(2, $res->json('meta.total'));
    }

    public function test_list_filters_by_category(): void
    {
        BlogPost::factory()->published()->create(['category' => 'Systems']);
        $ux = BlogPost::factory()->published()->create(['category' => 'UI/UX']);

        $res = $this->getJson('/api/v1/blog/posts?category=UI%2FUX')->assertOk();
        $this->assertSame([$ux->slug], array_column($res->json('data'), 'slug'));
    }

    public function test_show_returns_rendered_sections_toc_and_related(): void
    {
        $post = BlogPost::factory()->published()->create([
            'category' => 'Systems',
            'sections' => BlogPost::normaliseSections([
                ['heading' => 'Notice what keeps repeating', 'body_md' => "Do customers **ask** twice?", 'quote' => 'Keep it calm', 'quote_by' => 'Me'],
            ]),
            'cta_heading' => 'Talk it through',
        ]);
        $same = BlogPost::factory()->published('2026-09-02 09:00:00')->create(['category' => 'Systems']);
        $other = BlogPost::factory()->published('2026-09-03 09:00:00')->create(['category' => 'UI/UX']);

        $res = $this->getJson("/api/v1/blog/posts/{$post->slug}")->assertOk();
        $this->assertStringContainsString('<strong>ask</strong>', $res->json('data.sections.0.body_html'));
        $this->assertSame('notice-what-keeps-repeating', $res->json('data.sections.0.anchor'));
        $this->assertSame('Keep it calm', $res->json('data.sections.0.quote'));
        $this->assertSame([['id' => 'notice-what-keeps-repeating', 'heading' => 'Notice what keeps repeating']], $res->json('data.toc'));
        $this->assertSame('Talk it through', $res->json('data.cta_heading'));
        // Related: same category first, then newest; never itself.
        $this->assertSame([$same->slug, $other->slug], array_column($res->json('data.related'), 'slug'));
    }

    public function test_a_draft_or_deleted_post_is_404(): void
    {
        $draft = BlogPost::factory()->create();
        $gone = BlogPost::factory()->published()->create();
        $gone->delete();

        $this->getJson("/api/v1/blog/posts/{$draft->slug}")->assertNotFound();
        $this->getJson("/api/v1/blog/posts/{$gone->slug}")->assertNotFound();
        $this->getJson('/api/v1/blog/posts/nope')->assertNotFound();
    }

    public function test_slugs_feed_lists_published_only(): void
    {
        $pub = BlogPost::factory()->published()->create();
        BlogPost::factory()->create();

        $res = $this->getJson('/api/v1/blog/slugs')->assertOk();
        $this->assertSame([$pub->slug], array_column($res->json('data'), 'slug'));
        $this->assertArrayHasKey('updated_at', $res->json('data.0'));
    }
}
```

- [ ] **Step 2: Run → 404s (routes missing).**

- [ ] **Step 3: Resources**

```php
<?php
// BlogPostCardResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The index-card / related-card shape — everything a listing needs, no body. */
class BlogPostCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'cover_image_url' => $this->cover_image_url,
            'cover_image_alt' => $this->cover_image_alt,
            'category' => $this->category,
            'tags' => $this->tags ?? [],
            'reading_minutes' => (int) $this->reading_minutes,
            'published_at' => $this->published_at?->toISOString(),
        ];
    }
}
```

```php
<?php
// PublicBlogPostResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The full article for /blog/{slug}: card fields + sections rendered to safe
 * HTML (App\Support\BlogMarkdown via BlogPost::renderedSections) + toc + CTA +
 * SEO + up to 3 related cards. `withRelated()` is set by the controller.
 */
class PublicBlogPostResource extends BlogPostCardResource
{
    private Collection $related;

    public function withRelated(Collection $related): static
    {
        $this->related = $related;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'sections' => array_map(fn (array $s) => [
                'id' => $s['id'], 'anchor' => $s['anchor'], 'heading' => $s['heading'], 'body_html' => $s['body_html'],
                'image_url' => $s['image_url'], 'image_alt' => $s['image_alt'], 'quote' => $s['quote'], 'quote_by' => $s['quote_by'],
            ], $this->renderedSections()),
            'toc' => $this->toc(),
            'cta_heading' => $this->cta_heading,
            'cta_body' => $this->cta_body,
            'cta_label' => $this->cta_label,
            'cta_url' => $this->cta_url,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'updated_at' => $this->updated_at?->toISOString(),
            'related' => BlogPostCardResource::collection($this->related ?? collect())->resolve(),
        ];
    }
}
```

- [ ] **Step 4: Controller + routes**

```php
<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogPostCardResource;
use App\Http\Resources\PublicBlogPostResource;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Read-only blog feed for the storefront (see docs/global/BLOG.md). Drafts and deleted posts never appear. */
class PublicBlogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = BlogPost::published()->orderByDesc('published_at')->orderByDesc('id');
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        $categories = BlogPost::published()->whereNotNull('category')
            ->selectRaw('category as name, COUNT(*) as count')->groupBy('category')
            ->orderByDesc('count')->orderBy('name')->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count])->values();

        return BlogPostCardResource::collection($query->paginate(12))->additional(['categories' => $categories]);
    }

    public function show(string $slug): PublicBlogPostResource
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();

        $related = BlogPost::published()->whereKeyNot($post->id)
            ->orderByRaw('CASE WHEN category = ? THEN 0 ELSE 1 END', [$post->category ?? ''])
            ->orderByDesc('published_at')->orderByDesc('id')->limit(3)->get();

        return (new PublicBlogPostResource($post))->withRelated($related);
    }

    /** Sitemap feed — every published slug + timestamps, unpaginated. */
    public function slugs(): JsonResponse
    {
        return response()->json(['data' => BlogPost::published()->orderByDesc('published_at')
            ->get(['slug', 'published_at', 'updated_at'])
            ->map(fn (BlogPost $p) => ['slug' => $p->slug, 'published_at' => $p->published_at?->toISOString(), 'updated_at' => $p->updated_at?->toISOString()])]);
    }
}
```

`routes/api.php`, after the projects lines:

```php
// Public — the blog (drafts never served). /slugs feeds the frontend sitemap.
Route::get('/v1/blog/posts', [PublicBlogController::class, 'index'])->name('blog.index');
Route::get('/v1/blog/slugs', [PublicBlogController::class, 'slugs'])->name('blog.slugs');
Route::get('/v1/blog/posts/{slug}', [PublicBlogController::class, 'show'])->name('blog.show');
```

- [ ] **Step 5: Run → 5 pass. Pint.**

---

### Task 3: Admin API — CRUD, publish, render, views

**Files:**
- Create: `backend/app/Http/Resources/AdminBlogPostResource.php`
- Create: `backend/app/Http/Controllers/Api/V1/Admin/BlogPostsController.php`
- Modify: `backend/routes/api.php` (inside the cockpit group, after the CMS — Projects block)
- Test: `backend/tests/Feature/Blog/AdminBlogPostsTest.php`

**Interfaces:**
- Produces (all under `/v1/admin/blog`, cockpit token): `GET /posts?status=&q=&page=` (20/page, newest `updated_at` first, each row + `views`), `POST /posts`, `GET /posts/{post}`, `PUT /posts/{post}`, `POST /posts/{post}/publish`, `POST /posts/{post}/unpublish`, `DELETE /posts/{post}`, `POST /render`.
- Admin record = `{id, slug, title, excerpt, sections: [{id, heading, body_md, image_url, image_alt, quote, quote_by}], cover_image_url, cover_image_alt, category, tags, cta_*, seo_*, reading_minutes, status, published_at, views?, created_at, updated_at}`.
- `POST /render` body `{excerpt?: string, sections: [...]}` → `{sections: [{id, anchor, heading, body_html, image_url, image_alt, quote, quote_by}], toc, reading_minutes}`.

- [ ] **Step 1: Failing tests**

```php
<?php
namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use App\Models\PageView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBlogPostsTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(?User $founder = null): array
    {
        $founder ??= User::factory()->founder()->create();
        return ['Authorization' => 'Bearer '.$founder->createToken('admin-spa', ['cockpit'])->plainTextToken];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Still Managing Enquiries Through WhatsApp?',
            'excerpt' => 'WhatsApp is convenient. Most customers already know how to use it.',
            'sections' => [
                ['heading' => 'Notice what keeps repeating', 'body_md' => 'Do customers ask for the same updates?'],
            ],
            'category' => 'Systems',
            'tags' => ['portal', 'whatsapp'],
        ], $overrides);
    }

    public function test_create_generates_the_slug_and_reading_time_and_starts_as_draft(): void
    {
        $res = $this->postJson('/api/v1/admin/blog/posts', $this->payload(), $this->adminHeaders())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'still-managing-enquiries-through-whatsapp')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.reading_minutes', 1)
            ->assertJsonPath('data.sections.0.heading', 'Notice what keeps repeating');
        $this->assertMatchesRegularExpression('/^s_[a-z0-9]{6}$/', $res->json('data.sections.0.id'));
    }

    public function test_create_normalises_a_custom_slug_and_suffixes_a_collision(): void
    {
        BlogPost::factory()->create(['slug' => 'my-post']);
        $this->postJson('/api/v1/admin/blog/posts', $this->payload(['slug' => ' My Post! ']), $this->adminHeaders())
            ->assertCreated()->assertJsonPath('data.slug', 'my-post-2');
    }

    public function test_validation_limits(): void
    {
        $this->postJson('/api/v1/admin/blog/posts', $this->payload([
            'title' => str_repeat('x', 161),
            'seo_title' => str_repeat('x', 71),
            'tags' => array_fill(0, 11, 't'),
            'cover_image_url' => 'javascript:alert(1)',
            'sections' => [['heading' => '', 'body_md' => 'x', 'image_url' => 'data:x']],
        ]), $this->adminHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'seo_title', 'tags', 'cover_image_url', 'sections.0.heading', 'sections.0.image_url']);
    }

    public function test_publish_gates_on_completeness_then_stamps_published_at_once(): void
    {
        $headers = $this->adminHeaders();
        $id = $this->postJson('/api/v1/admin/blog/posts', $this->payload(['excerpt' => '', 'sections' => []]), $headers)
            ->assertCreated()->json('data.id'); // a draft may be incomplete

        $this->postJson("/api/v1/admin/blog/posts/{$id}/publish", [], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors(['excerpt', 'sections']);

        $this->putJson("/api/v1/admin/blog/posts/{$id}", $this->payload(), $headers)->assertOk();
        $first = $this->postJson("/api/v1/admin/blog/posts/{$id}/publish", [], $headers)
            ->assertOk()->assertJsonPath('data.status', 'published')->json('data.published_at');
        $this->assertNotNull($first);

        $this->postJson("/api/v1/admin/blog/posts/{$id}/unpublish", [], $headers)
            ->assertOk()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.published_at', $first);
        $this->postJson("/api/v1/admin/blog/posts/{$id}/publish", [], $headers)
            ->assertOk()->assertJsonPath('data.published_at', $first); // kept, not re-stamped
    }

    public function test_update_can_change_a_published_slug(): void
    {
        $post = BlogPost::factory()->published()->create(['slug' => 'old-slug']);
        $this->putJson("/api/v1/admin/blog/posts/{$post->id}", $this->payload(['slug' => 'new-slug']), $this->adminHeaders())
            ->assertOk()->assertJsonPath('data.slug', 'new-slug')->assertJsonPath('data.status', 'published');
        $this->getJson('/api/v1/blog/posts/old-slug')->assertNotFound();
        $this->getJson('/api/v1/blog/posts/new-slug')->assertOk();
    }

    public function test_index_filters_and_counts_views(): void
    {
        $headers = $this->adminHeaders();
        $pub = BlogPost::factory()->published()->create(['title' => 'Portal timing', 'slug' => 'portal-timing']);
        BlogPost::factory()->create(['title' => 'Draft idea']);
        PageView::create(['path' => '/blog/portal-timing', 'ip_hash' => 'a', 'user_agent' => 'x', 'referrer' => null, 'viewed_at' => now()]);
        PageView::create(['path' => '/blog/portal-timing', 'ip_hash' => 'b', 'user_agent' => 'x', 'referrer' => null, 'viewed_at' => now()]);

        $res = $this->getJson('/api/v1/admin/blog/posts?status=published', $headers)->assertOk();
        $this->assertSame([$pub->id], array_column($res->json('data'), 'id'));
        $this->assertSame(2, $res->json('data.0.views'));

        $this->getJson('/api/v1/admin/blog/posts?q=draft', $headers)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_delete_soft_deletes_and_hides_publicly(): void
    {
        $post = BlogPost::factory()->published()->create();
        $this->deleteJson("/api/v1/admin/blog/posts/{$post->id}", [], $this->adminHeaders())->assertOk();
        $this->assertSoftDeleted('blog_posts', ['id' => $post->id]);
        $this->getJson("/api/v1/blog/posts/{$post->slug}")->assertNotFound();
    }

    public function test_render_previews_without_saving(): void
    {
        $res = $this->postJson('/api/v1/admin/blog/render', [
            'excerpt' => 'Intro',
            'sections' => [['heading' => 'Keep the human part', 'body_md' => "A portal **keeps** people."]],
        ], $this->adminHeaders())->assertOk();
        $this->assertStringContainsString('<strong>keeps</strong>', $res->json('sections.0.body_html'));
        $this->assertSame('keep-the-human-part', $res->json('toc.0.id'));
        $this->assertSame(1, $res->json('reading_minutes'));
        $this->assertSame(0, BlogPost::count());
    }

    public function test_admin_blog_rejects_a_workspace_token(): void
    {
        $token = User::factory()->marketer()->create()->createToken('team-spa', ['workspace'])->plainTextToken;
        $this->getJson('/api/v1/admin/blog/posts', ['Authorization' => "Bearer {$token}"])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run → 404s / class not found.**

- [ ] **Step 3: Resource**

```php
<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The editable record for /admin/blog — raw Markdown sections, status, and (on the list) `views`. */
class AdminBlogPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'sections' => $this->sections ?? [],
            'cover_image_url' => $this->cover_image_url,
            'cover_image_alt' => $this->cover_image_alt,
            'category' => $this->category,
            'tags' => $this->tags ?? [],
            'cta_heading' => $this->cta_heading,
            'cta_body' => $this->cta_body,
            'cta_label' => $this->cta_label,
            'cta_url' => $this->cta_url,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'reading_minutes' => (int) $this->reading_minutes,
            'status' => $this->status,
            'published_at' => $this->published_at?->toISOString(),
            'views' => $this->when(isset($this->views), fn () => (int) $this->views),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 4: Controller**

```php
<?php
namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminBlogPostResource;
use App\Models\BlogPost;
use App\Models\PageView;
use App\Support\BlogMarkdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * The blog CMS (founder-only via the /v1/admin cockpit group). Drafts may be
 * incomplete; `publish` is the completeness gate. Slugs are generated from the
 * title when blank and always de-duplicated (BlogPost::uniqueSlug). Markdown is
 * stored as-is; rendering happens on read (and in `render` for the preview).
 */
class BlogPostsController extends Controller
{
    private const URL_RULE = 'regex:#^(https?://|/[^/])#';

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', 'in:draft,published'], 'q' => ['nullable', 'string', 'max:120']]);

        $query = BlogPost::query()->orderByDesc('updated_at')->orderByDesc('id');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.$request->string('q').'%');
        }
        $page = $query->paginate(20);

        // One grouped query for the page's view counts (page_views is keyed by path).
        $paths = $page->getCollection()->map(fn (BlogPost $p) => "/blog/{$p->slug}")->all();
        $views = $paths ? PageView::whereIn('path', $paths)->selectRaw('path, COUNT(*) as c')->groupBy('path')->pluck('c', 'path') : collect();
        $page->getCollection()->each(fn (BlogPost $p) => $p->views = (int) ($views["/blog/{$p->slug}"] ?? 0));

        return AdminBlogPostResource::collection($page);
    }

    public function store(Request $request): AdminBlogPostResource
    {
        $data = $this->validated($request);
        $post = BlogPost::create([...$data, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $post->logActivity('blog_post.created', ['title' => $post->title]);

        return new AdminBlogPostResource($post);
    }

    public function show(BlogPost $blogPost): AdminBlogPostResource
    {
        return new AdminBlogPostResource($blogPost);
    }

    public function update(Request $request, BlogPost $blogPost): AdminBlogPostResource
    {
        $data = $this->validated($request, $blogPost);
        $blogPost->update([...$data, 'updated_by' => $request->user()->id]);
        $blogPost->logActivity('blog_post.updated', ['title' => $blogPost->title]);

        return new AdminBlogPostResource($blogPost->fresh());
    }

    /** The completeness gate: title, excerpt, ≥1 section with heading + body. */
    public function publish(Request $request, BlogPost $blogPost): AdminBlogPostResource
    {
        $errors = [];
        if (trim((string) $blogPost->title) === '') { $errors['title'] = ['Give the post a title before publishing.']; }
        if (trim((string) $blogPost->excerpt) === '') { $errors['excerpt'] = ['Write a short introduction before publishing.']; }
        $complete = collect($blogPost->sections ?? [])->contains(fn ($s) => trim($s['heading'] ?? '') !== '' && trim($s['body_md'] ?? '') !== '');
        if (! $complete) { $errors['sections'] = ['Add at least one section with a heading and some content.']; }
        if ($errors) { throw ValidationException::withMessages($errors); }

        $blogPost->update([
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => $blogPost->published_at ?? now(),   // first publish only
            'updated_by' => $request->user()->id,
        ]);
        $blogPost->logActivity('blog_post.published', ['title' => $blogPost->title]);

        return new AdminBlogPostResource($blogPost->fresh());
    }

    public function unpublish(Request $request, BlogPost $blogPost): AdminBlogPostResource
    {
        $blogPost->update(['status' => BlogPost::STATUS_DRAFT, 'updated_by' => $request->user()->id]);
        $blogPost->logActivity('blog_post.unpublished', ['title' => $blogPost->title]);

        return new AdminBlogPostResource($blogPost->fresh());
    }

    public function destroy(BlogPost $blogPost): JsonResponse
    {
        $blogPost->logActivity('blog_post.deleted', ['title' => $blogPost->title]);
        $blogPost->delete();

        return response()->json(['message' => 'Post deleted.']);
    }

    /** Preview helper — the same renderer the public page uses. Saves nothing. */
    public function render(Request $request): JsonResponse
    {
        $data = $request->validate(['excerpt' => ['nullable', 'string', 'max:500'], ...$this->sectionRules()]);
        $post = new BlogPost(['sections' => BlogPost::normaliseSections($data['sections'] ?? [])]);

        return response()->json([
            'sections' => $post->renderedSections(),
            'toc' => $post->toc(),
            'reading_minutes' => BlogMarkdown::readingMinutes((string) ($data['excerpt'] ?? ''), $post->sections),
        ]);
    }

    private function validated(Request $request, ?BlogPost $existing = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:120'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            ...$this->sectionRules(),
            'cover_image_url' => ['nullable', 'string', 'max:500', self::URL_RULE],
            'cover_image_alt' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:60'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:40'],
            'cta_heading' => ['nullable', 'string', 'max:120'],
            'cta_body' => ['nullable', 'string', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'cta_url' => ['nullable', 'string', 'max:500', self::URL_RULE],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
        ]);

        $sections = BlogPost::normaliseSections($data['sections'] ?? []);
        $slugBase = trim((string) ($data['slug'] ?? '')) !== '' ? $data['slug'] : $data['title'];

        return [
            ...$data,
            'slug' => BlogPost::uniqueSlug($slugBase, $existing?->id),
            'excerpt' => trim((string) ($data['excerpt'] ?? '')),
            'sections' => $sections,
            'tags' => array_values(array_filter(array_map('trim', $data['tags'] ?? []))),
            'reading_minutes' => BlogMarkdown::readingMinutes((string) ($data['excerpt'] ?? ''), $sections),
        ];
    }

    private function sectionRules(): array
    {
        return [
            'sections' => ['nullable', 'array', 'max:40'],
            'sections.*.id' => ['nullable', 'string', 'max:12'],
            'sections.*.heading' => ['required', 'string', 'max:120'],
            'sections.*.body_md' => ['nullable', 'string', 'max:20000'],
            'sections.*.image_url' => ['nullable', 'string', 'max:500', self::URL_RULE],
            'sections.*.image_alt' => ['nullable', 'string', 'max:160'],
            'sections.*.quote' => ['nullable', 'string', 'max:500'],
            'sections.*.quote_by' => ['nullable', 'string', 'max:80'],
        ];
    }
}
```

Routes (inside the cockpit group, after `// CMS — Projects`):

```php
// CMS — Blog (see BLOG.md). `render` is the preview helper; it saves nothing.
Route::get('/blog/posts', [BlogPostsController::class, 'index'])->name('blog.index');
Route::post('/blog/posts', [BlogPostsController::class, 'store'])->name('blog.store');
Route::post('/blog/render', [BlogPostsController::class, 'render'])->name('blog.render');
Route::get('/blog/posts/{blogPost}', [BlogPostsController::class, 'show'])->name('blog.show');
Route::put('/blog/posts/{blogPost}', [BlogPostsController::class, 'update'])->name('blog.update');
Route::post('/blog/posts/{blogPost}/publish', [BlogPostsController::class, 'publish'])->name('blog.publish');
Route::post('/blog/posts/{blogPost}/unpublish', [BlogPostsController::class, 'unpublish'])->name('blog.unpublish');
Route::delete('/blog/posts/{blogPost}', [BlogPostsController::class, 'destroy'])->name('blog.destroy');
```

Note: a `sections.*.heading` of `''` fails `required` — the editor sends only sections the founder created, and the publish gate handles blank bodies. `test_validation_limits` relies on this.

- [ ] **Step 5: Run the Blog suite → all pass. Pint. Run the FULL suite** (`vendor/bin/phpunit`) before moving on.

---

### Task 4: Frontend foundations — data module, nav, route rules, sitemap, prose CSS

**Files:**
- Create: `frontend/app/data/blog.ts`
- Create: `frontend/server/api/__sitemap__/urls.ts`
- Modify: `frontend/nuxt.config.ts` (`sitemap`, `routeRules`)
- Modify: `frontend/app/data/adminNav.ts` (Catalog group)
- Modify: `frontend/app/layouts/public.vue:24-32` and `frontend/app/components/public/HeroEpoch.vue:30-38` (`links` / `navLinks`)
- Modify: `frontend/app/assets/css/main.css` (append `.blog-prose`, `.blog-toc`)

**Interfaces (produced, used by Tasks 5–8):**

```ts
// frontend/app/data/blog.ts
export interface BlogSection { id: string; heading: string; body_md: string; image_url: string | null; image_alt: string | null; quote: string | null; quote_by: string | null }
export interface BlogTocItem { id: string; heading: string }
export interface BlogRenderedSection extends BlogSection { anchor: string; body_html: string }
export interface BlogPostCard { slug: string; title: string; excerpt: string; cover_image_url: string | null; cover_image_alt: string | null; category: string | null; tags: string[]; reading_minutes: number; published_at: string | null }
export interface BlogPostPublic extends BlogPostCard { sections: BlogRenderedSection[]; toc: BlogTocItem[]; cta_heading: string | null; cta_body: string | null; cta_label: string | null; cta_url: string | null; seo_title: string | null; seo_description: string | null; updated_at: string | null; related: BlogPostCard[] }
export interface BlogPostAdmin { id: number; slug: string; title: string; excerpt: string; sections: BlogSection[]; cover_image_url: string | null; cover_image_alt: string | null; category: string | null; tags: string[]; cta_heading: string | null; cta_body: string | null; cta_label: string | null; cta_url: string | null; seo_title: string | null; seo_description: string | null; reading_minutes: number; status: 'draft' | 'published'; published_at: string | null; views?: number; created_at: string; updated_at: string }
export const blogStatusOptions = [{ value: '', label: 'All' }, { value: 'draft', label: 'Draft' }, { value: 'published', label: 'Published' }]
export const blogCtaDefaults = { heading: 'Have something like this in mind?', body: "I'm always open to a conversation about what that could look like for your business.", label: 'Get in touch', url: '/contact' }
export function newSection(heading = ''): BlogSection            // id = 's_' + 6 random [a-z0-9]
export function templateSections(): BlogSection[]                 // The problem · Why it matters · A practical step · Another practical step · Key takeaway
export function slugify(title: string): string                    // lowercase, non-alnum → '-', trimmed, ≤ 110
export function readingMinutes(excerpt: string, sections: Pick<BlogSection, 'body_md'>[]): number   // strip md markers, /200, min 1
export function parseMarkdownImport(md: string): { title: string; excerpt: string; sections: BlogSection[] }
export function fmtBlogDate(iso: string | null): string           // 'Sep 26, 2026' (en-MY, short month)
export const blogVoiceGuide: { voice: string[]; structure: string[] }
```

- [ ] **Step 1: `data/blog.ts`** — the `parseMarkdownImport` rules: `# ` before any section → title; lines before the first `## ` → intro; each `## ` opens a section whose body is everything until the next `## `; `### ` and below stay inside the body. If the intro is ≤ 500 chars it becomes the excerpt; otherwise the first paragraph is the excerpt and the remaining intro paragraphs become a leading section headed "Introduction".

```ts
export function parseMarkdownImport(md: string) {
  const lines = md.replace(/\r\n?/g, '\n').split('\n')
  let title = ''
  const intro: string[] = []
  const sections: BlogSection[] = []
  let current: BlogSection | null = null
  for (const line of lines) {
    const h1 = /^#\s+(.+)$/.exec(line)
    const h2 = /^##\s+(.+)$/.exec(line)
    if (h1 && !title && !current) { title = h1[1]!.trim(); continue }
    if (h2) { current = newSection(h2[1]!.trim()); sections.push(current); continue }
    if (current) current.body_md += `${line}\n`
    else intro.push(line)
  }
  for (const s of sections) s.body_md = s.body_md.trim()
  const introText = intro.join('\n').trim()
  let excerpt = introText
  if (introText.length > 500) {
    const [first, ...rest] = introText.split(/\n{2,}/)
    excerpt = first!.trim()
    if (rest.length) sections.unshift({ ...newSection('Introduction'), body_md: rest.join('\n\n').trim() })
  }
  return { title, excerpt, sections }
}
```

`readingMinutes`: `const text = [excerpt, ...sections.map(s => s.body_md)].join(' ').replace(/[#*_>`\-\[\]()!]/g, ' ')`; words = `text.split(/\s+/).filter(Boolean).length`; `Math.max(1, Math.ceil(words / 200))`.

`blogVoiceGuide.voice`: "Thoughtful, direct and human." · "Write for Malaysian business owners and founders, in clear English." · "Focus on UI/UX, digital experiences and custom systems that make things feel simpler for people." · "No agency buzzwords, exaggerated claims or aggressive sales language." `structure`: "Opening hook (the introduction)" · "The problem or question" · "Why it matters" · "Two to four practical sections" · "Key takeaway" · "Gentle invitation to get in touch (the closing CTA)".

- [ ] **Step 2: Nav.** `adminNav.ts` Catalog items: append `{ to: '/admin/blog', label: 'Blog', icon: 'i-lucide-newspaper', matchPrefix: '/admin/blog' }`. In both `layouts/public.vue` and `HeroEpoch.vue` insert `{ label: 'Blog', to: '/blog' }` immediately after the Company entry (the footer Explore column iterates the same `links` array, so it follows automatically).

- [ ] **Step 3: nuxt.config.ts.** In `sitemap` add `sources: ['/api/__sitemap__/urls'],`. In `routeRules` after `'/projects/**': { swr: 300 },` add `'/blog': { swr: 300 },` and `'/blog/**': { swr: 300 },`.

- [ ] **Step 4: Sitemap source** — `frontend/server/api/__sitemap__/urls.ts`:

```ts
// Dynamic sitemap entries: every published blog post. Fetched server-side from
// the backend via the docker-network base (runtimeConfig.apiBase). A backend
// hiccup yields an empty list rather than a broken sitemap.
export default defineSitemapEventHandler(async () => {
  const base = useRuntimeConfig().apiBase
  try {
    const res = await $fetch<{ data: { slug: string, updated_at: string | null }[] }>(`${base}/api/v1/blog/slugs`)
    return res.data.map(p => asSitemapUrl({ loc: `/blog/${p.slug}`, lastmod: p.updated_at ?? undefined }))
  }
  catch {
    return []
  }
})
```

- [ ] **Step 5: Prose CSS** (append to `main.css`, tokens only):

```css
/* ── Blog article body (see docs/global/BLOG.md) ───────────────────────── */
.blog-prose { font-size: 17px; line-height: 1.75; color: var(--color-text-secondary); }
.blog-prose p { margin: 0 0 1.25em; }
.blog-prose p:last-child { margin-bottom: 0; }
.blog-prose strong { color: var(--color-text); font-weight: 600; }
.blog-prose a { color: var(--color-accent); text-decoration: underline; text-underline-offset: 3px; }
.blog-prose a:hover { color: var(--color-accent-hover); }
.blog-prose h3 { font-size: 20px; font-weight: 600; letter-spacing: -0.01em; color: var(--color-text); margin: 2em 0 0.6em; }
.blog-prose h4 { font-size: 17px; font-weight: 600; color: var(--color-text); margin: 1.6em 0 0.5em; }
.blog-prose ul, .blog-prose ol { margin: 0 0 1.25em; padding-left: 1.4em; }
.blog-prose li { margin: 0.35em 0; }
.blog-prose ul { list-style: disc; }
.blog-prose ol { list-style: decimal; }
.blog-prose blockquote { margin: 1.5em 0; padding: 0.25em 0 0.25em 1.1em; border-left: 3px solid var(--color-accent); color: var(--color-text); font-style: italic; }
.blog-prose code { font-size: 0.9em; padding: 0.15em 0.4em; border-radius: 6px; background: var(--color-bg-secondary); color: var(--color-text); }
.blog-prose pre { margin: 1.5em 0; padding: 1em 1.2em; border-radius: 14px; overflow-x: auto; background: var(--color-bg-sunken); border: 1px solid var(--color-border); }
.blog-prose pre code { padding: 0; background: transparent; }
.blog-prose img { max-width: 100%; height: auto; border-radius: 14px; margin: 1.5em 0; }
.blog-prose hr { border: 0; border-top: 1px solid var(--color-border); margin: 2.5em 0; }
.blog-section-anchor { scroll-margin-top: 6rem; }
.blog-toc a { color: var(--color-text-secondary); transition: color 0.15s ease; }
.blog-toc a:hover, .blog-toc a.is-active { color: var(--color-text); }
```

- [ ] **Step 6: Verify** — `npx eslint app/data/blog.ts server/api/__sitemap__/urls.ts app/data/adminNav.ts app/layouts/public.vue app/components/public/HeroEpoch.vue` and `npm run typecheck` pass. Add the `.blog-prose` section to `docs/frontend/UI-STANDARDS.md` § 7 (Task 9 collects the doc edits; note it here so it isn't forgotten).

---

### Task 5: Shared public components — BlogCard, BlogToc, BlogArticle

**Files:**
- Create: `frontend/app/components/public/BlogCard.vue`
- Create: `frontend/app/components/public/BlogToc.vue`
- Create: `frontend/app/components/public/BlogArticle.vue`

**Interfaces:**
- Consumes: types + `fmtBlogDate`, `blogCtaDefaults` from `~/data/blog`.
- Produces: `<PublicBlogCard :post="BlogPostCard" />`, `<PublicBlogToc :items="BlogTocItem[]" />`, `<PublicBlogArticle :post="BlogPostPublic" :preview="boolean" />`. With `preview`, the share row and related grid are hidden and the CTA button is a non-navigating `<span>`.

- [ ] **Step 1: `BlogCard.vue`** — `<NuxtLink :to="`/blog/${post.slug}`">` wrapping: cover (`aspect-[16/9] object-cover rounded-2xl border`, `loading="lazy"`, alt) or an `--color-bg-secondary` placeholder with `i-lucide-newspaper`; meta line `category · fmtBlogDate · N min read` in `text-[12px]` tertiary; title `text-[18px] font-semibold tracking-tight` text; excerpt `text-[14px] line-clamp-3` secondary. Hover lifts (`hover:-translate-y-0.5 transition`).

- [ ] **Step 2: `BlogToc.vue`** — `<nav class="blog-toc" aria-label="On this page">` with eyebrow "On this page" (`text-[11px] uppercase tracking-widest` tertiary) and `<a :href="`#${item.id}`">` per item (`text-[13px]`). Active tracking: an `IntersectionObserver` in `onMounted` over `.blog-section-anchor` elements sets `active` to the last heading above 30% viewport; guard `typeof window` and disconnect in `onUnmounted`.

- [ ] **Step 3: `BlogArticle.vue`** — script:

```ts
import { blogCtaDefaults, fmtBlogDate, type BlogPostPublic } from '~/data/blog'
import PublicBlogToc from '~/components/public/BlogToc.vue'
import PublicBlogCard from '~/components/public/BlogCard.vue'

const props = withDefaults(defineProps<{ post: BlogPostPublic, preview?: boolean }>(), { preview: false })
const cta = computed(() => ({
  heading: props.post.cta_heading || blogCtaDefaults.heading,
  body: props.post.cta_body || blogCtaDefaults.body,
  label: props.post.cta_label || blogCtaDefaults.label,
  url: props.post.cta_url || blogCtaDefaults.url,
}))
const showToc = computed(() => props.post.toc.length >= 3)
const pageUrl = computed(() => `https://axelnovaventures.com/blog/${props.post.slug}`)
const shareLinks = computed(() => [
  { label: 'LinkedIn', icon: 'i-lucide-linkedin', href: `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(pageUrl.value)}` },
  { label: 'X', icon: 'i-lucide-twitter', href: `https://twitter.com/intent/tweet?url=${encodeURIComponent(pageUrl.value)}&text=${encodeURIComponent(props.post.title)}` },
  { label: 'WhatsApp', icon: 'i-lucide-message-circle', href: `https://wa.me/?text=${encodeURIComponent(`${props.post.title} ${pageUrl.value}`)}` },
])
const copied = ref(false)
async function copyLink() {
  try { await navigator.clipboard.writeText(pageUrl.value); copied.value = true; setTimeout(() => (copied.value = false), 1800) } catch { /* clipboard blocked — the link is in the address bar anyway */ }
}
```

Template skeleton (all colours via tokens):

```html
<article>
  <header class="max-w-[68ch] mx-auto">
    <p class="text-[12px] tracking-wide" tertiary>{{ post.category }} · {{ fmtBlogDate(post.published_at) }} · {{ post.reading_minutes }} min read</p>
    <h1 class="text-4xl md:text-5xl font-semibold tracking-tighter mt-3 mb-5" text>{{ post.title }}</h1>
    <p class="text-[19px] leading-[1.6]" secondary>{{ post.excerpt }}</p>
  </header>
  <figure v-if="post.cover_image_url" class="max-w-4xl mx-auto my-10 rounded-2xl overflow-hidden border"><img :src :alt="post.cover_image_alt ?? post.title" class="w-full aspect-[16/9] object-cover"></figure>
  <div class="max-w-5xl mx-auto lg:grid lg:grid-cols-[1fr_220px] lg:gap-12">
    <div class="max-w-[68ch]">
      <PublicBlogToc v-if="showToc" :items="post.toc" class="lg:hidden mb-8 rounded-2xl border p-4" />
      <section v-for="s in post.sections" :key="s.id" class="mb-12">
        <h2 :id="s.anchor" class="blog-section-anchor text-[26px] font-semibold tracking-tight mb-4" text>{{ s.heading }}</h2>
        <div class="blog-prose" v-html="s.body_html" />
        <figure v-if="s.image_url" class="mt-6 rounded-2xl overflow-hidden border"><img :src="s.image_url" :alt="s.image_alt ?? s.heading" class="w-full h-auto" loading="lazy"><figcaption v-if="s.image_alt" class="text-[12px] px-4 py-2" tertiary>{{ s.image_alt }}</figcaption></figure>
        <blockquote v-if="s.quote" class="mt-6 border-l-[3px] pl-5 text-[20px] leading-snug italic" accent-border text>“{{ s.quote }}”<footer v-if="s.quote_by" class="text-[13px] not-italic mt-2" tertiary>— {{ s.quote_by }}</footer></blockquote>
      </section>
      <aside class="rounded-3xl border p-7 mt-4" elevated> <h2 …>{{ cta.heading }}</h2><p …>{{ cta.body }}</p><NuxtLink v-if="!preview" :to="cta.url" class="btn-pill btn-pill-accent">{{ cta.label }}</NuxtLink><span v-else class="btn-pill btn-pill-accent">{{ cta.label }}</span></aside>
      <div v-if="!preview" class="flex flex-wrap items-center gap-2 mt-8"><span tertiary text-[12px]>Share</span><a v-for="l in shareLinks" :href="l.href" target="_blank" rel="noopener" class="btn-table-action"><UIcon :name="l.icon" class="size-3.5" />{{ l.label }}</a><button class="btn-table-action" @click="copyLink"><UIcon name="i-lucide-link" class="size-3.5" />{{ copied ? 'Copied' : 'Copy link' }}</button></div>
    </div>
    <aside v-if="showToc" class="hidden lg:block"><div class="sticky top-28"><PublicBlogToc :items="post.toc" /></div></aside>
  </div>
  <section v-if="!preview && post.related.length" class="max-w-5xl mx-auto mt-20"><h2 eyebrow>More from the blog</h2><div class="grid md:grid-cols-3 gap-6"><PublicBlogCard v-for="r in post.related" :key="r.slug" :post="r" /></div></section>
</article>
```

(`text` / `secondary` / `tertiary` / `elevated` / `accent-border` above stand for the matching `:style="{ color: 'var(--color-text…)' }"` / `background` / `borderColor` bindings — write them out.)

- [ ] **Step 4: Verify** — eslint on the three files; typecheck.

---

### Task 6: Public pages — `/blog` and `/blog/[slug]`

**Files:**
- Create: `frontend/app/pages/public/blog/index.vue`
- Create: `frontend/app/pages/public/blog/[slug].vue`

**Interfaces:** consumes Task 2 endpoints via `useApiBase()`, Task 5 components, `usePublicSeo`.

- [ ] **Step 1: Index page**

```ts
definePageMeta({ layout: 'public' })
import SectionHeader from '~/components/shared/SectionHeader.vue'
import PublicBlogCard from '~/components/public/BlogCard.vue'
import type { BlogPostCard } from '~/data/blog'

usePublicSeo({ title: 'Blog — Axel Nova Ventures', description: 'Notes on UI/UX, custom systems and the everyday tech decisions Malaysian business owners and founders run into.', path: '/blog' })

const route = useRoute()
const category = computed(() => (route.query.category as string) || '')
const page = computed(() => Number(route.query.page) || 1)
const apiBase = useApiBase()
const { data } = await useFetch<{ data: BlogPostCard[], meta: { current_page: number, last_page: number, total: number }, categories: { name: string, count: number }[] }>(
  () => `${apiBase}/api/v1/blog/posts?category=${encodeURIComponent(category.value)}&page=${page.value}`,
  { key: () => `public-blog-${category.value}-${page.value}` },
)
const posts = computed(() => data.value?.data ?? [])
const categories = computed(() => data.value?.categories ?? [])
const meta = computed(() => data.value?.meta)
function select(cat: string) { navigateTo({ path: '/blog', query: { ...(cat ? { category: cat } : {}) } }) }
useScrollReveal('.reveal')
```

Template: `<div class="max-w-7xl mx-auto px-6 pt-20 pb-24">` → `<SectionHeader eyebrow="Blog" title="Notes from the workbench." subtitle="Thoughts on interfaces, systems and the decisions behind them — written for business owners and founders." />` → category pills (same pill styling as `/projects` stack filters: "All" + each category) → `grid md:grid-cols-2 lg:grid-cols-3 gap-6` of `<PublicBlogCard class="reveal">` → empty state ("Nothing published yet. Check back soon.") → pagination (Prev / `page / last` / Next as `NuxtLink`s with `query`), shown when `meta.last_page > 1`.

- [ ] **Step 2: Article page**

```ts
definePageMeta({ layout: 'public' })
import PublicBlogArticle from '~/components/public/BlogArticle.vue'
import type { BlogPostPublic } from '~/data/blog'

const route = useRoute()
const slug = computed(() => route.params.slug as string)
const apiBase = useApiBase()
const { data, error } = await useFetch<{ data: BlogPostPublic }>(() => `${apiBase}/api/v1/blog/posts/${slug.value}`, { key: () => `public-blog-post-${slug.value}` })
const post = computed(() => data.value?.data)
if (!post.value) {
  throw createError({ statusCode: 404, statusMessage: 'Post not found', fatal: true })
}
const siteUrl = 'https://axelnovaventures.com'
usePublicSeo({
  title: `${post.value.seo_title || post.value.title} — Axel Nova Ventures`,
  description: post.value.seo_description || post.value.excerpt,
  path: `/blog/${slug.value}`,
  image: post.value.cover_image_url ?? undefined,
})
useHead({ script: [
  { type: 'application/ld+json', innerHTML: JSON.stringify({ '@context': 'https://schema.org', '@type': 'BlogPosting', headline: post.value.title, description: post.value.seo_description || post.value.excerpt, image: post.value.cover_image_url ?? undefined, datePublished: post.value.published_at, dateModified: post.value.updated_at ?? post.value.published_at, author: { '@type': 'Person', name: 'Ahmad Baihaqie' }, publisher: { '@type': 'Organization', name: 'Axel Nova Ventures', url: siteUrl }, mainEntityOfPage: `${siteUrl}/blog/${slug.value}`, articleSection: post.value.category ?? undefined, keywords: post.value.tags.join(', ') || undefined }) },
  { type: 'application/ld+json', innerHTML: JSON.stringify({ '@context': 'https://schema.org', '@type': 'BreadcrumbList', itemListElement: [ { '@type': 'ListItem', position: 1, name: 'Home', item: siteUrl }, { '@type': 'ListItem', position: 2, name: 'Blog', item: `${siteUrl}/blog` }, { '@type': 'ListItem', position: 3, name: post.value.title, item: `${siteUrl}/blog/${slug.value}` } ] }) },
] })
useScrollReveal('.reveal')
```

Template: `<div class="max-w-7xl mx-auto px-6 pt-16 pb-32">` → back link "← All posts" (`/blog`) → `<PublicBlogArticle :post="post" />`. The `createError(… fatal)` path renders Nuxt's error page for drafts / unknown slugs (a 404 status for crawlers). `error` from `useFetch` is unused once we branch on `post` — omit it to keep eslint quiet.

- [ ] **Step 3: Verify** — eslint + typecheck. With the dev stack up, `curl -s http://127.0.0.1:3003/blog | grep -c 'Notes from the workbench'` → 1, and `curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:3003/blog/does-not-exist` → 404.

---

### Task 7: Admin list — `/admin/blog`

**Files:**
- Create: `frontend/app/pages/admin/blog/index.vue`

**Interfaces:** consumes `GET /v1/admin/blog/posts?status=&q=&page=` (Task 3) via `useAdminAuth().apiFetch`; `blogStatusOptions`, `fmtBlogDate`, `BlogPostAdmin` from `~/data/blog`; `AdminExpandingSearch`, `AdminStatusFilter`.

- [ ] **Step 1: Page** — mirror `pages/admin/projects/index.vue`: header ("Blog", `N posts · N published · N drafts`, "New post" → `/admin/blog/new`), toolbar (`AdminExpandingSearch v-model="filters.q"` debounced 400 ms, `AdminStatusFilter v-model="filters.status" :options="blogStatusOptions"`), loading / error / empty states ("No posts yet. Write the first one."), and a **table** (not cards — rows are text-heavy): columns Title (+ slug mono under it), Status pill (`draft` → `--status-draft-bg/fg`, `published` → `--status-succeeded-bg/fg`), Category, Published (`fmtBlogDate` or "—"), Read (`N min`), Views, actions (`btn-table-action` Edit → `/admin/blog/{id}`; `btn-table-action is-danger` Delete with `confirm()`). Pagination Prev/Next from `meta.last_page` like `/team/payments`. On mobile the table scrolls horizontally inside `overflow-x-auto`.

- [ ] **Step 2: Verify** — eslint + typecheck.

---

### Task 8: Admin editor — `/admin/blog/[id]` + `BlogSectionEditor`

**Files:**
- Create: `frontend/app/components/admin/BlogSectionEditor.vue`
- Create: `frontend/app/pages/admin/blog/[id].vue`

**Interfaces:**
- `BlogSectionEditor` props `{ modelValue: BlogSection, index: number, count: number }`, emits `update:modelValue`, `move-up`, `move-down`, `remove`.
- Page consumes Task 3 endpoints, `PublicBlogArticle` (Task 5) for preview, and `newSection`, `templateSections`, `parseMarkdownImport`, `slugify`, `readingMinutes`, `blogCtaDefaults`, `blogVoiceGuide` from `~/data/blog`.

- [ ] **Step 1: `BlogSectionEditor.vue`**

```vue
<script setup lang="ts">
import type { BlogSection } from '~/data/blog'

const props = defineProps<{ modelValue: BlogSection, index: number, count: number }>()
const emit = defineEmits<{ 'update:modelValue': [BlogSection], 'move-up': [], 'move-down': [], 'remove': [] }>()

const section = computed({ get: () => props.modelValue, set: v => emit('update:modelValue', v) })
function patch(p: Partial<BlogSection>) { emit('update:modelValue', { ...props.modelValue, ...p }) }
const body = computed({ get: () => props.modelValue.body_md, set: v => patch({ body_md: v }) })
const showImage = ref(!!props.modelValue.image_url)
const showQuote = ref(!!props.modelValue.quote)

// Compact toolbar — the article layout owns H2 (section headings), so the
// body offers H3 only. Groups render as separated clusters.
const toolbarItems = [
  [
    { kind: 'mark', mark: 'bold', icon: 'i-lucide-bold', label: 'Bold' },
    { kind: 'mark', mark: 'italic', icon: 'i-lucide-italic', label: 'Italic' },
    { kind: 'link', icon: 'i-lucide-link', label: 'Link' },
  ],
  [
    { kind: 'heading', level: 3, icon: 'i-lucide-heading-3', label: 'Subheading' },
    { kind: 'bulletList', icon: 'i-lucide-list', label: 'Bullet list' },
    { kind: 'orderedList', icon: 'i-lucide-list-ordered', label: 'Numbered list' },
    { kind: 'blockquote', icon: 'i-lucide-text-quote', label: 'Quote' },
    { kind: 'codeBlock', icon: 'i-lucide-code', label: 'Code' },
  ],
] as const
</script>

<template>
  <div class="rounded-2xl border p-4 sm:p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
    <div class="flex items-center gap-2 mb-3">
      <span class="text-[11px] font-semibold uppercase tracking-widest" :style="{ color: 'var(--color-text-tertiary)' }">Section {{ index + 1 }}</span>
      <div class="ml-auto flex items-center gap-1">
        <button type="button" class="btn-table-action" :disabled="index === 0" aria-label="Move up" @click="emit('move-up')"><UIcon name="i-lucide-chevron-up" class="size-3.5" /></button>
        <button type="button" class="btn-table-action" :disabled="index === count - 1" aria-label="Move down" @click="emit('move-down')"><UIcon name="i-lucide-chevron-down" class="size-3.5" /></button>
        <button type="button" class="btn-table-action is-danger" @click="emit('remove')"><UIcon name="i-lucide-trash-2" class="size-3.5" />Remove</button>
      </div>
    </div>
    <input :value="section.heading" type="text" placeholder="Section heading" class="contact-input w-full text-[16px] font-semibold mb-3" @input="patch({ heading: ($event.target as HTMLInputElement).value })">
    <UEditor v-slot="{ editor }" v-model="body" content-type="markdown" placeholder="Write this section…" class="blog-editor rounded-xl border" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <UEditorToolbar :editor="editor" :items="toolbarItems" layout="fixed" class="border-b px-2 py-1 flex-wrap" :style="{ borderColor: 'var(--color-border)' }" />
    </UEditor>
    <div class="flex flex-wrap gap-2 mt-3">
      <button v-if="!showImage" type="button" class="btn-table-action" @click="showImage = true"><UIcon name="i-lucide-image" class="size-3.5" />Add image</button>
      <button v-if="!showQuote" type="button" class="btn-table-action" @click="showQuote = true"><UIcon name="i-lucide-quote" class="size-3.5" />Add quote</button>
    </div>
    <div v-if="showImage" class="grid sm:grid-cols-2 gap-3 mt-3">
      <input :value="section.image_url ?? ''" type="text" placeholder="Image URL (https://… or /path)" class="contact-input w-full" @input="patch({ image_url: ($event.target as HTMLInputElement).value || null })">
      <input :value="section.image_alt ?? ''" type="text" placeholder="Image description (alt text)" class="contact-input w-full" @input="patch({ image_alt: ($event.target as HTMLInputElement).value || null })">
    </div>
    <div v-if="showQuote" class="grid sm:grid-cols-[1fr_200px] gap-3 mt-3">
      <input :value="section.quote ?? ''" type="text" placeholder="Pull quote" class="contact-input w-full" @input="patch({ quote: ($event.target as HTMLInputElement).value || null })">
      <input :value="section.quote_by ?? ''" type="text" placeholder="Attribution (optional)" class="contact-input w-full" @input="patch({ quote_by: ($event.target as HTMLInputElement).value || null })">
    </div>
  </div>
</template>
```

If `UEditor` does not expose `editor` on its default slot in this @nuxt/ui build, bind a template ref and read `editorRef.value?.editor` instead (the component exposes `editor` — see `Editor.vue.d.ts` line 91). If the `.blog-editor` ProseMirror area needs a minimum height, add `.blog-editor .ProseMirror { min-height: 160px; padding: 12px 14px; }` to `main.css`.

- [ ] **Step 2: Editor page** — state:

```ts
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })
import PublicBlogArticle from '~/components/public/BlogArticle.vue'
import AdminBlogSectionEditor from '~/components/admin/BlogSectionEditor.vue'
import AdminSelect from '~/components/admin/Select.vue'
import { blogCtaDefaults, blogVoiceGuide, newSection, parseMarkdownImport, readingMinutes, slugify, templateSections, type BlogPostAdmin, type BlogPostPublic, type BlogSection } from '~/data/blog'

const route = useRoute(); const { apiFetch } = useAdminAuth(); const toast = useAdminToast()
const isNew = computed(() => route.params.id === 'new')
const form = reactive({ title: '', slug: '', excerpt: '', sections: [] as BlogSection[], cover_image_url: '', cover_image_alt: '', category: '', tags: '', cta_heading: '', cta_body: '', cta_label: '', cta_url: '', seo_title: '', seo_description: '' })
const post = ref<BlogPostAdmin | null>(null)      // server copy (status, published_at, id)
const autoSlug = ref(true)                          // off once the founder edits the slug
const categories = ref<string[]>([])                // datalist suggestions from the admin list
const saving = ref(false); const errors = ref<Record<string, string[]>>({})
let savedSnapshot = ''
const dirty = computed(() => JSON.stringify(form) !== savedSnapshot)
const liveMinutes = computed(() => readingMinutes(form.excerpt, form.sections))
watch(() => form.title, t => { if (autoSlug.value && (isNew.value || post.value?.status === 'draft')) form.slug = slugify(t) })
```

- `hydrate(p)` copies the record into `form` (nulls → `''`, tags → comma string), sets `post`, `autoSlug = false` for existing posts, `savedSnapshot = JSON.stringify(form)`.
- `payload()` returns the form with `''` → `null`, tags split on commas, `sections` as-is.
- `save()`: POST when new (then `navigateTo(`/admin/blog/${id}`, { replace: true })`), PUT otherwise; on 422 fill `errors` (keys like `sections.0.heading` are shown at the top as a list); toast.
- `publish()`: saves first if dirty, then `POST …/publish`; 422 → toast the first error. `unpublish()` similar.
- `preview()`: `POST /api/v1/admin/blog/render` with `{ excerpt, sections }` → build a `BlogPostPublic` from form + response (`related: []`, `published_at: post?.published_at ?? new Date().toISOString()`) → `previewPost.value = …; previewOpen.value = true`. Overlay: `<Teleport to="body"><div v-if="previewOpen" class="fixed inset-0 z-50 overflow-y-auto" :style="{ background: 'var(--color-bg)' }">` with a top bar (title "Preview", Close) and `<PublicBlogArticle :post="previewPost" preview class="max-w-7xl mx-auto px-6 py-12" />`. Escape closes (`onKeyStroke`).
- `applyTemplate()`: `if (form.sections.length && !confirm('Replace the current sections with the template?')) return` → `form.sections = templateSections()`; fill empty CTA fields from `blogCtaDefaults`.
- `importMarkdown()`: modal (`.confirm-overlay` / `.confirm-card`, `.contact-input` textarea 14 rows) → `parseMarkdownImport(text)` → title (if form title empty), excerpt (if empty), sections **appended**; toast `Imported N sections`.
- Section list: `<AdminBlogSectionEditor v-for="(s, i) in form.sections" :key="s.id" :model-value="s" :index="i" :count="form.sections.length" @update:model-value="v => form.sections[i] = v" @move-up="swap(i, i - 1)" @move-down="swap(i, i + 1)" @remove="form.sections.splice(i, 1)" />` + "Add section" (`btn-pill btn-pill-ghost`, appends `newSection()`).
- Unsaved guard: `onBeforeRouteLeave(() => !dirty.value || confirm('You have unsaved changes. Leave anyway?'))` and a `beforeunload` listener added in `onMounted`, removed in `onUnmounted`.
- Layout: `<div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">` → back link → header row (title "New post" / "Edit post", status pill, and on the right the action buttons: Preview `btn-pill-ghost`, Save `btn-pill-ghost`, Publish `btn-pill-accent` / Unpublish `btn-pill-warning`) → `grid lg:grid-cols-[1fr_340px] gap-6`:
  - **Main:** Title (`contact-input text-[20px] font-semibold`), Excerpt (`textarea rows=3 maxlength=500` + `N/500 · ~M min read`), helper row (Start from template · Import Markdown · Voice & structure toggle → collapsible card listing `blogVoiceGuide.voice` and `.structure`), section cards, Add section.
  - **Side rail (`lg:sticky lg:top-20 space-y-4`):** Cover (URL text input + alt + `<img>` thumbnail when set), Category (`<input list="blog-categories">` + `<datalist>`) & Tags (comma text), Closing CTA (4 inputs; placeholder shows the defaults), SEO (title + description with `N/70`, `N/160` counters), Slug (input + "Auto from title" checkbox; amber note when `post?.status === 'published' && form.slug !== post.slug`: "Changing the slug breaks the old link.").
- Mobile: single column; the header action row wraps; the rail cards stack below the sections.

- [ ] **Step 3: Verify** — eslint + typecheck. Manual: create → template → type → preview → publish → visit `/blog/<slug>` → edit → unpublish → confirm 404 publicly.

---

### Task 9: Docs + final verification

**Files:**
- Create: `docs/global/BLOG.md`
- Modify: `docs/global/ARCHITECTURE.md` (tables: a `### Blog` block after Team workspace; API routes: public + admin blog blocks; frontend routes: `/blog`, `/blog/[slug]`, `/admin/blog`, `/admin/blog/[id]`; the swr sentence), `docs/global/PLATFORM-OVERVIEW.md` (storefront table rows for `/blog` + `/blog/[slug]`; header nav sentence; admin sidebar line + a `### Blog — /admin/blog` subsection), `docs/frontend/PUBLIC-COMPONENTS.md` (`BlogCard`, `BlogToc`, `BlogArticle`), `docs/frontend/ADMIN-COMPONENTS.md` (`BlogSectionEditor` + the editor page), `docs/frontend/UI-STANDARDS.md` (§7: `.blog-prose` / `.blog-toc`).

- [ ] **Step 1: `BLOG.md`** sections: What it is · Model (`blog_posts`, section shape, Markdown + safe render, images by URL) · Endpoints (public + admin) · The editor (layout, helpers, preview, publish gate, slug rule) · **Writing your next post** (numbered: open `/admin/blog` → New post → paste your draft into Import Markdown *or* Start from template → title/excerpt → sections → cover URL + alt, category, tags → CTA → SEO → Preview → Publish → share row) · Follow-ups (uploads, RSS, comments, scheduling, marketer authoring, slug redirects).
- [ ] **Step 2: Full verification** — `vendor/bin/phpunit` (whole suite), Pint on every touched backend file, `npx eslint` on every touched frontend file, `npm run typecheck`, `php artisan migrate` on the dev DB (additive), and the two curl checks from Task 6.
- [ ] **Step 3: Checkpoint** — report to the founder; commit only when asked.
