# Axel Nova Platform — Architecture

## Repository structure

```
axelnova-dashboard/
  frontend/          Nuxt 4 portfolio + quote builder frontend
  backend/           Laravel 11 API
  docs/
    global/          ARCHITECTURE.md (this file), DEPLOY.md, QUOTE_BUILDER.md, DOCUMENT-GENERATION.md, README.md
    frontend/        UI-STANDARDS.md, *-COMPONENTS.md
    backend/         README.md
```

## Tech stack

| Layer | Technology |
|-------|-----------|
| Frontend | Nuxt 4, Vue 3, TypeScript, @nuxt/ui v4, Tailwind CSS v4 |
| Backend | Laravel 11, PHP 8.4+ |
| Database | MySQL 8 (shared via [axelnova-infra](../../../axelnova-infra/)) |
| Queue | Laravel database queue |
| Email | SMTP — Mailtrap in dev, Mailgun/Postmark/SES in prod |
| Auth | Laravel Sanctum (token-based, admin only) |

## Database tables

### Core (Phase 3)

| Table | Purpose |
|-------|---------|
| `pricing_configs` | Versioned pricing formula JSON — only one row `active=true` at a time |
| `quote_requests` | Lead submissions from the public quote builder |
| `quote_request_addons` | Denormalised add-ons selected per quote request |
| `users` | Admin users (authenticated via Sanctum) |

### Planned (Phase 4+)

| Table | Purpose |
|-------|---------|
| `clients` | Converted leads become clients |
| `quotations` | Formal quotations generated from leads |
| `quotation_line_items` | Line items from the pricing breakdown |
| `orders` | Post-acceptance engagements (one per converted quotation) |
| `documents` | Issued invoices & receipts — frozen `DocumentData` snapshots, rendered on demand. See [DOCUMENT-GENERATION.md](./DOCUMENT-GENERATION.md) |
| `projects` | Active project tracking |

### Analytics (Phase B — see [ANALYTICS.md](./ANALYTICS.md))

| Table | Purpose |
|-------|---------|
| `page_views` | Append-only public page-view log (hashed IP, path, referrer, UA). Bots dropped on write |
| `entity_likes` | Anonymous likes per entity (`project` / `service_package`), deduped per browser by `cookie_id` (hashed IP recorded for abuse/analytics) |

## API routes

All routes prefixed with `/api`:

```
GET  /v1/quote-builder/config        Public, cached 1h
POST /v1/quote-requests              Public, 3/hr/IP
GET  /v1/admin/leads                 Sanctum + admin role
GET  /v1/admin/leads/{id}            Sanctum + admin role
POST /v1/admin/leads/{id}/status     Sanctum + admin role
POST /v1/admin/leads/{id}/convert    Sanctum + admin role

# Document generation (see DOCUMENT-GENERATION.md)
POST /v1/admin/orders/{order}/documents   Sanctum — issue an invoice/receipt
GET  /v1/documents/{token}                Public  — token-gated document data (JSON)
# Frontend Nitro: GET /api/documents/{token}/pdf — renders & streams the PDF

# Analytics (see ANALYTICS.md)
POST /v1/track/page-view             Public  — page-view beacon (hashed IP, bots dropped)
POST /v1/likes/{type}/{id}           Public  — toggle an anonymous like
GET  /v1/admin/analytics/overview    Sanctum — traffic + likes overview (?range=7d|30d)
```

## Frontend routes

```
/                     Portfolio home
/projects             Project listing
/projects/[id]        Project detail
/services             Services & pricing
/about                About page
/contact              Contact form (Web3Forms)
/quote                Public quote builder (→ backend API)
/quote/success        Post-submission confirmation
/admin/login          Admin auth
/admin/leads          Lead list (Sanctum-protected)
/admin/leads/[id]     Lead detail + actions
```

## Key services

### PricingEngine (`backend/app/Services/Quoting/PricingEngine.php`)
Single source of truth for pricing calculations. Reads from the active `PricingConfig` row. Also exposes `configForFrontend()` which the Nuxt `/quote` page uses for live client-side estimates.

