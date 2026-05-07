# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Portfolio + client platform for Ahmad Baihaqie / Axel Nova Ventures. Production: https://axelnova.tech.

## Authoritative docs — read these before assuming

This repo is documentation-rich. Don't duplicate what's already written:

- [README.md](../README.md) — repo layout, full local-dev orchestration, common gotchas
- [ARCHITECTURE.md](../ARCHITECTURE.md) — DB tables, API routes, frontend routes, queued job flow, key services
- [DEPLOY.md](../DEPLOY.md) — VPS topology, CI deploy flow, rollback, ops cheatsheet
- [QUOTE_BUILDER.md](../QUOTE_BUILDER.md) — pricing formula, calculation order, how to update prices/packages/add-ons
- [frontend/UI-Standards.md](../frontend/UI-Standards.md) — design tokens, color, typography, motion (single source of truth for UI decisions)

## Big picture

Monorepo, two apps deployed independently:

- `backend/` — **Laravel 11** API (PHP 8.4). Public quote builder + Sanctum-protected `/admin/leads` portal. Database queue, Mailtrap (dev) / SMTP (prod). Port `8003`.
- `frontend/` — **Nuxt 4** SSR (Vue 3 + TypeScript + @nuxt/ui v4 / Tailwind v4). Portfolio + `/quote` builder + `/admin` SPA. Port `3003`.
- **MySQL 8** is shared via the sibling [axelnova-infra](../../axelnova-infra/) repo — both apps join its external `axelnova-shared` Docker network and reach MySQL via hostname `mysql`.

The pricing engine has two implementations that **must stay in sync**:
- [backend/app/Services/Quoting/PricingEngine.php](../backend/app/Services/Quoting/PricingEngine.php) — server-side, source of truth, drives final stored quote
- [frontend/app/composables/usePricingEngine.ts](../frontend/app/composables/usePricingEngine.ts) — TS port for live client-side estimates on `/quote`

Both read the same JSON config from `pricing_configs` (active row) via `GET /api/v1/quote-builder/config` (cached 1h). Pricing changes are **data, not code** — see [QUOTE_BUILDER.md](../QUOTE_BUILDER.md).

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
docker compose -f docker-compose.dev.yml down            # or `down -v` to wipe volumes
```

Endpoints: backend `http://127.0.0.1:8003`, frontend `http://127.0.0.1:3003`.

Production deploys via GitHub Actions on merge to `main` — see [DEPLOY.md](../DEPLOY.md). Don't SSH for routine deploys.

## Non-obvious rules

These are easy to get wrong; they exist for reasons:

**FOUC rule (frontend).** Never bind layout backgrounds to `colorMode.value` in templates — `colorMode` resolves to a default during SSR and flips after hydration, causing a gray flash on refresh. Use CSS variables (`--nav-bg-top`, `--nav-bg-scrolled`, etc.) and let `:root` / `.dark` rules handle the swap. `@nuxt/ui` injects the `.dark` class before paint.

**Scroll-reveal rule (frontend).** `useScrollReveal('.reveal')` is the only API for fade-in-on-scroll. Do not add static `opacity-0` Tailwind classes to elements that depend on JS to reveal — if GSAP fails, content must still be visible. Set initial hidden state via `gsap.set()` inside `onMounted`, not Tailwind.

**Design tokens (frontend).** All theming via CSS variables in [frontend/app/assets/css/main.css](../frontend/app/assets/css/main.css). Never hardcode hex in components. New patterns extend `main.css` AND update [UI-Standards.md](../frontend/UI-Standards.md) together. Both light and dark mode are first-class — verify in both.

**Queue worker is required for quote submissions.** `POST /v1/quote-requests` dispatches `SendClientQuoteEmail` and `NotifyAdminJob`. If `queue:work` isn't running, submissions silently queue and emails never send. The customer estimate is rendered **inline** in the email — no PDF, no attachments.

**Sanctum is route-scoped.** Stateful CSRF middleware only applies to `/v1/admin/*` ([backend/routes/api.php](../backend/routes/api.php)). Public POSTs (the quote form) are pure stateless. Don't set `SANCTUM_STATEFUL_DOMAINS` globally.

**Quote throttle is env-aware.** 3/hour/IP in production, 1000/min otherwise. Test freely in dev.

**Reference codes are atomic.** `AXN-YYYY-NNNN` codes are generated via DB transaction with `lockForUpdate()` in [backend/app/Support/ReferenceCodeGenerator.php](../backend/app/Support/ReferenceCodeGenerator.php). Counter resets each year. Don't reimplement counter logic elsewhere.

## Conventions

- Inline `:style` is fine for one-off CSS-var lookups; reusable styles go in `main.css`.
- Icons: `<UIcon name="i-lucide-…">` from Iconify. Never emojis.
- Prefer `tracking-tighter` / `tracking-tight` over arbitrary `tracking-[…]`.
- Pages call `useScrollReveal('.reveal')` once at script-setup; sections add `class="reveal"`.
- Hero animations are page-specific — set initial state via `gsap.set()` in `onMounted`.
- Branch protection on `main` — every change goes through a PR. CI runs build-check on PRs and deploys on merge.
