# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Portfolio + client platform for Ahmad Baihaqie / Axel Nova Ventures. Production: https://axelnovaventures.com.

## Documentation standard — CENTRALISED IN `docs/`

**All `.md` files in this repo live under [docs/](../docs/), categorised by scope. Filenames are ALL-CAPS-WITH-HYPHENS (`.md` extension stays lowercase).**

- `docs/global/` — repo-wide concerns (architecture, deploy, top-level features)
- `docs/frontend/` — anything Nuxt/UI specific (standards, component groups)
- `docs/backend/` — anything Laravel/API specific

Rules:
1. Never create a new `.md` file outside `docs/`. The only exception is the root `README.md`, kept at repo root because GitHub renders it as the landing page (its canonical copy lives at [docs/global/README.md](../docs/global/README.md) — keep them in sync, or make root a thin pointer).
2. New doc → pick the right subfolder (`global` / `frontend` / `backend`) → name in ALL-CAPS-WITH-HYPHENS (e.g. `QUEUE-WORKERS.md`, not `queue_workers.md`).
3. Component-level inline READMEs are also centralised — disambiguate the name (e.g. `PORTAL-COMPONENTS.md`, not generic `README.md` in subfolders).
4. When moving or renaming a doc, grep the repo for the old path and fix every reference.

## Authoritative docs — read these before assuming

This repo is documentation-rich. Don't duplicate what's already written:

- [docs/global/README.md](../docs/global/README.md) — repo layout, full local-dev orchestration, common gotchas
- [docs/global/ARCHITECTURE.md](../docs/global/ARCHITECTURE.md) — DB tables, API routes, frontend routes, queued job flow, key services
- [docs/global/DEPLOY.md](../docs/global/DEPLOY.md) — VPS topology, CI deploy flow, rollback, ops cheatsheet
- [docs/global/CI-AND-TESTING.md](../docs/global/CI-AND-TESTING.md) — PR quality gates (Pint/PHPUnit/ESLint/vue-tsc/audits), how to run the test suites, MySQL-only test DB, audit-ignore policy
- [docs/global/QUOTE_BUILDER.md](../docs/global/QUOTE_BUILDER.md) — pricing formula, calculation order, how to update prices/packages/add-ons
- [docs/global/PAYMENTS-LEDGER.md](../docs/global/PAYMENTS-LEDGER.md) — payments ledger model, `PaymentObserver` (sole writer of paid caches), `AXNI`/`AXNR`/`AXNP` numbering, build phases
- [docs/global/FEEDBACK-MODULE.md](../docs/global/FEEDBACK-MODULE.md) — Feedback & Reviews: `feedback` table + `AXNF` codes, token-gated `/feedback/{token}` client form (one submit per token), `pending → approved → published` moderation (**publishing requires `publish_consent` — nothing auto-publishes**), cached `public_testimonials_v1` wall feed (`FeedbackObserver` invalidates), admin request/log create modes (`RequestFeedbackJob` email needs the queue worker)
- [docs/global/COLLABORATIONS.md](../docs/global/COLLABORATIONS.md) — studio partners on `/collaborations` (**not** the `/partners` referral programme): `collaboration_partners` + `collaboration_partner_project` (mode `co_build`/`subcontract`/`referral`; a shared project is public only when partner active + `credit_public` + project active — `linkProject()` defaults credit ON only for co-builds), TRLabs seeded by data migration; profile, Live/Hidden and project links are edited in the admin (Partners › Collaborations — slug locked; a NEW partner is still a migration), and the /collaborations leads (`partner` / `collaboration` origins) live there, not in Sales › Inquiries, hand-built co-branded `/collaborations/trlabs` (`.collab-trlabs` token scope), inquiry origins `partner` / `collaboration`; never create `frontend/public/collaborations/` (static dir 301s the route)
- [docs/global/QUOTATION-REVISIONS.md](../docs/global/QUOTATION-REVISIONS.md) — quotation **families + numbered revisions** (R1, R2 …): Revise (change reason, one open revision at a time), supersede on accept (same order re-points; unpaid invoices voided, paid kept, new schedule netted with an adjustment invoice, overpaid flag), phased lump sums (40/40/20, 50/50 …), Care Plan periods, `AXNQ-… · R2` on the PDF; revisions are opened by the founder only — the connector edits the draft revision and reads orders (`get_order`)
- [docs/global/EMAIL-SYSTEM.md](../docs/global/EMAIL-SYSTEM.md) — every email the dashboard sends (catalog of 31 types: trigger, audience, related record, phase), the owner's design kit (`docs/global/email-kit/`) as Blade layouts + block components (`<x-email::…>`, config `config/email_design.php`), Resend-over-SMTP with Hostinger as automatic backup (`MAIL_MAILER=failover`), the `email_logs` table (forward-only status advanced by the Svix-signed `POST /v1/webhooks/resend`), owner alerts on bounce/spam/failure, the `AdminEmailHistory` panel (`GET /v1/admin/emails`), the single `scheduler` container, `mail:preview` / `mail:test`, and how to add a new email. **Never name template data `message`** (Laravel injects its own); **no email bodies are stored**
- [docs/global/MCP-CONNECTOR.md](../docs/global/MCP-CONNECTOR.md) — the scoped `/v1/connector/*` API + Cloudflare Worker MCP server (`connector/`) that lets Claude drive the quotation pipeline, draft blog posts, and draft the public catalogue (projects, services, packages). **Access model (v6): read everything, write gated, delete/publish portal-only** — reads (`list_quotations`/`get_quotation`, `list_blog_posts`/`get_blog_post`, `list_projects`/`get_project`, `list_services`/`get_service`/`get_package`) reach ANY non-deleted row, and `get_order` (v5.1) is a read-only money view of an order (tally / netted plan / invoices) — the connector never writes invoices or payments; `update_draft_quotation` edits only the quotation's DRAFT revision — a sent / accepted quote first needs the FOUNDER to open a revision (Revise in the admin); the connector never opens revisions, sends or accepts; blog posts are created as drafts and `update_blog_draft` (partial) is refused once published; projects / services / packages are created **Hidden** and edited (partial) only while Hidden (a package: while it **or its service** is Hidden — `ServicePackage::isEffectivelyLive()` / `effective_visibility` drives the guard, `public_url` and `quotable`) — never made Live, featured or reordered by the connector, and a package's `quote_package_key` must be NEW (unique across every package row, Live or Hidden — the engine resolves keys on hidden rows too); delete is portal-only (`DELETE /v1/admin/quotations/{id}`, soft, 409 when an order is attached), and publishing a post is portal-only — never connector tools. Sanctum abilities `connector:read`/`connector:draft` are **universal across modules** (no per-module ability — a new module adds routes, not abilities, so no token rotation), token minting, Worker deploy
- [docs/global/SEO-AND-DISCOVERY.md](../docs/global/SEO-AND-DISCOVERY.md) — search / AI-answer / Discover discovery layer: per-page meta via `usePublicSeo`, the JSON-LD entity graph (`/#business`, `/#website`, `/about#person` — never change an `@id`), sitemap sources, the site-wide `robots` meta (`max-image-preview:large`; page-level `noindex` still wins — unhead dedupes `robots` to one tag), the RSS feed `/feed.xml` (`server/routes/feed.xml.ts` ← `GET /v1/blog/feed`, latest 20 published, full text + Media RSS covers; a backend failure returns a VALID empty channel), `/llms.txt` (static facts in `server/utils/feed.ts` mirror the homepage JSON-LD — change together), the `robots.txt` AI-crawler groups (deliberately allowed; drop `Google-Extended` / `CCBot` to opt out of training only), Discover / Samsung News authoring rules and the owner's off-site checklist. Pure builders in `server/utils/feed.ts` are vitest-covered — extend the tests with the builder
- [docs/frontend/UI-STANDARDS.md](../docs/frontend/UI-STANDARDS.md) — design tokens, color, typography, motion (single source of truth for UI decisions)
- [docs/frontend/PORTAL-COMPONENTS.md](../docs/frontend/PORTAL-COMPONENTS.md), [ADMIN-COMPONENTS.md](../docs/frontend/ADMIN-COMPONENTS.md), [PUBLIC-COMPONENTS.md](../docs/frontend/PUBLIC-COMPONENTS.md) — component group READMEs
- [docs/backend/README.md](../docs/backend/README.md) — backend-specific notes

## Big picture

Monorepo, two apps deployed independently:

- `backend/` — **Laravel 11** API (PHP 8.4). Public quote builder + Sanctum-protected `/admin/leads` portal. Database queue, Mailtrap (dev) / SMTP (prod). Port `8003`. Docs: [docs/backend/](../docs/backend/).
- `frontend/` — **Nuxt 4** SSR (Vue 3 + TypeScript + @nuxt/ui v4 / Tailwind v4). Portfolio + `/quote` builder + `/admin` SPA. Port `3003`. Docs: [docs/frontend/](../docs/frontend/).
- **MySQL 8** is shared via the sibling [axelnova-infra](../../axelnova-infra/) repo — both apps join its external `axelnova-shared` Docker network and reach MySQL via hostname `mysql`.

The pricing engine has two implementations that **must stay in sync**:
- [backend/app/Services/Quoting/PricingEngine.php](../backend/app/Services/Quoting/PricingEngine.php) — server-side, source of truth, drives final stored quote
- [frontend/app/utils/pricing.ts](../frontend/app/utils/pricing.ts) — pure TS port (`calculateEstimate`) for live client-side estimates: the admin quote builder (via `usePricingEngine`) and the public `/services` estimator (`utils/serviceEstimator.ts`)