### usePricingEngine (`frontend/app/composables/usePricingEngine.ts`)
TypeScript port of the same calculation logic. Fetches config from `/api/v1/quote-builder/config` on mount, then runs estimates client-side in real-time as the form changes.

### ReferenceCodeGenerator (`backend/app/Support/ReferenceCodeGenerator.php`)
Mints the AXN document family — `AXN{TYPE}-{YYYY}-{NNNN}` (type letter fused into the prefix), `AXNQ-` (quotation) / `AXNO-` (order) / `AXNI-` (invoice, future) via the [`DocumentType`](../../backend/app/Support/DocumentType.php) enum — atomically using a DB transaction with `lockForUpdate()`. Each type has its own counter that resets each year.

### DocumentMapper (`backend/app/Services/Quoting/DocumentMapper.php`)
Maps a `Quotation` (live, via `toDocumentData`) or an `Order` (via `forOrder`, for invoices/receipts) to the `DocumentData` shape the PDF renderer consumes. See [DOCUMENT-GENERATION.md](./DOCUMENT-GENERATION.md).

### DocumentIssuer (`backend/app/Services/Quoting/DocumentIssuer.php`)
Issues an invoice/receipt for an order: assigns a derived atomic number (`INV-`/`RCP-` + the quotation ref) and **freezes** the `DocumentData` snapshot into `documents`, so the rendered PDF can never drift from what was issued.

## Queued job flow

```
POST /api/v1/quote-requests
  └─ QuoteRequestController::store()
       ├─ PricingEngine::calculate()
       ├─ ReferenceCodeGenerator::generate()
       ├─ QuoteRequest::create()
       ├─ QuoteRequestAddon::create() (for each addon)
       ├─ SendClientQuoteEmail::dispatch()  → emails the customer their estimate (HTML, no attachment)
       └─ NotifyAdminJob::dispatch()        → emails admin so they can follow up via /admin/leads
```

The customer's estimate is rendered **inline** in the email body (reference code, package, modifiers, add-ons, breakdown, valid-until date). No PDF, no attachments, no external object storage — admin handles the full conversation via the leads portal.

## Environment variables

See `backend/.env.example` for the full list. Key variables:

| Variable | Purpose |
|----------|---------|
| `NUXT_PUBLIC_API_BASE` | Backend base URL (set in `docker-compose.dev.yml` for dev) |
| `MAIL_*` | SMTP for outbound mail (Mailtrap in dev) |
| `ADMIN_NOTIFICATION_EMAIL` | Where new lead notifications go |
| `ADMIN_CALENDLY_URL` | Optional CTA URL in the customer email |

## Deployment

- **Frontend**: Nuxt runs in Docker, reverse-proxied by Caddy/Nginx to `axelnovaventures.com`
- **Local dev**: `docker compose -f docker-compose.dev.yml up -d --build` from the monorepo root brings up `axelnova-backend-dev` (port 8003) and `axelnova-frontend-dev` (port 3003). Both join the external `axelnova-shared` network from `axelnova-infra` and reach MySQL via hostname `mysql`.
- **Artisan commands**: run them inside the backend container — `docker compose -f docker-compose.dev.yml exec backend php artisan migrate` (because `DB_HOST=mysql` in `.env` only resolves on the docker network).
- **MySQL**: shared instance from `axelnova-infra` (Docker, `127.0.0.1:3306` from host, `mysql:3306` from containers); database `axelnova_dashboard_db`, user `axelnova_dashboard_user`.
- **Production**: backend via PHP-FPM + Nginx on port 8003 (TBD); frontend via the existing `frontend/docker-compose.yml` ghcr image.
- **Queue**: `php artisan queue:work` as a supervised process (Supervisor or systemd)
- **Documents (PDF)**: not stored — rendered on demand (token-gated) by headless Chromium in the frontend image, from live data (quotations) or a frozen snapshot (invoices/receipts). The frontend prod image installs `chromium` via `apk`; `pdf.ts` uses `playwright-core` against `/usr/bin/chromium-browser`. See [DOCUMENT-GENERATION.md](./DOCUMENT-GENERATION.md).
