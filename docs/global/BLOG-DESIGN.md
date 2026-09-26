# Blog — design spec

> Status: **approved 2026-09-26** (brainstormed in chat with the founder). The implementation plan is [BLOG-PLAN.md](./BLOG-PLAN.md); the living reference once built is [BLOG.md](./BLOG.md).

## Goal

A reusable blog for axelnovaventures.com that the founder writes and publishes entirely from the admin (`/admin/blog`), with no code change per article. Posts cover tech, systems, UI/UX and the industries the business serves, written for Malaysian business owners and founders. Two purposes, weighted equally: **inbound leads** (search traffic routed to services / contact) and **authority and reach** (a clean reading experience worth sharing to LinkedIn and Threads).

## Decisions taken in brainstorming

| Decision | Choice | Why |
|---|---|---|
| Content model | Structured sections stored as JSON on one `blog_posts` table | Matches the brief (add / remove / reorder sections, each with a heading + rich text); matches how the repo already stores structured content (`quotations.form_payload`, `projects.tags`) |
| Storage format for section bodies | **Markdown** | The Nuxt UI editor (`@nuxt/ui` 4.9) supports `contentType="markdown"` with `@tiptap/markdown` already bundled; Laravel ships CommonMark (`Str::markdown`). Human-readable, diff-able, and the founder's drafts already arrive as Markdown. Zero new dependencies |
| Rendering | Backend renders Markdown → HTML per section with CommonMark, `html_input => 'strip'`, `allow_unsafe_links => false` | Raw HTML / scripts can never reach the public page; the frontend only ever `v-html`s backend-rendered output |
| Images | **URL only** (cover + per-section), like `projects.cover_image_url` | No upload infrastructure now; additive later (R2 disk exists in `config/filesystems.php`, unwired) |
| Authoring | Founder only, in the cockpit under **Catalog** | As requested; marketer authoring is a follow-up |
| Preview | In-admin full-screen overlay rendering the exact public article component from the current form state, with HTML from the backend's render endpoint | No token-gated preview URL needed; drafts 404 publicly; preview == public output |
| Navbar | "Blog" placed **after Company** | As requested |
| Out of scope (follow-ups) | Image upload, RSS, comments, scheduled publishing, marketer authoring, slug redirects | YAGNI |

## 1. Data

### `blog_posts` (soft-deletes)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `slug` | string(120), unique | Auto from title when blank (`Str::slug`), de-duplicated with `-2`, `-3`… Editable; UI warns when changing a published post's slug |
| `title` | string(160) | Required |
| `excerpt` | text | Required to publish; ≤ 500 chars. The lede on the article page and the card text on the index |
| `sections` | json | Ordered list, see shape below. ≥ 1 complete section required to publish |
| `cover_image_url` | string(500) nullable | URL only |
| `cover_image_alt` | string(160) nullable | |
| `category` | string(60) nullable | Free text with suggestions from existing posts (no taxonomy table) |
| `tags` | json | List of strings, ≤ 10, each ≤ 40 |
| `cta_heading` | string(120) nullable | Closing call to action; the public page falls back to defaults when null |
| `cta_body` | text nullable | ≤ 500 |
| `cta_label` | string(60) nullable | Button text |
| `cta_url` | string(500) nullable | Internal path or absolute URL; default `/contact` |
| `seo_title` | string(70) nullable | Falls back to `title` |
| `seo_description` | string(160) nullable | Falls back to `excerpt` |
| `reading_minutes` | unsigned smallint | Derived on save: words in excerpt + section bodies (Markdown stripped) / 200, min 1 |
| `status` | enum `draft` \| `published` | |
| `published_at` | timestamp nullable | Stamped on first publish; kept on re-publish; unpublish leaves it (status is the gate) |
| `created_by` | FK users | |
| `updated_by` | FK users nullable | |
| timestamps + `deleted_at` | | |

Indexes: `slug` unique; `(status, published_at)`; `category`.