Both read the same JSON config from `pricing_configs` (active row) via `GET /api/v1/quote-builder/config` (cached 1h). Pricing changes are **data, not code** — see [docs/global/QUOTE_BUILDER.md](../docs/global/QUOTE_BUILDER.md).

## Common commands

Local dev runs entirely in Docker. The shared infra stack must be up first (`cd ../axelnova-infra && docker compose up -d`).

```bash
# Boot both apps (from repo root)
docker compose -f docker-compose.dev.yml up -d --build

# Logs
docker compose -f docker-compose.dev.yml logs -f backend frontend

# Artisan / composer / npm — must run inside containers (DB_HOST=mysql only resolves on docker net)
docker compose -f docker-compose.dev.yml exec backend  php artisan migrate
docker compose -f docker-compose.dev.yml exec backend  php artisan tinker
docker compose -f docker-compose.dev.yml exec backend  php artisan queue:work    # required when testing quote submissions
docker compose -f docker-compose.dev.yml exec frontend npm install <pkg>

# Stop
docker compose -f docker-compose.dev.yml down            # NEVER `down -v` — see the database-protection rule below
```

Endpoints: backend `http://127.0.0.1:8003`, frontend `http://127.0.0.1:3003`.

Production deploys via GitHub Actions on merge to `main` — see [docs/global/DEPLOY.md](../docs/global/DEPLOY.md). Don't SSH for routine deploys.

## Non-obvious rules

These are easy to get wrong; they exist for reasons:

**DATABASE PROTECTION — ABSOLUTE RULE.** Claude (and every subagent it dispatches) must NEVER run destructive database commands against the dev or prod database: `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`, `docker compose down -v`/`--volumes`, `docker volume rm/prune`, `DROP DATABASE/SCHEMA`, `TRUNCATE TABLE`. On 2026-07-15 a subagent ran `migrate:fresh --seed` on the dev DB while debugging and destroyed all local data. If a schema reset ever seems necessary, STOP and tell the owner — they run it themselves. PHPUnit is safe as-is: `phpunit.xml` forces `axelnova_dashboard_test` (`force="true"`), so `php artisan test` never touches the dev DB — run tests, never "fix" a migration problem by resetting a database. Additive `php artisan migrate` is allowed. Enforced by deny rules + a PreToolUse hook in [.claude/settings.json](settings.json) — do not weaken or remove them. When dispatching subagents that touch the backend, repeat this prohibition in their instructions.

**FOUC rule (frontend).** Never bind layout backgrounds to `colorMode.value` in templates — `colorMode` resolves to a default during SSR and flips after hydration, causing a gray flash on refresh. Use CSS variables (`--nav-bg-top`, `--nav-bg-scrolled`, etc.) and let `:root` / `.dark` rules handle the swap. `@nuxt/ui` injects the `.dark` class before paint.

**Scroll-reveal rule (frontend).** `useScrollReveal('.reveal')` is the only API for fade-in-on-scroll. Do not add static `opacity-0` Tailwind classes to elements that depend on JS to reveal — if GSAP fails, content must still be visible. Set initial hidden state via `gsap.set()` inside `onMounted`, not Tailwind.

**Design tokens (frontend).** All theming via CSS variables in [frontend/app/assets/css/main.css](../frontend/app/assets/css/main.css). Never hardcode hex in components. New patterns extend `main.css` AND update [docs/frontend/UI-STANDARDS.md](../docs/frontend/UI-STANDARDS.md) together. Both light and dark mode are first-class — verify in both.

**Public pages are swr-cached — a visible change must purge.** In production Nitro caches every public marketing page **and its `_payload.json`** (`swr` route rules in `nuxt.config.ts`), and an expired entry is still served once more with no age limit — so freshness relies on `App\Support\SiteCache::purge()` → `POST /_cache/purge`, not the 300s window. Any model a public page renders needs its observer to purge when a row that is or was public changes (today: blog posts, service categories / packages that are or were Live, add-ons, scope fields, pricing configs). Every public page's footer lists the Live services. See [DEPLOY.md § Page caching](../docs/global/DEPLOY.md#page-caching).

**Queue worker is required for every email.** The admin's quotation Send dispatches `SendClientQuoteEmail`; inquiries, invoices, feedback and team / partner mail are queued too. If `queue:work` isn't running, they silently queue and emails never send. (The public priced quote funnel, `POST /v1/quote-requests`, and its instant-estimate + new-lead emails were **retired 2026-10-12** — `/quote` is the inquiry form and the admin builds every priced quote.)

