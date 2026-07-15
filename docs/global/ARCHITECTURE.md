# Axel Nova Platform — Architecture

## Repository structure

```
axelnova-dashboard/
  frontend/          Nuxt 4 portfolio + quote builder frontend
  backend/           Laravel 11 API
  docs/
    global/          ARCHITECTURE.md (this file), DEPLOY.md, QUOTE_BUILDER.md, DOCUMENT-GENERATION.md, README.md
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
| Auth | Laravel Sanctum bearer tokens — three isolated user surfaces (admin cockpit / team workspace / partner portal), each minting tokens with its own ability (`cockpit` / `workspace` / `partner`) enforced by the `abilities:` middleware, plus a machine-only **MCP connector** surface (`connector:read`/`connector:draft` — see [MCP-CONNECTOR.md](./MCP-CONNECTOR.md)). Admin → Team jump via `POST /v1/admin/team-session` token exchange |

## Database tables

### Core (Phase 3)

| Table | Purpose |
|-------|---------|
| `pricing_configs` | Versioned pricing formula JSON — only one row `active=true` at a time |
| `quote_requests` | Lead submissions from the public quote builder |
| `quote_request_addons` | Denormalised add-ons selected per quote request |
| `users` | Team accounts (authenticated via Sanctum). `role` = founder/marketer/engineer (COCKPIT_ROLES/WORKSPACE_ROLES on the model); `availability` (Task 4, self-service, available\|busy); `monthly_allowance_myr` (Task 7, nullable, snapshotted onto payslips); `deactivated_at` (Task 8 — nullable persistent lockout, set/cleared from `/admin/users`, checked at login on both `/v1/admin` and `/v1/team`) |

### Planned (Phase 4+)

| Table | Purpose |
|-------|---------|
| `clients` | Converted leads become clients |
| `quotations` | Formal quotations generated from leads |
| `quotation_line_items` | Line items from the pricing breakdown |
| `orders` | Post-acceptance engagements (one per converted quotation) |
| `documents` | Issued invoices & receipts — frozen `DocumentData` snapshots, rendered on demand. See [DOCUMENT-GENERATION.md](./DOCUMENT-GENERATION.md) |
| `projects` | Active project tracking |

### Analytics (Phase B — see [ANALYTICS.md](./ANALYTICS.md))

| Table | Purpose |
|-------|---------|
| `page_views` | Append-only public page-view log (hashed IP, path, referrer, UA). Bots dropped on write |
| `entity_likes` | Anonymous likes per entity (`project` / `service_package`), deduped per browser by `cookie_id` (hashed IP recorded for abuse/analytics) |

### Payments ledger (see [PAYMENTS-LEDGER.md](./PAYMENTS-LEDGER.md))

| Table | Purpose |
|-------|---------|
| `payments` | The money ledger — one signed row per movement (refunds are negative rows). Single source of truth; order/invoice paid caches derive from it via `PaymentObserver` |
| `gateway_events` | Raw inbound webhook log + idempotency gate for the Billplz/Stripe phases (empty until then) |
| `receipts.payment_id` | Receipts now anchor to the payment that produced them (1 payment : 1 receipt) |

### Team workspace (portal restructure, Task 5/6/7)

| Table | Purpose |
|-------|---------|
| `tasks` | The tasks engine — delegated work with an optional extra-pay bonus. `assignee_id` null = the pick-up pool; status spine `open → in_progress → completed \| payment_pending → paid` (completing with `pay_amount_myr` set forks to `payment_pending` automatically; admin mark-paid OR payslip settlement writes `paid`). `payroll_entry_id` (nullable FK, nullOnDelete) stamps which payslip settles the extra — the per-task double-count guard: generation only picks up `payment_pending` + unlinked tasks, and ad-hoc mark-paid rejects a linked task (422). `notes` is the append-only timestamped team log. Soft-deletes. The team Calendar is a view over this table (deadline + completed_at) — no table of its own |
| `payroll_entries` | The payslip ledger (Task 7). Two `kind`s share the table: `monthly` (the recurring run) and `one_time` (an ad-hoc bonus / payout). Itemised as `allowance_snapshot_myr` (the member's `users.monthly_allowance_myr` FROZEN at generation; null = none on file, distinct from 0; monthly only) + `task_extras_myr` (Σ of the linked pending task bonuses) + `discretionary_myr` (the manual one-off amount; one_time only), with `gross_myr` kept as the TOTAL so legacy consumers stay valid. **The per-period double-count guard is monthly-scoped** — `UNIQUE (user_id, monthly_period)` where `monthly_period` is a generated column = `period_label` for monthly rows, NULL for one-offs (so several one-offs can share a month; monthly stays one-per-period). One-offs still carry a YYYY-MM `period_label` (the payment's month) so year-to-date rollups bucket them; the UI labels them by `one_time_type` (signing/festive/performance/spot/other). `paid_at` is the sole settlement marker (no status column); settling flips the linked task extras to `paid`. Pre-Task-7 rows carry a hand-entered gross with null snapshot / 0 extras — the UI reads them as `legacy` (gated on `monthly` so a discretionary one-off is never misflagged) and renders gross-only. **The settled payslip IS the team-comp expense record** — there is no general finance/expenses/P&L module in this repo (only `marketing_expenses` + this table; `payments` is client revenue), so nothing double-counts; P&L aggregation is future work. Statutory maths (EPF/SOCSO/EIS/PCB) stays out of scope |
| `announcements` | Company notices authored from the cockpit (Task 6). `audience` scopes visibility once published: `team` (workspace only), `partners` (**forward hook** — the partner portal doesn't read this table yet), or `all` (both). `published_at` null = draft; publishing sets it once (re-publishing an already-published row keeps the original timestamp; toggling off reverts to draft). No soft-deletes, no delete endpoint — "unpublish" is the only retraction verb |

### Partner portal (portal restructure, Task 9 — type-aware referrer + investor)

| Table | Purpose |
|-------|---------|
| `external_accounts` | The unified partner-portal identity — one authenticatable row per login, `type` enum (`referrer` \| `investor`), unique `email`, hashed 8-digit `password` passcode (nullable until credentialed), `status` (`active` \| `suspended`), `last_login_at`. The `external` Sanctum guard (config/auth.php) authenticates against this table and nowhere else; the old `referral` guard on `referral_partners` is gone. Passcodes are minted by staff (approve / reset-passcode) or self-service forgot-passcode, and only ever surfaced by email |
| `referral_partners` | Now a plain referrer **profile** (code, tier, commission, business status `pending\|active\|paused`) linked to its account via `external_account_id` (nullable FK, nullOnDelete — null until approved/credentialed). Its legacy `password`/`last_login_at` columns remain physically for rollback safety but are nulled/unused |
| `investors` | The investor profile — `external_account_id` FK (cascadeOnDelete), name, company, notes. No content model yet (portal Documents/Reports are premium empty states); admin investor CRUD is future work |

## API routes

All routes prefixed with `/api`:

```
GET  /v1/quote-builder/config        Public, cached 1h
POST /v1/quote-requests              Public, 3/hr/IP
GET  /v1/admin/leads                 Sanctum + admin role
GET  /v1/admin/leads/{id}            Sanctum + admin role
POST /v1/admin/leads/{id}/status     Sanctum + admin role
POST /v1/admin/leads/{id}/convert    Sanctum + admin role

