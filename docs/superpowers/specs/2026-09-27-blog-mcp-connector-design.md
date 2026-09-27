# Blog drafts via the MCP connector — design

**Date:** 2026-09-27 · **Status:** approved in chat, awaiting spec review · **Contract:** connector v3 → **v4**

## Goal

Let the founder ask Claude (claude.ai, via the existing Axel Nova MCP connector at `mcp.axelnova.tech`) to write or revise a blog post, and have it land in **Admin › Blog as a draft**. The founder previews and publishes by hand in the existing editor. Image URLs (Unsplash, Pexels, …) are **supplied by the founder** in chat; Claude never searches for or invents images.

### Success criteria

- "Write a guide about X, cover image `<url>`" → a draft appears at `/admin/blog/{id}` with title, excerpt, sections, cover + alt, format, category/tags, written to the founder's voice guide.
- "Here's my post, make it a draft" (pasted text or a file, from claude.ai or Claude Code) → the founder's wording is kept verbatim, only arranged into title / excerpt / sections, with the missing metadata filled in.
- "Change the cover on my UX audit draft" / "rewrite section 3" → only those fields change.
- Claude can **never** publish, unpublish, delete, or edit a published post.
- The existing connector token keeps working — **no token rotation** needed to ship this.
- One copy of the voice guide + default CTA, read by the editor, the public article, and Claude.

### Decisions made in chat

| Decision | Choice |
|---|---|
| Can Claude edit published posts? | **No.** Drafts only — a published post's edits go live on save, which would skip the founder's preview |
| Where do images come from? | **Founder pastes URLs.** No image-search tool (Unsplash API would add key, attribution, and download-tracking obligations) |
| Voice guide + CTA defaults | **One shared copy on the backend** (`config/blog.php`); frontend copies deleted |
| Token abilities | **Reuse `connector:read` / `connector:draft`.** They are *universal* connector abilities — read and draft-write across every module (quotations, blog, and any future one). No per-module ability |
| Who writes the words? | **Both modes.** "Write a post about X" → Claude writes to the voice guide. "Here's my post" → Claude keeps the founder's wording and only structures it + fills metadata; rewrites only when asked, and reports any change it made |

## Access model (connector v4)

Unchanged principle, extended to the blog: **read everything, write drafts only, destroy never.**

- ✅ Read any non-deleted blog post (draft or published) and the writing guide — `connector:read`.
- ✅ Create a post (always `draft`); update a post **while it is a draft** — `connector:draft`.
- ❌ Publish, unpublish, delete, or edit a published post. There are no such routes on the connector. Soft-deleted posts never surface.

A connector token is still rejected by every `/v1/admin/*` route (`abilities:cockpit`), and a cockpit token is rejected by `/v1/connector/*`.

## Backend

### Shared config — `backend/config/blog.php` (new)

```php
return [
    'voice' => [ /* the 4 voice lines, moved from frontend/app/data/blog.ts */ ],
    'structure' => [ /* the 6 structure lines */ ],
    'cta_defaults' => [
        'heading' => 'Have something like this in mind?',
        'body' => 'I’m always open to a conversation about what that could look like for your business.',
        'label' => 'Get in touch',
        'url' => '/contact',
    ],
];
```

`App\Support\BlogGuide` (new) builds the guide payload from this config so the admin and connector endpoints share one builder:

- `BlogGuide::base()` → `{voice, structure, cta_defaults, formats}` (formats from `BlogPost::FORMATS`).
- `BlogGuide::forConnector()` → `base()` + `categories` (distinct non-null categories of non-deleted posts), `tags` (union of tags in use), `section_shape` (field list + limits), `image_rules` (URL must be `https://…` or a root-relative path; alt text required; Unsplash sizing hint `?w=1600&q=80&auto=format`; only use URLs the user supplied).

### Shared write logic — `App\Services\Blog\BlogPostInput` (new)

Extracted from `Admin\BlogPostsController::validated()` / `sectionRules()` so the admin editor and the connector save through the same code (mirrors the quotation "one write shape, many writers" rule).