### Section shape (one element of `sections`)

```json
{
  "id": "s_8f2a",                      // client-generated, stable across reorders (v-for key + TOC anchor seed)
  "heading": "Notice what keeps repeating",
  "body_md": "Do customers ask for the same updates? …",
  "image_url": null, "image_alt": null,
  "quote": null, "quote_by": null
}
```

Validation per section: `heading` required ≤ 120; `body_md` required to publish, ≤ 20 000 chars; `image_url` / `cta_url` valid URL (or leading-slash path for `cta_url`); `image_alt` ≤ 160; `quote` ≤ 500; `quote_by` ≤ 80.

### Derived on read

- `sections[].body_html` — CommonMark render (GFM, `html_input: strip`, `allow_unsafe_links: false`).
- `toc` — `[{ id, heading }]` from section headings; `id` = `Str::slug(heading)` de-duplicated within the post; the public page renders `<h2 :id>` anchors from it.
- `related` (public show only) — up to 3 other published posts, same category first, then newest.
- `views` (admin index only) — `COUNT(page_views) WHERE path = '/blog/{slug}'`, one grouped query.

## 2. API

### Public (no auth, cache-friendly)

```
GET /v1/blog/posts?category=&page=   Published only, newest published_at first, 12/page.
                                     Card fields: slug, title, excerpt, cover_image_url/alt, category,
                                     tags, reading_minutes, published_at. meta.categories = [{name, count}]
GET /v1/blog/posts/{slug}            Published only (draft / deleted → 404). Full post + sections[].body_html
                                     + toc + related[] (card fields) + cta_* + seo_*
```

### Admin (`/v1/admin/blog/*`, cockpit token)

```
GET    /v1/admin/blog/posts          ?status=draft|published, ?q= (title). Paginated 20, newest updated first.
                                     Each row + views
POST   /v1/admin/blog/posts          Create (draft by default). Body = the editable fields; slug optional
GET    /v1/admin/blog/posts/{post}   Full editable record (body_md, not html)
PUT    /v1/admin/blog/posts/{post}   Update (any status; a published post's edits go live on save)
POST   /v1/admin/blog/posts/{post}/publish    Validates publishability (title, excerpt, ≥1 section with
                                     heading + body_md) → 422 with field errors otherwise; stamps
                                     published_at if null
POST   /v1/admin/blog/posts/{post}/unpublish  status → draft (published_at kept)
DELETE /v1/admin/blog/posts/{post}   Soft delete (vanishes from public + admin list)
POST   /v1/admin/blog/render         Preview helper: {excerpt?, sections[]} → {sections: [{id, body_html}],
                                     toc, reading_minutes}. Same renderer the public show uses, so the
                                     admin preview is byte-for-byte what readers get. Nothing is saved
```

Slug rule on create/update: if `slug` blank → generate from title; always normalise with `Str::slug`; unique among **all** rows including soft-deleted ones (the DB unique index is absolute) excluding self, else suffix `-2`, `-3`…

## 3. Admin editor (frontend)

