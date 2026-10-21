# Blog

The site's blog: posts about UI/UX, custom systems and the everyday tech decisions Malaysian business owners and founders run into. The founder writes and publishes from **`/admin/blog`** (Catalog › Blog); readers see **`/blog`** and **`/blog/{slug}`**. No code changes per article.

Design record: [BLOG-DESIGN.md](./BLOG-DESIGN.md) · build plan: [BLOG-PLAN.md](./BLOG-PLAN.md).

## The model

One table, **`blog_posts`** (soft-deletes), migration `2026_09_26_000002`:

| Column | Notes |
|---|---|
| `slug` (unique) | Generated from the title when blank, always normalised (`Str::slug`) and de-duplicated with `-2`, `-3`… **across all rows including soft-deleted ones** (the unique index is absolute). Editable; the editor warns when a published post's slug changes because the old URL simply 404s (no redirect table) |
| `title`, `excerpt` | Title ≤ 160. The excerpt (≤ 500, counting Markdown markers) is the lede on the article and the card text on the index. Stored as Markdown limited to inline `**bold**` / `*italic*`: the article gets `excerpt_html` (`BlogMarkdown::introHtml` — `<p>`/`<strong>`/`<em>` only, anything else flattened to text); cards, the SEO/OG fallback and the connector list get `excerpt` as plain text (`BlogMarkdown::plainText`). Only the admin/connector edit APIs return the raw Markdown |
| `sections` (json) | Ordered list of `{id, heading, body_md, image_url, image_alt, quote, quote_by}`. **Markdown is the storage format.** `id` is a stable `s_xxxxxx` the editor mints (v-for key, survives reorders) |
| `faqs` (json, nullable) | 0–8 `{question, answer}` pairs (`BlogPost::FAQ_MAX`; question ≤ 200, answer ≤ 1200), **plain text**: trimmed, tags stripped (`strip_tags` — the body's `html_input => 'strip'` posture), fully blank rows dropped, a half-filled row is a field error (`required_with` both ways, named "FAQ question" / "FAQ answer"). Rendered as the **Frequently asked questions** block after the last section — static `<h2>` + `<h3>` question / `<p>` answer pairs, never collapsed — and emitted as **`FAQPage`** JSON-LD by `BlogArticle`. `toc()` appends `{id: frequently-asked-questions, heading: Frequently asked questions}` last when a post has FAQs and reserves that anchor (a section with the same heading becomes `-2`). Null (rows from before the column) reads as `[]` through `BlogPost::faqList()`; the admin and public resources always serve an array, cards never carry it. Migration `2026_10_22_000001` |
| `cover_image_url` / `cover_image_alt` | **URL only, no upload** — same as `projects.cover_image_url`. Absolute `http(s)://` or a root-relative path |
| `category`, `tags` | One category + ≤ 10 tags (the editor's "topics"). Together they are the post's **topics** — the index's topic dropdown lists category ∪ tags and `?topic=` matches either. There is **no taxonomy table**: the names in use are derived from the posts (see *Categories & topics* below) |
| `format` | Editorial format — `article` (default) \| `guide` \| `tutorial` \| `case_study` \| `opinion` \| `news` (`BlogPost::FORMATS`). Rendered as the accent eyebrow before the date ("GUIDE · 26 September 2026") on cards and the article; a dropdown in the editor's right rail. Migration `2026_09_26_000003` |
| `cta_*` | Closing call to action (heading / body / label / url). Null = the shared defaults in `config/blog.php` (`cta_defaults`, → `/contact`) — the public API fills each empty field from them, so readers always get a complete CTA |
| `seo_title` / `seo_description` | Fall back to title / excerpt |
| `reading_minutes` | Derived on save: excerpt + section bodies at 200 wpm, min 1 (`BlogMarkdown::readingMinutes`) |
| `status`, `published_at` | `draft` \| `published`. Drafts may be incomplete. First publish stamps `published_at`; unpublish keeps it, so a re-publish doesn't re-date the post |

### The writing guide — one copy

The voice rules, the default structure, and the default closing CTA live in **[`backend/config/blog.php`](../../backend/config/blog.php)** — the only copy. [`App\Support\BlogGuide`](../../backend/app/Support/BlogGuide.php) serves them to the editor (`GET /v1/admin/blog/guide` → the Voice & structure panel, CTA placeholders, Start-from-template's CTA fill, and the category/topic pickers' `categories`/`tags` lists), to Claude through the MCP connector (`GET /v1/connector/blog/guide`, plus categories/tags in use and the image rules), and `PublicBlogPostResource` fills empty `cta_*` from it. Edit the file and deploy — the editor and Claude both follow the new rules.

### Categories & topics — derived, no table

There is no taxonomy table. [`App\Services\Blog\BlogTaxonomy`](../../backend/app/Services/Blog/BlogTaxonomy.php)`::inUse()` reads every **non-deleted** post (drafts included) and returns the distinct `categories` and `tags`, de-duplicated **case-insensitively** (the oldest post's spelling wins) and sorted. A name exists while at least one post carries it — to retire one, take it off every post; to rename one, edit each post. Adding a new name in the editor needs no extra step: it is saved on the post and appears for every other post from then on.

- **Editor** — `components/admin/CreatableSelect.vue`: the category is a single-pick dropdown, topics a checkbox dropdown with chips; both search, and both offer `Add "…"` only when no existing name matches case-insensitively, so the picker itself prevents `cloudflare` beside `Cloudflare`. The server does **not** re-case editor saves — that is what lets the founder fix a name's capitalisation.
- **Connector** — Claude has no picker, so `BlogTaxonomy::snap()` rewrites a sent `category`/`tags` to the spelling **other** posts already use (the post being saved is excluded, so a re-cased name only it carries sticks).
- **Both** — `BlogPostInput::derive()` trims tags and drops case-insensitive duplicates within the post.

If renaming everywhere or pre-creating empty topics is ever needed, a managed table can be seeded from `inUse()` without losing anything.

### Markdown → safe HTML

[`App\Support\BlogMarkdown`](../../backend/app/Support/BlogMarkdown.php) renders sections with the CommonMark converter Laravel ships (`Str::markdown`, GFM) using `html_input => 'strip'` and `allow_unsafe_links => false`. Raw HTML and `javascript:`/`data:` links never reach the page, which is what makes the public `v-html` safe. Rendering happens on **read** (`BlogPost::renderedSections()`) — the admin API only ever carries Markdown. Zero new dependencies: the admin editor is Nuxt UI's `UEditor` in `content-type="markdown"` (Tiptap + `@tiptap/markdown`, already bundled).

`BlogPost::toc()` builds `[{id, heading}]` anchors from the section headings, de-duplicated within the post (`keep-the-human-part`, `keep-the-human-part-2`), and ends with the FAQ block's entry (`frequently-asked-questions`, reserved — a section with that heading becomes `-2`) when the post has FAQs.

## Endpoints

```
# Public (no auth; the Nuxt pages sit behind swr 300s, purged on every live change — see Frontend → Cache)
GET  /v1/blog/posts?format=&topic=&page=   Published only, newest first, 12/page. `format` ∈ FORMATS (422
                                      otherwise); `topic` matches the category OR a tag, case-insensitively.
                                      Card fields + top-level `formats: [{value, count}]` and
                                      `topics: [{name, count}]` (category ∪ tags across published posts)
GET  /v1/blog/posts/{slug}            Published only (draft / deleted → 404). Card fields +
                                      sections[].body_html + anchor, faqs, toc, cta_*, seo_*, related[3]
GET  /v1/blog/slugs                   Unpaginated {slug, published_at, updated_at} — the sitemap feed

# Admin (/v1/admin/blog/*, cockpit token — Admin\BlogPostsController)
GET    /posts?status=&q=&page=        20/page, newest updated first, each row + `views`
                                      (COUNT of page_views at /blog/{slug})
POST   /posts                         Create (draft). Slug optional
GET    /posts/{post}                  Editable record (Markdown, never HTML)
PUT    /posts/{post}                  Update — a published post's edits go live on save
POST   /posts/{post}/publish          Completeness gate: title + excerpt + ≥1 section with heading and
                                      body → 422 with field errors otherwise
POST   /posts/{post}/unpublish        Back to draft
DELETE /posts/{post}                  Soft delete
POST   /render                        {excerpt?, sections[], faqs[]?} → {excerpt_html, sections[].body_html + anchor,
                                      faqs, toc, reading_minutes}. The editor's Preview; saves nothing
GET    /guide                         {data: {voice, structure, cta_defaults, formats}} from config/blog.php

# MCP connector (/v1/connector/blog/*, connector:read / connector:draft — see MCP-CONNECTOR.md)
GET  /guide · GET /posts · GET /posts/{id}   Read any non-deleted post + the writing guide
POST /posts                                  Create — always a draft; image alt text required
PUT  /posts/{id}                             Partial update of a DRAFT (`faqs` sent → replaced, omitted → kept,
                                             [] → cleared); published → 422
```

Both writers (editor and connector) save through [`App\Services\Blog\BlogPostInput`](../../backend/app/Services/Blog/BlogPostInput.php) — the editor as a full replace, the connector as a partial patch (a title change never moves the slug; reading time is recomputed from the merged post; `faqs` is replaced only when sent — the editor's full save always sends it).

Tests: `backend/tests/Feature/Blog/` (model rules, public feed, admin CMS).

## Frontend

- **Public:** `pages/public/blog/index.vue` (grid; **format pills** on the left via `?format=`, a **topic dropdown** on the right via `?topic=`; pagination) and `pages/public/blog/[slug].vue` (SSR-awaited fetch; `usePublicSeo` + `BlogPosting` and `BreadcrumbList` JSON-LD; an unknown or draft slug throws a real 404). Shared pieces in `components/public/`: `BlogCard`, `BlogToc` (active-heading tracking via IntersectionObserver), `BlogArticle` (the one layout — full-width header with the format eyebrow, byline "By Ahmad Baihaqie, Founder" + "Updated …" when edited after publishing, and cover; then a two-track body: a sticky **left pane** of "On this page" section links (from 2 sections up) and the text column on the right with anchored H2s, in-section image / pull quote, then the **Frequently asked questions** block when the post has FAQs (static `<h2>` + `<h3>` question / `<p>` answer pairs — never collapsed, so readers and crawlers get the same text — with the `FAQPage` JSON-LD injected by `BlogArticle` itself via `useHead`, skipped in the editor preview), closed by a rule + share row; then the closing CTA card full-width across both tracks (copy left, button right from `md`) and related posts full-width below. The pane collapses inline above the first section below `lg`). Article body styles are `.blog-prose` in `main.css` (tokens only; see UI-STANDARDS §7).
- **Home page:** `components/public/BlogLatest.vue` shows the three newest posts below the client previews, and renders nothing while nothing is published. `/` is also `swr: 300` and is cleared by the same purge, so it updates on the next visit.
- **Nav:** "Blog" sits after Company in `layouts/public.vue` and its mirror in `HeroEpoch.vue` (keep in sync); the footer Explore column follows the same array.
- **Sitemap:** `server/api/__sitemap__/urls.ts` feeds `sitemap.sources` from `/v1/blog/slugs` (plus services and projects — see [SEO.md](../frontend/SEO.md)). **Cache:** `/blog` and `/blog/**` are `swr: 300` for speed, but `BlogPostObserver` purges the Nuxt page cache (`SiteCache` → `POST /_cache/purge`) on publish, unpublish, and any edit/delete of a live post — so a change shows on the **next visit**. If the purge isn't configured or fails, it falls back to the five-minute window. Setup: [DEPLOY.md § Page caching](./DEPLOY.md#page-caching).
- **Analytics:** page views are tracked by path already, so `/blog/*` needs nothing new; the admin list shows per-post views.
- **Admin:** `pages/admin/blog/index.vue` (table + filters) and `pages/admin/blog/[id].vue` (`new` or an id), with `components/admin/BlogSectionEditor.vue` per section and `components/admin/BlogFaqEditor.vue` (the FAQ repeater below the sections, max 8 — see [ADMIN-COMPONENTS.md](../frontend/ADMIN-COMPONENTS.md)). Types, template, the Markdown importer, the reading-time estimate and the voice guide live in `data/blog.ts`.

## Writing your next post

1. Open **Admin › Catalog › Blog** and click **New post**.
2. Either **Import Markdown** (paste a draft: `#` → title, the paragraphs before the first `##` → introduction, each `##` → a section) or **Start from template** (five placeholder sections in the default structure plus the default closing CTA). **Voice & structure** opens the writing guidelines.
3. Fix the **title** and **introduction** (the hook — **Bold** / *Italic* toolbar, ⌘B / ⌘I; the counter shows length and the live reading time).
4. Work through the **sections**: heading, body (bold / italic / link / subheading / lists / quote / code), and optionally **Add image** (URL + alt) or **Add quote**. Move up / down to reorder; Remove to drop one.
5. **Frequently asked questions** (the block below the sections): **Add question** → a question and its answer, up to 8, move up / down to reorder. Three to six real questions readers ask, each answered directly in one to three plain-text sentences (see *Writing for Google Discover and AI answers* below). Leave it empty when there is nothing genuinely asked — an empty row is dropped on save, a half-filled one is flagged.
6. In the side rail: **cover image URL + alt**; **format** (Article / Guide / Tutorial / Case study / Opinion / News — the label before the date), **category** (a dropdown of the ones in use, or add a new one) and **topics** (search, tick existing ones, or add new — up to 10); the **closing call to action** (blank = defaults); and **SEO** overrides if the title is long.
7. **Preview** — rendered by the backend, so it is exactly what readers see (FAQs included). Close with Escape.
8. **Save draft** as often as you like. **Publish** when it is complete (the gate needs a title, an introduction and at least one filled section). It appears at `/blog/<slug>` straight away (and on the home page and `/blog` list).
9. Edit any time — changes to a published post go live on save. **Unpublish** to pull it back to a draft.

## Drafting with Claude

The MCP connector (see [MCP-CONNECTOR.md](./MCP-CONNECTOR.md)) lets Claude — in claude.ai or Claude Code — create and revise **drafts**; it can never publish, unpublish, delete, or edit a published post. Two modes:

- **"Write a post about X, cover image `<url>`"** — Claude reads the guide and writes in your voice, following the default structure.
- **"Here's my post, make it a draft"** (pasted text or a file) — your wording is kept verbatim; Claude only arranges it into title / introduction / sections and fills the missing pieces (alt text, format, category, tags, SEO), and tells you if it changed anything.

Images are URLs you supply (Unsplash, Pexels, …); Claude always writes alt text. When the topic has questions readers genuinely ask, Claude adds 3–6 `faqs` (plain-text pairs, per the guide's `faq_rules`); on an update it only touches them when it sends the key. Claude replies with the `/admin/blog/{id}` link — open it, **Preview**, then **Publish** yourself. To have Claude revise a live post, unpublish it first (or ask for a new draft).

## Writing for Google Discover and AI answers

What makes a post eligible for Discover and quotable by AI assistants. The page-level mechanics (feeds, `llms.txt`, the `BlogPosting` schema, robots policy) are in [SEO-AND-DISCOVERY.md](./SEO-AND-DISCOVERY.md); this is the authoring side:

- **Cover image at least 1200 × 675 (16:9)**, with a real `cover_image_alt`. Discover needs a large image; the article renders it at 16:9 and it doubles as the share preview.
- **Open with the answer.** The introduction is 2–4 sentences that answer the title's question directly — it is the lede on the page, the card text, the search snippet and the `BlogPosting` description.
- **Question-phrased H2s.** Section headings that read as the question a reader would type ("When does a portal pay for itself?") become the anchors, the "On this page" links and the headings assistants quote.
- **Original material over generic advice:** your own numbers, a comparison, a definition in your words. Generic listicles are neither surfaced nor cited.
- **3–6 real FAQs** in the FAQ field: questions people actually ask, answered directly in one to three plain-text sentences. They render as the "Frequently asked questions" block and as `FAQPage` schema — leave the field empty rather than padding it.

## Follow-ups (not built)

Image upload (the R2 disk exists in `config/filesystems.php`, unwired) · comments · scheduled publishing · marketer-role authoring from the team workspace · slug redirects.
