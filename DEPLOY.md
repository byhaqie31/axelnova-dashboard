# Production deploy — axelnova-dashboard monorepo

One-time runbook for cutting over from the legacy `~/axelnova-dashboard/` standalone Nuxt site (port 3001) to the Phase 3 monorepo (backend + frontend + queue).

> **Heads-up:** the dashboard's GitHub Actions deploy workflow is still wired up for the legacy single-image build. Do NOT `git push origin main` of the monorepo branch until the workflow is updated, or CI will run and fail/overwrite. This runbook deploys directly from the VPS via `docker compose --build` — no CI involved.

---

## 0. Before you start — gather these secrets

| Secret | Where it goes | How to get it |
|---|---|---|
| New DB password for `axelnova_dashboard_user` | `backend/.env.production` + MySQL `ALTER USER` | Generate fresh: `openssl rand -base64 24` |
| Hostinger mailbox password (`no-reply@axelnova.tech`) | `backend/.env.production` `MAIL_PASSWORD` | Hostinger panel → Emails → mailbox settings |
| Cloudflare Turnstile site key + secret (production) | Both env files | Cloudflare dashboard → Turnstile → Add widget for `dashboard.axelnova.tech` |
| MySQL root password | One-time, for the `ALTER USER` step | Already on VPS in `~/infra/.env` (`MYSQL_ROOT_PASSWORD`) |

Have these in a password manager / scratchpad **before** SSH-ing in.

---

## 1. Pre-flight on VPS

```bash
ssh vps

# Shared infra up?
docker ps --format '{{.Names}}\t{{.Status}}' | grep -E 'mysql|phpmyadmin'

# Shared network exists?
docker network inspect axelnova-shared > /dev/null && echo OK || echo "MISSING — bring up ~/infra first"

# Legacy site running? (sanity check before we replace it)
docker ps --filter name=axelnova-dashboard --format '{{.Names}}\t{{.Status}}'
```

---

## 2. Park the legacy site

```bash
cd ~/axelnova-dashboard
docker compose down
cd ~
mv axelnova-dashboard axelnova-dashboard-legacy
```

Caddy is still proxying to port 3001 → site now returns 502. **Downtime starts here** until step 10 (Caddy reload).

---

## 3. Get the new monorepo onto the VPS

Two options — pick one:

**A. Clone from GitHub (if the monorepo branch is pushed):**
```bash
cd ~
git clone -b <branch-name> git@github.com:byhaqie31/axelnova-dashboard.git
```

**B. rsync from your laptop (if not pushed yet):**
```bash
# on laptop:
rsync -av --delete \
    --exclude='node_modules' --exclude='vendor' --exclude='.env' --exclude='.env.production' \
    --exclude='backend/storage/logs/*' --exclude='backend/storage/framework' \
    --exclude='frontend/.nuxt' --exclude='frontend/.output' \
    /Users/BHQIMBP14/Developer/axelnova-dashboard/ vps:~/axelnova-dashboard/
```

Then on VPS:
```bash
cd ~/axelnova-dashboard
ls docker-compose.prod.yml backend/Dockerfile frontend/Dockerfile  # all three should exist
```

---

## 4. Create the host storage dir

```bash
mkdir -p ~/data/axelnova-dashboard/storage
```

The compose file bind-mounts this into both `backend` and `queue` containers at `/app/storage`.

---

## 5. Rotate the dashboard DB user password

```bash
NEW_DB_PW=$(openssl rand -base64 24)
echo "Save this in your password manager: $NEW_DB_PW"

MYSQL_ROOT_PW=$(grep MYSQL_ROOT_PASSWORD ~/infra/.env | cut -d= -f2-)
MYSQL_CT=$(docker ps --filter name=mysql --format '{{.Names}}' | head -1)

docker exec "$MYSQL_CT" mysql -uroot -p"$MYSQL_ROOT_PW" -e "
    ALTER USER 'axelnova_dashboard_user'@'%' IDENTIFIED BY '$NEW_DB_PW';
    FLUSH PRIVILEGES;"

# Update VPS-side init-databases.sql so a future volume re-init keeps the same password.
# (The committed repo keeps the placeholder — only the VPS copy holds the real value.)
sed -i.bak "s/axelnova_dashboard_local_pw/$NEW_DB_PW/" ~/infra/scripts/init-databases.sql
grep axelnova_dashboard_user ~/infra/scripts/init-databases.sql   # confirm the sub took
```

---

## 6. Fill in the env files

