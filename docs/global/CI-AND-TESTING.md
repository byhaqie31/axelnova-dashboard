# CI & Testing

Quality and security gates for the monorepo. Two PR workflows plus the deploy pipeline:

| Workflow | Trigger | What it proves |
|---|---|---|
| [ci.yml](../../.github/workflows/ci.yml) | PR → main | Code quality + security: backend Pint/PHPUnit/composer-audit, frontend ESLint/vue-tsc/vitest/npm-audit |
| [build-check.yml](../../.github/workflows/build-check.yml) | PR → main | Both Docker images still build |
| [deploy.yml](../../.github/workflows/deploy.yml) | merge → main | Ships to the VPS (see [DEPLOY.md](./DEPLOY.md)) |

## Backend tests (PHPUnit)

Tests live in `backend/tests/` and run against **MySQL, never SQLite** — the migration
history uses MySQL-dialect SQL (`ENUM` modifications, `SET FOREIGN_KEY_CHECKS`,
`INTERVAL` arithmetic). [phpunit.xml](../../backend/phpunit.xml) pins
`DB_DATABASE=axelnova_dashboard_test` with `force="true"` so a test run can never
touch the dev/prod database. `RefreshDatabase` re-migrates once per run, then wraps
each test in a transaction.

```bash
# Run the whole suite (inside the backend container)
docker compose -f docker-compose.dev.yml exec backend vendor/bin/phpunit

# One suite / one test
docker compose -f docker-compose.dev.yml exec backend vendor/bin/phpunit tests/Feature/Auth
docker compose -f docker-compose.dev.yml exec backend vendor/bin/phpunit --filter=test_a_refund_subtracts
```