# Auth surfaces (cockpit / workspace / partner — isolated token abilities)
POST /v1/admin/login                 Public, throttled — mints ['cockpit'] token (founder role only)
POST /v1/team/login                  Public, throttled — mints ['workspace'] token (all internal roles)
POST /v1/partner/login               Public, throttled — mints ['partner'] token on the `external`
                                     guard (external_accounts — referrer OR investor, Task 9)
POST /v1/partner/forgot-passcode     Public, throttled — self-service passcode reissue (active
                                     accounts of either type; silent for unknown emails)
POST /v1/admin/team-session          Cockpit — exchanges the admin session for a fresh workspace
                                     token (the admin→team direct sign-in; audited per call)

# Partner portal (/v1/partner/*, auth:external + abilities:partner — Task 9).
# Shared endpoints serve both types; referrer-only ones add partner.type:referrer
# middleware and 403 an investor token. No investor content endpoints yet.
GET  /v1/partner/me                  Shared — {type, email, profile: referrer|investor fields}
POST /v1/partner/logout              Shared — revokes the current token
GET  /v1/partner/dashboard           Referrer-only — own leads + derived earnings + the ?ref link
POST /v1/partner/referrals           Referrer-only — context-aware "refer another business"

# Team workspace (/v1/team/*, Sanctum + workspace role) — Task 4 of the portal
# restructure dropped inquiry triage, the referral programme, and marketing
# spend entry; the team no longer touches admin-owned operational data.
GET   /v1/team/me                    Self profile (name, email, role, tier, availability)
PATCH /v1/team/me                    Self-service update — {name?, availability: 'available'|'busy'}
GET   /v1/team/payslips              Own payslips (breakdown) + `pending_extras` (own completed-with-pay
                                     tasks not yet on a slip). Founder's full ledger is /v1/admin/payroll
