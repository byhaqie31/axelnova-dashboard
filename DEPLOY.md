# Deploy & Ops — axelnova-dashboard

Production runs at **https://axelnova.tech**. Stack lives on a Hostinger VPS (`vps` SSH alias) — backend, frontend, and queue worker in Docker via [docker-compose.prod.yml](./docker-compose.prod.yml); fronted by system **nginx** which terminates TLS and routes traffic.

## Daily flow

```bash
git checkout -b some-fix
# ... commit changes ...
git push -u origin some-fix
# Open PR on GitHub → merge to main → CI auto-deploys
```

That's it. No SSH needed for deploys. Watch runs at: https://github.com/byhaqie31/axelnova-dashboard/actions

The PR triggers a build-check workflow first (builds both images to catch Dockerfile/source breakage). Merge to `main` triggers the deploy workflow which SSHes the VPS and rolls out.

## What the deploy workflow does

[.github/workflows/deploy.yml](./.github/workflows/deploy.yml) on every push to `main`:

1. SSH into VPS using `VPS_HOST` / `VPS_USER` / `VPS_SSH_KEY` repo secrets
2. `git reset --hard origin/main`
3. `docker compose -f docker-compose.prod.yml up -d --build` (incremental, layer-cached)
4. Wait for entrypoint config:cache
5. `php artisan migrate --force`
6. `php artisan queue:restart` (reloads job code in worker)
7. Health-check `/up`
8. Prune dangling images

Total: ~30s when only source changes; ~2 min for a full rebuild (PHP extension recompile or `npm ci` invalidation).

## Architecture

| Component | Where | Purpose |
|---|---|---|
| nginx (system) | `/etc/nginx/sites-available/axelnova.tech` | TLS terminator + reverse proxy. Routes `/api/*` → `127.0.0.1:8003`, everything else → `127.0.0.1:3003` |
| Frontend (Nuxt 4 SSR) | `axelnova-frontend` container | Built from [frontend/Dockerfile](./frontend/Dockerfile), port 3000 → host 3003 |
| Backend (Laravel 11) | `axelnova-backend` container | nginx + php-fpm via supervisord, built from [backend/Dockerfile](./backend/Dockerfile), port 8003 |
| Queue worker | `axelnova-queue` container | Same image as backend; runs `php artisan queue:work`. Healthcheck disabled (no HTTP server) |
| MySQL | `axelnova-mysql` (shared infra at `~/infra/`) | Shared with portfolio-v2; reachable as `mysql:3306` from app containers via `axelnova-shared` Docker network |
| TLS cert | `/etc/letsencrypt/live/axelnova.tech/` | Let's Encrypt, auto-renewed via certbot timer |

## Common ops

```bash
ssh vps
cd ~/axelnova-dashboard

# Tail Laravel log (host file — bind-mounted from container)
tail -f ~/data/axelnova-dashboard/storage/logs/laravel.log

# All container logs
docker compose -f docker-compose.prod.yml logs -f

# One service
docker compose -f docker-compose.prod.yml logs -f backend

# Tinker / artisan inside backend
docker compose -f docker-compose.prod.yml exec backend php artisan tinker

# Restart queue worker (after code changes touching jobs)
docker compose -f docker-compose.prod.yml exec backend php artisan queue:restart

# Failed jobs
docker compose -f docker-compose.prod.yml exec backend php artisan queue:failed
docker compose -f docker-compose.prod.yml exec backend php artisan queue:retry all

# DB shell as the dashboard user (password from VPS-only env)
DB_PW=$(grep ^DB_PASSWORD= backend/.env.production | cut -d= -f2-)
docker exec -it axelnova-mysql mysql -uaxelnova_dashboard_user -p"$DB_PW" axelnova_dashboard_db
```

## Environment variables

`.env.production` files are gitignored (VPS-only). On a fresh VPS install or env change:

```bash
cp backend/.env.production.example backend/.env.production
cp frontend/.env.production.example frontend/.env.production
# edit each, replace <FILL_IN> values
chmod 600 backend/.env.production frontend/.env.production
docker compose -f docker-compose.prod.yml up -d --force-recreate
```

Required values to fill on first setup:
- **Backend**: `APP_KEY` (`base64:` + base64-encoded 32 random bytes), `DB_PASSWORD`, `MAIL_PASSWORD`, optional `TURNSTILE_SECRET` + `TURNSTILE_SITE_KEY`
- **Frontend**: optional `NUXT_PUBLIC_TURNSTILE_SITE_KEY` (must match backend's `TURNSTILE_SITE_KEY`)

`SANCTUM_STATEFUL_DOMAINS` doesn't need to be set — the Sanctum stateful middleware is route-scoped to admin endpoints only ([backend/routes/api.php](./backend/routes/api.php)). Public POSTs (the quote form) don't trigger CSRF protection.

## Manual deploy (CI fallback)

If GitHub Actions is down or you need to push without going through CI:

```bash
ssh vps
cd ~/axelnova-dashboard
git pull origin main
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec backend php artisan migrate --force
docker compose -f docker-compose.prod.yml exec backend php artisan queue:restart
```

This is exactly what the workflow script does — same commands.

## Rollback

```bash
ssh vps
cd ~/axelnova-dashboard
git log --oneline -5             # find last good commit
git checkout <sha>               # detached HEAD on the prior commit
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec backend php artisan queue:restart
```

After rollback, the next merge to `main` will redeploy whatever's on `main` — including the bad commit if you haven't reverted it. To stay rolled back, open a PR that reverts the bad commit and merge that.

DB migrations don't auto-rollback. If a bad migration is the cause:
1. Pin to prior SHA (above)
2. `php artisan migrate:rollback --step=N` to undo the offending migration(s)

## Storage and backups

`~/data/axelnova-dashboard/storage` on VPS — bind-mounted into backend + queue containers at `/app/storage`. Persists across deploys. Holds Laravel logs and any uploaded files.

Backups are not yet automated. Manual backup:

```bash
# From your laptop
rsync -av byhaqie31@187.77.151.66:~/data/axelnova-dashboard/storage/ ~/backups/axelnova-storage/
```

For database, use `axelnova-infra/scripts/` patterns or `mysqldump` directly via the shared MySQL container:

```bash
ssh vps
DB_PW=$(grep ^DB_PASSWORD= ~/axelnova-dashboard/backend/.env.production | cut -d= -f2-)
docker exec axelnova-mysql mysqldump -uaxelnova_dashboard_user -p"$DB_PW" axelnova_dashboard_db \
    > ~/backups/axelnova_dashboard_db_$(date +%Y%m%d_%H%M%S).sql
```

## Things to know

- `TrustProxies` is wired so Laravel respects `X-Forwarded-*` headers from nginx — correct client IP for per-IP rate limiting and HTTPS-aware redirect URLs
- Quote-form throttle is env-aware: 3/hour in production (spam protection), 1000/min in non-production (dev/staging testing)
- Sanctum stateful middleware only runs on admin routes; public endpoints are pure stateless POSTs
- Turnstile widget is conditional — empty `NUXT_PUBLIC_TURNSTILE_SITE_KEY` skips the widget AND the verification call entirely (frontend sends `dev-bypass` token, backend accepts when `TURNSTILE_SECRET` is empty)
- Branch protection on `main` means no direct pushes — every change goes through a PR
