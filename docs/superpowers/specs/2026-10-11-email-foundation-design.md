# Email Foundation — Phases 0 + 1 + Email Templates Design

**Date:** 2026-10-11 · **Status:** Approved by owner 2026-10-11 (§5.13 added from the owner's design brief + answers) · **Branch:** `feat/email-foundation`

## 1. Context

An audit of outbound email (2026-10-11) found 13 mailables, all clustered on intake and accounts (leads, inquiries, partner/team credentials). Once a quote is accepted the platform goes silent: no order confirmation, no receipt email, no refund confirmation, no order-status updates, no reminders, no admin alert for new referrals or submitted feedback. There is also no scheduler (`routes/console.php` is empty), no record of what was sent, and no way to know whether a client actually received an email.

The owner chose to address **all** findings, in seven phases. **This spec covers Phase 0 (move sending to Resend), Phase 1 (the foundation every later phase builds on), and the email template system built from the owner's design kit (§5.13).** Phases 2–6 get their own specs (see §6).

### Current state

| Concern | Today |
|---|---|
| Sending | Hostinger mailbox SMTP — `smtp.hostinger.com:587`, as `no-reply@axelnova.tech` |
| Queue | `database` queue; `axelnova-queue` container + a supervisord worker inside `axelnova-backend` |
| Mail config | No published `config/mail.php` (Laravel 11.51 slim config — framework defaults + `MAIL_*` env) |
| Domain | `axelnova.tech` — DNS on **Cloudflare**, MX → Hostinger, SPF includes Hostinger, Hostinger DKIM active (`hostingermail-a/b/c._domainkey`), DMARC `p=none` |
| Scheduler | None |
| Record of sends | Only `invoice.email_queued` in the activity log |

## 2. Decisions (agreed with owner)

1. **Sender domain is `axelnova.tech`.** `axelnovaventures.com` is the website only and is never a From domain.
2. **Resend becomes the primary sending route, over SMTP** (`smtp.resend.com:587`). Not the HTTP API: `api.resend.com` sits behind Cloudflare, and the VPS's outbound path to Cloudflare ranges is unreliable (the reason prod runs through a Cloudflare Tunnel). Resend's SMTP endpoint is AWS-hosted and unaffected.
3. **Hostinger SMTP stays as the automatic backup** via Laravel's `failover` mailer. Hostinger mailboxes and the root MX records are untouched — inbound mail, including client replies to dashboard emails, keeps landing in Hostinger.
4. **Monitoring = Resend dashboard + a per-record "Emails" history panel in admin.** No standalone admin Emails page.
5. **Capture approach = Option A:** one central listener on Laravel's mail events writes the log. Existing emails keep their send code; each only declares what it is and which record it belongs to.
6. **No email bodies are stored.** Resend's dashboard keeps content; any email can be rebuilt from its record.
7. **Owner gets an alert email** when an email to a client, partner or team member bounces, is marked as spam, or permanently fails.
8. **Opens and clicks are not tracked** (unreliable — Apple Mail pre-fetches — and noisy).
9. **All email goes through the queue**, except `PartnerPasscodeMail` until Phase 2 (see §5.7).
10. **Email design = the owner's kit in `docs/global/email-kit/`.** Every email moves from Laravel markdown mail to HTML Blade views built from the kit's two layouts and blocks (§5.13). Email 1 splits into an estimate and a formal quotation.

### Non-goals (this spec)

- Triggers and mailables for new emails (Phases 2–6). Their **templates** are built here (§5.13); each phase wires its mailable + trigger.
- Credential-flow changes (Phase 2).
- Reminders (Phase 5) — Phase 1 only stands up the scheduler.
- Open/click tracking, marketing email, unsubscribe management.

## 3. Phase 0 — Resend setup (owner checklist)

Phase 0 is account + DNS work, done by the owner. **None of it changes production behaviour** until the env switch in the rollout (§7) — it can be done any time before Phase 1 ships.

1. **Create a Resend account** (free plan: 3,000/month, 100/day; Pro is $20/month for 50,000 with no daily cap).
2. **Add the domain `axelnova.tech`** in Resend → Domains. Pick the region closest to Malaysia that Resend offers.
3. **Add Resend's DNS records in Cloudflare** (Resend shows exact values; it also offers an automatic Cloudflare setup). Expect three records, none on the root, so Hostinger is unaffected:
   - `resend._domainkey` — TXT (DKIM)
   - `send` — MX (bounce handling)
   - `send` — TXT (SPF for the bounce subdomain)
4. **Click Verify** in Resend; wait until the domain shows *Verified*.
5. **Create an API key** — permission *Sending access*, restricted to `axelnova.tech`. This key is the SMTP password. Store it in a password manager; it is shown once.
6. **Create a webhook** — endpoint `https://admin.axelnovaventures.com/api/v1/webhooks/resend`, events: `email.sent`, `email.delivered`, `email.delivery_delayed`, `email.bounced`, `email.complained`, `email.failed`, `email.suppressed`. Copy the **signing secret** (`whsec_…`).
   - The endpoint returns 404 until Phase 1 is deployed. Resend retries failed deliveries, so creating the webhook early is harmless; alternatively create it right after deploy.
7. **Later (1–2 weeks after go-live, once both routes send cleanly):** change `_dmarc.axelnova.tech` from `v=DMARC1; p=none` to `v=DMARC1; p=quarantine`.

Values handed to the deploy (§7): the Resend API key and the webhook signing secret.

## 4. Phase 1 — Architecture overview

```
 Controller / Job / Scheduler
        │  Mail::to()->send(new XyzMail)          (queued)
        ▼
 Queue worker ──▶ Laravel Mailer ──▶ failover: [ resend  →  hostinger ]
        │                   │                      │ each route notes its name
        │                   └── MessageSent ───────┴──▶ EmailLogger ──▶ email_logs (status: sent)
        └── final job failure ─────────────────────────▶ EmailLogger ──▶ email_logs (status: failed)

 Resend ──webhook──▶ POST /api/v1/webhooks/resend ──▶ verify signature ──▶ match row ──▶ advance status
                                                                                   └──▶ alert owner (bounce / spam / failed)

 Admin page ──▶ GET /api/v1/admin/emails?related=…  ──▶ EmailHistory panel
```

## 5. Phase 1 — Design

### 5.1 Sending routes

- Publish `config/mail.php` (`php artisan config:publish mail`) and define two SMTP mailers plus a failover mailer:
  - `resend` — `smtp.resend.com`, port 587, STARTTLS, username `resend`, password `RESEND_SMTP_KEY`.
  - `hostinger` — the current Hostinger settings (`MAIL_HOST`/`MAIL_USERNAME`/`MAIL_PASSWORD` keep their meaning).
  - `failover` — `['resend', 'hostinger']`.
- Production: `MAIL_MAILER=failover`. Dev keeps `MAIL_MAILER=smtp` (Mailtrap); tests keep `array`.
- **Route tagging:** each route is wrapped so a successful send records which route carried it (`resend` / `hostinger`; in dev/tests, the mailer name). Laravel's `SentMessage` doesn't expose this, so the wrapper is required for the log's *route* field.
- If Resend rejects a send (outage, or the free plan's 100/day cap), the failover moves the email to Hostinger automatically. Emails sent via Hostinger never receive delivery reports and stay at `sent`.
- New env vars: `RESEND_SMTP_KEY`, `RESEND_WEBHOOK_SECRET` (in `config/services.php` under `resend`). Added to `backend/.env.production.example` and `.env.example` with placeholders.

### 5.2 The email log

New table **`email_logs`**, one row per email that left (or definitively failed to leave) the dashboard:

| Column | Notes |
|---|---|
| `id` | bigint |
| `type` | stable key, e.g. `invoice.sent` (see §5.3) |
| `audience` | `client` · `partner` · `team` · `admin` — drives alerts |
| `related_type`, `related_id` | nullable morph to the record the email is about |
| `to_email`, `to_name` | primary recipient (every current email has exactly one) |
| `subject` | as sent |
| `route` | `resend` · `hostinger` · other mailer name; null for `failed` rows |
| `status` | `sent` · `delayed` · `delivered` · `bounced` · `complained` · `failed` |
| `status_detail` | bounce reason / failure message, nullable |
| `message_id` | RFC `Message-ID` header value set at send time (indexed) |
| `provider_id` | Resend email id — from the SMTP reply if present, else from the first matched webhook (indexed) |
| `triggered_by` | nullable FK → `users`; null = automatic (public form, scheduler, system) |
| `sent_at` | when the route accepted it (null for `failed`) |
| `status_changed_at` | last status change |
| `alerted_at` | when the owner alert was queued; guards against duplicates |
| timestamps | |

Indexes: `(related_type, related_id)`, `to_email`, `message_id`, `provider_id`, `status`.

Rows are never pruned (volume is tens per day). The morph map uses short aliases (`quotation`, `invoice`, …) so the API can accept them directly.

**Statuses move forward only**, by rank: `sent` 0 → `delayed` 1 → `delivered` 2 → `bounced` / `failed` 3 → `complained` 4. A late or repeated event with a lower-or-equal rank is ignored, which makes webhook handling idempotent without storing event ids.

### 5.3 Linking each email to its record

Each mailable implements a small interface declaring its **type**, **audience**, and **which of its public properties is the related record**. The listener reads the mailable class (`__laravel_mailable`) and that property from the event data — no custom tracking headers, no changes to send call sites. Data is read only for linking; nothing from it is persisted beyond the related id.

| Mailable | `type` | `audience` | Related record |
|---|---|---|---|
| `ClientEstimateMail` (split from `ClientQuoteMail`) | `quotation.estimate` | client | Quotation |
| `ClientQuotationMail` (split from `ClientQuoteMail`) | `quotation.sent` | client | Quotation (a revision links to its quotation family) |
| `AdminNotificationMail` | `quotation.new_lead_alert` | admin | Quotation |
| `InquiryReceivedMail` | `inquiry.received` | client | Inquiry |
| `InquiryAdminNotificationMail` | `inquiry.new_alert` | admin | Inquiry |
| `InvoiceMail` | `invoice.sent` | client | Invoice |
| `FeedbackRequestMail` | `feedback.request` | client | Feedback |
| `ReferralReceivedMail` | `referral.received` | partner | Referral |
| `ReferralCommissionMail` | `referral.commission` | partner | Referral |
| `PartnerPasscodeMail` | `partner.passcode` | partner | ExternalAccount |
| `PartnerResetRequestedMail` | `partner.reset_alert` | admin | ExternalAccount |
| `TeamWelcomeMail` | `team.welcome` | team | User |
| `TeamPasswordResetMail` | `team.password_reset` | team | User |
| `TeamPasswordResetRequestedMail` | `team.reset_alert` | admin | User |
| **new** `EmailDeliveryAlertMail` | `system.delivery_alert` | admin | EmailLog |

A mailable that doesn't implement the interface is still logged (type = class basename, audience = `admin` if sent to `services.admin.email`, else `client`, no related record), so a future email can never silently escape the log. A test enforces that every class in `app/Mail` implements the interface.

### 5.4 Capture

- **Sent:** a listener on `Illuminate\Mail\Events\MessageSent` writes the row with `status = sent`. Before sending, a `MessageSending` listener sets the RFC `Message-ID` to `<{uuid}@axelnova.tech>` so the value is known and matchable. `provider_id` is filled if the SMTP reply carried an id that differs from our `Message-ID`.
- **Failed:** only the **final** failure is a row (retries are not). Two carriers exist today:
  - queued mailables (`ShouldQueue`) fail as `SendQueuedMailable` jobs → a `JobFailed` listener unwraps the mailable and writes a `failed` row;
  - mailables sent from inside our own jobs (`SendClientQuoteEmail`, `NotifyAdminJob`, `SendInvoiceEmail`, `RequestFeedbackJob`) → each job's `failed()` hook writes the `failed` row.
- **Who triggered it:** authenticated admin/team requests put the user id into Laravel's `Context`, which Laravel carries into queued jobs automatically; the listener reads it. Absent → `null` → shown as *Automatic*.
- Logging must never break sending: listener errors are caught and reported to the Laravel log, and the email still goes out.

### 5.5 Resend webhook

- **Route:** `POST /api/v1/webhooks/resend`, public, stateless, in its own throttle group (generous — Resend may burst). No CSRF (outside `/v1/admin/*`).
- **Signature:** verify Resend's Svix headers (`svix-id`, `svix-timestamp`, `svix-signature`) against `RESEND_WEBHOOK_SECRET` — HMAC-SHA256 over `{id}.{timestamp}.{raw body}`; reject a bad signature or a timestamp older than 5 minutes with 401. No new package needed.
- **Matching a report to a row**, in order:
  1. `data.message_id` = `email_logs.message_id`
  2. `data.email_id` = `email_logs.provider_id`
  3. fallback: same `to_email` + `subject`, `sent_at` within 10 minutes of `data.created_at`, most recent unmatched row
  On first match, `provider_id` is stored from `data.email_id` (this powers "View in Resend").
- **Event → status:** `email.sent` → no change (already `sent`) · `email.delivery_delayed` → `delayed` · `email.delivered` → `delivered` · `email.bounced` → `bounced` (detail = bounce message/type) · `email.suppressed` → `bounced` (detail: "Suppressed by Resend — this address bounced or complained before") · `email.failed` → `failed` · `email.complained` → `complained`. Forward-only per §5.2.
- **Unmatched events** (e.g. a test sent from Resend's dashboard): respond 200, log a warning, change nothing.
- **Which matching step works is verified live** after Phase 0 (§7). The design works with any of the three; step 3 alone is acceptable at this volume.

### 5.6 Delivery alerts

- When a row with `audience ≠ admin` enters `bounced`, `complained` or `failed` and `alerted_at` is null → queue `EmailDeliveryAlertMail` to `services.admin.email`, then set `alerted_at`. One alert per email, ever.
- The alert names the email type, recipient, related record (with a deep link to its admin page), status and reason, and suggests the fix (e.g. "check the client's email address").
- Alerts are `audience = admin`, so an alert that bounces can't trigger another alert.

### 5.7 Queue consistency

- Switch to queued: `ReferralCommissionMail`, `TeamPasswordResetRequestedMail`, `PartnerResetRequestedMail`. Admin responses change from "emailed" to "queued — delivery shows on the record". `referrals.commission_email_sent_at` keeps meaning "requested at".
- **`PartnerPasscodeMail` stays synchronous until Phase 2.** It carries a plaintext passcode; queuing it would write that passcode into the `jobs` table. Phase 2 replaces plaintext credentials with one-time links, after which it is queued like the rest. (`TeamWelcomeMail` / `TeamPasswordResetMail` are already queued with plaintext passwords in the payload — also fixed by Phase 2.)
- `NotifyAdminJob`'s 10-second delay exists only for Mailtrap's 1-email/second dev limit: keep it outside production, drop it in production.

### 5.8 Scheduler

- New **`scheduler` service** — same backend image, `php artisan schedule:work`, healthcheck disabled, small memory limit — added to `docker-compose.prod.yml`, `docker-compose.prod.ghcr.yml` and `docker-compose.dev.yml`. `deploy-ghcr.yml` brings it up with `backend frontend queue`. Exactly one scheduler container exists, and every task uses `withoutOverlapping()`.
- App timezone is already `Asia/Kuala_Lumpur`; schedule times are Malaysia time.
- **Phase 1 schedules one task:** `quotations:expire-overdue`, hourly → `Quotation::expireOverdue()`. The existing lazy expiry on read stays as a safety net.

### 5.9 Admin API + history panel

- **`GET /api/v1/admin/emails`** (cockpit only), filtered by either `related_type` + `related_id`, or `email` (all emails to an address). Newest first, paginated (25). Each item: type + human label, recipient, subject, status + label, `status_detail`, route, triggered-by name (or *Automatic*), `sent_at`, `status_changed_at`, and `resend_url` when `provider_id` is known.
- **`EmailHistory.vue`** (`frontend/app/components/admin/`), reusable: props `relatedType` + `relatedId`, or `email`. One row per email with a status pill (Sent · Delayed · Delivered · Bounced · Spam complaint · Failed), recipient, time, reason when present, "via Hostinger backup" note when that route was used, and a "View in Resend" link. Bounced/failed rows are visually flagged. Empty state: "No emails sent yet." Design tokens only; verified in light and dark mode.
- **Phase 1 placement — only where emails exist today:** quotation, invoice, inquiry, feedback, referral (covers its partner), team user, and **client** (by the client's email address — everything ever sent to them). Order and payment pages get the panel in Phase 3, when order and receipt emails exist.

### 5.10 Verification tooling

- **`php artisan mail:test {to}`** — sends a small test email through the configured mailer and prints the resulting log row (route, `message_id`, `provider_id`). Used for the go-live checks below and for future debugging.

### 5.11 Docs

- New **`docs/global/EMAIL-SYSTEM.md`** — email catalog (every type, trigger, audience, related record), routes + failover, the log and status model, webhook, alerts, scheduler, the template system (layouts, blocks, config, kit location) and "how to add a new email". Linked from the authoritative-docs list in `.claude/CLAUDE.md`.
- Update `docs/global/DEPLOY.md` (scheduler container, Resend env vars, webhook, go-live checks), `docs/global/ARCHITECTURE.md` (table, routes), `docs/global/README.md` + `docs/global/PLATFORM-OVERVIEW.md` (Mailtrap delay note, email provider).

### 5.12 Testing

Backend (PHPUnit against `axelnova_dashboard_test` only):
- every `app/Mail` class implements the logging interface;
- each existing email (14 mailables after the split) produces one row with the right type, audience, related record and recipient;
- queued-mail final failure and job-carried failure each produce one `failed` row; retries don't add rows;
- triggered-by is captured from an admin request and is null for public/scheduled sends;
- webhook: valid signature accepted; bad signature and stale timestamp rejected; each event maps to the right status; forward-only ordering holds (e.g. `delivered` after `bounced` is ignored); each matching step works; unmatched events return 200 and change nothing;
- alerts: sent once on bounce/complaint/failure for non-admin audiences, never for admin audience, never twice;
- route tagging records `resend` vs `hostinger` under failover;
- `quotations:expire-overdue` expires exactly the overdue sent quotations;
- `GET /admin/emails` filters, paginates and is cockpit-only;
- the three queue-switched emails are now queued (existing tests updated from `assertSent` to `assertQueued`).

- template fidelity: each of the 7 mocked emails, rendered with the kit's sample data, matches its kit file (see §5.13);
- the estimate/quotation split: the quote-builder submit sends the estimate, admin Send sends the quotation.

Frontend: Vitest for the panel's status → label/colour mapping; ESLint + vue-tsc as in CI.

### 5.13 Email templates (owner design kit)

**Source:** `docs/global/email-kit/`. `00-blocks.html` shows every block once, wrapped in `BLOCK:` comments; `01a`, `01b`, `02`, `03`, `06`, `09` are client-layout mockups and `28` is the internal layout. All are email-safe (tables, inline styles), 600px wide, with one breakpoint at 620px. Every name, reference, date and amount in them is sample data.

**Structure**
- Every mailable switches from `markdown:` to an HTML `view:`. The old `resources/views/mail/*` markdown templates and `default.css` are deleted once every email is migrated.
- `resources/views/emails/layouts/client.blade.php` and `internal.blade.php`. The kit's `<head>` is copied verbatim — including the `color-scheme` meta tags, the Geist font link and the `<style>` block with every responsive class. **Client:** accent bar, logo header, sign-off slot, company footer. **Internal:** ink bar, "Internal" tag, compact padding, "Sent automatically…" footer, no sign-off. The hidden preheader line is a per-email slot.
- `resources/views/emails/blocks/` — one partial per block: `heading-intro` (+ internal variant), `next-steps`, `order-tracker`, `summary-table`, `highlight-panel`, `section-label`, `line-items` (priced, unpriced, with/without total and detail lines), `main-button`, `whatsapp-button`, `warning-box`, `paragraph`, `signature`, `spacer`. Markup is copied from the kit exactly; only text and values become variables. **No Tailwind, flex or grid.**
- `resources/views/emails/{type}.blade.php` — one per email, composed from blocks.
- **Plain-text part:** generated from each email's rendered HTML at send time, so every email stays multipart (better deliverability) without hand-written text templates.

**Config — one place each** (`config/email_design.php`):
- **Accent colour** `#4B45D6` — used for the client accent bar, eyebrow labels, links (including the `<style>` rule), the signature link and the order tracker's current step.
- **Logo** — the transparent "A" mark from the PDF letterhead (95×140 PNG, extracted from `frontend/server/utils/pdf/logo.ts`), published as a static file on the website (`frontend/public/email/`) and referenced by absolute URL. The client header shows **the mark plus the "Axel Nova Ventures" text** (owner's choice), replacing the kit's text-only wordmark. The internal header stays as mocked (text + "Internal").
- **Footer company details** — name, SSM registration `202603119899 (CA0420977-U)`, "Kuala Lumpur, Malaysia". The © year is the current year.
- Bank details are **not** copied again: the invoice email reads the existing `DocumentMapper::BANK` (already kept in sync with the PDF and the /support page).

**Resolved values** (kit placeholders and sample-only content):

| Value | Source |
|---|---|
| `[account name]` | `DocumentMapper::BANK` holder — Axel Nova Ventures |
| `[account number]` | `DocumentMapper::BANK` — OCBC 7051415701 |
| `[online payment link]` | **Row hidden** until a card/FPX provider is connected |
| `[receipt no]` | The payment's receipt number (`AXNR-…`); the receipt is issued first if it doesn't exist yet |
| `[payment reference]` | `payments.reference`; row hidden when blank |
| `[review window]` | Entered when marking an order Delivered (Phase 3); no default |
| #9 "What was handed over" | Entered when marking an order Delivered (Phase 3) |
| #1a "What's included" | The package's feature list; add-ons with prices from the quote |
| #1b line items | The quotation document's line items (the same items the PDF shows) + total |
| Greeting name | The name as stored on the record (no first-name splitting) |

**Email set (33 templates — email 1 is split in two):**
- **Mocked — built to match the kit exactly:** 01a estimate, 01b quotation, 02 inquiry received, 03 invoice, 06 payment receipt, 09 project delivered, 28 delivery alert. 01a/01b/02/03/28 are wired to their mailables now; 06 and 09 get their mailables in Phase 3.
- **Existing, not mocked — re-skinned with their current copy:** 4 feedback request, 15 referral received, 17 commission, 18 portal access, 20 team welcome, 21 team password reset, 24 new lead, 25 new inquiry, 26 partner reset alert, 27 team reset request. (18/20/21 move to set-up links in Phase 2.)
- **New — template + preview data now, mailable + trigger in their phase:** 5 order confirmed, 7 refund, 8 project started, 10 project completed (carries the feedback button), 11 order cancelled, 12 invoice due soon, 13 invoice overdue, 14 quote expiring, 16 referral update, 19 confirm passcode reset, 22 task assigned, 23 payslip, 29 new referral, 30 feedback submitted, 31 quote expired, 32 account recovery.
- **Email 4 (feedback request) is only ever sent on a manual request.** Email 10 carries the feedback button instead.
- **Variants carry over:** 02 keeps its four versions (contact, project, partner hire, collaboration) and 04 its three, using today's copy for each.

**Copy approval checkpoint.** Before building the non-mocked templates, Claude delivers one copy list covering every one of them (subject, preheader, eyebrow, heading, lead, step/row/button text). Existing copy is kept verbatim; every new line is marked. Building starts after the owner approves the list. Any further value without a real source stops work for an owner decision — none are invented.

**Verification**
- **Fidelity test:** for each mocked email, render its view with the kit's sample data (bracketed placeholders passed through literally) and compare it to the kit file after normalising whitespace and stripping the kit's sample-data comment. The only allowed differences are the logo header and the hidden payment-link row, each asserted explicitly.
- **`php artisan mail:preview {type|all}`** — sends templates filled with sample data to the dev mailer (Mailtrap): the kit's own sample data for mocked emails, equivalent sample data for the rest. The owner checks them in Mailtrap at desktop width and at 390px.
- Existing mail tests are updated for the new views and the estimate/quotation split, and must pass.
- Templates are built **first**: they don't depend on Resend, so they proceed while the owner does Phase 0.

## 6. Roadmap after Phase 1 (each gets its own spec)

| Phase | Scope |
|---|---|
| 2 · Security | One-time set-password links replace plaintext temp passwords (team welcome/reset) and passcodes; partner "forgot passcode" confirms by link before rotating; founder account recovery |
| 3 · Client lifecycle | Order confirmed (on accept), receipt email, refund confirmation, order status emails with a "notify client" checkbox, completed email carries the feedback button (the separate feedback request stays manual-only); panel added to order + payment pages |
| 4 · Alerts | New referral → owner (public + partner portal), feedback submitted → owner, partner-portal referral acknowledgement, referral status → partner |
| 5 · Reminders | Invoice due-soon/overdue (stop when paid/void), quote expiring → client, quote expired → owner; built on the Phase 1 scheduler and log (the log prevents double-sends) |
| 6 · Team | Task assigned, payslip on payroll settle |

## 7. Rollout

1. **Phase 0** (owner) — any time; no production effect.
2. **Before switching:** from the VPS, confirm `smtp.resend.com:587` is reachable (`openssl s_client -starttls smtp -connect smtp.resend.com:587`).
3. **Merge + deploy Phase 1** with production still on `MAIL_MAILER=smtp` (Hostinger). The migration creates `email_logs`; logging starts immediately (`route = smtp`); the scheduler container starts.
4. **Switch:** set `MAIL_MAILER=failover`, `RESEND_SMTP_KEY`, `RESEND_WEBHOOK_SECRET` in `backend/.env.production`; recreate `backend`, `queue`, `scheduler`.
5. **Live checks:** `mail:test delivered@resend.dev` → row reaches `delivered`; `mail:test bounced@resend.dev` → row reaches `bounced` and the owner alert arrives. Record which matching step (§5.5) succeeded in `EMAIL-SYSTEM.md`.
6. **Watch 1–2 weeks**, then tighten DMARC (§3 step 7).

**Rollback:** set `MAIL_MAILER=smtp` and recreate the containers — sending returns to Hostinger directly; the log keeps working.

## 8. Risks / open items

| Item | Mitigation |
|---|---|
| Unknown whether Resend keeps our `Message-ID` and whether its SMTP reply includes its email id | Three-step matching; verified live in rollout step 5 |
| VPS → `smtp.resend.com` reachability | Checked in rollout step 2; failover to Hostinger regardless |
| Free plan's 100/day cap | Expected to trip the failover to Hostinger; upgrade to Pro if the log shows Hostinger routing more than occasionally |
| Plaintext credentials already sit in queued job payloads (team emails) | Out of scope here; fixed in Phase 2 |
| Two queue workers run in prod (supervisord in `backend` + `queue` container) | Unchanged; both drain the same queue safely. The scheduler is deliberately a single container |
