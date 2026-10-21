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
| Email | Resend over SMTP in prod with the Hostinger mailbox as automatic backup (`failover`), Mailtrap in dev; HTML templates from the owner's design kit; every send logged in `email_logs` — see [EMAIL-SYSTEM.md](./EMAIL-SYSTEM.md) |
| Scheduler | One `scheduler` container (`schedule:work`); tasks in `routes/console.php` |
| Auth | Laravel Sanctum bearer tokens — three isolated user surfaces (admin cockpit / team workspace / partner portal), each minting tokens with its own ability (`cockpit` / `workspace` / `partner`) enforced by the `abilities:` middleware, plus a machine-only **MCP connector** surface (`connector:read`/`connector:draft` — see [MCP-CONNECTOR.md](./MCP-CONNECTOR.md)). Admin → Team jump via `POST /v1/admin/team-session` token exchange |

## Database tables

### Core (Phase 3)

| Table | Purpose |
|-------|---------|
| `pricing_configs` | Versioned pricing formula JSON — only one row `active=true` at a time |
| `quote_requests` | Lead submissions from the public quote builder |
| `quote_request_addons` | Denormalised add-ons selected per quote request |
| `users` | Team accounts (authenticated via Sanctum). `role` = founder/marketer/engineer (COCKPIT_ROLES/WORKSPACE_ROLES on the model); `availability` (Task 4, self-service, available\|busy); `monthly_allowance_myr` (Task 7, nullable, snapshotted onto payslips); `deactivated_at` (Task 8 — nullable persistent lockout, set/cleared from `/admin/users`, checked at login on both `/v1/admin` and `/v1/team`); `password_set_at` (email Phase 2 — null while an invited teammate hasn't chosen a password yet → `setup_pending` in the admin payload; pre-Phase-2 rows backfilled to `created_at`) |

### Planned (Phase 4+)

| Table | Purpose |
|-------|---------|
| `clients` | Converted leads become clients |
| `quotations` | Formal quotations generated from leads |
| `quotation_line_items` | Line items from the pricing breakdown |
| `orders` | Post-acceptance engagements (one per converted quotation) |
| `documents` | Issued invoices & receipts — frozen `DocumentData` snapshots, rendered on demand. See [DOCUMENT-GENERATION.md](./DOCUMENT-GENERATION.md) |
| `projects` | Active project tracking |

### Quotation revisions (see [QUOTATION-REVISIONS.md](./QUOTATION-REVISIONS.md))

| Table | Purpose |
|---|---|
| `quotation_revisions` | Numbered revisions (R1, R2 …) of a quotation family — status (`draft/sent/accepted/superseded/rejected/expired/discarded`), `change_reason`, `replaces_revision_id`, own `public_token`, the content snapshot (`document`, `form_payload`, `estimate_*`, `locale`, `addons` JSON) and lifecycle dates. `quotations.current_revision_id` points at the current one; the `quotations` row mirrors its content |
| `order_care_plans` | Care Plan coverage periods per order (`included` / `separate`, monthly RM, `starts_on` / `ends_on`, `ended_at`) — closed and reopened on supersede so no month is billed twice or skipped |

`orders` gained `current_revision_id`, `revised_at`, `schedule_netting`, `revision_flags`; `invoices` gained `revision_id`, `void_reason`, `care_period` and the `adjustment` / `phase` / `care_plan` types.

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

### Feedback & Reviews (see [FEEDBACK-MODULE.md](./FEEDBACK-MODULE.md))

| Table | Purpose |
|-------|---------|
| `feedback` | One review per order — `ORDERS \|\|--o\| FEEDBACK : "reviewed by"` (`order_id` unique + nullable so admin can log standalone feedback; `client_id` denormalised). Token-gated client submission (`public_token`), `AXNF-` reference codes, overall + four dimension scores + NPS, publish lifecycle `pending → approved → published` gated on `publish_consent`. Soft-deletes |

### Email log (see [EMAIL-SYSTEM.md](./EMAIL-SYSTEM.md))

| Table | Purpose |
|-------|---------|
| `email_logs` | One row per email that left (or finally failed to leave) the dashboard: catalog `type`, `audience`, the record it's about (`related_type` short alias + `related_id`), recipient, subject, `route` (resend / hostinger), forward-only `status` (sent → delayed → delivered → bounced/failed → complained) advanced by the Resend webhook, our `message_id` + Resend's `provider_id`, `triggered_by` user, `alerted_at`. No bodies stored |
| `access_tokens` | One-time set-up / reset links (email Phase 2 — see [EMAIL-SYSTEM.md](./EMAIL-SYSTEM.md#credential-links-phase-2)). `account_type` (`user` \| `external_account`) + `account_id`, `purpose` (`setup` 72h \| `reset` 60 min), `surface` (`team` \| `partner` \| `admin` — which page the link opens; `admin` requires a founder at redeem), unique `token_hash` (SHA-256 — the raw token only ever lives in the email), `expires_at`, `used_at` (claimed atomically, single use). Issuing a link deletes the account's earlier unused link of the same purpose; using one (or deactivating the teammate) deletes all its other unused links. Written only by `App\Services\Auth\AccessLinks` |

### Team workspace (portal restructure, Task 5/6/7)

| Table | Purpose |
|-------|---------|
| `tasks` | The tasks engine — delegated work with an optional extra-pay bonus. `assignee_id` null = the pick-up pool; status spine `open → in_progress → completed \| payment_pending → paid` (completing with `pay_amount_myr` set forks to `payment_pending` automatically; admin mark-paid OR payslip settlement writes `paid` — and mark-paid ALSO records a settled `collaboration` one-time `payroll_entries` row for the assignee and links the task, so an ad-hoc payout never bypasses the ledger). `payroll_entry_id` (nullable FK, nullOnDelete) stamps which payroll entry (a payslip, or mark-paid's own collaboration one-off) settles the extra — the per-task double-count guard: generation only picks up `payment_pending` + unlinked tasks, and ad-hoc mark-paid rejects a linked task (422) and an unassigned one (422 — nobody to pay). The `2026_09_26` backfill migration gave every pre-existing ad-hoc-paid task its entry. `notes` is the append-only timestamped team log. Soft-deletes. The team Calendar is a view over this table (deadline + completed_at) — no table of its own |
| `payroll_entries` | The payslip ledger (Task 7). Two `kind`s share the table: `monthly` (the recurring run) and `one_time` (an ad-hoc bonus / payout). Itemised as `allowance_snapshot_myr` (the member's `users.monthly_allowance_myr` FROZEN at generation; null = none on file, distinct from 0; monthly only) + `task_extras_myr` (Σ of the linked pending task bonuses) + `discretionary_myr` (the manual one-off amount; one_time only), with `gross_myr` kept as the TOTAL so legacy consumers stay valid. **The per-period double-count guard is monthly-scoped** — `UNIQUE (user_id, monthly_period)` where `monthly_period` is a generated column = `period_label` for monthly rows, NULL for one-offs (so several one-offs can share a month; monthly stays one-per-period). One-offs still carry a YYYY-MM `period_label` (the payment's month) so year-to-date rollups bucket them; the UI labels them by `one_time_type` (collaboration/signing/festive/performance/spot/other — `collaboration` = **Project collaboration**, a one-off project with an engineer who has a team login; it is also what a task's mark-paid records under, with gross = the task's pay as `task_extras_myr` and `discretionary_myr` 0). `paid_at` is the sole settlement marker (no status column); settling flips the linked task extras to `paid`. Pre-Task-7 rows carry a hand-entered gross with null snapshot / 0 extras — the UI reads them as `legacy` (gated on `monthly` so a discretionary one-off is never misflagged) and renders gross-only. **The settled payslip IS the team-comp expense record** — there is no general finance/expenses/P&L module in this repo (only `company_expenses` — renamed from `marketing_expenses`, it was always general company spend — + this table; `payments` is client revenue), so nothing double-counts; P&L aggregation is future work. Statutory maths (EPF/SOCSO/EIS/PCB) stays out of scope |
| `announcements` | Company notices authored from the cockpit (Task 6). `audience` scopes visibility once published: `team` (workspace only), `partners` (**forward hook** — the partner portal doesn't read this table yet), or `all` (both). `published_at` null = draft; publishing sets it once (re-publishing an already-published row keeps the original timestamp; toggling off reverts to draft). No soft-deletes, no delete endpoint — "unpublish" is the only retraction verb |

### Blog (see [BLOG.md](./BLOG.md))

| Table | Purpose |
|-------|---------|
| `blog_posts` | One row per article. Scalars (title, unique `slug`, `excerpt`, cover URL + alt, `category`, `format` — article\|guide\|tutorial\|case_study\|opinion\|news, the accent eyebrow — `tags` json, `cta_*`, `seo_*`, derived `reading_minutes`, `status` draft\|published, `published_at`) + a `sections` json column — the ordered list of `{id, heading, body_md, image_url, image_alt, quote, quote_by}`. **Markdown is the storage format**; `App\Support\BlogMarkdown` renders it to safe HTML on read (raw HTML stripped, unsafe links dropped). Images are URLs only. Soft-deletes; the slug unique index is absolute (deleted rows included), so `BlogPost::uniqueSlug` checks `withTrashed()`. Founder-authored from `/admin/blog`; only `published` rows are served publicly |

### Collaborations (see [COLLABORATIONS.md](./COLLABORATIONS.md))

| Table | Purpose |
|-------|---------|
| `collaboration_partners` | Studio partners on `/collaborations`, e.g. TRLabs. **Not** `referral_partners` (the `/partners` commission programme). Profile scalars (unique `slug`, name, tagline, summary, story, `logo_url`/`symbol_url` paths under `frontend/public/logos/`, website, founder, location), `services` json, `partner_since`, `active` (DB default false), `sort_order`, `updated_by`. Soft-deletes. Seeded by data migration (TRLabs); profile, Live/Hidden and project links are edited in the admin (Partners › Collaborations) — the slug never changes there, and adding a new partner is still a migration |
| `collaboration_partner_project` | A project built with a partner: unique (partner, project), both FKs cascadeOnDelete, `mode` `co_build`\|`subcontract`\|`referral`, `role_note`, `credit_public`. Public only when partner active + `credit_public` + project active. `CollaborationPartner::linkProject()` defaults credit ON only for co-builds |

### Partner portal (portal restructure, Task 9 — type-aware referrer + investor)

| Table | Purpose |
|-------|---------|
| `external_accounts` | The unified partner-portal identity — one authenticatable row per login, `type` enum (`referrer` \| `investor`), unique `email`, hashed `password` passcode (nullable until the partner chooses one through their set-up link; 10+ characters since email Phase 2, older 8-digit ones still valid), `status` (`active` \| `suspended`), `last_login_at`. The `external` Sanctum guard (config/auth.php) authenticates against this table and nowhere else; the old `referral` guard on `referral_partners` is gone. Partners choose their own passcode through an emailed link (approve → set-up link; staff reset or self-service forgot → reset link); nobody else ever sees it |
| `referral_partners` | Now a plain referrer **profile** (code, tier, commission, business status `pending\|active\|paused`) linked to its account via `external_account_id` (nullable FK, nullOnDelete — null until approved/credentialed). Its legacy `password`/`last_login_at` columns remain physically for rollback safety but are nulled/unused |
| `investors` | The investor profile — `external_account_id` FK (cascadeOnDelete), name, company, notes. No content model yet (portal Documents/Reports are premium empty states); admin investor CRUD is future work |

## API routes

All routes prefixed with `/api`:

```
GET  /v1/quote-builder/config        Public, cached 1h
GET  /v1/admin/leads                 Sanctum + admin role
GET  /v1/admin/leads/{id}            Sanctum + admin role
POST /v1/admin/leads/{id}/status     Sanctum + admin role
POST /v1/admin/leads/{id}/convert    Sanctum + admin role

# Auth surfaces (cockpit / workspace / partner — isolated token abilities)
POST /v1/admin/login                 Public, throttled — mints ['cockpit'] token (founder role only)
POST /v1/team/login                  Public, throttled — mints ['workspace'] token (all internal roles)
POST /v1/partner/login               Public, throttled — mints ['partner'] token on the `external`
                                     guard (external_accounts — referrer OR investor, Task 9)
POST /v1/admin/forgot-password       Public, throttled — founder account recovery: emails a 60-min reset
                                     link (AdminAccountRecoveryMail) to a founder account only; one
                                     generic response whether or not the email matches
POST /v1/team/forgot-password        Public, throttled — emails an active teammate a 60-min reset link
                                     (TeamPasswordResetMail); generic response; nothing changes until used
POST /v1/partner/forgot-passcode     Public, throttled — emails a 60-min reset link (PartnerResetLinkMail;
                                     active accounts of either type); generic response; no rotation.
                                     All three forgot routes: ≤3 links/hour per address (AccessLinks)
GET  /v1/auth/access-links/{token}   Public, throttled — whose link is it {purpose, surface, name, email,
                                     min_length}; unknown/expired/used → 410 with one generic message
POST /v1/auth/access-links/{token}   Public, throttled — {secret, secret_confirmation} sets the password /
                                     passcode once, signs the account out everywhere, and (reset links
                                     only) sends the owner a heads-up. Same 410 as above

# Email (see EMAIL-SYSTEM.md)
POST /v1/webhooks/resend             Public, Svix-signed (RESEND_WEBHOOK_SECRET), 600/min — Resend
                                     delivery reports advance email_logs.status forward-only
GET  /v1/admin/emails                Sanctum (cockpit) — email history: ?related_type=&related_id=
                                     or ?email= ; newest first, 25/page (AdminEmailHistory panel)
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
GET   /v1/team/tasks                 {pool, mine} — the kanban/calendar feed in one round-trip.
                                     Founders also get `team` (every task assigned to someone else,
                                     any status) for the whole-team view; the key is absent for other
                                     roles. Read-only — claim/status still refuse tasks that aren't yours
POST  /v1/team/tasks/{id}/claim      Pick up a pool task (assignee=me + in_progress; 409 if taken)
PATCH /v1/team/tasks/{id}/status     Own tasks only — {status: in_progress|completed|open, note?};
                                     completing with pay forks to payment_pending; 'open' releases
                                     back to the pool; notes append as "[Y-m-d H:i] Name: note"
GET   /v1/team/announcements         Read-only feed — published + audience in (team, all), newest-
                                     published first. Drafts and 'partners'-only rows never appear
GET   /v1/team/analytics/overview    Marketer surface (role:founder,marketer — engineers 403) —
                                     read-only mirror of the cockpit traffic overview

# Tasks engine (cockpit side — Task 5)
GET    /v1/admin/tasks               Filters: status, priority, assignee_id ('unassigned' = pool), q
POST   /v1/admin/tasks               Create (assign now or leave in the pool; status always 'open')
GET    /v1/admin/tasks/{id}          Detail
PATCH  /v1/admin/tasks/{id}          Edit shape (title/desc/assignee/pay/duration/deadline/priority) —
                                     never status; assignment keeps status (the assignee starts it).
                                     LOCKED once status != 'open' (in_progress/completed/…/paid → 422) so
                                     the shape can't change under the person working it; Delete recalls it
POST   /v1/admin/tasks/{id}/mark-paid  payment_pending (or completed-with-pay) → paid + paid_at, AND records
                                     the payout in payroll: a SETTLED one_time `collaboration` entry for the
                                     assignee (gross = the task's pay as a task extra; body: paid_at? — default
                                     now, also the entry's period month — method? note?), task linked in the
                                     same transaction. A payslip-LINKED task is rejected (422 — settle the slip
                                     instead) and so is an unassigned one (422 — nobody to pay), so the two
                                     payout paths stay mutually exclusive and nothing double-pays
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
                                     — a collaboration/signing/festive/performance/spot bonus and/or the member's pending task extras
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
POST   /v1/admin/users               Create {name, email, role, monthly_allowance_myr?} — no password:
                                     the account starts with an unusable random one + password_set_at
                                     null, and TeamWelcomeMail carries a 72-hour set-up link (email
                                     Phase 2). A mail failure is logged, never fails provisioning
PATCH  /v1/admin/users/{user}        Edit {name?, role?, monthly_allowance_myr?} — a role CHANGE revokes
                                     the teammate's tokens; renaming/re-budgeting the allowance doesn't.
                                     Demoting the platform's last founder → 422
POST   /v1/admin/users/{user}/deactivate    Persistent lockout (`deactivated_at` + revokes tokens).
                                     Self-deactivation → 422; already-deactivated → 422 (idempotent guard)
POST   /v1/admin/users/{user}/reactivate    Clears the lockout; already-active → 422
POST   /v1/admin/users/{user}/reset-password    Emails the teammate a link — a fresh set-up link while
                                     set-up is pending (TeamWelcomeMail), otherwise a 60-min reset link
                                     (TeamPasswordResetMail). Changes nothing until they use it (no token
                                     revocation here — deactivate to lock someone out), logs
                                     `team.password_reset`, returns `{message}` only. Deactivated → 422

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
# Claude draft quotations, blog posts and the public catalogue (projects, services,
# packages — created Hidden, edited only while Hidden). Draft-only: tokens carry the UNIVERSAL
# connector:read/connector:draft abilities (every module), never cockpit — so a connector
# token is rejected by every /v1/admin route, and each endpoint opens exactly its own ability.
GET  /v1/connector/catalog                connector:read  — merged quote catalog (packages/modifiers/addons/rush)
GET  /v1/connector/quotations             connector:read  — slim list of any non-deleted quotation
POST /v1/connector/quotations/draft       connector:draft — create a DRAFT quotation (priced or bespoke)
GET  /v1/connector/quotations/{ref}       connector:read  — read back any quotation (AXNQ code; ?revision=N)
PUT  /v1/connector/quotations/{ref}       connector:draft — update the quotation's DRAFT revision
GET  /v1/connector/orders/{reference}     connector:read  — get_order: read-only money view (AXNO or AXNQ) — tally, netted plan, invoices
GET  /v1/connector/blog/guide             connector:read  — blog writing guide (voice, structure, CTA defaults, formats, …)
GET  /v1/connector/blog/posts             connector:read  — slim list of any non-deleted post
GET  /v1/connector/blog/posts/{id}        connector:read  — full Markdown record
POST /v1/connector/blog/posts             connector:draft — create a post (always draft)
PUT  /v1/connector/blog/posts/{id}        connector:draft — partial update of a DRAFT (published → 422)
GET  /v1/connector/projects               connector:read  — slim list of every project (Live + Hidden; visibility, q, page)
GET  /v1/connector/projects/{id}          connector:read  — full project record + admin / public URL
POST /v1/connector/projects               connector:draft — create a project (always Hidden, unfeatured, appended)
PUT  /v1/connector/projects/{id}          connector:draft — partial update of a HIDDEN project (Live → 422)
GET  /v1/connector/services               connector:read  — every service (category) with its packages
GET  /v1/connector/services/{id}          connector:read  — one service + its packages' full records
POST /v1/connector/services               connector:draft — create a service (always Hidden, never default)
PUT  /v1/connector/services/{id}          connector:draft — partial update of a HIDDEN service (Live → 422)
GET  /v1/connector/packages/{id}          connector:read  — full package record (+ quote_package_key / quotable)
POST /v1/connector/packages               connector:draft — create a package (always Hidden; optional NEW quote_package_key)
PUT  /v1/connector/packages/{id}          connector:draft — partial update of an effectively HIDDEN package (it or its service Hidden; effectively Live → 422)

# Quotation revisions (see QUOTATION-REVISIONS.md) — cockpit
GET  /v1/admin/quotations/{id}?revision=N           Sanctum — one family, shown through revision N (default: open, else current)
POST /v1/admin/quotations/{id}/revisions            Sanctum — "Revise": {change_reason, from_revision?} → new draft revision
POST /v1/admin/quotations/{id}/revisions/{n}/discard Sanctum — discard a draft revision (R2+)
POST /v1/admin/quotations/{id}/accept               Sanctum — {revision?}: first acceptance creates the order;
                                                    a later revision SUPERSEDES the accepted one on the same order
POST /v1/admin/quotations/{id}/status               Sanctum — only {status: rejected} (re-opening in place → 422 "use Revise")

# Document generation (see DOCUMENT-GENERATION.md)
POST /v1/admin/orders/{order}/documents   Sanctum — issue an invoice/receipt
PUT  /v1/admin/invoices/{invoice}         Sanctum — re-edit an issued invoice in place (re-freezes the
                                          payload from stored inputs, same AXNI number; amounts lock
                                          once payments exist — see PAYMENTS-LEDGER.md Phase 4)
POST /v1/admin/invoices/{invoice}/send    Sanctum — queue the client invoice email (PDF link + attachment)
GET  /v1/documents/{token}                Public  — token-gated document data (JSON)
# Frontend Nitro: GET /api/documents/{token}/pdf — renders & streams the PDF

# Revenue reporting (derived — no table of its own)
GET  /v1/admin/revenue/monthly       Sanctum — booked vs collected per calendar month
GET  /v1/admin/revenue/monthly/{YYYY-MM}  Sanctum — one month broken down: orders won, payments landed (tagged with the order's month), per-client roll-up; same rules as /monthly so it sums to its row. 404 for malformed/future months
                                     (?months=6|12|24, anything else falls back to 12).
                                     BOOKED = Σ orders.final_amount_myr of non-cancelled orders
                                     created that month (what we sold); COLLECTED = signed Σ over
                                     succeeded payments by paid_at (what we banked — refunds are
                                     negative rows and net out on their own, and are ALSO reported
                                     separately as `refunded`). The two diverge by months under
                                     deposit terms; that gap is the point. Series is dense
                                     (zero-filled) so quiet months keep their slot. Month bucketing
                                     is SQL DATE_FORMAT and relies on APP_TIMEZONE=Asia/Kuala_Lumpur
                                     matching the stored timestamps — change both sides together.
                                     Deliberately NOT on AnalyticsController: that one is exposed to
                                     the marketer surface via role:founder,marketer, and revenue must
                                     not ride a route shared with a non-founder role.

# Blog (see BLOG.md)
GET  /v1/blog/posts                  Public  — published cards, newest first, ?format= & ?topic=, 12/page + formats/topics sets
GET  /v1/blog/posts/{slug}           Public  — full article (rendered sections + toc + related); draft → 404
GET  /v1/blog/slugs                  Public  — unpaginated slug feed for the frontend sitemap
GET    /v1/admin/blog/posts          Cockpit — all posts (?status, ?q) + page-view counts
POST   /v1/admin/blog/posts          Cockpit — create (draft)
GET    /v1/admin/blog/posts/{id}     Cockpit — editable record (Markdown)
PUT    /v1/admin/blog/posts/{id}     Cockpit — update (published edits go live on save)
POST   /v1/admin/blog/posts/{id}/publish     Cockpit — completeness gate (422) → published, stamps published_at once
POST   /v1/admin/blog/posts/{id}/unpublish   Cockpit — back to draft
DELETE /v1/admin/blog/posts/{id}     Cockpit — soft delete
POST   /v1/admin/blog/render         Cockpit — preview renderer over unsaved form state (saves nothing)
GET    /v1/admin/blog/guide          Cockpit — writing guide + CTA defaults (config/blog.php, shared with the connector)

# Collaborations (see COLLABORATIONS.md) — public, read-only (no admin editor yet)
GET  /v1/collaborations              Public  — active studio partners (sort_order) + public projects_count
GET  /v1/collaborations/{slug}       Public  — profile + publicly credited, active shared projects; else 404
                                     (/v1/projects/{slug} also returns partners[] — public credits only)
# POST /v1/inquiries also takes origin=partner (+ partner slug → collaboration_partner_id) and
# origin=collaboration (+ portfolio_url, folded into the message) from the /collaborations pages.

# Partners › Collaborations (cockpit) — COLLABORATIONS.md §Admin
GET    /v1/admin/collaboration-partners                 Partners (any state) + counts: projects_total/public,
                                                        enquiries_open/new; + applications_new
GET    /v1/admin/collaboration-partners/{p}             Profile + linked projects (public + hidden_reason)
PATCH  /v1/admin/collaboration-partners/{p}             Profile fields, active, sort_order — `slug` prohibited;
                                                        logo/symbol must be site paths. Logged (RecordsActivity)
POST   /v1/admin/collaboration-partners/{p}/projects    Link {project_id, mode, role_note?, credit_public?} via
                                                        linkProject(); already linked → 422
PATCH  /v1/admin/collaboration-partners/{p}/projects/{project}   mode / role_note / credit_public; not linked → 404
DELETE /v1/admin/collaboration-partners/{p}/projects/{project}   Unlink (the project stays)
GET    /v1/admin/inquiries                              No `origin` → sales leads only (quote + contact);
                                                        collab leads by origin=partner|collaboration
                                                        (+ collaboration_partner_id)

# Analytics (see ANALYTICS.md)
POST /v1/track/page-view             Public  — page-view beacon (hashed IP, bots dropped)
POST /v1/likes/{type}/{id}           Public  — toggle an anonymous like (projects)
GET  /v1/admin/analytics/overview    Sanctum — traffic + likes overview (?range=7d|30d)
GET  /v1/team/analytics/overview     Sanctum workspace + role:founder,marketer — the marketer's
                                     read-only mirror (same controller); engineers 403
```

## Frontend routes

Public marketing routes (`/`, `/about`, `/company`, `/contact`, `/services{,/**}`, `/projects{,/**}`, `/collaborations{,/**}`, `/support`, `/blog{,/**}`, `/legal/**`) are cached with `swr` route rules in [frontend/nuxt.config.ts](../../frontend/nuxt.config.ts). Everything authenticated or per-recipient — `/admin`, `/portal`, `/team`, `/partners`, `/quote/**`, `/feedback/**`, `/proposals/**` — is deliberately excluded and must stay that way, since caching those would serve one visitor's page to another. A live blog change purges the cache on demand (`BlogPostObserver` → `SiteCache` → Nuxt `POST /_cache/purge`). See [DEPLOY.md § Page caching](./DEPLOY.md#page-caching).


```
/                     Portfolio home
/projects             Project listing
/projects/[id]        Project detail
/blog                 Blog index — published posts, format pills (?format=) + topic dropdown (?topic=), pagination
/blog/[slug]          Article — one shared layout (BlogArticle): format eyebrow + byline header, left pane
                      (section links), text column right, closing CTA, share row, related
/services             Services & pricing
/about                About page
/contact              Contact form → POST /v1/inquiries (origin=contact) — same pipeline as /quote
/support              "Support Our Work" (footer) — DuitNow QR + OCBC account (data/studioPayment.ts), copy button
/collaborations       Studio partners (NOT the /partners referral programme) — co-branded partner cards,
                      the three ways of working, "Partner with us" → POST /v1/inquiries (origin=collaboration)
/collaborations/trlabs  Axel Nova × TRLabs — hand-built co-branded page (.collab-trlabs scope); enquiry form
                      → POST /v1/inquiries (origin=partner, partner=trlabs). See COLLABORATIONS.md
/admin/collaborations Partners › Collaborations — tabs Partners | Applications (origin=collaboration leads)
/admin/collaborations/[id]  One partner: Live/Hidden, public profile form, shared projects (link / edit /
                      "Show publicly" / unlink), its enquiries (origin=partner)
/admin/collaborations/enquiries/[id]  A collab lead — the same AdminInquiryDetail as /admin/inquiries/[id];
                      each route redirects a lead of the other kind to the right one
/quote                Project inquiry form → POST /v1/inquiries (origin=quote)
/quote/success        Post-submission confirmation
/admin/login          Admin auth (+ "Forgot password?" → /admin/forgot)
/admin/forgot         Founder account recovery — POST /v1/admin/forgot-password (generic response)
/admin/reset-password One-time reset link landing (?token=) — shared AccessLinkForm
/admin/leads          Lead list (Sanctum-protected)
/admin/leads/[id]     Lead detail + actions
/admin/users          Team provisioning — create (marketer|engineer only; founder not creatable from
                      the UI, though the backend whitelist still allows it), edit (name/role/allowance;
                      founder rows keep a locked role), deactivate/reactivate (confirm dialog), "Resend set-up link" /
                      "Send reset link" (by `setup_pending`) — no password is ever chosen or shown here
/admin/tasks          Tasks engine — scannable one-line listing (title/assignee/priority/deadline/status);
                      row → detail page. /admin/tasks/new + /admin/tasks/[id] are full pages (no slideover);
                      detail carries pay/duration/payment status + Mark paid/Delete, and is read-only once
                      the task is in progress or beyond
/admin/revenue        Revenue — monthly booked-vs-collected reporting (6/12/24-month window).
/admin/revenue/[month]  One month's breakdown (e.g. /admin/revenue/2026-07) — opened by clicking a month row or a month on the chart; prev/next stepper.
                      Totals row (booked / collected / net of fees / fees), a smooth area chart (SVG, `utils/areaChart.ts`)
                      (one shared MYR axis — never a second scale), and a table of exact figures.
                      Read-only and fully derived from `payments` + `orders`; adds no table
/admin/announcements  Announcements — post/edit (slideover), publish toggle (§12.2). No delete
/admin/payroll        Payroll — generate a monthly payslip (member + period, with live preview) or
                      Record one-time payment (bonus / ad-hoc payout, optional pending-tasks sweep,
                      mark-paid-now toggle), itemised ledger (allowance/extras/discretionary/gross),
                      Settle (confirm). Legacy rows render gross-only

/admin/blog           Blog CMS — list (status / search filters, views), New post
/admin/blog/[id]      Editor (`new` or an id) — title, introduction, ordered section cards (Markdown-backed
                      rich text + optional image / quote), side rail (cover URL, category, tags, CTA, SEO,
                      slug), Start-from-template, Import Markdown, backend-rendered Preview, Publish gate

# Team workspace (/team/*) — Task 4 reframed this to five personal
# destinations; inquiries/referrals/spend-entry pages were removed (admin-owned).
# The marketer surface (marketing + analytics) was added later — nav + pages
# render for founder/marketer only (teamNav roles filter; backend 403s engineers).
/team/login           Team auth
/team/forgot          "Forgot password" — emails a 60-min reset link (generic response); the owner gets
                      TeamPasswordChangedMail only after the teammate actually sets a new password
/team/set-password    One-time set-up / reset link landing (?token=) — shared AccessLinkForm
/team                 Home — company announcements feed (published + audience team|all, newest first)
/team/tasks           Tasks kanban — Available → In progress → Complete (payment is a card badge, not a column).
                      Founders get a "Mine / Whole team" toggle: team scope adds everyone else's cards
                      (dashed border + assignee chip, view-only) to every column; preference persists in localStorage
/team/calendar        Calendar — month view over task deadlines + completed-date log (no table of its own).
                      Same founder-only Mine / Whole team toggle as /team/tasks (shared preference)
/team/payments        "Payments" — own payroll entries (monthly allowance/extras + one-time bonus entries, tagged by type)
                      + a "Pending extras" block on top. Reads GET /v1/team/payslips (API path unchanged);
                      /team/payslips 301-redirects here (nuxt.config routeRules)
/team/profile         Self-service profile — display name + availability (Available|Busy)
/team/marketing       Marketer-only — Threads analytics (coming-soon until the Threads API integration lands)
/team/analytics       Marketer-only — read-only mirror of the site traffic overview (/v1/team/analytics/overview)

# /partners is the PUBLIC marketing landing (pages/public/partners/index.vue,
# stripPublicPrefix'd), not the portal — a route collision with the logged-in
# dashboard was resolved by moving the dashboard to /partners/home. A
# logged-in partner hitting bare /partners is auto-forwarded there client-side
# (localStorage token check in the public page's onMounted; SSR-safe, no SEO
# impact). Task 9 made the portal type-aware (referrer + investor share one
# login); the old single /partners/portal page 301s to /partners/home.
# partnersNav flags each item shared|referrer|investor; the partner-type route
# middleware bounces the wrong type to /partners/home (the API 403s regardless).
/partners/login       Partner auth — email + passcode (glass card, both types; free-form field since
                      email Phase 2 — chosen passcodes are 10+ chars, older 8-digit ones still work)
/partners/forgot      Self-service reset link (60 min; current passcode keeps working until it's used)
/partners/set-passcode One-time set-up / reset link landing (?token=) — shared AccessLinkForm
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

### calculateEstimate (`frontend/app/utils/pricing.ts`)
Pure TypeScript port of the same calculation (base → legacy modifiers → scope fields → add-ons → rush → RM 50), parity-tested in `pricing.test.ts`. `usePricingEngine` (the admin builder's cached config) and the public `/services` estimator (`utils/serviceEstimator.ts`, which derives each package's controls from the catalog) both price through it.

### ReferenceCodeGenerator (`backend/app/Support/ReferenceCodeGenerator.php`)
Mints the AXN document family — `AXN{TYPE}-{YYYY}-{NNNN}` (type letter fused into the prefix), `AXNQ-` (quotation) / `AXNO-` (order) / `AXNI-` (invoice, future) via the [`DocumentType`](../../backend/app/Support/DocumentType.php) enum — atomically using a DB transaction with `lockForUpdate()`. Each type has its own counter that resets each year.

### DocumentMapper (`backend/app/Services/Quoting/DocumentMapper.php`)
Maps a `Quotation` (live, via `toDocumentData`) or an `Order` (via `forOrder`, for invoices/receipts) to the `DocumentData` shape the PDF renderer consumes. See [DOCUMENT-GENERATION.md](./DOCUMENT-GENERATION.md).

### DocumentIssuer (`backend/app/Services/Quoting/DocumentIssuer.php`)
Issues an invoice/receipt for an order: assigns a derived atomic number (`INV-`/`RCP-` + the quotation ref) and **freezes** the `DocumentData` snapshot into `documents`, so the rendered PDF can never drift from what was issued.

## Queued job flow

```
POST /api/v1/inquiries                (the /quote, /contact, partner and collaboration forms)
  ├─ Inquiry::create()
  ├─ InquiryReceivedMail → the inquirer (queued)
  └─ InquiryAdminNotificationMail → the owner (queued, 10s later)

POST /api/v1/admin/quotations/{id}/send
  └─ SendClientQuoteEmail::dispatch($id, $revisionId) → ClientQuotationMail: the formal quotation

Every send → email_logs row (MessageSent listener); final failure → failed row + owner alert.
```

The public priced quote funnel (`POST /v1/quote-requests` → instant estimate email + new-lead alert) was **retired on 2026-10-12**: `/quote` is the inquiry form and the admin builds every priced quote. Quotations it created (`source_meta.created_via = quote_funnel`) remain and normalize on read.

## Environment variables

See `backend/.env.example` for the full list. Key variables:

| Variable | Purpose |
|----------|---------|
| `NUXT_PUBLIC_API_BASE` | Backend base URL (set in `docker-compose.dev.yml` for dev) |
| `FRONTEND_URL` | The app / admin-cockpit origin + the CORS anchor |
| `PUBLIC_SITE_URL` | The PUBLIC site where clients/partners/teammates land — used for the referral link, client quote-PDF links, and partner/team login emails. Set this when admin runs on its own subdomain (`FRONTEND_URL=https://admin.example.com`, `PUBLIC_SITE_URL=https://example.com`); falls back to `FRONTEND_URL` when unset |
| `MAIL_MAILER` | `failover` in prod (Resend → Hostinger), `smtp` (Mailtrap) in dev |
| `MAIL_*` | The Hostinger SMTP route in prod / Mailtrap in dev |
| `RESEND_SMTP_KEY` / `RESEND_WEBHOOK_SECRET` | Resend SMTP password (API key) and webhook signing secret |
| `EMAIL_LOGO_URL` | Optional — absolute URL of the email header logo PNG (defaults to `{PUBLIC_SITE_URL}/email/axn-mark.png`) |
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
