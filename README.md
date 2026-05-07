# axelnova-dashboard

Portfolio + client platform for Ahmad Baihaqie / Axel Nova Ventures.

- **Frontend**: Nuxt 4 (TypeScript, Tailwind v4, @nuxt/ui v4) — `axelnova.tech` and `dashboard.axelnova.tech`
- **Backend**: Laravel 11 API (Phase 3: public quote builder funnel)
- **Database**: shared MySQL 8 from [axelnova-infra](../axelnova-infra/)

For full architecture, see [ARCHITECTURE.md](./ARCHITECTURE.md). For pricing config / quote builder details, see [QUOTE_BUILDER.md](./QUOTE_BUILDER.md).

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
  ARCHITECTURE.md
  QUOTE_BUILDER.md
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