GET   /v1/team/tasks                 {pool, mine} — the kanban/calendar feed in one round-trip
POST  /v1/team/tasks/{id}/claim      Pick up a pool task (assignee=me + in_progress; 409 if taken)
PATCH /v1/team/tasks/{id}/status     Own tasks only — {status: in_progress|completed|open, note?};
                                     completing with pay forks to payment_pending; 'open' releases
                                     back to the pool; notes append as "[Y-m-d H:i] Name: note"
GET   /v1/team/announcements         Read-only feed — published + audience in (team, all), newest-
                                     published first. Drafts and 'partners'-only rows never appear

# Tasks engine (cockpit side — Task 5)
GET    /v1/admin/tasks               Filters: status, priority, assignee_id ('unassigned' = pool), q
POST   /v1/admin/tasks               Create (assign now or leave in the pool; status always 'open')
GET    /v1/admin/tasks/{id}          Detail
PATCH  /v1/admin/tasks/{id}          Edit shape (title/desc/assignee/pay/duration/deadline/priority) —
                                     never status; assignment keeps status (the assignee starts it)
POST   /v1/admin/tasks/{id}/mark-paid  payment_pending (or completed-with-pay) → paid + paid_at (ad-hoc,
                                     no payslip); a task marked paid this way is never swept into a payslip,
                                     and a payslip-LINKED task is rejected here (422 — settle the slip
                                     instead), so the two payout paths stay mutually exclusive
DELETE /v1/admin/tasks/{id}          Soft delete (vanishes from team lists)

# Payroll / payslips (cockpit side — Task 7; founder-only via view-all-payroll)
GET    /v1/admin/payroll             Full ledger (paginate; ?user_id filter), each row itemised
GET    /v1/admin/payroll/preview     Dry-run for a member (?user_id, &period_label?): allowance on file +
                                     count/sum of unlinked payment_pending extras + projected gross;
                                     `period_taken` flags an existing (user, period) slip (null if no period)
POST   /v1/admin/payroll             GENERATE a MONTHLY payslip {user_id, period_label, method?, note?} —
                                     snapshots allowance, sweeps + links the member's unlinked
                                     payment_pending task extras, gross = allowance(0-if-null) + extras.
                                     Duplicate period → 422; empty slip (no allowance, no extras) → 422
POST   /v1/admin/payroll/one-time    RECORD a ONE-TIME entry {user_id, one_time_type, discretionary_myr?,
                                     include_pending_tasks?, mark_paid?(default true), paid_at?, method?, note?}
                                     — a signing/festive/spot bonus and/or the member's pending task extras
                                     paid immediately, outside the monthly cycle. gross = discretionary +
                                     (swept extras). Not period-guarded; allowed for deactivated teammates;
                                     empty (no amount, no extras) → 422. mark_paid=false drafts a pending
                                     one-off the normal settle action closes later
POST   /v1/admin/payroll/{id}/settle Stamp paid_at (+ method?) and flip the linked task extras to paid.
                                     Already-settled → 422 (idempotent guard)

# Team provisioning (cockpit side — Task 8; founder-only via manage-users)
GET    /v1/admin/users               Roster, alphabetical (name, email, role, tier, availability,
                                     monthly_allowance_myr, deactivated_at, created_at)
GET    /v1/admin/users/{user}        Full profile for the /admin/users/[id] detail page — roster fields
                                     PLUS the teammate's self-filled phone/bank/address (off the list
                                     payload) + `profile_complete`/`profile_missing`. Founder reads only;
                                     bank/address are filled by the teammate on /team/profile (self-serve)