First-time setup: the test database must exist. It's created once via MySQL root
(the app user can't `CREATE DATABASE`):

```bash
# In axelnova-infra's mysql container
CREATE DATABASE IF NOT EXISTS axelnova_dashboard_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON axelnova_dashboard_test.* TO 'axelnova_dashboard_user'@'%';
```

In CI the `mysql:8.0` service container provides the same database; credentials come
from exported env vars (phpunit.xml carries a test-only `APP_KEY`, so no `.env` is needed).

### What the suites pin down

- `tests/Feature/Auth/` — logins per surface, role gating, **cross-surface token
  isolation** (a team token must 403 on `/v1/admin/*` even for a founder — enforced by
  the `abilities:` middleware), and the `POST /v1/admin/team-session` exchange.
- `tests/Feature/Support/ReferenceCodeGeneratorTest.php` — AXN code format, per-type
  yearly counters, soft-delete sequence safety.
- `tests/Feature/Payments/PaymentObserverTest.php` — the ledger contract: signed sums,
  refunds as negative child rows, caches clamped at zero, void invoices never auto-flip.
- `tests/Feature/Pricing/PricingEngineTest.php` — the pricing formula. **The TS port
  (`frontend/app/utils/pricing.ts`, parity tests in `pricing.test.ts`) must produce the
  same numbers** — when a test here changes, the port changes with it.
- `tests/Feature/Security/` — CORS allowlist (never `*`), baseline security headers,
  JSON 401s on api/* (no web-login redirect).

## Backend style — Pint

`vendor/bin/pint --test` gates CI; run `vendor/bin/pint` (fix mode) before pushing.
The whole codebase is Pint-clean as of the CI introduction.

## Frontend checks

```bash
docker compose -f docker-compose.dev.yml exec frontend npm run lint       # eslint (flat config, @nuxt/eslint)
docker compose -f docker-compose.dev.yml exec frontend npm run lint:fix
docker compose -f docker-compose.dev.yml exec frontend npm run typecheck  # vue-tsc via `nuxt typecheck`
docker compose -f docker-compose.dev.yml exec frontend npm test           # vitest — PDF renderer unit tests
```

**Renderer tests (vitest).** [vitest.config.ts](../../frontend/vitest.config.ts) runs
`server/**/*.test.ts` in plain node (no Nuxt runtime) — today that is the PDF
document renderer in `server/utils/pdf/`: locale-string coverage (every key in
both `en` and `bm`, no blanks, matching placeholders), schedule / header date
formatting per locale, and snapshots of the Payment plan partial for each
plan × locale (`__snapshots__/` next to the tests — commit them; CI runs with
`CI=true`, where a missing snapshot fails). After an intentional markup change,
`npx vitest run -u` refreshes them — review the diff.

Lint policy ([eslint.config.mjs](../../frontend/eslint.config.mjs)): errors gate CI;
`@typescript-eslint/no-explicit-any` is demoted to a warning (~80 pre-existing `any`s
to burn down), and `vue/no-mutating-props` runs with `shallowOnly` because the quote
builder deliberately shares one reactive `state` object with `QuoteScopeFields`.
`@nuxt/eslint-config` ignores every folder named `public` (`**/public`, meant for the
static dir), which silently hid `app/pages/public/` and `app/components/public/` — the
whole public site — until 2026-10-12; an `onResolved` hook narrows it to the root
`public/` only. A `v-html` must carry
`<!-- eslint-disable-next-line vue/no-v-html -- <where it is sanitised> -->` on the line
above (keep the element on one line so the directive reaches the attribute).

Gotcha: `docker-compose.dev.yml` bind-mounts `package.json` / `eslint.config.mjs` as
single files — after editing them on the host, `docker compose up -d --force-recreate frontend`
(a plain restart keeps the stale inode).

## Dependency audits

- **Backend**: `composer audit` fails CI on any new advisory. Four Laravel 11
  framework advisories that are only fixed in **Laravel 12** are acknowledged in
  `composer.json` → `config.audit.ignore`, each with a re-check note. Remove those
  entries when the L12 upgrade lands. Bump a transitive package on its own
  (`composer update symfony/http-foundation symfony/yaml`, 2026-10-21) and run the
  suite afterwards — a patch release can still change behaviour: http-foundation
  7.4.21 made `IpUtils::isPrivateIp()` treat the RFC 5737 documentation ranges as
  non-public, which flipped the `api` rate limiter's bucket for the test IPs, so
  tests now use real routable addresses (8.8.8.8, 1.1.1.1) for "public" traffic.
- **Frontend**: `npx audit-ci --config audit-ci.jsonc` (a devDependency wrapping
  `npm audit`) — production dependencies only (`skip-dev`), high severity and above.
  npm itself has no way to acknowledge an advisory, so advisories **with no patched
  release** are allowlisted by GHSA id in
  [frontend/audit-ci.jsonc](../../frontend/audit-ci.jsonc), each with the reason it
  is acceptable and the condition for removing it. Current entries (2026-10): `braces`
  and `node-forge` (no fixed version published) and the `simple-git` 3.36 family
  (dev-only, via `@nuxt/devtools` 3.x; fixed in simple-git 4, which needs devtools 4).
  audit-ci prints a warning for an allowlisted id it no longer finds — delete the
  entry then. **A fixable advisory is never allowlisted**: bump the one package
  (`npm update <pkg>` for a transitive one), as with vue / shell-quote / seroval /
  source-map-js in Oct 2026. Run it locally the same way:
  `docker compose -f docker-compose.dev.yml exec frontend npx audit-ci --config audit-ci.jsonc`.
  Two gotchas from the vite / launch-editor bump (2026-10-21): (1) npm may pin an
  advisory to the package that *bundles* the vulnerable one (vite 8.2.0–8.2.2 for
  the launch-editor advisories), so the bundler is what has to move — `npm audit`
  names it. (2) npm 10.9's `npm update <pkg> --package-lock-only` can crash with
  `Cannot read properties of null (reading 'edgesOut')` (an arborist bug in its
  peer-set loader). Don't reach for `--legacy-peer-deps` — it silently drops
  peer-installed entries and rewrites flags on dozens of others. Run the same
  command through `npx -y npm@12` instead, then diff the lock package-by-package:
  npm 12 prunes nested *optional* peers that npm 10 still expects (it removed
  `node_modules/devframe/node_modules/crossws`, and `npm ci` on npm 10 — which CI
  uses — then failed with `Missing: crossws from lock file`). Restore that entry
  from the previous lock and `npm ci` is clean again.

### The `nostics` override — do not remove without testing SSR

`package.json` pins `overrides: { "nostics": "^1.2.0" }`. Without it, the dev-only
`devframe` (pulled in by `@nuxt/devtools`) requires `nostics@^0.2.0`, which npm hoists
to the top-level `node_modules/nostics` while Nuxt keeps `1.2.0` nested. Nitro treats
`nostics` as an external and copies the **hoisted** copy into
`.output/server/node_modules/`, so the built server imports `createConsoleReporter`
from a 0.2.0 that doesn't export it — every SSR request then dies with a 500
(`SyntaxError: The requested module 'nostics' does not provide an export named
'createConsoleReporter'`). This took production down on 2026-08-12.

CI never catches this: `nuxt build` succeeds, and the failure only appears when the
built server handles a request. If you touch this override, verify by **running the
production image**, not just building it:

```bash
docker build -t axelnova-frontend-check frontend/
docker run -d --name fe-check -p 127.0.0.1:3998:3000 -e NODE_ENV=production axelnova-frontend-check
curl -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3998/   # must be 200, not 500
```