- Nav: Catalog → **Blog** (`/admin/blog`, icon `i-lucide-newspaper`) after Projects.
- `/admin/blog` — list: title, status pill (Draft / Published), category, published date, reading time, views; status filter (`AdminStatusFilter`), search (`AdminExpandingSearch`), "New post".
- `/admin/blog/[id]` — `id === 'new'` creates, mirroring `/admin/projects/[id]`.
  - **Main column:** title, excerpt, section cards, "Add section".
  - **Sticky side rail:** Publish card (status pill, Save draft / Save changes, Publish / Unpublish, Preview), Cover (URL + alt, live thumbnail), Category (input with datalist of existing) + Tags (chip input), Closing CTA (heading / body / label / URL, all optional), SEO (title + description with live character counts), Slug (auto-from-title toggle; warning when editing a published post's slug).
  - **Section card:** heading input; `<UEditor content-type="markdown">` with a compact toolbar (bold, italic, link, H3, bullet list, ordered list, blockquote, code); "Add image" reveals URL + alt; "Add quote" reveals quote + attribution; move up / move down / remove. Mobile: single column, cards full-width, toolbar wraps.
  - **Helpers above the sections:** "Start from template" (confirms if sections exist; pre-fills headings: *The problem*, *Why it matters*, *Practical step 1*, *Practical step 2*, *Key takeaway*; CTA defaults), "Import Markdown" (textarea modal; `# ` → title, paragraphs before the first `## ` → excerpt, each `## ` → a section with the Markdown below it as `body_md`), collapsible "Voice & structure" note (the founder's writing guidelines, static copy in `data/blog.ts`).
  - Live reading-time estimate next to the excerpt (same 200 wpm rule, computed client-side for display only; backend value is authoritative).
  - Preview: full-screen overlay (Teleport) that renders `PublicBlogArticle` with the form state; section HTML + toc + reading time come from `POST /v1/admin/blog/render` (the public renderer), so the preview is exactly what readers will see. Nothing is saved by previewing.
  - Unsaved-changes guard (`onBeforeRouteLeave` + `beforeunload`).

## 4. Public pages

- Navbar links (layouts/public.vue + HeroEpoch.vue mirror + footer Explore): `Home · About · Company · Blog · Projects · Services · Partners · Contact`.
- `/blog` — heading + one-line intro, category pills (All + categories), card grid (cover, category, title, excerpt, date · reading time), pagination, empty state. `useScrollReveal('.reveal')`.
- `/blog/[slug]` — meta line (category · date · reading time), title, excerpt lede, cover (`aspect-[16/9]`, `object-cover`, alt), TOC (sticky aside on `lg+` when ≥ 3 sections, inline list on mobile), sections (`<h2 :id>` anchors, `.blog-prose` body, optional image figure, optional pull-quote), closing CTA card (defaults: "Have something like this in mind?" / "I'm always open to a conversation about what that could look like for your business." / "Get in touch" → `/contact`), share row (LinkedIn, X, WhatsApp, copy link), "More from the blog" (3 cards). Reading width `max-w-[68ch]`.
- Shared components: `components/public/BlogCard.vue`, `components/public/BlogArticle.vue` (used by the public page AND the admin preview), `components/public/BlogToc.vue`.
- Styles: `.blog-prose` in `main.css` using existing tokens (text, headings, links, lists, blockquote, code, hr, img), verified light + dark. No hardcoded hex.
- SEO: `useSeoMeta` (title `seo_title ?? title` + " · Axel Nova Ventures", description, canonical, OG/Twitter with cover), JSON-LD `BlogPosting` + `BreadcrumbList`, 404 for drafts. Sitemap: `server/api/__sitemap__/urls.ts` → `sitemap.sources` (published slugs + `lastmod`). `routeRules`: `/blog: swr 300`, `/blog/**: swr 300`.
- Analytics: existing page-view tracking covers `/blog/*`; admin list shows views.

## 5. Testing

- Backend (PHPUnit, MySQL test DB): public list (published only, ordering, category filter, meta.categories, pagination), show (rendered html, toc ids, related, draft 404, deleted 404), admin CRUD + workspace-token 403, slug generation / normalisation / uniqueness, publish validation (422 field errors) + published_at semantics, unpublish, Markdown safety (script / raw HTML stripped, `javascript:` links dropped), reading time, views count.
- Frontend: ESLint + vue-tsc. The founder checks the UI visually (no screenshot verification).

## 6. Docs

- New `docs/global/BLOG.md`: model, endpoints, editor walkthrough, **"Writing your next post"** step-by-step (template / import / preview / publish), follow-ups.
- `ARCHITECTURE.md` (table row + routes + frontend routes), `PLATFORM-OVERVIEW.md` (storefront table + admin nav), `ADMIN-COMPONENTS.md` / `PUBLIC-COMPONENTS.md` entries.