POST   /v1/admin/users               Create {name, email, password (min 12), role, monthly_allowance_myr?}
                                     — queues TeamWelcomeMail to the new teammate (motivational welcome +
                                     their email/temp-password + Team Portal link); a mail failure is
                                     logged, never fails provisioning (founder also sees creds one-time in UI)
PATCH  /v1/admin/users/{user}        Edit {name?, role?, monthly_allowance_myr?} — a role CHANGE revokes
                                     the teammate's tokens; renaming/re-budgeting the allowance doesn't.
                                     Demoting the platform's last founder → 422
POST   /v1/admin/users/{user}/deactivate    Persistent lockout (`deactivated_at` + revokes tokens).
                                     Self-deactivation → 422; already-deactivated → 422 (idempotent guard)
POST   /v1/admin/users/{user}/reactivate    Clears the lockout; already-active → 422

# Announcements (cockpit side — Task 6)
GET    /v1/admin/announcements       All rows, newest first (creator eager-loaded)
POST   /v1/admin/announcements       Create — title/body/audience required; published boolean sets
                                     published_at to now() or null
PATCH  /v1/admin/announcements/{id}  Edit title/body/audience and/or toggle `published` — publishing
                                     a draft stamps published_at once; re-publishing an already-
                                     published row keeps its original timestamp; unpublishing clears
                                     it back to null (draft). No delete endpoint.

# MCP connector (/v1/connector/*, auth:sanctum + abilities:connector:*) — see MCP-CONNECTOR.md.
# A fourth, isolated surface for the remote MCP server (mcp.axelnova.tech) that lets
# Claude draft quotations. Draft-only: tokens carry connector:read/connector:draft,
# never cockpit — so a connector token is rejected by every /v1/admin route, and each
# endpoint opens exactly its own ability.
GET  /v1/connector/catalog                connector:read  — merged quote catalog (packages/modifiers/addons/rush)
POST /v1/connector/quotations/draft       connector:draft — create a DRAFT quotation (priced or bespoke)
GET  /v1/connector/quotations/{ref}       connector:read  — read back a connector-created draft (AXNQ code)

# Document generation (see DOCUMENT-GENERATION.md)
POST /v1/admin/orders/{order}/documents   Sanctum — issue an invoice/receipt
PUT  /v1/admin/invoices/{invoice}         Sanctum — re-edit an issued invoice in place (re-freezes the
                                          payload from stored inputs, same AXNI number; amounts lock
                                          once payments exist — see PAYMENTS-LEDGER.md Phase 4)
POST /v1/admin/invoices/{invoice}/send    Sanctum — queue the client invoice email (PDF link + attachment)
GET  /v1/documents/{token}                Public  — token-gated document data (JSON)
# Frontend Nitro: GET /api/documents/{token}/pdf — renders & streams the PDF

# Analytics (see ANALYTICS.md)
POST /v1/track/page-view             Public  — page-view beacon (hashed IP, bots dropped)
POST /v1/likes/{type}/{id}           Public  — toggle an anonymous like
GET  /v1/admin/analytics/overview    Sanctum — traffic + likes overview (?range=7d|30d)
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
/admin/users          Team provisioning — create (marketer|engineer only; founder not creatable from
                      the UI, though the backend whitelist still allows it), edit (name/role/allowance;
                      founder rows keep a locked role), deactivate/reactivate (confirm dialog)
/admin/tasks          Tasks engine — create/assign/edit (slideover), mark bonus paid, delete
/admin/announcements  Announcements — post/edit (slideover), publish toggle (§12.2). No delete
/admin/payroll        Payroll — generate a monthly payslip (member + period, with live preview) or
                      Record one-time payment (bonus / ad-hoc payout, optional pending-tasks sweep,
                      mark-paid-now toggle), itemised ledger (allowance/extras/discretionary/gross),
                      Settle (confirm). Legacy rows render gross-only

