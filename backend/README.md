# Axel Nova Platform — Backend API

Laravel 11 API for the Axel Nova Ventures platform (`dashboard.axelnova.tech`).

## Setup

### 1. Install dependencies
```bash
cd backend
composer install
```

### 2. Configure environment
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your credentials:
- `DB_*` — MySQL credentials
- `AWS_*` — Cloudflare R2 credentials (endpoint format: `https://<account-id>.r2.cloudflarestorage.com`)
- `TURNSTILE_SECRET` — from Cloudflare Turnstile dashboard
- `MAIL_*` — SMTP provider (Mailgun, Postmark, etc.)
- `ADMIN_NOTIFICATION_EMAIL` — your email for lead notifications
- `ADMIN_CALENDLY_URL` — your Calendly link (included in PDFs)

### 3. Create the database
```sql
CREATE DATABASE axelnova_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. Run migrations and seed
```bash
php artisan migrate
php artisan db:seed
```

### 5. Start the server
```bash
php artisan serve          # dev server at http://localhost:8000
php artisan queue:work     # process queued jobs (PDF, email)
```

---

## API Endpoints

| Method | Endpoint | Auth | Rate limit | Description |
|--------|----------|------|-----------|-------------|
| GET | `/api/v1/quote-builder/config` | Public | — | Active pricing config (cached 1h) |
| POST | `/api/v1/quote-requests` | Public + Turnstile | 3/hr/IP | Submit quote request |
| GET | `/api/v1/admin/leads` | Sanctum + admin | — | List leads with filters |
| GET | `/api/v1/admin/leads/{id}` | Sanctum + admin | — | Lead detail + marks viewed |
| POST | `/api/v1/admin/leads/{id}/status` | Sanctum + admin | — | Update status |
| POST | `/api/v1/admin/leads/{id}/convert` | Sanctum + admin | — | Mark as converted (Phase 4 builds on this) |

---

## Test Commands

```bash
# Get pricing config
curl http://localhost:8000/api/v1/quote-builder/config

# Submit a quote (Turnstile disabled when TURNSTILE_SECRET is empty)
curl -X POST http://localhost:8000/api/v1/quote-requests \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Ahmad Test",
    "email": "test@example.com",
    "phone": "+60177109486",
    "package_key": "web_business",
    "modifiers": { "cms": true, "extra_page": 7 },
    "addon_keys": ["seo", "analytics"],
    "rush": false,
    "form_payload": { "source": "Google", "notes": "Test submission." },
    "cf_turnstile_response": "test-token"
  }'

# Validation failure
curl -X POST http://localhost:8000/api/v1/quote-requests \
  -H "Content-Type: application/json" \
  -d '{"name": "A", "email": "not-an-email", "package_key": "invalid"}'

# Admin leads (replace TOKEN with a Sanctum token)
curl http://localhost:8000/api/v1/admin/leads \
  -H "Authorization: Bearer TOKEN"
```

---

## Queued Jobs

Run `php artisan queue:work --tries=3` to process:

1. **GenerateQuotePdfJob** — renders `resources/views/pdfs/quote.blade.php` via spatie/laravel-pdf, uploads to R2. Retries 3× with 10s/30s/60s backoff.
2. **SendClientQuoteEmail** — attaches PDF from R2, sends to the client. Re-queues with 30s delay if PDF isn't ready yet. Unique per quote_request_id (no double-send).
3. **NotifyAdminJob** — sends admin notification with lead summary and admin panel deep-link.

Monitor queue: `php artisan queue:monitor database`

---

## Updating Pricing

Pricing is config-driven — never hardcoded. To update prices:

1. Insert a new row in `pricing_configs` with a new `version` string
2. Set `active = true` — the `PricingConfigObserver` automatically deactivates all other rows
3. The `PricingEngine` picks up the new config immediately (cache clears within 1 hour, or run `php artisan cache:clear`)

The frontend fetches config from `/api/v1/quote-builder/config` and runs the same calculation logic client-side for live estimates.
