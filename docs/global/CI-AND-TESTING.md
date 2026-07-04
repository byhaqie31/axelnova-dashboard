# CI & Testing

Quality and security gates for the monorepo. Two PR workflows plus the deploy pipeline:

| Workflow | Trigger | What it proves |
|---|---|---|
| [ci.yml](../../.github/workflows/ci.yml) | PR → main | Code quality + security: backend Pint/PHPUnit/composer-audit, frontend ESLint/vue-tsc/npm-audit |
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
  (`frontend/app/composables/usePricingEngine.ts`) must produce the same numbers** —
  when a test here changes, the port changes with it.
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
```

Lint policy ([eslint.config.mjs](../../frontend/eslint.config.mjs)): errors gate CI;
`@typescript-eslint/no-explicit-any` is demoted to a warning (~80 pre-existing `any`s
to burn down), and `vue/no-mutating-props` runs with `shallowOnly` because the quote
builder deliberately shares one reactive `state` object with `QuoteScopeFields`.

Gotcha: `docker-compose.dev.yml` bind-mounts `package.json` / `eslint.config.mjs` as
single files — after editing them on the host, `docker compose up -d --force-recreate frontend`
(a plain restart keeps the stale inode).

## Dependency audits

- **Backend**: `composer audit` fails CI on any new advisory. Three Laravel 11
  framework advisories that are only fixed in **Laravel 12** are acknowledged in
  `composer.json` → `config.audit.ignore`, each with a re-check note. Remove those
  entries when the L12 upgrade lands.
- **Frontend**: `npm audit --omit=dev --audit-level=high` — production dependencies
  only, high severity and above (dev-tool advisories don't ship to users).
