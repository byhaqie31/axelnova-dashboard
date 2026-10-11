# Email System

How the dashboard sends email, what it sends, how delivery is tracked, and how to add a new email. Design spec: [2026-10-11-email-foundation-design.md](../superpowers/specs/2026-10-11-email-foundation-design.md). Visual design: the owner's kit in [email-kit/](./email-kit/).

## At a glance

```
 Controller / Job / Scheduler ──▶ Mail::to()->send(new XyzMail)   (queued)
                                         │
 queue worker ──▶ Laravel mailer ──▶ failover: [ resend → hostinger ]
        │                │                │ each route notes its name (RouteTaggingTransport)
        │                └─ MessageSent ──┴──▶ EmailLogger ──▶ email_logs (sent)
        └─ final job failure ────────────────▶ EmailLogger ──▶ email_logs (failed) ──▶ owner alert

 Resend ──webhook──▶ POST /api/v1/webhooks/resend ──▶ verify ──▶ match row ──▶ advance status ──▶ owner alert on problems
 Admin record page ──▶ GET /api/v1/admin/emails ──▶ AdminEmailHistory panel
```

- **Sender:** `no-reply@axelnova.tech`. `axelnova.tech` is the email domain; `axelnovaventures.com` is the website only.
- **Routes:** Resend over SMTP (`smtp.resend.com:587`) is primary, the Hostinger mailbox SMTP is the automatic backup (`MAIL_MAILER=failover`). Not Resend's HTTP API: `api.resend.com` sits behind Cloudflare, which the VPS can't reliably reach. Dev stays on Mailtrap (`MAIL_MAILER=smtp`); tests use `array`.
- **Everything is queued**, except `PartnerPasscodeMail` (its plaintext passcode must not enter the `jobs` table — Phase 2 replaces it with a link).
- **Every send is logged** in `email_logs`; Resend's reports advance its status; the owner is alerted when a client/partner/team email bounces, is marked as spam, or finally fails.

## Email catalog

The single list lives in [`EmailCatalog`](../../backend/app/Support/Mail/EmailCatalog.php) (label, audience, template, subject). "Live" = sent today; a phase number = template built, trigger arrives in that phase.

| # | Type | Template | Audience | Sent when | Related record | Status |
|---|---|---|---|---|---|---|
| 1a | `quotation.estimate` | `emails.quotation.estimate` | client | Quote-builder submit | Quotation | Live |
| 1b | `quotation.sent` | `emails.quotation.sent` | client | Admin "Send" on a quotation | Quotation | Live |
| 2 | `inquiry.received` | `emails.inquiry.received` | client | Contact / quote / partner / collaboration form | Inquiry | Live |
| 3 | `invoice.sent` | `emails.invoice.sent` | client | Admin "Send" on an invoice | Invoice | Live |
| 4 | `feedback.request` | `emails.feedback.request` | client | Admin requests feedback (manual only) | Feedback | Live |
| 5 | `order.confirmed` | `emails.order.confirmed` | client | Quote accepted | Order | Phase 3 |
| 6 | `payment.receipt` | `emails.payment.receipt` | client | Payment recorded | Payment | Phase 3 |
| 7 | `payment.refund` | `emails.payment.refund` | client | Refund recorded | Payment | Phase 3 |
| 8 | `order.started` | `emails.order.started` | client | Order → in progress | Order | Phase 3 |
| 9 | `order.delivered` | `emails.order.delivered` | client | Order → delivered | Order | Phase 3 |
| 10 | `order.completed` | `emails.order.completed` | client | Order → completed (carries the feedback button) | Order | Phase 3 |
| 11 | `order.cancelled` | `emails.order.cancelled` | client | Order → cancelled | Order | Phase 3 |
| 12 | `invoice.due_soon` | `emails.invoice.due-soon` | client | Before the due date (scheduled) | Invoice | Phase 5 |
| 13 | `invoice.overdue` | `emails.invoice.overdue` | client | After the due date (scheduled) | Invoice | Phase 5 |
| 14 | `quotation.expiring` | `emails.quotation.expiring` | client | Before a quote expires (scheduled) | Quotation | Phase 5 |
| 15 | `referral.received` | `emails.referral.received` | partner | Referral submitted | Referral | Live |
| 16 | `referral.update` | `emails.referral.update` | partner | Referral status changes | Referral | Phase 4 |
| 17 | `referral.commission` | `emails.referral.commission` | partner | Admin commission button | Referral | Live |
| 18 | `partner.passcode` | `emails.partner.passcode` | partner | Partner approved / passcode reset | ExternalAccount | Live (link in Phase 2) |
| 19 | `partner.reset_confirm` | `emails.partner.reset-confirm` | partner | Partner "forgot passcode" | ExternalAccount | Phase 2 |
| 20 | `team.welcome` | `emails.team.welcome` | team | Teammate created | User | Live (link in Phase 2) |
| 21 | `team.password_reset` | `emails.team.password-reset` | team | Founder resets a password | User | Live (link in Phase 2) |
| 22 | `task.assigned` | `emails.team.task-assigned` | team | Task assigned | Task | Phase 6 |
| 23 | `payroll.payslip` | `emails.team.payslip` | team | Payroll settled | PayrollEntry | Phase 6 |
| 24 | `quotation.new_lead_alert` | `emails.admin.new-lead` | admin | Quote-builder submit | Quotation | Live |
| 25 | `inquiry.new_alert` | `emails.admin.new-inquiry` | admin | Any inquiry form (Reply-To = the inquirer) | Inquiry | Live |
| 26 | `partner.reset_alert` | `emails.admin.partner-reset` | admin | Partner reset their passcode | ExternalAccount | Live |
| 27 | `team.reset_alert` | `emails.admin.team-reset-request` | admin | Teammate used "forgot password" | User | Live |
| 28 | `system.delivery_alert` | `emails.admin.delivery-alert` | admin | A client/partner/team email bounced, was marked spam, or failed | EmailLog | Live |
| 29 | `referral.new_alert` | `emails.admin.new-referral` | admin | Referral submitted | Referral | Phase 4 |
| 30 | `feedback.submitted_alert` | `emails.admin.feedback-submitted` | admin | Client submits feedback | Feedback | Phase 4 |
| 31 | `quotation.expired_alert` | `emails.admin.quote-expired` | admin | Quote expired without a reply (scheduled) | Quotation | Phase 5 |
| 32 | `admin.account_recovery` | `emails.admin.account-recovery` | admin | Founder password reset | User | Phase 2 |

