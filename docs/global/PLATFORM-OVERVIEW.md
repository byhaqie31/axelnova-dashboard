# Axel Nova Dashboard — Full Platform Overview

A complete reference for how `axelnova-dashboard` is set up: the stack, how to run it, what the public storefront contains, and every option/setting in the admin panel.

> Portfolio + client platform for Ahmad Baihaqie / Axel Nova Ventures.
> Production: **https://axelnovaventures.com** · Admin: **/admin** on the same host.

---

## Table of contents

1. [Big picture](#1-big-picture)
2. [Tech stack](#2-tech-stack)
3. [Repository layout](#3-repository-layout)
4. [Local development setup](#4-local-development-setup)
5. [Environment variables](#5-environment-variables)
6. [Production & deployment](#6-production--deployment)
7. [The frontend (Nuxt 4)](#7-the-frontend-nuxt-4)
8. [The storefront — public pages](#8-the-storefront--public-pages)
9. [The quote builder funnel](#9-the-quote-builder-funnel)
10. [The admin panel — options & settings](#10-the-admin-panel--options--settings)
11. [The backend (Laravel 11)](#11-the-backend-laravel-11)
12. [Database schema](#12-database-schema)
13. [API reference](#13-api-reference)
14. [The pricing engine](#14-the-pricing-engine)
15. [Background jobs & email](#15-background-jobs--email)
16. [Key conventions & gotchas](#16-key-conventions--gotchas)

---

## 1. Big picture

A **monorepo with two apps deployed independently**, sharing one MySQL database:

- **`frontend/`** — Nuxt 4 SSR app. Serves three surfaces from one codebase:
  - the **public storefront** (portfolio + services + marketing) at `axelnovaventures.com`
  - the **public quote builder** funnel at `/quote`
  - the **admin SPA** at `/admin` (Sanctum token auth)
  - a token-gated **client portal** stub at `/portal/[token]`
- **`backend/`** — Laravel 11 API (PHP 8.4). Public endpoints feed the storefront/quote builder; a Sanctum-protected `/v1/admin/*` group powers the admin panel (quotations, orders, services CMS, projects CMS).
- **MySQL 8** — shared from the sibling [`axelnova-infra`](../../../axelnova-infra/) repo. Both apps join its external `axelnova-shared` Docker network and reach the DB via hostname `mysql`.

The core business flow:

```
Visitor builds a quote on /quote
   → POST /api/v1/quote-requests
   → backend prices it (PricingEngine), generates AXN-YYYY-NNNN code,
     creates a Client + Quotation, queues two emails
   → customer gets an inline HTML estimate; admin gets a lead notification
   → admin reviews in /admin/quotations, updates status, and "Accepts"
   → an Order (ORD-YYYY-NNNN) is created and tracked in /admin/orders
```

---

## 2. Tech stack

| Layer | Technology |
|-------|-----------|
| Frontend | Nuxt 4, Vue 3, TypeScript, `@nuxt/ui` v4, Tailwind CSS v4 |
| Motion | GSAP (+ SplitText), Lenis smooth-scroll, VueUse |
| Fonts | Inter (self-hosted via `@nuxtjs/google-fonts`, `download: true`) |
| Backend | Laravel 11, PHP 8.4 |
| Database | MySQL 8 (shared via `axelnova-infra`) |
| Queue | Laravel **database** queue |
| Cache/session | Database driver |
| Email | SMTP — Mailtrap in dev, real SMTP in prod |
| Auth | Laravel Sanctum (token-based, admin only) + a `role:admin` middleware |
| Forms (storefront) | Web3Forms (contact + referral forms — no backend needed) |
| Infra/deploy | Docker, Docker Compose, GitHub Actions, GHCR images, nginx + Let's Encrypt on a Hostinger VPS |

---

## 3. Repository layout

```
axelnova-dashboard/
  backend/                      Laravel 11 API
    Dockerfile                  Production image (nginx + php-fpm via supervisord)
    Dockerfile.dev              Dev image (php artisan serve)
    app/                        Controllers, Models, Jobs, Mail, Services, Observers, Support
    database/migrations|seeders
    routes/api.php              All API routes
    .env.example                Dev env template
    .env.production.example     Prod env template (VPS-only)
  frontend/                     Nuxt 4 app
    Dockerfile                  Production image
    Dockerfile.dev              Dev image (Nuxt dev server + Vite HMR)
    app/                        pages, components, composables, layouts, middleware, data, assets
    nuxt.config.ts
  docker-compose.dev.yml        Local orchestrator (backend + worker + frontend)
  docker-compose.prod.yml       Prod: build images on the VPS
  docker-compose.prod.ghcr.yml  Prod: pull images from GHCR (current pipeline)
  docs/
    global/                     ARCHITECTURE, DEPLOY, QUOTE_BUILDER, README, this file
    frontend/                   UI-STANDARDS, MOTION, *-COMPONENTS
    backend/                    README
  .github/workflows/            CI build-check + deploy
```

**Documentation rule:** every `.md` lives under `docs/` (except the root `README.md`), named `ALL-CAPS-WITH-HYPHENS.md`, split into `global/` · `frontend/` · `backend/`.

---

## 4. Local development setup

Everything runs in Docker. The shared infra stack **must be up first** — it provides MySQL + phpMyAdmin and the `axelnova-shared` network.

```bash
# 1. Boot shared infra (one-time per machine; sets MySQL + the shared network)
cd ../axelnova-infra
cp .env.example .env            # set MYSQL_ROOT_PASSWORD before first boot
docker compose up -d

# 2. Boot both apps from the dashboard repo root
cd ../axelnova-dashboard
docker compose -f docker-compose.dev.yml up -d --build
```

This starts three containers, all on `axelnova-shared`:

| Container | Image | Bound to (host) | Purpose |
|---|---|---|---|
| `axelnova-backend-dev` | `axelnova-backend:dev` | `127.0.0.1:8003` | Laravel API (`php artisan serve`) |
| `axelnova-worker-dev` | `axelnova-backend:dev` | — | Queue worker (`queue:work --tries=3 --backoff=10`) |
| `axelnova-frontend-dev` | `axelnova-frontend:dev` | `127.0.0.1:3003`, `:24678` | Nuxt dev server + Vite HMR |

**Endpoints:** frontend `http://127.0.0.1:3003`, backend `http://127.0.0.1:8003`.

```bash
# Verify
docker compose -f docker-compose.dev.yml ps
curl http://127.0.0.1:8003/api/v1/quote-builder/config | head -c 200

# Logs
docker compose -f docker-compose.dev.yml logs -f backend frontend

# First-time DB setup (run INSIDE the container — DB_HOST=mysql only resolves on the docker net)
docker compose -f docker-compose.dev.yml exec backend php artisan migrate --seed

# Other artisan / composer / npm — always inside the container
docker compose -f docker-compose.dev.yml exec backend  php artisan tinker
docker compose -f docker-compose.dev.yml exec frontend npm install <pkg>

# Stop (add -v to also wipe the frontend node_modules / nuxt cache volumes)
docker compose -f docker-compose.dev.yml down
```

> **Why a separate worker container?** Quote submissions dispatch queued jobs (emails). The worker is split out so its logs are isolated and it can restart independently. `queue:work` is a long-lived daemon — after editing anything in `app/Jobs/` or `app/Mail/`, restart it: `docker compose -f docker-compose.dev.yml restart worker`.

**Frontend ↔ backend wiring in dev** (set in `docker-compose.dev.yml`):
- `NUXT_API_BASE=http://backend:8003` — SSR fetches inside the container use the docker-network hostname.
- `NUXT_PUBLIC_API_BASE=http://localhost:8003` — browser fetches use the host loopback.

---

## 5. Environment variables

### Backend (`backend/.env`)

| Variable | Purpose / value |
|---|---|
| `APP_NAME` | "Axel Nova Platform" |
| `APP_URL` | `http://localhost:8003` (dev) |
| `APP_TIMEZONE` | `Asia/Kuala_Lumpur` |
| `DB_HOST` | **`mysql`** (docker-network hostname, not `127.0.0.1`) |
| `DB_DATABASE` / `DB_USERNAME` | `axelnova_dashboard_db` / `axelnova_dashboard_user` |
| `CACHE_STORE` / `QUEUE_CONNECTION` / `SESSION_DRIVER` | `database` (all three) |
| `MAIL_*` | SMTP — Mailtrap sandbox in dev, real SMTP in prod |
| `MAIL_FROM_ADDRESS` / `_NAME` | `baihaqie@axelnova.tech` / "Axel Nova Ventures" |
| `ADMIN_NOTIFICATION_EMAIL` | where new-lead notifications go |
| `ADMIN_NAME` | "Ahmad Baihaqie" |
| `ADMIN_CALENDLY_URL` | CTA link in the customer quote email |
| `ADMIN_LOGIN_EMAIL` / `ADMIN_LOGIN_PASSWORD` | seeds the single admin user |
| `FRONTEND_URL` | `http://localhost:3003` |

### Frontend (runtime config / `frontend/.env.production`)

| Variable | Purpose |
|---|---|
| `NUXT_API_BASE` | Private (SSR) backend base URL |
| `NUXT_PUBLIC_API_BASE` | Public (browser) backend base URL |

---

## 6. Production & deployment

Production lives on a **Hostinger VPS**, fronted by **system nginx** which terminates TLS (Let's Encrypt) and reverse-proxies:

- `/api/*` → `127.0.0.1:8003` (backend container)
- everything else → `127.0.0.1:3003` (frontend container)

| Component | Container | Notes |
|---|---|---|
| Frontend (Nuxt SSR) | `axelnova-frontend` | port 3000 → host 3003 |
| Backend (Laravel) | `axelnova-backend` | nginx + php-fpm via supervisord, port 8003 |
| Queue worker | `axelnova-queue` | same image as backend, runs `queue:work`; healthcheck disabled (no HTTP server) |
| MySQL | `axelnova-mysql` | shared infra, reachable as `mysql:3306` |

### Deploy flow (current = GHCR image-pull)

CI builds the two images, pushes them to **GHCR**, then the VPS **pulls** them — no building on the VPS:

```
ghcr.io/byhaqie31/axelnova-dashboard-backend:latest
ghcr.io/byhaqie31/axelnova-dashboard-frontend:latest
```

The day-to-day developer flow:

```bash
git checkout -b some-fix
# ...commit...
git push -u origin some-fix
# Open PR → CI build-check runs → merge to main → CI auto-deploys
```

On merge to `main`, the deploy workflow SSHes the VPS and runs (roughly): pull images → `up -d --no-deps backend frontend queue` → `php artisan migrate --force` → `php artisan queue:restart` → health-check `/up` → prune dangling images.

> A legacy `docker-compose.prod.yml` (build-on-VPS) still exists as a fallback; the active pipeline uses `docker-compose.prod.ghcr.yml`.

### Prod env & storage

- `.env.production` files are gitignored (VPS-only); seed them from the `.env.production.example` templates and `chmod 600`.
- First-setup values to fill: backend `APP_KEY`, `DB_PASSWORD`, `MAIL_PASSWORD`.
- `~/data/axelnova-dashboard/storage` is bind-mounted into backend + queue (Laravel logs, uploads). Persists across deploys.
- `SANCTUM_STATEFUL_DOMAINS` is **not** required — Sanctum's stateful middleware is route-scoped to admin endpoints only.

### Rollback

Pin to a prior commit SHA on the VPS and redeploy; `migrate:rollback --step=N` for bad migrations. To stay rolled back, merge a revert PR (otherwise the next merge to `main` redeploys the bad commit).

---

## 7. The frontend (Nuxt 4)

### Routing model

Pages live under `app/pages/` in three folders — `public/`, `admin/`, `portal/`. A `pages:extend` hook in `nuxt.config.ts` **strips the `/public` prefix** from URLs, so `pages/public/index.vue` serves `/`, `pages/public/about.vue` serves `/about`, etc. This lets the storefront mirror the admin/portal folder structure without polluting public URLs.

Three layouts in `app/layouts/`:
- **`public.vue`** — storefront nav + footer (sticky smart-hide header, dark-mode toggle, "Let's talk" CTA).
- **`admin.vue`** — admin shell (sidebar nav, user menu, sign-out).
- **`portal.vue`** — client portal shell.

Two middlewares in `app/middleware/`:
- **`admin-auth.ts`** — redirects unauthenticated users to `/admin/login?redirect=…`.
- **`portal-token.ts`** — gates the `/portal/[token]` route.

### Composables (`app/composables/`)

| Composable | Role |
|---|---|
| `useApi.ts` / `useApiBase.ts` | Backend fetch wrappers + runtime base URL |
| `useAdminAuth.ts` | Bearer token in `localStorage` (`axn_admin_token`), `apiFetch()`, `logout()` |
| `usePricingEngine.ts` | **TS port of the backend PricingEngine** for live `/quote` estimates |
| `useQuoteForm.ts` | Shared quote-form state across `/quote`, `/quote/preview`, `/quote/success` |
| `useScrollReveal.ts` / `useReveal.ts` | Fade-in-on-scroll (GSAP) — the only fade-in API |
| `useMotion.ts` / `useSplitTextReveal.ts` / `useCountUp.ts` / `useMagnetic.ts` / `useParallaxImage.ts` | Motion primitives |

### Theming

All theming is via **CSS variables** in `app/assets/css/main.css` (never hardcoded hex). Light and dark are both first-class; `@nuxt/ui` injects the `.dark` class before paint. Layout backgrounds bind to CSS vars (`--nav-bg-top`, etc.) — never to `colorMode.value` in templates (avoids a gray flash on refresh). Icons use `<UIcon name="i-lucide-…">`; never emojis.

---

## 8. The storefront — public pages

The public **layout** (`public.vue`) wraps every storefront page:
- **Header:** brand mark, nav (Home · About · Company · Projects · Services · Partners · Contact), dark-mode toggle, "Let's talk" CTA, mobile drawer. Smart-hides on scroll-down, reveals on scroll-up.
- **Footer:** company registration block (Axel Nova Ventures, 202603119899 / CA0420977-U, Kuala Lumpur) with an availability dot; 5 link columns (Explore, Services, Support, Legal); bottom bar with socials (GitHub, LinkedIn, portfolio, email) + a discreet admin link.

### Page-by-page

| Route | Page | What's on it |
|---|---|---|
| `/` | **Home** | Hero with animated split-text headline ("I craft interfaces people actually enjoy"), "Open to freelance" badge, CTAs to `/quote` and `/services`. Count-up **stats** (7+ yrs, 3 yrs industry, 10+ projects, 2 degrees). **Selected work** grid with filter tabs (All/Laravel/Nuxt/Fintech/Live) fed by `GET /api/v1/projects`. Closing "Have a project in mind?" CTA banner. |
| `/about` | **About** | Bio (5 paragraphs) + auto-rotating photo carousel (5 photos). **Skills grid** (Frontend / Backend / Data & Queue / Infrastructure / Tools). "My Story" + "Things I believe" sidebar. **Timeline** "Life in chapters" (2019 → Now). **Dreams & ambitions** 6-card grid. Closing note. |
| `/company` | **Company** | Brand story. Animated logo with rotating aurora halo. "At a glance" sticky card (registration no., established 2026, HQ KL, stage). "The Name" (Axel / Nova etymology). **Vision** 3 pillars (design-first, engineering with intention, human-centered). Philosophy pull-quote. Closing CTAs to `/services` and `/about`. |
| `/contact` | **Contact** | **Contact form** (name, email, subject pills, message) → **Web3Forms** (`api.web3forms.com/submit`). Sidebar: availability card + channel cards (WhatsApp, email, phone). Shows a success state after submit. |
| `/services` | **Services hub** | Category **tabs** + **currency switcher** (MYR/USD/GBP/SGD with hardcoded conversion). Package cards per category (name, tagline, converted price, duration, features, CTA — featured = "Most popular"). Fed by `GET /api/v1/services`. Live **estimator** (project type, pages slider, API toggle, timeline → cost + weeks). 4-step **process**. Contact channel cards. |
| `/services/[slug]` | **Service detail** | SEO-enriched deep-dive per category (web-presence, admin-portal, ui-ux-frontend, digital-marketing, booking-portal, ecommerce): deliverables, tech stack, packages, category-specific + general **FAQs**, "Ready to start?" CTA to `/quote?service=<slug>`. Emits Service + BreadcrumbList + FAQPage JSON-LD. |
| `/projects` | **Project registry** | Filterable grid — stack pills (All/Laravel/Nuxt/Docker/Redis/MySQL/FastAPI) + status dropdown (All/Live/In progress/Planning). Fed by `GET /api/v1/projects`. |
| `/projects/[id]` | **Project detail** | Status + featured badges, title, description, "Visit project" / "Source" links, cover image, long description, stack + tags. Fed by `GET /api/v1/projects/{slug}`. |
| `/quote`, `/quote/preview`, `/quote/success` | **Quote builder** | The lead funnel — see [section 9](#9-the-quote-builder-funnel). |
| `/partners` | **Partner program** | Referral program landing — audience cards, 3-step "how it works", **commission tiers** (5% cold / 10% warm / up to 15% closed), terms snapshot, 8-item FAQ, CTA to `/partners/refer`. |
| `/partners/refer` | **Refer a business** | Referral **form** (your details + business details + relationship tier + agree-to-terms) → **Web3Forms**. Success state + tier reference cards. |
| `/proposals/[slug]` | **Proposal viewer** | Confidential, `noindex`. Custom layout (no public chrome). Renders a proposal: scope, timeline, pricing, sign section. (`demo` slug = sample; others 404.) |
| `/investor/roofly` | **Investor brief** | `noindex`. Roofly investor materials hub — pitch deck / investment package / financial summary cards + "request a walkthrough" mailto. |
| `/legal/privacy-policy` · `/legal/terms` · `/legal/cookies` · `/legal/disclaimer` · `/legal/refund` | **Legal** | Five PDPA-aware policy pages with cross-links and "last updated" dates. |

**Storefront integrations:** Web3Forms (contact + referral, access key `a9100b0c-…`), Calendly (`calendly.com/baihaqie` discovery-call link), Ko-fi (support link). Sitemap excludes `/admin/**`, `/portal/**`, `/proposals/**`, `/quote/preview`, `/quote/success`, `/investor/**`.

---

## 9. The quote builder funnel

Three pages backed by `useQuoteForm()` shared state and the cached config endpoint.

**Step 1 — `/quote`** (collect + live estimate)
- **About you:** full name*, company/project, email*, phone/WhatsApp*, "how did you find me?" (Google/LinkedIn/Referral/GitHub/Other).
- **Project type:** category cards + package cards, **loaded dynamically** from `GET /api/v1/quote-builder/config`. Deep-linkable via `?category=X&package=Y` from service-page CTAs.
- **Scope details** (conditional per category): e.g. Web — pages slider, CMS toggle, booking toggle, languages; Dashboard — modules slider, real-time toggle, charts complexity; Design — screens, design-system/prototype toggles; Frontend — components/pages, state/testing toggles; SaaS — features, auth methods, payment, admin portal.
- **Add-ons** (read dynamically from config) and a **rush** toggle (+20% price, −30% timeline for week/month projects).
- A **live breakdown sidebar** recomputes cost + timeline on every change via `usePricingEngine.ts` (the TS port of the backend engine — no API round-trip).
- "Continue to preview" enabled once name/email/phone/package are set.

**Step 2 — `/quote/preview`** (`noindex`) — formatted quote: client header, reference placeholder, valid-until, human-readable scope summary, pricing breakdown line items, timeline. **Submit → `POST /api/v1/quote-requests`** → redirects to success with `?ref=&until=`. Guards back to `/quote` if minimum data is missing.

**Step 3 — `/quote/success`** (`noindex`) — green checkmark, **reference code** (copy button), valid-until, "what happens next" 4-step list, "Book a discovery call →" (Calendly) and "Back to home".

The **server** is the source of truth: on submit it re-prices with `PricingEngine`, generates the real `AXN-YYYY-NNNN` code, upserts the Client, stores the Quotation + add-ons, and queues the two emails.

---

## 10. The admin panel — options & settings

The admin SPA lives at `/admin`, behind `admin-auth` middleware. Auth is a **Sanctum bearer token** stored in `localStorage` as `axn_admin_token`; every request goes through `useAdminAuth().apiFetch()`. The shell (`admin.vue`) has a sidebar and a user menu showing the signed-in name/email + a "Founder" role badge and Sign-out.

**Sidebar nav** (`app/data/adminNav.ts`): Dashboard · Quotations · Orders · Services · Projects · Investors · Analytics.

### Login — `/admin/login`
Email + password (with show/hide), "Sign in" → `POST /v1/admin/login`. On success stores the token and redirects to the dashboard (or the `?redirect=` target). Invalid creds show an inline error.

### Dashboard — `/admin/index`
Overview with four **stat tiles**: Total quotations · New (unactioned) · Active orders · Page views (7d, marked "Soon"). Plus a **Recent quotations** table (5 latest, clickable rows: ref code, name, estimate range, status, submitted). Calls `/v1/admin/quotations` (twice, for totals + new), `/v1/admin/orders`, `/v1/admin/me`.

### Quotations — `/admin/quotations` (list) + `/admin/quotations/[id]` (detail)
**List controls:** debounced search (name/email/ref), **status filter** (Active default — excludes accepted — / New / Viewed / Contacted / Rejected / Spam), pagination. Table: ref code, name+email, package key, estimate range, status pill, submitted date.

**Detail actions/settings:**
- Header (ref code, name, company, status pill), **estimate card** (price range, ETA value+unit, package key), **add-ons card**, **scope details** (key/value from `form_payload`).
- Contact grid: email (mailto), phone (tel), submitted timestamp.
- **Update Status** buttons: New / Viewed / Contacted / Rejected / Spam → `POST /v1/admin/quotations/{id}/status`.
- **Actions:** "Reply by email" (pre-filled mailto), "WhatsApp" (pre-filled wa.me), and **"Accept & create order"** → `POST /v1/admin/quotations/{id}/accept` (mints an `ORD-YYYY-NNNN` Order and redirects to it).
- Audit timestamps: submitted + first-viewed. (Opening a `new` quotation auto-marks it viewed.)

### Orders — `/admin/orders` (list) + `/admin/orders/[id]` (detail)
**List controls:** debounced search (name/email/order#/ref), **status filter** (All / Pending / In progress / Delivered / Completed / Cancelled), pagination. Table: order# (+ref), client name+email, value range + package, status, started-at, created.

**Detail actions/settings:**
- Header (order#, client, company, status pill, value range).
- **Timeline:** created → work started → delivered → engagement closed (check/empty per `started_at` / `delivered_at` / `completed_at`).
- Scope snapshot (ETA + package) with a link back to the source quotation.
- **Order Status** buttons: Pending / In progress / Delivered / Completed / Cancelled → `POST /v1/admin/orders/{id}/status` (the backend auto-stamps `started_at` / `delivered_at` / `completed_at` on transition).
- Contact: "Email client" / "WhatsApp" buttons.

### Services — catalog & pricing manager
`/admin/services` (hub) + `/admin/services/categories/[id]` + `/admin/services/packages/[id]` (both also handle `/new`).

**Hub:** stat tiles (categories / total packages / featured). "New category" button. Each category renders collapsibly with: icon + name, "Default tab" / "Inactive" badges, description, and actions **+ Package · Edit · Delete**. Each package row shows name (+featured/inactive badges), tagline, price (`RM X – RM Y` or `RM X+`), duration + ETA, and Edit/Delete.

**Category editor — every field:**
- Slug* (unique) · Name*
- **Icon*** — grid picker from `serviceIcons`
- Description* (textarea)
- **Sort order** — visual pill UI (move left/right, click a position to insert, "+" auto-appends)
- Toggles: **Active** (visible on public services page) · **Default tab** (the one highlighted by default — only one allowed)
- Calls `GET/POST/PUT /v1/admin/service-categories[/{id}]`.

**Package editor — every field:**
- **Category*** (select) · Slug* (unique within category) · Name*
- **Tagline*** (marketing copy)
- **Price min (MYR)*** · Price max (MYR) (blank = open-ended `RM X+`) · **Unit*** (e.g. "per project") · **Duration label*** (e.g. "5–6 weeks") · Revisions (e.g. "2 rounds")
- **ETA value*** (1–999) · **ETA unit*** (hour / day / week / month) — *used by the quote builder for math + rush logic*
- **Features*** (textarea, one per line)
- **CTA label** (default "Get a quote") + **"Wire CTA to the quote builder"** checkbox → reveals **quote category key** + **quote package key** (these are what make a package appear in `/quote` and deep-link as `/quote?category=…&package=…`)
- **Sort order** (same pill UI as categories)
- Toggles: **Featured** · **Active**
- Calls `GET/POST/PUT /v1/admin/service-packages[/{id}]`.

> A package is "quotable" only if it has **both** a `quote_key` and a `price_max_myr`. Custom-quote / retainer rows (null `quote_key`) show on the public services page but never in the quote builder. Saving any category/package auto-clears the quote-builder config cache (observers).

### Projects — `/admin/projects` (list) + `/admin/projects/[id]` (editor)
**List:** stat tiles (total / featured / live), debounced search (name/slug), **status filter** (All / Live / In progress / Soon / Planning), "New project" button. Cards show name+slug, status badge, description (clamped), first 4 tags, featured/inactive badges, Edit + Delete (confirm).

**Editor — every field:** Slug* (unique) · Name* · Short description* (≤500) · Long description* · **Status*** (Live / In progress / Soon / Planning) · Sort order · Live URL · Repo URL · Cover image URL · Tags (comma-sep) · Stack (comma-sep) · **Featured** toggle · **Active** toggle. Calls `GET/POST/PUT/DELETE /v1/admin/projects[/{id}]`.

### Analytics — `/admin/analytics`
**Placeholder (Phase B).** Shows a "not wired up yet" banner and four planned metric cards: Page views, Project likes, Service interest, Quote funnel. The `page_views` and `entity_likes` tables already exist; the tracking endpoints + queries are the next build.

### Investors — `/admin/investors`
**MVP placeholder.** Stat tiles (active investors, deal rooms, pitch views, conversations — all 0/—). A "pitch materials" list (e.g. Roofly: open-landing + copy-link buttons) and an "Investor CRM coming soon" panel. "New investor" button is disabled ("Soon"). No API yet — data is hardcoded.

---

## 11. The backend (Laravel 11)

### Controllers (`app/Http/Controllers/Api/V1/`)

**Public:**
- `QuoteBuilderConfigController@show` — returns the active pricing config (cached 1h).
- `PublicServicesController@index` — active categories + active packages, ordered.
- `PublicProjectsController@index` / `@show` — active projects / single by slug.
- `QuoteRequestController@store` — the funnel: prices via `PricingEngine`, generates the ref code, upserts the Client by email, creates the Quotation + add-ons, dispatches `SendClientQuoteEmail` and `NotifyAdminJob` (the admin one delayed 10s to dodge Mailtrap's rate limit).

**Admin (`Admin/`):** `AuthController` (login/logout/me), `QuotationsController` (index/show/updateStatus/accept), `OrdersController` (index/show/updateStatus), `ServiceCategoriesController` + `ServicePackagesController` (full CRUD with `SortOrder` placement), `ProjectsController` (full CRUD).

### Services & support
- **`Services/Quoting/PricingEngine.php`** — source-of-truth calculation (see [section 14](#14-the-pricing-engine)).
- `Services/Quoting/EstimateResult.php` + `QuoteRequestInput.php` — typed value objects (`minMyr`, `maxMyr`, `etaValue`, `etaUnit`, `breakdown`).
- **`Support/ReferenceCodeGenerator.php`** — atomic `AXN-YYYY-NNNN` codes via a DB transaction with `lockForUpdate()`; counter resets yearly. (Orders use the analogous `ORD-YYYY-NNNN`.)
- **`Support/SortOrder.php`** — ordered-list helper (`placeNew` / `move` / `removeFromScope`) for the catalog sort_order columns; caller wraps in a transaction.

### Observers (`app/Observers/`)
`PricingConfigObserver` (enforces exactly one `active` config + clears cache), `ServiceCategoryObserver`, `ServicePackageObserver` — all invalidate the `quote_builder_config_v1` cache on save/delete, so **pricing changes are data, not deploys**.

### Auth
Sanctum personal access tokens (no expiry, manually revoked). A custom `role:admin` middleware (`CheckRole`) gates the admin group — login also checks `role === 'admin'`. `TrustProxies` is set to `*` so Laravel sees real client IPs behind nginx (correct per-IP throttling + HTTPS-aware URLs). `config/services.php` carries the admin email/name used by the mailers.

---

## 12. Database schema

**Active business tables:**

| Table | Key columns |
|---|---|
| `users` | name, email (unique), password, **role** (default `admin`) |
| `pricing_configs` | version (unique), **config** (JSON), active (only one true), notes |
| `clients` | name, email (unique), phone, company, notes, tags (JSON) · soft-deletes |
| `quotations` | **reference_code** (unique), client_id, name/email/phone/company, **package_key**, pricing_config_id, **form_payload** (JSON), estimate_min/max_myr, **estimate_eta_value/eta_unit**, status (new/viewed/contacted/rejected/spam → accepted), ip_address, user_agent, submitted_at, viewed_at · soft-deletes |
| `quotation_addons` | quotation_id, addon_key, addon_label, amount_myr |
| `orders` | **order_number** (unique), quotation_id, client_id, value_min/max_myr, status (pending/in_progress/delivered/completed/cancelled), started_at, delivered_at, completed_at, notes · soft-deletes |
| `service_categories` | slug (unique), name, icon, description, sort_order, active, **is_default** |
| `service_packages` | service_category_id (FK), slug, name, tagline, price_min/max_myr, unit, duration_text, revisions, **eta_value/eta_unit**, featured, features (JSON), cta, **quote_key** (JSON), sort_order, active · unique(category, slug) |
| `projects` | slug (unique), name, description (≤500), long_description, status (live/soon/wip/planning), url, repo, tags (JSON), stack (JSON), featured, sort_order, cover_image_url, active |
| `page_views` | path, ip_hash (SHA-256), user_agent, referrer, viewed_at *(append-only; analytics Phase B)* |
| `entity_likes` | entity_type, entity_id, ip_hash, cookie_id · unique(type, id, ip_hash) *(Phase B)* |

Plus Laravel stock tables: `password_reset_tokens`, `sessions`, `cache`, `jobs`, `failed_jobs`, `personal_access_tokens`.

> History note: `quote_requests` was renamed to `quotations` (and `quote_request_addons` → `quotation_addons`); legacy `estimate_weeks` became `estimate_eta_value` + `estimate_eta_unit`; `pdf_path` and several legacy FK columns were dropped. The Clients/Orders model was backfilled from accepted quotations.

**Seeders:** `DatabaseSeeder` runs `AdminUserSeeder` (from `ADMIN_LOGIN_*` env), `PricingConfigSeeder` (the `2026.05.01` config — base packages, modifiers, add-ons, rush 1.20, MYR, 30-day validity), `ServiceCategoriesSeeder` (categories + packages), `ProjectsSeeder` (portfolio items).

---

## 13. API reference

All routes are under `/api`.

**Public**

| Method | Path | Purpose |
|---|---|---|
| GET | `/v1/quote-builder/config` | Active pricing config (cached 1h) |
| GET | `/v1/services` | Active categories + packages |
| GET | `/v1/projects` | Active projects |
| GET | `/v1/projects/{slug}` | Single project |
| POST | `/v1/quote-requests` | Submit a quote (throttle **3/hr/IP in prod**, 1000/min in dev) |
| POST | `/v1/admin/login` | Login (throttle 10/min prod) → bearer token |

**Admin** (`EnsureFrontendRequestsAreStateful` + `auth:sanctum` + `role:admin`)

| Method | Path | Purpose |
|---|---|---|
| POST | `/v1/admin/logout` · GET `/v1/admin/me` | Session |
| GET | `/v1/admin/quotations` · `/{id}` | List / detail (detail marks viewed) |
| POST | `/v1/admin/quotations/{id}/status` · `/accept` | Update status / accept → create Order |
| GET | `/v1/admin/orders` · `/{id}` | List / detail |
| POST | `/v1/admin/orders/{id}/status` | Update status (auto-stamps timestamps) |
| GET/POST/PUT/DELETE | `/v1/admin/service-categories[/{id}]` | Categories CRUD |
| GET/POST/PUT/DELETE | `/v1/admin/service-packages[/{id}]` | Packages CRUD |
| GET/POST/PUT/DELETE | `/v1/admin/projects[/{id}]` | Projects CRUD |

Health check: `GET /up`.

---

## 14. The pricing engine

Two implementations that **must stay in sync**: `backend/app/Services/Quoting/PricingEngine.php` (source of truth, drives the stored quote) and `frontend/app/composables/usePricingEngine.ts` (TS port for live `/quote` estimates). Both read the same config from `GET /api/v1/quote-builder/config`.

**Two-layer catalog/pricing model:**
- **Catalog** (admin-managed): `service_categories` + `service_packages` — names, taglines, prices, ETA, features, the deep-link `quote_key`.
- **Pricing rules** (engineered config): `pricing_configs.config` JSON — `modifiers`, `addons`, `rush_multiplier`, `currency`, `valid_for_days`, plus a `base_packages` fallback map. *(Modifiers/add-ons have no admin UI yet — edit the JSON.)*

The engine merges them: start with JSON `base_packages`, then layer admin `service_packages` on top keyed by `quote_key.package` (DB wins on conflict).

**Calculation order (don't reorder):** resolve base → apply modifiers (numeric over a threshold, or toggle) → sum add-ons (fixed) → apply rush (×`rush_multiplier`, and −30% ETA only when `eta_unit ∈ {week, month}`) → round to nearest RM 50 → emit an auditable breakdown.

To change prices: edit packages in the admin UI (cache auto-clears), or insert a new active `pricing_configs` row for rule changes. See [QUOTE_BUILDER.md](./QUOTE_BUILDER.md) for the full guide.

---

## 15. Background jobs & email

`POST /v1/quote-requests` dispatches two **database-queue** jobs (so the **queue worker must be running** or emails silently never send):

- **`SendClientQuoteEmail`** → `ClientQuoteMail` — subject "Your quote {ref} from Axel Nova Ventures"; markdown template `mail.client-quote`; the **estimate is rendered inline** (ref code, package, modifiers, add-ons, breakdown, valid-until, Calendly link). No PDF, no attachments. (`ShouldBeUnique`, unique 1h per quotation, 5 tries.)
- **`NotifyAdminJob`** → `AdminNotificationMail` — subject "New lead: {ref} — RM …k–…k"; markdown template `mail.admin-notification`; deep-links to `/admin/quotations/{id}`. Dispatched with a **10s delay** so the customer email clears Mailtrap's 1-email/sec free-tier cap first (shrink/remove this delay on a real provider).

---

## 16. Key conventions & gotchas

- **`DB_HOST=mysql`** resolves only on the docker network → run all `php artisan …` **inside** the backend container.
- **Queue worker is mandatory** for quote submissions; restart it after editing any Job/Mail class (it's a long-lived daemon).
- **Pricing changes are data, not code** — observers auto-clear the config cache; you rarely need `cache:clear`.
- **Reference/order codes are atomic** (`lockForUpdate()` transaction, yearly reset) — don't reimplement the counter elsewhere.
- **Sanctum is route-scoped** to `/v1/admin/*` — don't set `SANCTUM_STATEFUL_DOMAINS` globally; public POSTs stay stateless.
- **Quote throttle is env-aware** — 3/hr/IP in prod, 1000/min otherwise; test freely in dev.
- **No FOUC** — theme via CSS vars, never bind layout backgrounds to `colorMode.value`.
- **Scroll-reveal via `useScrollReveal('.reveal')`** only; never rely on static `opacity-0` that needs JS to undo (content must survive a GSAP failure).
- **Design tokens live in `main.css`**; never hardcode hex. Icons are Lucide via `<UIcon>`, never emojis.
- **`main` is protected** — every change goes through a PR; CI build-checks on PRs and deploys on merge.

---

*Authoritative companion docs:* [ARCHITECTURE.md](./ARCHITECTURE.md) · [DEPLOY.md](./DEPLOY.md) · [QUOTE_BUILDER.md](./QUOTE_BUILDER.md) · [README.md](./README.md) · [frontend/UI-STANDARDS.md](../frontend/UI-STANDARDS.md) · [frontend/*-COMPONENTS.md](../frontend/) · [backend/README.md](../backend/README.md)
</content>
</invoke>
