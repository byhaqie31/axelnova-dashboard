# axelnova-dashboard

Portfolio + client platform for Ahmad Baihaqie / Axel Nova Ventures.

- **Frontend**: Nuxt 4 (TypeScript, Tailwind v4, @nuxt/ui v4) — `axelnovaventures.com` and `admin.axelnovaventures.com`
- **Backend**: Laravel 11 API (Phase 3: public quote builder funnel)
- **Database**: shared MySQL 8 from [axelnova-infra](../axelnova-infra/)

All `.md` documentation is centralised under [docs/](./docs/) — `docs/global/` (repo-wide), `docs/frontend/` (Nuxt), `docs/backend/` (Laravel). Start with [docs/global/ARCHITECTURE.md](./docs/global/ARCHITECTURE.md) and [docs/global/QUOTE_BUILDER.md](./docs/global/QUOTE_BUILDER.md).

## Repo layout

```
axelnova-dashboard/
  backend/                    Laravel 11 API
    Dockerfile.dev            Local dev image (PHP 8.4-cli + extensions + artisan serve)
  frontend/                   Nuxt 4 app
    Dockerfile.dev            Local dev image (node:22-alpine + Nuxt dev server)
    Dockerfile                Production image
    docker-compose.yml        Production-only frontend deploy (ghcr image)
  docker-compose.dev.yml      Local dev orchestrator — boots both apps on axelnova-shared
  docs/
    global/                   ARCHITECTURE.md, DEPLOY.md, QUOTE_BUILDER.md, README.md (mirror)
    frontend/                 UI-STANDARDS.md, *-COMPONENTS.md
    backend/                  README.md (Laravel-specific notes)
```

## Prerequisites

1. Docker Desktop running on macOS
2. The shared infra stack must be up first — it provides MySQL + phpMyAdmin and the `axelnova-shared` network that the dashboard joins:

   ```bash
   cd ../axelnova-infra
   cp .env.example .env          # set MYSQL_ROOT_PASSWORD before first boot
   docker compose up -d
   ```

   See [axelnova-infra/README.md](../axelnova-infra/README.md) for details.

---

## Local development (full Docker)

**Start everything:**

```bash
cd /Users/BHQIMBP14/Developer/axelnova-dashboard
docker compose -f docker-compose.dev.yml up -d --build
```

This builds and starts:

| Container | Image | Bound to | Purpose |
|---|---|---|---|
| `axelnova-backend-dev` | `axelnova-backend:dev` | `127.0.0.1:8003` | Laravel API (`php artisan serve`) |
| `axelnova-frontend-dev` | `axelnova-frontend:dev` | `127.0.0.1:3003`, `127.0.0.1:24678` | Nuxt dev server + Vite HMR |

Both join the external `axelnova-shared` network — backend reaches MySQL via hostname `mysql`, frontend reaches backend via `http://axelnova-backend-dev:8003`.

**Verify it's up:**

```bash
docker compose -f docker-compose.dev.yml ps
curl http://127.0.0.1:8003/api/v1/quote-builder/config | head -c 200
open http://127.0.0.1:3003
```

**Tail logs:**

```bash
docker compose -f docker-compose.dev.yml logs -f backend frontend
```

**Run artisan / npm / composer commands inside the containers** (because `DB_HOST=mysql` only resolves on the docker network):

```bash
# Laravel
docker compose -f docker-compose.dev.yml exec backend php artisan migrate
docker compose -f docker-compose.dev.yml exec backend php artisan db:seed
docker compose -f docker-compose.dev.yml exec backend php artisan tinker
docker compose -f docker-compose.dev.yml exec backend composer require <pkg>

# Nuxt
docker compose -f docker-compose.dev.yml exec frontend npm install <pkg>
```

**Run the queue worker** (needed when testing quote submissions — generates PDFs and sends emails):

```bash
docker compose -f docker-compose.dev.yml exec backend php artisan queue:work
```

**Stop everything:**

