# Blog drafts via the MCP connector — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let Claude (claude.ai or Claude Code, via the Axel Nova MCP connector) read blog posts and create / partially update blog **drafts**, with the voice guide + default CTA moved to one backend copy.

**Architecture:** A shared `BlogPostInput` (rules + derivation) is extracted from the admin blog controller so the admin editor and a new `Connector\BlogPostController` save through the same code. `config/blog.php` + `App\Support\BlogGuide` become the single source of the voice guide and CTA defaults, served to the editor (`/v1/admin/blog/guide`), the public article (server-side CTA fallback), and Claude (`/v1/connector/blog/guide`). The Cloudflare Worker gains five tools that proxy the new connector routes, gated by the existing universal `connector:read` / `connector:draft` abilities.

**Tech Stack:** Laravel 11 / PHP 8.4 / Sanctum / PHPUnit (MySQL test DB), Nuxt 4 / Vue 3 / TS, Cloudflare Worker (`agents/mcp`, `@modelcontextprotocol/sdk`, zod).

**Spec:** [docs/superpowers/specs/2026-09-27-blog-mcp-connector-design.md](../specs/2026-09-27-blog-mcp-connector-design.md)

## Global Constraints

- **No commits, no pushes.** Stay on `feat/task-payment-in-payroll`. The founder commits.
- **Never run destructive DB commands** (`migrate:fresh|refresh|reset|rollback`, `db:wipe`, `down -v`, …). `php artisan test` is safe (forced `axelnova_dashboard_test`). No migration is needed for this feature.
- Run backend commands inside the container: `docker compose -f docker-compose.dev.yml exec backend …`.
- Connector abilities are universal: reads → `connector:read` (60/min), writes → `connector:draft` (30/min). No new ability.
- Connector can never publish, unpublish, delete, or edit a published post (422 on update of a published post).
- New posts via the connector are always `status=draft`, `published_at=null`.
- Image rules (connector only): `https://…` or root-relative path (`regex:#^(https?://|/[^/])#`); alt text required when an image URL is set.
- Activity log `changes` carry `via: 'mcp_connector'` for connector writes.
- Worker `CONNECTOR_VERSION = "4.0.0"`; server name stays `axelnova-quotations`.
- Frontend: tokens only, no hex; editor must stay usable when the guide fails to load.

## Review Focus

1. **Cover URL sent without alt on update, but the post already has alt** → accepted (check the *merged* state, not the request). Test in Task 3.
2. **Update with `title` only** → slug unchanged (a URL never moves unless `slug` is sent). Test in Task 2 (derive) + Task 3 (endpoint).
3. **Update with `title: ""`** → 422 (partial `sometimes|required`). Test in Task 3.
4. **Update sends `sections` where one item omits `id`** → that item gets a fresh id, the others keep theirs. Test in Task 3.
5. **Non-existent / soft-deleted id** on show or update → 404 with an instructive message, never a 500. Test in Task 3.

---

### Task 1: Shared config, `BlogGuide`, admin guide endpoint, server-side CTA fallback

**Files:**
- Create: `backend/config/blog.php`, `backend/app/Support/BlogGuide.php`
- Modify: `backend/app/Http/Controllers/Api/V1/Admin/BlogPostsController.php` (add `guide()`), `backend/routes/api.php` (admin blog group), `backend/app/Http/Resources/PublicBlogPostResource.php` (cta fallback)
- Test: `backend/tests/Feature/Blog/AdminBlogPostsTest.php`, `backend/tests/Feature/Blog/PublicBlogTest.php`

**Interfaces — Produces:** `BlogGuide::base(): array{voice: list<string>, structure: list<string>, cta_defaults: array{heading,body,label,url}, formats: list<string>}`, `BlogGuide::forConnector(): array` (base + `categories`, `tags`, `modes`, `section_shape`, `image_rules`). `GET /api/v1/admin/blog/guide` → `{data: BlogGuide::base()}`.

- [ ] Step 1: Tests — admin guide returns config payload for a cockpit token and 403 for a `workspace`/connector token; public show of a post with null `cta_*` serves `config('blog.cta_defaults')`, a post with its own `cta_heading` keeps it.
- [ ] Step 2: Run `php artisan test --filter='AdminBlogPostsTest|PublicBlogTest'` → new tests FAIL.
- [ ] Step 3: Implement.

```php
// config/blog.php
return [
    'voice' => [
        'Thoughtful, direct and human.',
        'Written for Malaysian business owners and founders, in clear English.',
        'Focus on UI/UX, digital experiences and custom systems that make things feel simpler for people.',
        'No agency buzzwords, exaggerated claims or aggressive sales language.',
    ],
    'structure' => [
        'Opening hook — the introduction',
        'The problem or question',
        'Why it matters',
        'Two to four practical sections',
        'Key takeaway',
        'Gentle invitation to get in touch — the closing CTA',
    ],
    'cta_defaults' => [
        'heading' => 'Have something like this in mind?',
        'body' => 'I’m always open to a conversation about what that could look like for your business.',
        'label' => 'Get in touch',
        'url' => '/contact',
    ],
];
```

`PublicBlogPostResource`: `'cta_heading' => $this->cta_heading ?: config('blog.cta_defaults.heading')` (same for body/label/url).

- [ ] Step 4: Re-run → PASS.

