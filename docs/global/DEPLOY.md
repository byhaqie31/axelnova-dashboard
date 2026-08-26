# Deploy & Ops — axelnova-dashboard

Production runs at **https://axelnovaventures.com**. Stack lives on a Hostinger VPS (`vps` SSH alias) — backend, frontend, and queue worker in Docker via [docker-compose.prod.yml](./docker-compose.prod.yml). Public traffic arrives over a **Cloudflare Tunnel** (`cloudflared`) which hands off to system **nginx** on loopback; nginx still terminates TLS and routes to the containers. See [Edge & tunnel](#edge--tunnel) — the tunnel is not optional, the direct origin path is broken.

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
| Cloudflare Tunnel | `cloudflared` systemd service, config `/etc/cloudflared/config.yml` | **Public entry point.** Dials out to Cloudflare and holds 4 QUIC connections; every hostname maps to `https://127.0.0.1:443` (`noTLSVerify`, loopback hop). Tunnel `axelnova`, id `db431d01-ce3e-4155-b929-46ea2e3bac73` |
| nginx (system) | `/etc/nginx/sites-available/axelnovaventures.com` | TLS terminator + reverse proxy, now reached from loopback by `cloudflared`. Routes `/api/*` → `127.0.0.1:8003`, everything else → `127.0.0.1:3003` |
| Frontend (Nuxt 4 SSR) | `axelnova-frontend` container | Built from [frontend/Dockerfile](./frontend/Dockerfile), port 3000 → host 3003 |
| Backend (Laravel 11) | `axelnova-backend` container | nginx + php-fpm via supervisord, built from [backend/Dockerfile](./backend/Dockerfile), port 8003 |
| Queue worker | `axelnova-queue` container | Same image as backend; runs `php artisan queue:work`. Healthcheck disabled (no HTTP server) |
| MySQL | `axelnova-mysql` (shared infra at `~/infra/`) | Shared with portfolio-v2; reachable as `mysql:3306` from app containers via `axelnova-shared` Docker network |
| TLS cert | `/etc/letsencrypt/live/axelnovaventures.com/` | Let's Encrypt (ECDSA), auto-renewed via certbot timer. Covers `axelnovaventures.com`, `www.axelnovaventures.com`, `admin.axelnovaventures.com`; expires 2026-11-24. HTTP-01 renewal through the tunnel is verified working — see [ACME through the tunnel](#acme-through-the-tunnel) |

## Edge & tunnel

**Why this exists.** On 2026-08-26 the site was ~65% unreachable for cold visitors (6–23s TTFB or outright timeouts). `tcpdump` on the VPS showed Cloudflare's SYN arriving, the origin answering SYN-ACK in 0.1 ms, and Cloudflare never receiving it — **outbound packets from this VPS to Cloudflare's IP ranges are dropped by Hostinger's network.** Kernel counters at the time: `TCPSynRetrans` 4.82M, `TCPTimeouts` 5.80M. The apps, host, nginx and DNS were all verified healthy; origin served in 40 ms locally and 140 ms when reached directly.

A Cloudflare Tunnel fixes it by inverting the direction: the VPS dials **out** and holds the connection, so there are no inbound handshakes left to drop. Measured immediately after cutover: **0.17–0.26s, 0% failure**, versus 1.5–6s with a third timing out.

```
Visitor → Cloudflare edge → [tunnel, outbound] → cloudflared → nginx :443 → containers
```

DNS for `axelnovaventures.com`, `www`, and `admin` are CNAMEs to the tunnel — there is no longer an `A` record pointing at the origin IP.

### ACME through the tunnel

HTTP-01 validation works through the tunnel, but **only for hostnames nginx actually recognises**. A hostname served via a tunnel `httpHostHeader` rewrite will fail with a 404 on the challenge path, because certbot's temporary server block never matches the rewritten Host.

`www` hit exactly this: it originally rode the apex vhost via `httpHostHeader`, so `certbot --nginx` could not authenticate it. The fix was to make it real rather than disguised — add `www.axelnovaventures.com` to `server_name` in the vhost, drop the `httpHostHeader` line from the tunnel ingress, then re-run certbot. Keep it that way: if you add a hostname to the tunnel, give it a genuine `server_name` in nginx or its certificate will never renew.

```bash
sudo systemctl status cloudflared        # should be active (running)
cloudflared tunnel info axelnova         # expect 4 connections (kul/sin POPs)
sudo journalctl -u cloudflared -n 50     # connection churn / errors
sudo systemctl restart cloudflared
```

**⚠️ Real client IP.** `cloudflared` connects to nginx from `127.0.0.1`, so loopback **must** be trusted or every visitor logs as `127.0.0.1` — which silently collapses Laravel's per-IP throttles into one global bucket (the quote form's 8/hour/IP would become 8/hour for the whole site). `/etc/nginx/conf.d/cloudflare-realip.conf` therefore carries, alongside the Cloudflare ranges:

```nginx
set_real_ip_from 127.0.0.1;
set_real_ip_from ::1;
real_ip_header CF-Connecting-IP;
```

Verify after any nginx or tunnel change by hitting the site and confirming `/var/log/nginx/access.log` shows a real client IP, not `127.0.0.1`.

**Rollback.** Delete the CNAMEs in the Cloudflare dashboard and re-add `A @ 187.77.151.66 Proxied` (plus `A admin`). Nothing on the VPS needs changing — nginx, Docker and the certs were never touched by the cutover. Expect the original packet loss to return.

**Still open.** The Hostinger network fault is *routed around*, not fixed — a support ticket with the tcpdump evidence is warranted. Other sites on the same box (roofly, hop, portfolio, axelnova.tech) remain on the broken path and can be added as extra `ingress` entries on the same tunnel.

## Page caching

Public marketing routes carry `swr` route rules in [frontend/nuxt.config.ts](./frontend/nuxt.config.ts): `/`, `/about`, `/company`, `/contact`, `/services{,/**}`, `/projects{,/**}` at 300s, `/legal/**` at 3600s. This matters most for the homepage, which `await`s the projects API during SSR — without it every cold visitor waits on the backend before seeing anything.

The emitted `Cache-Control: s-maxage=…, stale-while-revalidate` also lets Cloudflare cache the HTML at the edge, so cold arrivals are served from a nearby POP without touching the origin.

**Authenticated and per-recipient routes are deliberately excluded and must stay that way** — `/admin`, `/portal`, `/team`, `/partners`, `/quote/**`, `/feedback/**`, `/proposals/**`. Caching any of them would serve one visitor's page to another. Verify with `curl -I` that those return no `s-maxage`.

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
- **Backend**: `APP_KEY` (`base64:` + base64-encoded 32 random bytes), `DB_PASSWORD`, `MAIL_PASSWORD`

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

- `TrustProxies` is wired so Laravel respects `X-Forwarded-*` headers from nginx — correct client IP for per-IP rate limiting and HTTPS-aware redirect URLs. This depends on nginx resolving the real IP first; see the real-client-IP warning under [Edge & tunnel](#edge--tunnel)
- Public-form throttles are env-aware: in production quotes/referrals 8/hour/IP, inquiries 20/hour/IP (spam protection); 1000/min in non-production (dev/staging testing)
- Sanctum stateful middleware only runs on admin routes; public endpoints are pure stateless POSTs
- Branch protection on `main` means no direct pushes — every change goes through a PR