```bash
cd ~/axelnova-dashboard

# Backend
cp backend/.env.production.example backend/.env.production

# Generate APP_KEY using a one-shot container
docker build -t axelnova-backend:latest ./backend
APP_KEY=$(docker run --rm axelnova-backend:latest php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;")
echo "APP_KEY=$APP_KEY"

# Edit backend/.env.production and replace each <FILL_IN>:
#   APP_KEY            → value just generated
#   DB_PASSWORD        → $NEW_DB_PW from step 5
#   MAIL_PASSWORD      → Hostinger mailbox password
#   TURNSTILE_SECRET   → Cloudflare Turnstile secret
#   TURNSTILE_SITE_KEY → Cloudflare Turnstile site key
nano backend/.env.production

# Frontend
cp frontend/.env.production.example frontend/.env.production

# Edit frontend/.env.production:
#   NUXT_PUBLIC_TURNSTILE_SITE_KEY → same value as TURNSTILE_SITE_KEY above
nano frontend/.env.production
```

Sanity check that no `<FILL_IN>` markers remain:
```bash
grep -n FILL_IN backend/.env.production frontend/.env.production && echo "STILL HAS PLACEHOLDERS — fix before continuing" || echo "OK"
```

---

## 7. Build and start the stack

```bash
cd ~/axelnova-dashboard
docker compose -f docker-compose.prod.yml up -d --build
```

First boot: ~3 min (compiles PHP extensions, builds Nuxt SSR bundle). Subsequent rebuilds ~30s thanks to layer cache.

Watch it come up:
```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs --tail 50
```

All three (`axelnova-backend`, `axelnova-queue`, `axelnova-frontend`) should be `Up (healthy)` within ~30s of the build finishing.

---

## 8. Run migrations (and seed pricing config — first deploy only)

```bash
docker compose -f docker-compose.prod.yml exec backend php artisan migrate --force

# First deploy only — populates active pricing config row:
docker compose -f docker-compose.prod.yml exec backend php artisan db:seed --force --class=PricingConfigSeeder
```

Confirm:
```bash
docker compose -f docker-compose.prod.yml exec backend php artisan tinker --execute="echo \App\Models\PricingConfig::active()?->version;"
# Expected: 2026.05.01
```

---

## 9. Internal health check (still on VPS, pre-Caddy)

```bash
curl -fsS http://127.0.0.1:8003/up
# {"status":"ok","timestamp":"..."}

curl -fsSI http://127.0.0.1:3003/ | head -1
# HTTP/1.1 200 OK
```

If either fails: `docker compose -f docker-compose.prod.yml logs --tail 200 backend frontend queue`. Don't proceed to Caddy until both pass.

---

## 10. Update the Caddy reverse proxy

Edit `/etc/caddy/Caddyfile` — replace the existing `dashboard.axelnova.tech` block with:

```caddy
dashboard.axelnova.tech {
    encode gzip

    # Laravel API — no path strip (routes/api.php is already mounted under /api/*)
    @api path /api/*
    handle @api {
        reverse_proxy 127.0.0.1:8003
    }

    # Everything else → Nuxt SSR frontend
    handle {
        reverse_proxy 127.0.0.1:3003
    }
}
```

Validate + reload:
```bash
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

**Downtime ends here.**

---

## 11. External smoke test (from your laptop)

```bash
curl -fsSI https://dashboard.axelnova.tech/ | head -3
# HTTP/2 200 — served by Nuxt

curl -fsS https://dashboard.axelnova.tech/api/services
# JSON response from Laravel (real endpoints from routes/api.php)
```

Then in a browser: open `https://dashboard.axelnova.tech/quote`, submit a real quote. Verify:

```bash
# On VPS — confirm the quote landed
docker compose -f docker-compose.prod.yml exec backend php artisan tinker --execute="echo \App\Models\QuoteRequest::latest()->first()?->reference_code;"

# Confirm queue processed the email job
docker compose -f docker-compose.prod.yml logs --tail 50 queue | grep -i 'processed\|fail'
```

Email should arrive at `baihaqie@axelnova.tech` (admin) and the customer email submitted in the form.

---

## 12. Decommission the legacy site

After **24–48h with no issues**, fully retire the old setup:

```bash
cd ~/axelnova-dashboard-legacy
docker compose down --rmi all
cd ~
rm -rf axelnova-dashboard-legacy
```

Optionally also delete the now-unused legacy image from GHCR (Cloudflare dashboard → Container registry → `axelnova-dashboard:latest`).

---

## Subsequent deploys

```bash
ssh vps
cd ~/axelnova-dashboard
git pull   # or rsync from laptop
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec backend php artisan migrate --force
docker compose -f docker-compose.prod.yml exec backend php artisan queue:restart
```

The `queue:restart` is required after any code change touching jobs — the worker container caches code in memory between jobs.

---

## Common ops

```bash
# Live Laravel log
tail -f ~/data/axelnova-dashboard/storage/logs/laravel.log

# Container logs (all services)
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
```

---

## Rollback

If a deploy breaks prod:

```bash
cd ~/axelnova-dashboard
git log --oneline -5
git checkout <last-good-sha>
docker compose -f docker-compose.prod.yml up -d --build
```

DB migrations don't auto-rollback. If the bad change is a migration: pin to the prior SHA, then manually `php artisan migrate:rollback --step=N` to undo the offending migration(s).