**Sanctum is route-scoped.** Stateful CSRF middleware only applies to `/v1/admin/*` ([backend/routes/api.php](../backend/routes/api.php)). Public POSTs (the quote form) are pure stateless. Don't set `SANCTUM_STATEFUL_DOMAINS` globally.

**Public-form throttles are env-aware.** In production: referrals 8/hour/IP, inquiries 20/hour/IP (lighter intake); 1000/min otherwise. Note Laravel's simple `throttle:N,M` keys on domain+IP, not path — routes in one throttle group share a per-IP bucket, which is why inquiries sit in their own group. Test freely in dev.

**Reference codes are atomic.** The AXN document family uses the production format `AXN{TYPE}-{YYYY}-{NNNN}` (type letter fused into the prefix) — `AXNQ-` (quotation), `AXNO-` (order), `AXNI-` (invoice), `AXNR-` (receipt), `AXNP-` (payment), e.g. `AXNQ-2026-0012`. Codes are minted via DB transaction with `lockForUpdate()` in [backend/app/Support/ReferenceCodeGenerator.php](../backend/app/Support/ReferenceCodeGenerator.php), passing a [`DocumentType`](../backend/app/Support/DocumentType.php). Each type has its own counter that resets each year. Don't reimplement counter logic elsewhere; add new document kinds by extending the enum, not by minting strings at call sites. Invoices/receipts/payments mint the same way — [DocumentIssuer](../backend/app/Services/Quoting/DocumentIssuer.php) calls `ReferenceCodeGenerator::generate(DocumentType::Invoice|Receipt|Payment)`, it does not derive numbers from the parent quotation. (Already-issued `INV-`/`RCP-` numbers stay frozen.)

**Payments are a ledger, not a column.** `payments` is the single source of truth for money — one signed row per movement; refunds are **negative** rows with a `parent_payment_id`, never status flips. `orders.amount_paid_myr` and `invoices.amount_paid`/`status` are derived caches written **only** by [`PaymentObserver`](../backend/app/Observers/PaymentObserver.php) (recomputed from succeeded rows). Never edit a paid amount directly — that was the old drift bug. Receipts anchor to their `payment_id` (1:1), not the invoice. See [docs/global/PAYMENTS-LEDGER.md](../docs/global/PAYMENTS-LEDGER.md).

**Quotations are families of revisions.** A quotation's content lives in numbered revisions (`quotation_revisions`); the `quotations` row is a MIRROR of the current revision. Never write a quotation's content or status on the row — edit the draft revision via [`QuotationRevisions`](../backend/app/Services/Quoting/QuotationRevisions.php) (`writeDraft` / `revise` / `accept` / `reject`), which re-mirrors the row. Accepted and superseded revisions are frozen; changing accepted terms means a new revision, never re-opening one. Render any revision through `QuotationRevision::asQuotation()`. See [QUOTATION-REVISIONS.md](../docs/global/QUOTATION-REVISIONS.md).

**Quotations: one write shape, two writers, one renderer.** A quotation `form_payload` is written by two paths — the admin builder and the MCP connector (the public funnel was retired 2026-10-12; its `quote_funnel` rows remain) — which ALL emit the same **canonical multi-package shape** (`packages[]` with resolved `service_package_id`, top-level `rush`, grouped `breakdown`, `source_meta.created_via`). Multi-package lives in `form_payload.packages[]` — no new table; the scalar `package_key`/`service_package_id` columns hold the FIRST package. Never read `form_payload` fields raw — go through [`FormPayloadNormalizer`](../backend/app/Services/Quoting/FormPayloadNormalizer.php) (`Quotation::normalizedForm()`) or its TS port `normalizePackages()`, which map ANY legacy shape (old funnel flat, `scope_values`, connector) into `packages[]`. Existing rows are **not** migrated — they normalize on read. Multi-package pricing is `PricingEngine::calculateMulti()` (sum min/max, longest ETA). Line items are seeded by the single shared [`DocumentSeeder`](../backend/app/Services/Quoting/DocumentSeeder.php) (admin "Seed" button + connector both call it) into `document.items` — the shape the PDF renders. See [docs/global/QUOTE_BUILDER.md](../docs/global/QUOTE_BUILDER.md).

## Conventions

- Inline `:style` is fine for one-off CSS-var lookups; reusable styles go in `main.css`.
- Icons: `<UIcon name="i-lucide-…">` from Iconify. Never emojis.
- Prefer `tracking-tighter` / `tracking-tight` over arbitrary `tracking-[…]`.
- Pages call `useScrollReveal('.reveal')` once at script-setup; sections add `class="reveal"`.
- Hero animations are page-specific — set initial state via `gsap.set()` in `onMounted`.
- Branch protection on `main` — every change goes through a PR. CI runs build-check on PRs and deploys on merge.