Tooling mails outside the catalog: `PreviewMail` (`mail:preview`) and `TestMail` (`mail:test`) — the log files them under their class name.

## Templates

- **Layouts** — `resources/views/emails/layouts/client.blade.php` (accent bar, logo header, company footer, founder sign-off) and `internal.blade.php` (ink bar, "Internal" tag, no sign-off). Admin-audience emails use internal; everything else client.
- **Blocks** — one anonymous component per kit block in `resources/views/emails/blocks/` (`heading-intro`, `heading-intro-internal`, `next-steps`, `order-tracker`, `summary-table`, `highlight-panel`, `section-label`, `line-items`, `main-button`, `whatsapp-button`, `warning-box`, `paragraph`, `signature`, `spacer`, `logo-header`). Used as `<x-email::blocks.summary-table :rows="…" />`. Markup is the kit's, verbatim — tables + inline styles, 600px, one 620px breakpoint. **No Tailwind, flex or grid.**
- **Config — one place each** ([`config/email_design.php`](../../backend/config/email_design.php)): the accent colour, the logo (a PNG at `frontend/public/email/axn-mark.png`, absolute URL via `EMAIL_LOGO_URL`), the signature identity and the footer company details. Bank details come from `DocumentMapper::BANK` (shared with the PDFs).
- **Data** — templates take presentation-ready values (formatted with [`EmailFormat`](../../backend/app/Support/Mail/EmailFormat.php): `RM 6,500`, `10 Nov 2026`); each mailable maps its models in `viewData()`. Rows/blocks whose value is missing are hidden. Two traps: Laravel injects its own `$message` into mail views (never name template data `message`), and a mailable's public properties would override `with` data — `KitMailable::buildViewData()` makes `viewData()` win.
- **Plain text** — every email gets a text part generated from its HTML at send time ([`HtmlToText`](../../backend/app/Support/Mail/HtmlToText.php)), so all mail is multipart.
- **Fidelity** — `tests/Feature/Mail/*FidelityTest` + `QuoteEmailsTest` + `InquiryAndInvoiceEmailsTest` render the mocked emails with the kit's sample data and compare them to the kit files (byte-identical copies in `backend/tests/Fixtures/email-kit/`).

## The email log (`email_logs`)

One row per email that left (or definitively failed to leave). Written by the auto-discovered listeners in `app/Listeners/Mail` (`StampMessageId`, `RecordSentEmail`, `RecordFailedQueuedEmail`) via [`EmailLogger`](../../backend/app/Services/Mail/EmailLogger.php), plus the `failed()` hooks on our email jobs. Logging never blocks a send — listener errors are reported, not thrown.