```bash
docker compose -f docker-compose.dev.yml down

# also wipe named volumes (frontend node_modules, nuxt cache, etc.):
docker compose -f docker-compose.dev.yml down -v
```

**Rebuild after Dockerfile or dependency changes:**

```bash
docker compose -f docker-compose.dev.yml up -d --build
```

---

## Production

The two layers ship independently.

### Frontend (Docker, ghcr image)

```bash
cd frontend
docker compose up -d --build
docker compose logs -f axelnova-dashboard
```

Container listens on `127.0.0.1:3001` only — a host-level reverse proxy (Caddy / Nginx / Traefik) terminates TLS and forwards to it. Image is published to `ghcr.io/byhaqie31/axelnova-dashboard:latest`.

### Backend (TBD)

Production backend deploy isn't wired yet. Plan: PHP-FPM + Nginx, port 8003, queue worker via Supervisor or systemd. See [ARCHITECTURE.md](./ARCHITECTURE.md) for the intended setup.

---

## Common gotchas

- **`DB_HOST` in `backend/.env` is `mysql`**, not `127.0.0.1`. That hostname only resolves on the `axelnova-shared` docker network, so `php artisan ...` from the Mac host won't connect. Always use `docker compose -f docker-compose.dev.yml exec backend php artisan ...` instead.
- **MySQL only honors `MYSQL_ROOT_PASSWORD` on first volume boot.** If you change it in `axelnova-infra/.env` later, you must `docker compose down -v` (in the infra repo) to nuke the volume — otherwise auth will fail.
- **The frontend prod compose binds port 3001**, which collides with `hop-frontend-dev` per [axelnova-infra/docs/port-allocation.md](../axelnova-infra/docs/port-allocation.md). Resolve before running both in production on the same host.

---

## Troubleshooting

### Queue worker

- **Worker runs old code after editing a Job/Mail class.** `php artisan queue:work` is a long-lived daemon — it loads code into memory at boot and never reloads. After editing anything under [backend/app/Jobs/](./backend/app/Jobs/) or [backend/app/Mail/](./backend/app/Mail/), restart the worker:
  ```bash
  docker compose -f docker-compose.dev.yml restart worker
  ```

- **Worker container shows empty logs.** `queue:work` is silent when idle (it polls the `jobs` DB table once a second but only logs at boot or when a job runs). Submit a quote to trigger output, or verify the process is alive:
  ```bash
  docker compose -f docker-compose.dev.yml exec worker ps aux
  ```

- **Jobs queued but never processed.** Means no worker is running. Confirm queue depth, then start the worker:
  ```bash
  docker compose -f docker-compose.dev.yml exec backend \
    php artisan tinker --execute="echo DB::table('jobs')->count() . ' queued, ' . DB::table('failed_jobs')->count() . ' failed';"
  docker compose -f docker-compose.dev.yml up -d worker
  ```

- **Inspect a failed job's exception.** `queue:failed` only shows a one-line summary — pull the full stack trace from the DB:
  ```bash
  docker compose -f docker-compose.dev.yml exec backend \
    php artisan tinker --execute="echo DB::table('failed_jobs')->latest('id')->value('exception');"
  docker compose -f docker-compose.dev.yml exec backend php artisan queue:flush   # clear all
  ```

### Mailtrap

- **`550 5.7.0 Too many emails per second`.** Mailtrap free caps at 1 email/sec, and a quote submission fires two emails (customer + admin). The admin email is intentionally delayed by 10s in [QuoteRequestController.php](./backend/app/Http/Controllers/Api/V1/QuoteRequestController.php) to dodge this. When you swap to a real provider (Mailgun/Postmark/SES), shrink or remove that delay — they handle hundreds of emails/sec.

- **Email body never lands but worker shows DONE.** Check `MAIL_USERNAME` / `MAIL_PASSWORD` in [backend/.env](./backend/.env) are filled in. With empty creds, Mailtrap silently swallows the message. Fastest sanity check is to switch `MAIL_MAILER=log` temporarily and tail `backend/storage/logs/laravel.log` — the rendered email body dumps there.