### Task 2: Extract `BlogPostInput`; admin controller uses it

**Files:**
- Create: `backend/app/Services/Blog/BlogPostInput.php`
- Modify: `backend/app/Http/Controllers/Api/V1/Admin/BlogPostsController.php`
- Test: `backend/tests/Unit/Blog/BlogPostInputTest.php` (derive semantics) + existing `tests/Feature/Blog/*` unchanged

**Interfaces — Produces:**
- `BlogPostInput::URL_RULE` (string)
- `BlogPostInput::rules(bool $partial = false, bool $requireAlt = false): array`
- `BlogPostInput::sectionRules(bool $requireAlt = false): array`
- `BlogPostInput::derive(array $data, ?BlogPost $existing = null, bool $partial = false): array`

Semantics of `derive`:
- Full (`$partial=false`, admin): exactly today's behaviour — slug from `slug ?: title`, `format ?? 'article'`, trimmed excerpt, normalised sections, cleaned tags, reading time.
- Partial: only keys present in `$data` are returned/transformed; slug recomputed only if `slug` key present (blank → from merged title); reading time recomputed if `excerpt` or `sections` present, from merged values.

- [ ] Step 1: Unit tests for partial derive (title-only keeps no `slug` key; `sections` present → reading_minutes recomputed from merged excerpt; `slug` present → unique slug ignoring own id).
- [ ] Step 2: Run → FAIL (class missing).
- [ ] Step 3: Implement; replace `validated()` / `sectionRules()` / `URL_RULE` in the admin controller with `BlogPostInput`.
- [ ] Step 4: Run `php artisan test --filter=Blog` → all PASS (existing admin tests unchanged).

### Task 3: Connector blog controller + routes

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/Connector/BlogPostController.php`
- Modify: `backend/routes/api.php` (connector group)
- Test: `backend/tests/Feature/Connector/ConnectorBlogTest.php`

**Interfaces — Consumes:** `BlogPostInput::*`, `BlogGuide::forConnector()`, `AdminBlogPostResource`. **Produces (HTTP):**
- `GET /api/v1/connector/blog/guide` → `BlogGuide::forConnector()` (unwrapped)
- `GET /api/v1/connector/blog/posts?status=&q=&page=&per_page=` → `{data: [row], meta: {current_page, per_page, last_page, total}}`; row = `id, title, slug, status, format, category, excerpt(≤160), updated_at, published_at, admin_url, public_url|null`
- `GET /api/v1/connector/blog/posts/{id}` → `{data: AdminBlogPostResource + admin_url + public_url}`
- `POST /api/v1/connector/blog/posts` → 201 `{data: …}`
- `PUT /api/v1/connector/blog/posts/{id}` → 200 `{data: …}`; published → 422 `errors.status`

- [ ] Step 1: Write `ConnectorBlogTest` covering: auth matrix (none → 401, cockpit → 403, read-only → 403 on writes, read-only OK on reads); forced draft; alt required (cover + section) on create; merged-alt on update (Review Focus 1); published → 422 unchanged; partial update leaves other fields; title-only keeps slug (RF 2); `title: ""` → 422 (RF 3); section id preservation (RF 4); slug de-dup; soft-deleted → 404 + absent from list (RF 5); per_page cap 25; status/q filters; `public_url` only when published; activity log `via`; guide payload (categories/tags exclude soft-deleted).
- [ ] Step 2: Run `php artisan test --filter=ConnectorBlogTest` → FAIL.
- [ ] Step 3: Implement controller + routes (`whereNumber('id')`).
- [ ] Step 4: Run → PASS; run `--filter='Connector|Blog'` → PASS.

### Task 4: Frontend — guide from the backend

**Files:**
- Modify: `frontend/app/data/blog.ts` (remove `blogVoiceGuide`, `blogCtaDefaults`; add `BlogGuide` type), `frontend/app/pages/admin/blog/[id].vue` (fetch guide; panel, placeholders, template CTA fill, preview CTA fallback), `frontend/app/components/public/BlogArticle.vue` (no local fallback; hide the CTA card if it has no heading)

- [ ] Step 1: Implement.
- [ ] Step 2: `docker compose -f docker-compose.dev.yml exec frontend npm run lint` and the type check → clean. `grep -rn "blogCtaDefaults\|blogVoiceGuide" frontend/app` → no hits.

### Task 5: Worker tools

**Files:**
- Modify: `connector/src/index.ts` (version 4.0.0, header comment, five tools), `connector/src/auth.ts` (login copy)

- [ ] Step 1: Implement `get_blog_guide`, `list_blog_posts`, `get_blog_post`, `create_blog_draft`, `update_blog_draft` with zod limits from the spec and the two-mode wording rule in the descriptions.
- [ ] Step 2: `cd connector && npm run typecheck` and `npx wrangler deploy --dry-run --outdir=dist` → clean (dry-run deploys nothing).

### Task 6: Docs + final verification

**Files:**
- Modify: `docs/global/MCP-CONNECTOR.md`, `docs/global/BLOG.md`, `docs/global/ARCHITECTURE.md`

- [ ] Step 1: Update docs per spec §Docs.
- [ ] Step 2: Pint on changed PHP files; full `php artisan test`; frontend lint + typecheck; Worker typecheck. Report results verbatim.
