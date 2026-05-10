# Axel Nova Platform — Architecture

## Repository structure

```
axelnova-dashboard/
  frontend/          Nuxt 4 portfolio + quote builder frontend
  backend/           Laravel 11 API
  docs/
    global/          ARCHITECTURE.md (this file), DEPLOY.md, QUOTE_BUILDER.md, README.md
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
| `invoices` | Issued invoices (Stripe integration Phase 5) |
| `projects` | Active project tracking |

## API routes

All routes prefixed with `/api`:

```
GET  /v1/quote-builder/config        Public, cached 1h
POST /v1/quote-requests              Public, 3/hr/IP
GET  /v1/admin/leads                 Sanctum + admin role
GET  /v1/admin/leads/{id}            Sanctum + admin role
POST /v1/admin/leads/{id}/status     Sanctum + admin role
POST /v1/admin/leads/{id}/convert    Sanctum + admin role
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
Generates `AXN-YYYY-NNNN` codes atomically using a DB transaction with `lockForUpdate()`. Counter resets each year.

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
- **R2**: Private bucket — PDFs served via signed temporary URLs (1h expiry)