- `rules(bool $partial = false, bool $requireAlt = false): array` — the current rule set. `$partial` wraps each top-level field in `sometimes` (title becomes `sometimes|required`). `$requireAlt` adds `cover_image_alt: required_with:cover_image_url` and `sections.*.image_alt: required_with:sections.*.image_url` (connector only — the editor's behaviour is unchanged).
- `sectionRules(): array` — unchanged, still used by `render`.
- `derive(array $data, ?BlogPost $existing = null): array` — slug, section normalisation, tag cleanup, format default, reading time. Partial semantics:
  - **slug** is only recomputed when `slug` is sent, or when creating. A title change on update never silently changes the URL.
  - **reading_minutes** is recomputed from the *merged* excerpt + sections (sent value, else existing).
  - Fields not sent are not returned, so `update()` leaves them untouched.

`Admin\BlogPostsController` switches to `BlogPostInput` with no behaviour change (full-replace, as today). Existing `tests/Feature/Blog/*` must pass unchanged.

### Admin guide endpoint

`GET /v1/admin/blog/guide` (cockpit) → `BlogGuide::base()`. The editor reads voice/structure/CTA defaults from here.

### Public CTA fallback moves server-side

`PublicBlogPostResource` returns `cta_*` resolved against `config('blog.cta_defaults')` when the post's own value is null. `AdminBlogPostResource` keeps returning the raw (nullable) values, so the editor can still tell "default" from "custom".

### Connector blog surface — `App\Http\Controllers\Api\V1\Connector\BlogPostController` (new)

Added inside the existing `v1/connector` group in `routes/api.php`:

| Method | Path | Ability | Throttle | Behaviour |
|---|---|---|---|---|
| GET | `/v1/connector/blog/guide` | `connector:read` | 60/min | `BlogGuide::forConnector()` |
| GET | `/v1/connector/blog/posts` | `connector:read` | 60/min | Slim rows: `id, title, slug, status, format, category, excerpt (≤160 chars), updated_at, published_at, admin_url, public_url (published only)`. Filters `status` (draft\|published), `q` (title), `page`, `per_page` (default 10, silently capped 25). Newest updated first. Response `{data, meta}` like the quotation list |
| GET | `/v1/connector/blog/posts/{id}` | `connector:read` | 60/min | Full editable record (Markdown, never HTML) = `AdminBlogPostResource` fields + `admin_url` + `public_url`. Soft-deleted → 404 |
| POST | `/v1/connector/blog/posts` | `connector:draft` | 30/min | `BlogPostInput` (`requireAlt`). Forces `status=draft`; `status` / `published_at` in the body are ignored (not in the rule set). `created_by`/`updated_by` = token owner (founder). Returns the record + `admin_url`, 201 |
| PUT | `/v1/connector/blog/posts/{id}` | `connector:draft` | 30/min | **Partial** update (`partial`, `requireAlt`). If the post is published → **422** `{message: "This post is published, so changes would go live immediately. Ask the founder to unpublish it in the admin first, or create a new draft instead."}`. `sections`, when sent, replaces the list (ids preserved by `normaliseSections`) |

- Activity log: `blog_post.created` / `blog_post.updated` with `{title, via: 'mcp_connector'}` — no migration needed.
- `admin_url` = `config('services.frontend.url')/admin/blog/{id}`; `public_url` = `…/blog/{slug}` only when published.
- Error bodies are Laravel's standard 422 JSON so the Worker's passthrough lets Claude self-correct.

## Frontend

- `frontend/app/data/blog.ts` — delete `blogVoiceGuide` and `blogCtaDefaults`; add a `BlogGuide` type.
- `frontend/app/pages/admin/blog/[id].vue` — fetch `/v1/admin/blog/guide` once on mount; the Voice & structure panel, CTA placeholders, and `applyTemplate()` CTA fill use it. While it loads (or if it fails) the panel shows nothing and placeholders are blank — the editor stays fully usable. `templateSections()` stays in the frontend (editor UX, not a writing rule).
- `frontend/app/components/public/BlogArticle.vue` — drop the fallback; render `post.cta_*` as served (always filled by the backend now).

## Worker — `connector/src/index.ts`

`CONNECTOR_VERSION` → `"4.0.0"`. New constants `BLOG_GUIDE_PATH`, `BLOG_POSTS_PATH`. Five tools, all using the existing `apiFetch` + `passthrough`:

| Tool | Input | Description highlights |
|---|---|---|
| `get_blog_guide` | — | **Call FIRST before writing or revising a post.** Voice, structure, formats, categories/tags in use, default CTA, image rules |
| `list_blog_posts` | `status?`, `q?`, `page?`, `per_page?` | Find a post without knowing its id; slim rows — use `get_blog_post` for the full text |
| `get_blog_post` | `id` | Full Markdown record; read before any update |
| `create_blog_draft` | title (required), slug?, excerpt?, sections[]?, cover_image_url?, cover_image_alt?, format?, category?, tags?, cta_*?, seo_title?, seo_description? | Always a DRAFT, never published. **Two modes:** (1) writing from a topic/brief → follow the guide's voice + structure: the excerpt is the hook, 2–4 practical sections, a takeaway; (2) the user supplies their own text → **keep their wording verbatim**, only arrange it (first heading/line → title, text before the first section heading → excerpt, each heading → a section) and fill what's missing (alt text, format, category, tags, SEO); rewrite only when asked, and list any change made in the reply. Either way: do not repeat the closing CTA inside a section; use ONLY image URLs the user gave; always write alt text. Returns `admin_url` for the founder to preview + publish |
| `update_blog_draft` | `id` + any create field | Partial: send only what changes. `sections`, if sent, replaces the whole list — read the post first and send the full edited list, keeping each section's `id`. Same wording rule: user-supplied text is kept verbatim unless a rewrite is asked for. Refused for published posts |

Zod schemas mirror the backend limits (title ≤160, excerpt ≤500, ≤40 sections, heading ≤120, body_md ≤20000, tags ≤10 × ≤40, seo_title ≤70, seo_description ≤160, format enum). The server name stays `axelnova-quotations` (renaming isn't needed to connect). `auth.ts` login copy: "…requesting access to draft quotations and blog posts."

## Deploy

1. Merge the PR → CI deploys Laravel (config + routes live).
2. `cd connector && npx wrangler deploy` — founder runs it by hand.
3. Refresh/reconnect the connector in claude.ai to pick up the new tools. Claude Code gets the same tools through the synced "claude.ai Axel Nova MCP" connector (authorize once via `/mcp`).

No token rotation: the existing `connector:read` + `connector:draft` token already opens the blog routes. The normal 30-day `npm run rotate-token` cadence is unchanged.

## Testing

`backend/tests/Feature/Connector/ConnectorBlogTest.php` (new; runs on the forced `axelnova_dashboard_test` DB):

- Cockpit token → 403 on every blog connector route; read-only token (`connector:read`) → 403 on POST/PUT; no token → 401.
- `connector:read` token without `connector:draft` can read guide/list/show.
- Create always stores `status=draft`, `published_at=null`, even when the body sends `status: published` / `published_at`.
- Create with `cover_image_url` but no alt → 422; same for a section image.
- Update of a published post → 422 with the instructive message; the post is unchanged.
- Partial update: sending only `cover_image_url`/`alt` leaves title, sections, slug, reading time untouched; sending `title` alone keeps the slug; sending `sections` recomputes reading time.
- Slug de-duplication still holds via the connector.
- Soft-deleted post → 404 on show, absent from list.
- List pagination cap (per_page 100 → 25), `status`/`q` filters, `public_url` only on published rows.
- Activity log rows carry `via: mcp_connector`.
- Guide returns config voice/structure/CTA, `BlogPost::FORMATS`, categories/tags in use (excluding soft-deleted posts).

Also:
- `tests/Feature/Blog/*` pass unchanged after the `BlogPostInput` extraction.
- `AdminBlogPostsTest`: `GET /v1/admin/blog/guide` returns the config payload (cockpit only).
- `PublicBlogTest`: a post with null `cta_*` is served with the config defaults; a post with its own CTA keeps it.
- Frontend: ESLint + `vue-tsc`. Worker: `npm run typecheck` and `npx wrangler deploy --dry-run --outdir=dist`.

## Docs

- `docs/global/MCP-CONNECTOR.md` — v4 access model, "abilities are universal across modules" rule, blog endpoints + tools, updated tool list in "Adding it in claude.ai", file map.
- `docs/global/BLOG.md` — `config/blog.php` as the source of voice + CTA defaults, admin guide endpoint, server-side CTA fallback, "Drafting with Claude" subsection (both modes, from claude.ai or Claude Code).
- `docs/global/ARCHITECTURE.md` — new routes.

## Out of scope

Image search/upload, publishing or unpublishing through the connector, deleting posts, per-module connector abilities, renaming the MCP server, copying a published post into a new draft as a dedicated tool (Claude can do it with `get_blog_post` + `create_blog_draft`).