# Team workspace (/team/*) — Task 4 reframed this to five personal
# destinations; inquiries/referrals/marketing pages were removed (admin-owned).
/team/login           Team auth
/team/forgot          "Forgot password" — notifies the founder, no self-service reset
/team                 Home — company announcements feed (published + audience team|all, newest first)
/team/tasks           Tasks kanban — Available → In progress → Complete (payment is a card badge, not a column)
/team/calendar        Calendar — month view over task deadlines + completed-date log (no table of its own)
/team/payslips        Own payslips (monthly allowance/extras + one-time bonus entries, tagged by type) + a "Pending extras" block on top
/team/profile         Self-service profile — display name + availability (Available|Busy)

# /partners is the PUBLIC marketing landing (pages/public/partners/index.vue,
# stripPublicPrefix'd), not the portal — a route collision with the logged-in
# dashboard was resolved by moving the dashboard to /partners/home. A
# logged-in partner hitting bare /partners is auto-forwarded there client-side
# (localStorage token check in the public page's onMounted; SSR-safe, no SEO
# impact). Task 9 made the portal type-aware (referrer + investor share one
# login); the old single /partners/portal page 301s to /partners/home.
# partnersNav flags each item shared|referrer|investor; the partner-type route
# middleware bounces the wrong type to /partners/home (the API 403s regardless).
/partners/login       Partner auth — email + 8-digit passcode (glass card, both types)
/partners/forgot      Self-service passcode reissue
/partners/home        Dashboard — referrer: stats trio + ?ref link; investor: portfolio-coming-online
/partners/profile     Shared — type badge + email; referrer: code/tier/commission; investor: company
/partners/referrals   Referrer-only — referral list + "refer another" form
/partners/earnings    Referrer-only — earned/estimated totals, commission bands, per-referral breakdown
/partners/documents   Investor-only — deal-room shelf (premium empty state; no content model yet)
/partners/reports     Investor-only — performance reports (premium empty state; no content model yet)
```

## Key services

### PricingEngine (`backend/app/Services/Quoting/PricingEngine.php`)
Single source of truth for pricing calculations. Reads from the active `PricingConfig` row. Also exposes `configForFrontend()` which the Nuxt `/quote` page uses for live client-side estimates.

### usePricingEngine (`frontend/app/composables/usePricingEngine.ts`)
TypeScript port of the same calculation logic. Fetches config from `/api/v1/quote-builder/config` on mount, then runs estimates client-side in real-time as the form changes.

### ReferenceCodeGenerator (`backend/app/Support/ReferenceCodeGenerator.php`)
Mints the AXN document family — `AXN{TYPE}-{YYYY}-{NNNN}` (type letter fused into the prefix), `AXNQ-` (quotation) / `AXNO-` (order) / `AXNI-` (invoice, future) via the [`DocumentType`](../../backend/app/Support/DocumentType.php) enum — atomically using a DB transaction with `lockForUpdate()`. Each type has its own counter that resets each year.

### DocumentMapper (`backend/app/Services/Quoting/DocumentMapper.php`)
Maps a `Quotation` (live, via `toDocumentData`) or an `Order` (via `forOrder`, for invoices/receipts) to the `DocumentData` shape the PDF renderer consumes. See [DOCUMENT-GENERATION.md](./DOCUMENT-GENERATION.md).

### DocumentIssuer (`backend/app/Services/Quoting/DocumentIssuer.php`)
Issues an invoice/receipt for an order: assigns a derived atomic number (`INV-`/`RCP-` + the quotation ref) and **freezes** the `DocumentData` snapshot into `documents`, so the rendered PDF can never drift from what was issued.

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
| `FRONTEND_URL` | The app / admin-cockpit origin + the CORS anchor |
| `PUBLIC_SITE_URL` | The PUBLIC site where clients/partners/teammates land — used for the referral link, client quote-PDF links, and partner/team login emails. Set this when admin runs on its own subdomain (`FRONTEND_URL=https://admin.example.com`, `PUBLIC_SITE_URL=https://example.com`); falls back to `FRONTEND_URL` when unset |
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
- **Documents (PDF)**: not stored — rendered on demand (token-gated) by headless Chromium in the frontend image, from live data (quotations) or a frozen snapshot (invoices/receipts). The frontend prod image installs `chromium` via `apk`; `pdf.ts` uses `playwright-core` against `/usr/bin/chromium-browser`. See [DOCUMENT-GENERATION.md](./DOCUMENT-GENERATION.md).
