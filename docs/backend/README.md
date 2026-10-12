# Axel Nova Platform — Backend API

Laravel 11 API for the Axel Nova Ventures platform (`admin.axelnovaventures.com`).

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
- `MAIL_*` — SMTP: Mailtrap in dev; in prod the Hostinger backup route behind `MAIL_MAILER=failover` (Resend primary — `RESEND_SMTP_KEY`, `RESEND_WEBHOOK_SECRET`; see [EMAIL-SYSTEM.md](../global/EMAIL-SYSTEM.md))
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
| POST | `/api/v1/inquiries` | Public | 20/hr/IP | Submit an inquiry (`/quote`, `/contact`, partner, collaboration forms) |
| POST | `/api/v1/admin/login` | Public | 10/min | Issue Sanctum token |
| POST | `/api/v1/admin/logout` | Sanctum + admin | — | Revoke current token |
| GET | `/api/v1/admin/me` | Sanctum + admin | — | Current admin user |
| GET | `/api/v1/admin/quotations` | Sanctum + admin | — | List quotations (excludes accepted by default) |
| GET | `/api/v1/admin/quotations/{id}` | Sanctum + admin | — | Quotation detail + marks viewed |
| POST | `/api/v1/admin/quotations/{id}/status` | Sanctum + admin | — | Update non-terminal status |
| POST | `/api/v1/admin/quotations/{id}/accept` | Sanctum + admin | — | Accept → create matching Order |
| GET | `/api/v1/admin/orders` | Sanctum + admin | — | List orders |
| GET | `/api/v1/admin/orders/{id}` | Sanctum + admin | — | Order detail |
| POST | `/api/v1/admin/orders/{id}/status` | Sanctum + admin | — | Update project lifecycle status |

---

## Test Commands

```bash
# Get pricing config
curl http://localhost:8000/api/v1/quote-builder/config

# Submit a quote request (the /quote inquiry form)
curl -X POST http://localhost:8003/api/v1/inquiries \
  -H "Content-Type: application/json" \
  -d '{"name": "Ahmad Test", "email": "test@example.com", "message": "A booking site for my clinic.", "origin": "quote"}'

# Validation failure
curl -X POST http://localhost:8003/api/v1/inquiries \
  -H "Content-Type: application/json" \
  -d '{"name": "A", "email": "not-an-email"}'

# Admin quotations (replace TOKEN with a Sanctum token)
curl http://localhost:8000/api/v1/admin/quotations \
  -H "Authorization: Bearer TOKEN"
```

---

## Queued Jobs

Run `php artisan queue:work --tries=3` to process:

- **SendClientQuoteEmail** — the admin's quotation Send → `ClientQuotationMail` (unique per revision for 1h, 5 tries).
- Every other email (inquiry auto-reply + owner alert, invoices, feedback, team / partner mail) is a queued mailable — see [EMAIL-SYSTEM.md](../global/EMAIL-SYSTEM.md).

(The public quote funnel — `POST /api/v1/quote-requests`, its instant estimate and the `NotifyAdminJob` new-lead alert — was retired on 2026-10-12.)

Monitor queue: `php artisan queue:monitor database`

---

## Updating Pricing

Pricing is config-driven — never hardcoded. To update prices:

1. Insert a new row in `pricing_configs` with a new `version` string
2. Set `active = true` — the `PricingConfigObserver` automatically deactivates all other rows
3. The `PricingEngine` picks up the new config immediately (cache clears within 1 hour, or run `php artisan cache:clear`)

The frontend fetches config from `/api/v1/quote-builder/config` and runs the same calculation logic client-side for live estimates.