| Column | Meaning |
|---|---|
| `type`, `audience` | Catalog type and audience (client · partner · team · admin) |
| `related_type`, `related_id` | Short alias (`EmailLog::RELATED`: quotation, invoice, inquiry, …) + id — not a global morph map |
| `to_email`, `to_name`, `subject` | As sent. No bodies are stored — Resend keeps content |
| `route` | `resend` / `hostinger` (or the mailer name in dev/tests); null for `failed` |
| `status`, `status_detail` | See below; the detail holds the bounce/failure reason |
| `message_id` | The `<uuid@axelnova.tech>` Message-ID we stamp on every email |
| `provider_id` | Resend's email id — from the SMTP reply if present, else from the first matched report |
| `triggered_by` | The admin/team user whose request caused it (Laravel `Context`, carried into queued jobs by the `RecordMailActor` middleware); null = automatic |
| `sent_at`, `status_changed_at`, `alerted_at` | |

**Statuses move forward only:** `sent` 0 → `delayed` 1 → `delivered` 2 → `bounced` / `failed` 3 → `complained` 4. A report with an equal or lower rank changes nothing, so late or repeated reports are harmless.

## Resend webhook

`POST /api/v1/webhooks/resend` ([`ResendWebhookController`](../../backend/app/Http/Controllers/Api/V1/Webhooks/ResendWebhookController.php)), own throttle group (600/min).

- **Signature:** Svix — HMAC-SHA256 of `{svix-id}.{svix-timestamp}.{raw body}` with the base64 key after `whsec_` (`RESEND_WEBHOOK_SECRET`); timestamps older than 5 minutes are rejected (401).
- **Matching** ([`ResendEventMatcher`](../../backend/app/Services/Mail/ResendEventMatcher.php)): `data.message_id` → `data.email_id` → same recipient + subject sent within 10 minutes.
- **Events:** `email.delivery_delayed` → delayed · `email.delivered` → delivered · `email.bounced` / `email.suppressed` → bounced · `email.failed` → failed · `email.complained` → complained · `email.sent` → no change. Opens and clicks are not tracked.
- Unknown emails and untracked event types return 200 and change nothing (a 4xx makes Resend retry forever).
- **Which matching step works in production is recorded here after go-live** — see [DEPLOY.md](./DEPLOY.md#email-resend).

## Delivery alerts

[`DeliveryAlerts`](../../backend/app/Services/Mail/DeliveryAlerts.php): when a row whose audience isn't `admin` becomes `bounced`, `complained` or `failed`, the owner gets `system.delivery_alert` once (`alerted_at` is claimed atomically). The alert is admin-audience itself, so it can never alert again.

## Admin history panel

`GET /api/v1/admin/emails?related_type=invoice&related_id=12` or `?email=client@…` (founder-only, newest first, 25 a page) feeds `AdminEmailHistory` ([ADMIN-COMPONENTS.md](../frontend/ADMIN-COMPONENTS.md)), placed on the quotation, invoice, inquiry, feedback and team-user pages (by record) and the client and referral pages (by address). "View in Resend" opens `https://resend.com/emails/{provider_id}`.

## Scheduler

One `scheduler` container (`php artisan schedule:work`) in every compose file — never more than one, or tasks run twice. Tasks live in [`routes/console.php`](../../backend/routes/console.php), all `withoutOverlapping()`, in app time (Asia/Kuala_Lumpur):

| Command | When | What |
|---|---|---|
| `quotations:expire-overdue` | hourly | Expire sent quotations past validity (the admin list's lazy expiry stays as a safety net) |

## Commands

- `php artisan mail:preview {type|--all} [--to=] [--pause=1200]` — send templates with sample data to the configured mailer (Mailtrap in dev) for visual review. The pause respects Mailtrap free's ~1 email/second.
- `php artisan mail:test {address}` — send one test email and print its log row (route, Message-ID, Resend id).

## Adding a new email

1. Add the type to `EmailCatalog::define()` — label, audience, view, subject closure.
2. Build the template in `resources/views/emails/{group}/{name}.blade.php` from the blocks, inside the layout that matches the audience.
3. Add sample data to `EmailSamples` (`mail:preview` and the catalog tests use it).
4. Create the mailable: extend `KitMailable`, implement `emailType()`, `relatedProperty()` (the public property holding the record) and `viewData()`; `implements ShouldQueue`.
5. Send it with `Mail::to(...)->send(...)` — logging, alerts and the history panel follow automatically. If you send from inside your own job, give the job a `failed()` hook that calls `EmailLogger::recordFailure()`.
6. Tests: the catalog/coverage tests fail until steps 1–4 are consistent.
