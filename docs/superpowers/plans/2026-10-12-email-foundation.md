# Email Foundation + Templates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild every dashboard email from the owner's design kit, then add the Phase 1 email foundation (Resend + Hostinger failover, email log, delivery webhook, alerts, scheduler, per-record history panel).

**Architecture:** Part A replaces Laravel markdown mail with HTML Blade views composed from anonymous components (two layouts + one component per kit block), a catalog of every email type with sample data, a `mail:preview` command and kit-fidelity tests. Part B adds an `email_logs` table written by mail-event listeners, a signed Resend webhook that advances status forward-only, owner alerts on bounce/complaint/failure, a scheduler container, and an admin endpoint + Vue panel that read the log.

**Tech Stack:** Laravel 11.51 (PHP 8.4, PHPUnit, Pint), Symfony Mailer, MySQL 8 (test DB `axelnova_dashboard_test`), Nuxt 4 + @nuxt/ui v4 + Vitest, Docker Compose.

**Spec:** `docs/superpowers/specs/2026-10-11-email-foundation-design.md` (§5.13 = templates; §5.1–5.12 = foundation). Design kit: `docs/global/email-kit/`.

## Global Constraints

- **NO `git commit` / `git push`.** The owner reviews the whole build first. Leave everything uncommitted on `feat/email-foundation`.
- **DATABASE PROTECTION (absolute):** never run `migrate:fresh|refresh|reset|rollback`, `db:wipe`, `docker compose down -v`, `docker volume rm/prune`, `DROP`, `TRUNCATE`. Additive `php artisan migrate` only. Tests use `axelnova_dashboard_test` (forced by `phpunit.xml`). Repeat this in every subagent dispatch.
- Run backend commands in the container: `docker compose -f docker-compose.dev.yml exec -T backend <cmd>` (from repo root).
- Kit markup is copied **exactly** — tables, inline styles, `color-scheme` meta tags, responsive classes. No Tailwind, flex or grid in emails.
- Accent `#4B45D6` and the logo URL live **only** in `config/email_design.php`. Logo is a PNG.
- Never invent values with no source. Kit values in `[square brackets]` are resolved per spec §5.13; anything else without a source → stop and ask the owner.
- Existing emails keep their current copy except where a mockup changes it. All new or changed copy is listed for owner review at hand-off.
- Sender domain `axelnova.tech`. Dates: `j M Y` (e.g. `10 Nov 2026`); date-times: `j M Y, g:i a`; money: `RM 6,500` when whole, `RM 6,500.50` otherwise.
- Baseline before work: **602 passed, 1 skipped**. Every task ends green.
- Frontend gates: ESLint + `nuxt typecheck` + Vitest. No browser screenshots (the owner checks visually).

## Review Focus

1. **Client-entered text with HTML characters** (`O'Brien & Co <Ltd>`) must render escaped and never break the table layout → test in Task A4.
2. **Missing optional data** (no due date, no quote ref, no payment reference, no PDF attachment, no package features, no WhatsApp URL) must hide the row/block — never render an empty row, `RM 0`, or a dead button → tests in Tasks A4–A6.
3. **Multi-package quotations** must list every package in the estimate and quotation emails → test in Task A4.
4. **Logging must never block sending:** if the log listener throws, the email still goes out → test in Task B3.
5. **Webhook events out of order, repeated, or for unknown emails** must not regress status, double-alert or 500 → tests in Tasks B4–B5.

---

# PART A — Email templates

## File map (Part A)

| File | Responsibility |
|---|---|
| `backend/config/email_design.php` | Accent, logo, company footer, signature identity |
| `backend/app/Support/Mail/EmailFormat.php` | Money/date formatting for email values |
| `backend/app/Support/Mail/HtmlToText.php` | Plain-text part from rendered HTML |
| `backend/app/Listeners/Mail/AddPlainTextPart.php` | `MessageSending` → add text part when missing |
| `backend/resources/views/emails/layouts/{client,internal}.blade.php` | The two kit layouts (anonymous components) |
| `backend/resources/views/emails/blocks/*.blade.php` | One component per kit block |
| `backend/resources/views/emails/{group}/{name}.blade.php` | One template per email type |
| `backend/app/Support/Mail/EmailCatalog.php` | Every email type: label, audience, view, subject, sample data |
| `backend/app/Support/Mail/EmailSamples.php` | Sample data per type (kit data for mocked emails) |
| `backend/app/Mail/PreviewMail.php` + `app/Console/Commands/MailPreview.php` | `mail:preview` |
| `frontend/public/email/axn-mark.png` | Logo PNG (from the PDF letterhead mark) |
| `backend/tests/Support/KitHtml.php` | Kit-fidelity normaliser |
| `backend/tests/Feature/Mail/*` | Fidelity, mapping, catalog tests |

## Email type catalog (the contract for every template)

| # | Type key | View | Audience | Layout |
|---|---|---|---|---|
| 1a | `quotation.estimate` | `emails.quotation.estimate` | client | client |
| 1b | `quotation.sent` | `emails.quotation.sent` | client | client |
| 2 | `inquiry.received` | `emails.inquiry.received` | client | client |
| 3 | `invoice.sent` | `emails.invoice.sent` | client | client |
| 4 | `feedback.request` | `emails.feedback.request` | client | client |
| 5 | `order.confirmed` | `emails.order.confirmed` | client | client |
| 6 | `payment.receipt` | `emails.payment.receipt` | client | client |
| 7 | `payment.refund` | `emails.payment.refund` | client | client |
| 8 | `order.started` | `emails.order.started` | client | client |
| 9 | `order.delivered` | `emails.order.delivered` | client | client |
| 10 | `order.completed` | `emails.order.completed` | client | client |
| 11 | `order.cancelled` | `emails.order.cancelled` | client | client |
| 12 | `invoice.due_soon` | `emails.invoice.due-soon` | client | client |
| 13 | `invoice.overdue` | `emails.invoice.overdue` | client | client |
| 14 | `quotation.expiring` | `emails.quotation.expiring` | client | client |
| 15 | `referral.received` | `emails.referral.received` | partner | client |
| 16 | `referral.update` | `emails.referral.update` | partner | client |
| 17 | `referral.commission` | `emails.referral.commission` | partner | client |
| 18 | `partner.passcode` | `emails.partner.passcode` | partner | client |
| 19 | `partner.reset_confirm` | `emails.partner.reset-confirm` | partner | client |
| 20 | `team.welcome` | `emails.team.welcome` | team | client |
| 21 | `team.password_reset` | `emails.team.password-reset` | team | client |
| 22 | `task.assigned` | `emails.team.task-assigned` | team | client |
| 23 | `payroll.payslip` | `emails.team.payslip` | team | client |
| 24 | `quotation.new_lead_alert` | `emails.admin.new-lead` | admin | internal |
| 25 | `inquiry.new_alert` | `emails.admin.new-inquiry` | admin | internal |
| 26 | `partner.reset_alert` | `emails.admin.partner-reset` | admin | internal |
| 27 | `team.reset_alert` | `emails.admin.team-reset-request` | admin | internal |
| 28 | `system.delivery_alert` | `emails.admin.delivery-alert` | admin | internal |
| 29 | `referral.new_alert` | `emails.admin.new-referral` | admin | internal |
| 30 | `feedback.submitted_alert` | `emails.admin.feedback-submitted` | admin | internal |
| 31 | `quotation.expired_alert` | `emails.admin.quote-expired` | admin | internal |
| 32 | `admin.account_recovery` | `emails.admin.account-recovery` | admin | internal |

Templates receive **presentation-ready values** (formatted strings); mailables do the mapping. Copy lives in templates.

---

### Task 1 (A1): Template foundations

**Files:**
- Create: `backend/config/email_design.php`, `backend/app/Support/Mail/EmailFormat.php`, `backend/app/Support/Mail/HtmlToText.php`, `backend/app/Listeners/Mail/AddPlainTextPart.php`, `frontend/public/email/axn-mark.png`
- Modify: `backend/app/Providers/AppServiceProvider.php` (register `Blade::anonymousComponentPath(resource_path('views/emails'), 'email')` + `Event::listen(MessageSending::class, AddPlainTextPart::class)`)
- Test: `backend/tests/Unit/Mail/EmailFormatTest.php`, `backend/tests/Unit/Mail/HtmlToTextTest.php`

**Interfaces — Produces:**
- `config('email_design.accent')`, `config('email_design.logo.url|width|height')`, `config('email_design.company.name|registration|location')`
- `EmailFormat::money(float|int|string|null $v): string` · `EmailFormat::range($min, $max): string` (`RM 5,000 to RM 8,000`) · `EmailFormat::date(?CarbonInterface): ?string` · `EmailFormat::dateTime(?CarbonInterface): ?string`
- `HtmlToText::convert(string $html): string`

- [ ] **Step 1: Write failing tests**

```php
// tests/Unit/Mail/EmailFormatTest.php
public function test_money_drops_zero_cents_and_keeps_real_cents(): void
{
    $this->assertSame('RM 6,500', EmailFormat::money(6500));
    $this->assertSame('RM 6,500', EmailFormat::money('6500.00'));
    $this->assertSame('RM 1,234.50', EmailFormat::money(1234.5));
    $this->assertSame('RM 0', EmailFormat::money(null));
}
public function test_range_and_dates(): void
{
    $this->assertSame('RM 5,000 to RM 8,000', EmailFormat::range(5000, 8000));
    $this->assertSame('RM 5,000', EmailFormat::range(5000, 5000));
    $this->assertSame('10 Nov 2026', EmailFormat::date(Carbon::parse('2026-11-10')));
    $this->assertSame('11 Oct 2026, 8:46 am', EmailFormat::dateTime(Carbon::parse('2026-10-11 08:46')));
    $this->assertNull(EmailFormat::date(null));
}
// tests/Unit/Mail/HtmlToTextTest.php
public function test_converts_kit_html_to_readable_text(): void
{
    $html = '<html><head><style>a{color:red}</style></head><body>'
        .'<div style="display: none; max-height: 0;">Preheader</div>'
        .'<h1>Hello &amp; welcome</h1><p>Line one<br>Line two</p>'
        .'<table><tr><td>Label</td><td>Value</td></tr></table>'
        .'<a href="https://x.test/pdf">View PDF&nbsp;&rarr;</a></body></html>';
    $text = HtmlToText::convert($html);
    $this->assertStringNotContainsString('Preheader', $text);
    $this->assertStringNotContainsString('color:red', $text);
    $this->assertStringContainsString("Hello & welcome", $text);
    $this->assertStringContainsString("Line one\nLine two", $text);
    $this->assertStringContainsString('Label  Value', $text);
    $this->assertStringContainsString('View PDF → https://x.test/pdf', $text);
}
```

- [ ] **Step 2: Run — expect FAIL** (`class not found`): `docker compose -f docker-compose.dev.yml exec -T backend php artisan test --filter='EmailFormatTest|HtmlToTextTest'`

- [ ] **Step 3: Implement**

```php
// config/email_design.php
<?php

// Email look-and-feel (owner design kit: docs/global/email-kit/). The ONE place
// for the accent colour and the logo; every other style is fixed kit markup.
return [
    'accent' => '#4B45D6',

    // PNG only — Gmail drops SVG. Absolute URL: mail clients fetch it.
    'logo' => [
        'url' => env('EMAIL_LOGO_URL', rtrim((string) env('PUBLIC_SITE_URL', env('FRONTEND_URL', 'http://localhost:3003')), '/').'/email/axn-mark.png'),
        'width' => 16,
        'height' => 24,
    ],

    'company' => [
        'name' => 'Axel Nova Ventures',
        'registration' => '202603119899 (CA0420977-U)',
        'location' => 'Kuala Lumpur, Malaysia',
    ],
];
```

```php
// app/Support/Mail/EmailFormat.php
final class EmailFormat
{
    public static function money(float|int|string|null $value): string
    {
        $n = round((float) $value, 2);
        return 'RM '.number_format($n, fmod($n, 1.0) === 0.0 ? 0 : 2);
    }

    public static function range(float|int|string|null $min, float|int|string|null $max): string
    {
        return (float) $min === (float) $max
            ? self::money($max)
            : self::money($min).' to '.self::money($max);
    }

    public static function date(?CarbonInterface $at): ?string
    {
        return $at?->format('j M Y');
    }

    public static function dateTime(?CarbonInterface $at): ?string
    {
        return $at?->format('j M Y, g:i a');
    }
}
```

```php
// app/Support/Mail/HtmlToText.php
final class HtmlToText
{
    public static function convert(string $html): string
    {
        $html = preg_replace('#<(head|style|script)\b.*?</\1>#is', '', $html);
        $html = preg_replace('#<div[^>]*display:\s*none[^>]*>.*?</div>#is', '', $html);
        $html = preg_replace('#<!--.*?-->#s', '', $html);
        $html = preg_replace_callback('#<a\b[^>]*href="([^"]*)"[^>]*>(.*?)</a>#is', function ($m) {
            $label = trim(strip_tags($m[2]));
            $href = html_entity_decode($m[1]);
            return str_starts_with($href, 'mailto:') || $label === $href ? $label : "{$label} {$href}";
        }, $html);
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</(p|h[1-6]|tr|li|div)>#i', "\n\n", $html);
        $html = preg_replace('#</td>#i', '  ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $lines = array_map(fn ($l) => trim(preg_replace('/[ \t]{3,}/', '  ', $l)), explode("\n", $text));
        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }
}
```
(`→` comes from `&rarr;` decoding; `View PDF → https://…` is the button form.)

```php
// app/Listeners/Mail/AddPlainTextPart.php
final class AddPlainTextPart
{
    public function handle(MessageSending $event): void
    {
        $email = $event->message;
        $html = $email->getHtmlBody();
        if ($email->getTextBody() === null && is_string($html) && $html !== '') {
            $email->text(HtmlToText::convert($html));
        }
    }
}
```

Logo: decode the base64 PNG in `frontend/server/utils/pdf/logo.ts` (`STUDIO_LOGO`) to `frontend/public/email/axn-mark.png` (95×140 RGBA).

- [ ] **Step 4: Run tests — expect PASS.** Run the full suite — still 602+ green.

---

### Task 2 (A2): Layouts + blocks, verified against `00-blocks.html`

**Files:**
- Create: `backend/resources/views/emails/layouts/client.blade.php`, `.../layouts/internal.blade.php`, `.../blocks/{logo-header,heading-intro,heading-intro-internal,next-steps,order-tracker,summary-table,highlight-panel,section-label,line-items,main-button,whatsapp-button,warning-box,paragraph,signature,spacer}.blade.php`
- Create: `backend/tests/Support/KitHtml.php`, `backend/tests/Fixtures/views/kit-blocks.blade.php`, `backend/tests/Feature/Mail/KitBlocksFidelityTest.php`

**Interfaces — Produces (component props):**
- `<x-email::layouts.client :title :preheader>` slot = body · `<x-email::layouts.internal :title :preheader>`
- `heading-intro`: `eyebrow`, `heading`, slot = lead (HTML allowed via slot) · `heading-intro-internal`: same
- `next-steps`: `:steps="[['title'=>..,'text'=>..], …]"` (step 1 has the ink border, numbers auto `01…`)
- `order-tracker`: `:current="1..4"` (labels fixed: Confirmed, In progress, Delivered, Completed; `< current` → `0N · Done` + ink border; `= current` → `0N · Now` + accent border; `> current` → muted)
- `summary-table`: `:rows="['Label' => 'value', …]"` — `null` values are skipped; first rendered row carries the top border
- `highlight-panel`: `label`, `amount`, `note` (nullable)
- `section-label`: `text`
- `line-items`: `:items="[['label'=>..,'detail'=>?,'amount'=>?], …]"`, `:total="['label'=>'Total','amount'=>..]|null"` — priced when any item has `amount`, otherwise kit's `colspan="2"` form
- `main-button`: `url`, `label` (renders `label&nbsp;&rarr;`)
- `whatsapp-button`: `url`, `prompt`, `text` (default "The quickest way to reach me is a WhatsApp message."), `label` (default "Chat on WhatsApp")
- `warning-box`: `label`, `title`, `text`
- `paragraph`: slot (16/26, `margin: 32px 0 0 0`)
- `signature`: `signoff` (default "Talk soon,"; `null` omits the line)
- `spacer`: `:height`, `:lg="bool"` (adds `class="gap-lg"`)
- `KitHtml::normalize(string $html): string` · `KitHtml::kit(string $file): string` · `KitHtml::expected(string $file): string` (normalized kit file with the kit's text wordmark swapped for the rendered `blocks/logo-header`)

- [ ] **Step 1: Write the fidelity harness + failing test**

```php
// tests/Support/KitHtml.php
final class KitHtml
{
    public static function kit(string $file): string
    {
        // The dev container mounts ./backend only — tests read a byte-identical
        // copy of docs/global/email-kit/ (B12 checks the copies with `cmp`).
        return file_get_contents(base_path('tests/Fixtures/email-kit/'.$file));
    }

    public static function normalize(string $html): string
    {
        $html = preg_replace('/<!--(?!\[if).*?-->/s', '', $html);      // keep MSO conditionals
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/[ \t\r\n]+/', ' ', $html);
        $html = preg_replace('/>\s+</', '><', $html);
        return trim($html);
    }
}
```
Step 3 copies `docs/global/email-kit/*.html` to `backend/tests/Fixtures/email-kit/` unchanged.

```php
// tests/Feature/Mail/KitBlocksFidelityTest.php
public function test_every_block_matches_the_kit_catalogue(): void
{
    Carbon::setTestNow('2026-10-11 08:46');
    View::addLocation(base_path('tests/Fixtures/views'));
    $rendered = view('kit-blocks')->render();
    $this->assertSame(KitHtml::normalize(KitHtml::kit('00-blocks.html')), KitHtml::normalize($rendered));
}
```
`tests/Fixtures/views/kit-blocks.blade.php` composes every block with the kit's placeholder text, inside the client layout with `title="Axel Nova email blocks"`, the kit preheader, and the logo header swapped back to the kit wordmark for this one fixture (layout prop `:wordmark="true"`).

- [ ] **Step 2: Run — FAIL** (`View [kit-blocks] not found`).

- [ ] **Step 3: Implement layouts + blocks** by copying each `BLOCK:` region of `00-blocks.html` verbatim into its component and replacing only text/values with props; `$accent = config('email_design.accent')` replaces every `#4B45D6`. Client layout = kit lines 1–63 (head + preheader + container + accent bar + header + 48px gap) and 239–260 (48px gap + footer + closers) with `{{ now()->year }}` for the © year and the company config for the footer text. Logo header (`blocks/logo-header`):

```blade
<table role="presentation" cellpadding="0" cellspacing="0" border="0">
  <tr>
    <td valign="middle" style="padding: 0 10px 0 0;"><img src="{{ config('email_design.logo.url') }}" width="{{ config('email_design.logo.width') }}" height="{{ config('email_design.logo.height') }}" alt="" style="display: block; border: 0; outline: none; text-decoration: none; width: {{ config('email_design.logo.width') }}px; height: {{ config('email_design.logo.height') }}px;"></td>
    <td valign="middle" style="font-size: 15px; line-height: 22px; font-weight: 600; letter-spacing: -0.01em; color: #14161A;">{{ config('email_design.company.name') }}</td>
  </tr>
</table>
```
Internal layout = `28-delivery-alert.html` lines 1–68 and 128–146 (ink bar `#14161A`, `Internal` tag, `padding: 28px 40px 36px 40px`, internal footer).

- [ ] **Step 4: Run — PASS.** Full suite green.

---

### Task 3 (A3): Catalog, samples, `mail:preview`

**Files:**
- Create: `backend/app/Support/Mail/EmailCatalog.php`, `backend/app/Support/Mail/EmailSamples.php`, `backend/app/Mail/PreviewMail.php`, `backend/app/Console/Commands/MailPreview.php`
- Test: `backend/tests/Feature/Mail/EmailCatalogTest.php`

**Interfaces — Produces:**
- `EmailCatalog::all(): array<string, array{label: string, audience: 'client'|'partner'|'team'|'admin', view: string, subject: string}>` · `EmailCatalog::get(string $type): array` · `EmailCatalog::label(string $type): string`
- `EmailSamples::for(string $type): array` (view data)
- `php artisan mail:preview {type?} {--all} {--to=}` — default recipient `config('services.admin.email')`; subject `"[Preview] {subject}"`

- [ ] **Step 1: Failing test** — every catalog entry's view exists and renders with its sample data without error, and `mail:preview --all` sends 33 messages:

```php
public function test_every_catalog_type_renders_with_its_samples(): void
{
    foreach (EmailCatalog::all() as $type => $def) {
        $html = view($def['view'], EmailSamples::for($type))->render();
        $this->assertStringContainsString('<!DOCTYPE html>', $html, $type);
    }
    $this->assertCount(33, EmailCatalog::all());
}

public function test_preview_command_sends_every_type(): void
{
    Mail::fake();
    $this->artisan('mail:preview', ['--all' => true])->assertSuccessful();
    Mail::assertSent(PreviewMail::class, 33);
}
```

- [ ] **Step 2: Run — FAIL.**
- [ ] **Step 3: Implement** the catalog (table above, subjects = the mailables' existing subjects for existing emails; new ones in their template task) and `PreviewMail` (`view:` the catalog view, `with:` the samples). Catalog entries are added task by task; the count assertion is updated in each template task (`33` once Task A8 lands — until then assert `count` equals the number of views built so far).
- [ ] **Step 4: PASS.**

---

### Task 4 (A4): Email 1 split — estimate (01a) + quotation (01b)

**Files:**
- Create: `backend/app/Mail/ClientEstimateMail.php`, `backend/app/Mail/ClientQuotationMail.php`, `backend/resources/views/emails/quotation/{estimate,sent}.blade.php`
- Delete: `backend/app/Mail/ClientQuoteMail.php`, `backend/resources/views/mail/client-quote.blade.php`
- Modify: `backend/app/Jobs/SendClientQuoteEmail.php` (`$this->revisionId === null` → `ClientEstimateMail`, else `ClientQuotationMail`)
- Test: `backend/tests/Feature/Mail/QuoteEmailsTest.php`

**Data contracts:**
- `quotation.estimate`: `name, reference, packageName, timeline, validUntil, range, included: list<string>, addons: list<{label, amount}>, pdfUrl: ?string, whatsappUrl: ?string`
- `quotation.sent`: `name, reference, total, timeline, validUntil, items: list<{label, detail: ?string, amount}>, pdfUrl: ?string, whatsappUrl: ?string`

Mapping: `packageName` = names of every package in `normalizedForm()['packages']` joined with ` + ` (via `PricingEngine::active()->packageName(key)`); `included` = each package's `service_packages.features` (multi-package: each feature prefixed `"{Package}: "`); `addons` = `$quote->addons` (`addon_label`, `amount_myr`); `items` = `DocumentMapper::toDocumentData($quote)['items']` → `label=title, detail=desc, amount=qty×rate`; `total` = `$quote->finalAmount()`; detailed-layout quotes (no `items`) render the total panel without the line-items block. `pdfUrl`/`whatsappUrl` exactly as in today's `ClientQuoteMail`. Subject for both: today's `"Your quote {displayReference} from Axel Nova Ventures"`.

- [ ] **Step 1: Failing tests**

```php
public function test_estimate_matches_kit(): void
{
    Carbon::setTestNow('2026-10-11 08:46');
    $html = view('emails.quotation.estimate', EmailSamples::for('quotation.estimate'))->render();
    $this->assertSame(KitHtml::expected('01a-estimate.html'), KitHtml::normalize($html));
}
public function test_quotation_matches_kit(): void { /* same for 01b-quotation.html */ }

public function test_funnel_sends_estimate_and_admin_send_sends_quotation(): void
{
    // funnel: SendClientQuoteEmail::dispatchSync($q->id) → Mail::assertSent(ClientEstimateMail::class)
    // admin:  SendClientQuoteEmail::dispatchSync($q->id, $revision->id) → Mail::assertSent(ClientQuotationMail::class)
}

public function test_client_text_is_escaped(): void
{
    $data = ['name' => "O'Brien & Co <Ltd>"] + EmailSamples::for('quotation.estimate');
    $html = view('emails.quotation.estimate', $data)->render();
    $this->assertStringContainsString('O&#039;Brien &amp; Co &lt;Ltd&gt;', $html);
}

public function test_optional_blocks_hide(): void
{
    $data = ['pdfUrl' => null, 'whatsappUrl' => null, 'addons' => []] + EmailSamples::for('quotation.estimate');
    $html = view('emails.quotation.estimate', $data)->render();
    $this->assertStringNotContainsString('Download PDF', $html);
    $this->assertStringNotContainsString('Chat on WhatsApp', $html);
    $this->assertStringNotContainsString('Add-ons', $html);
}

public function test_multi_package_estimate_lists_every_package(): void
{
    // build a quotation whose normalizedForm has two packages → mailable data packageName contains both names joined by ' + '
}
```
`KitHtml::expected($file)` = normalized kit file with the kit wordmark `<p …>Axel Nova Ventures</p>` replaced by the normalized rendered `blocks/logo-header`.

- [ ] **Step 2: FAIL.** - [ ] **Step 3: Implement** (block order and spacer heights copied from the kit file). - [ ] **Step 4: PASS**, full suite green.

---

### Task 5 (A5): Inquiry received (02, four variants) + invoice (03)

**Files:**
- Create: `backend/resources/views/emails/inquiry/received.blade.php`, `backend/resources/views/emails/invoice/sent.blade.php`
- Modify: `backend/app/Mail/InquiryReceivedMail.php`, `backend/app/Mail/InvoiceMail.php`; make `DocumentMapper::BANK` public
- Delete: `backend/resources/views/mail/{inquiry-received,client-invoice}.blade.php`
- Test: `backend/tests/Feature/Mail/InquiryAndInvoiceEmailsTest.php`; keep `PublicInquiryIntakeTest` + `PartnerInquiryIntakeTest` + `InvoiceSendTest` green

**Data contracts:**
- `inquiry.received`: `name, variant ('contact'|'project'|'partner'|'collaboration'), partnerName: ?string, partnerFounder: ?string, rows: array<label, value>, whatsappUrl: ?string`. Variant leads and step-3 timing come from today's copy (contact: "usually within one working day"; project/partner: "usually within 1–2 business days"; collaboration: "usually within a few working days"); kit 02 is the contact variant. `rows` = today's hints (Partner, Subject, Project type, Budget, Timeline) + `Received` (date-time).
- `invoice.sent`: `name, invoiceNumber, projectName: ?string, amountDue, dueDate: ?string, quoteRef: ?string, issuedDate: ?string, bank: {name, holder, account}, onlinePaymentLink: ?string (always null until a gateway exists → row hidden), pdfUrl, attached: bool`. Lead says "The PDF is attached to this email and linked below." when attached, else "The PDF is linked below."

- [ ] **Step 1: Failing tests** — kit fidelity for both (invoice rendered with `onlinePaymentLink = '[online payment link]'`, `bank.holder = '[account name]'`, `bank.account = '[account number]'` so it reproduces the kit file), plus: `onlinePaymentLink = null` hides the "Card or FPX" row; no `dueDate` hides the Due row and the panel note; `attached = false` changes the lead; existing "tailored quote" assertions still hold for the project variant.
- [ ] **Step 2: FAIL.** - [ ] **Step 3: Implement.** - [ ] **Step 4: PASS**, full suite green.

---

### Task 6 (A6): Receipt (06), delivered (09), delivery alert (28) templates

**Files:**
- Create: `backend/resources/views/emails/payment/receipt.blade.php`, `.../order/delivered.blade.php`, `.../admin/delivery-alert.blade.php`
- Test: `backend/tests/Feature/Mail/KitTemplatesFidelityTest.php`

**Data contracts:**
- `payment.receipt`: `name, amountPaid, paidDate, methodLabel, receiptNumber, paymentReference: ?string, invoiceNumber: ?string, balanceLeft, receiptUrl`
- `order.delivered`: `name, orderNumber, packageName, handoverItems: list<string>, reviewWindow: string, balance: ?{amount, invoiceNumber, dueDate}, invoiceUrl: ?string`
- `system.delivery_alert`: `emailLabel, problem ('bounced'|'complained'|'failed'), recordLabel, recipient, sentAt: ?string, reason: ?string, fix, recordUrl` — heading/lead/label vary by `problem` (kit = bounced)

(Mailables for 06/09 arrive in Phase 3; 28's mailable in Task B5.)

- [ ] **Step 1: Failing tests** — kit fidelity for 06 (with `[receipt no]`/`[payment reference]` placeholders), 09 (`[review window]`), 28; `paymentReference = null` hides the row; `balance = null` hides the panel and the button.
- [ ] **Step 2: FAIL.** - [ ] **Step 3: Implement.** - [ ] **Step 4: PASS.**

---

### Task 7 (A7): Re-skin the remaining existing emails; remove markdown mail

**Files:**
- Create: `emails/feedback/request`, `emails/referral/{received,commission}`, `emails/partner/passcode`, `emails/team/{welcome,password-reset}`, `emails/admin/{new-lead,new-inquiry,partner-reset,team-reset-request}` (`.blade.php`)
- Modify: `FeedbackRequestMail`, `ReferralReceivedMail`, `ReferralCommissionMail`, `PartnerPasscodeMail`, `TeamWelcomeMail`, `TeamPasswordResetMail`, `AdminNotificationMail`, `InquiryAdminNotificationMail`, `PartnerResetRequestedMail`, `TeamPasswordResetRequestedMail` → `view:` with presentation data
- Delete: `backend/resources/views/mail/` (all remaining markdown templates + `default.css`)
- Test: `backend/tests/Feature/Mail/ExistingEmailsCopyTest.php`

**Rules:** every sentence of today's copy survives verbatim (sign-offs too: "Best,", "Thanks for building with me,", "Welcome aboard — let's do great things."); new text is limited to eyebrow labels, preheaders and section/row labels, each recorded in the copy-review list. Credentials (`passcode`, `password`) render in a summary-table row.

- [ ] **Step 1: Failing test** — for each mailable, render with a factory model and assert every sentence of the old template (captured as string fixtures in the test before deletion) is present, and that `resources/views/mail` no longer exists.
- [ ] **Step 2: FAIL.** - [ ] **Step 3: Implement.** - [ ] **Step 4: PASS**, full suite green (existing mail tests included).

---

### Task 8 (A8): New templates (Phases 2–6) with samples

**Files:** Create the 16 views for types 5, 7, 8, 10–14, 16, 19, 22, 23, 29–32 (paths per catalog) + their catalog entries and samples.

**Content outline** (copy drafted in the founder's voice, all marked new in the review list):

| Type | Blocks |
|---|---|
| `order.confirmed` | heading-intro · order-tracker(1) · summary-table (Order, Package, Total, Deposit, Deposit due) · next-steps (Deposit invoice / Kick-off / Build) · main-button View quotation · signature |
| `payment.refund` | heading-intro · highlight-panel (Refunded, amount, method + date) · summary-table (Original payment, Refund ref, Method) · paragraph timing · signature |
| `order.started` | heading-intro · order-tracker(2) · summary-table (Order, Package, Expected delivery) · whatsapp-button · signature |
| `order.completed` | heading-intro · order-tracker(4) · paragraph thanks · main-button **Share your feedback** · signature |
| `order.cancelled` | heading-intro · summary-table (Order, Cancelled on, Refund — when applicable) · paragraph · signature |
| `invoice.due_soon` | heading-intro · highlight-panel (Amount due, due date) · summary-table (Invoice, Due) · main-button View invoice · signature |
| `invoice.overdue` | heading-intro · warning-box (days overdue) · highlight-panel · same "How to pay" rows as `invoice.sent` · main-button · signature |
| `quotation.expiring` | heading-intro · highlight-panel (Total, valid until) · main-button View quotation · whatsapp-button · signature |
| `referral.update` | heading-intro · summary-table (Business, Status) · paragraph per status · main-button Open portal · signature |
| `partner.reset_confirm` | heading-intro · main-button Confirm reset · warning-box ("Didn't ask? Ignore this email") · signature |
| `task.assigned` | heading-intro · summary-table (Task, Deadline, Priority, Pay) · main-button Open task · signature |
| `payroll.payslip` | heading-intro · highlight-panel (Gross, period) · line-items (Allowance, Task extras, Discretionary, total) · summary-table (Paid on, Method) · main-button View payslip · signature |
| `referral.new_alert` | internal heading · summary-table (Referrer, Business, Contact, Email, Phone, Tier) · main-button Open referral |
| `feedback.submitted_alert` | internal heading · summary-table (From, Overall, NPS, Publish consent) · section-label + paragraph (praise / improve) · main-button Review feedback |
| `quotation.expired_alert` | internal heading · summary-table (Quote, Client, Value, Expired on) · main-button Open quote |
| `admin.account_recovery` | internal heading · main-button Reset password · warning-box (link expiry, ignore if not you) |

- [ ] **Step 1: Failing test** — `EmailCatalogTest` asserts 33 types; each new view renders with samples; each client-layout one contains the signature; each internal one contains "Internal".
- [ ] **Step 2: FAIL.** - [ ] **Step 3: Implement.** - [ ] **Step 4: PASS**, full suite green; `mail:preview --all` sends 33 to Mailtrap.

---

# PART B — Foundation (spec §5.1–5.12)

### Task 9 (B1): Sending routes — Resend + Hostinger failover, route tagging

**Files:**
- Create: `backend/config/mail.php` (`php artisan config:publish mail`, then edit), `backend/app/Support/Mail/RouteTaggingTransport.php`, `backend/app/Support/Mail/MailRouteRecorder.php`
- Modify: `AppServiceProvider` (`Mail::extend('tagged', …)`, singleton recorder), `config/services.php` (`resend.webhook_secret`), `backend/.env.example`, `backend/.env.production.example`
- Test: `backend/tests/Feature/Mail/MailRoutesTest.php`

**Interfaces — Produces:** `app(MailRouteRecorder::class)->pull(): ?string` (returns + clears the last route); mailers `resend`, `hostinger`, `failover`.

```php
// config/mail.php (mailers excerpt)
'resend' => ['transport' => 'tagged', 'route' => 'resend', 'inner' => [
    'transport' => 'smtp', 'host' => 'smtp.resend.com', 'port' => 587, 'encryption' => 'tls',
    'username' => 'resend', 'password' => env('RESEND_SMTP_KEY'), 'timeout' => 30,
]],
'hostinger' => ['transport' => 'tagged', 'route' => 'hostinger', 'inner' => [
    'transport' => 'smtp', 'host' => env('MAIL_HOST', 'smtp.hostinger.com'), 'port' => env('MAIL_PORT', 587),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'), 'username' => env('MAIL_USERNAME'), 'password' => env('MAIL_PASSWORD'), 'timeout' => 30,
]],
'failover' => ['transport' => 'failover', 'mailers' => ['resend', 'hostinger']],
```

```php
// AppServiceProvider::boot
$this->app->singleton(MailRouteRecorder::class);
Mail::extend('tagged', fn (array $config) => new RouteTaggingTransport(
    app('mail.manager')->createSymfonyTransport($config['inner']),
    $config['route'],
    app(MailRouteRecorder::class),
));
```

```php
final class RouteTaggingTransport implements TransportInterface
{
    public function __construct(private TransportInterface $inner, private string $route, private MailRouteRecorder $recorder) {}
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $sent = $this->inner->send($message, $envelope);
        $this->recorder->record($this->route);
        return $sent;
    }
    public function __toString(): string { return "tagged({$this->route}):".$this->inner; }
}
```

- [ ] **Step 1: Failing test** — wrap two `ArrayTransport`-like fakes: a failover whose first transport throws `TransportException` records `hostinger`; a healthy first records `resend`.
- [ ] **Step 2: FAIL.** - [ ] **Step 3: Implement.** - [ ] **Step 4: PASS.** Dev keeps `MAIL_MAILER=smtp`; tests keep `array`.

---

### Task 10 (B2): `email_logs` + `LoggableEmail` on every mailable

**Files:**
- Create: migration `create_email_logs_table`, `backend/app/Models/EmailLog.php`, `backend/app/Mail/Contracts/LoggableEmail.php`
- Modify: every `app/Mail/*.php` (implement the interface), `AppServiceProvider` (`Relation::morphMap` short aliases — `quotation, invoice, inquiry, feedback, referral, external_account, user, order, payment, email_log` — **`enforceMorphMap` is NOT enabled**, existing morphs keep class names)
- Test: `backend/tests/Feature/Mail/LoggableEmailCoverageTest.php`, `backend/tests/Unit/Mail/EmailLogStatusTest.php`

**Interfaces — Produces:**
```php
interface LoggableEmail
{
    public static function emailType(): string;          // catalog key
    public static function relatedProperty(): ?string;   // public property holding the related model
}
// EmailLog
public const RANK = ['sent' => 0, 'delayed' => 1, 'delivered' => 2, 'bounced' => 3, 'failed' => 3, 'complained' => 4];
public function advanceTo(string $status, ?string $detail = null): bool; // forward-only; updates status_changed_at
public function isProblem(): bool;                                        // bounced|complained|failed
```
Columns per spec §5.2 (`subject`, `route`, `sent_at` nullable; `status_detail` text).

- [ ] **Step 1: Failing tests** — every class in `app/Mail` (except `PreviewMail`, which is logged as `system.preview`) implements `LoggableEmail` and its `emailType()` exists in `EmailCatalog`; `advanceTo('delivered')` after `bounced` returns false and keeps `bounced`; `advanceTo('complained')` after `delivered` succeeds.
- [ ] **Step 2: FAIL.** - [ ] **Step 3: Implement** + `php artisan migrate` (additive). - [ ] **Step 4: PASS.**

---

### Task 11 (B3): Capture — sent rows, failed rows, actor

**Files:**
- Create: `backend/app/Listeners/Mail/StampMessageId.php` (MessageSending), `backend/app/Listeners/Mail/RecordSentEmail.php` (MessageSent), `backend/app/Listeners/Mail/RecordFailedQueuedEmail.php` (JobFailed), `backend/app/Services/Mail/EmailLogger.php`, `backend/app/Http/Middleware/RecordMailActor.php`
- Modify: `SendClientQuoteEmail`, `NotifyAdminJob`, `SendInvoiceEmail`, `RequestFeedbackJob` (add `failed(Throwable $e)`); `routes/api.php` (add `RecordMailActor` to the admin + team groups); `AppServiceProvider` (listeners)
- Test: `backend/tests/Feature/Mail/EmailCaptureTest.php`

**Interfaces — Produces:**
```php
EmailLogger::recordSent(MessageSent $event): ?EmailLog
EmailLogger::recordFailure(string $mailableClass, ?Model $related, string $toEmail, ?string $toName, ?string $subject, Throwable $e): EmailLog
```
Rules: Message-ID `<{uuid}@axelnova.tech>` set only when absent; `message_id` stored without brackets; `provider_id` = `$sent->getMessageId()` when it differs; `route` = `MailRouteRecorder::pull() ?? config('mail.default')`; `triggered_by` = `Context::getHidden('mail_actor_id')`; type/audience/related from `__laravel_mailable` + `LoggableEmail` + catalog; unknown mailables → class basename, audience `admin` when sent to `services.admin.email` else `client`. Listener bodies wrapped in `try { … } catch (Throwable $e) { report($e); }`.

- [ ] **Step 1: Failing tests** (use `MAIL_MAILER=array`, real send — not `Mail::fake()`):
  - sending `InvoiceMail` writes one `sent` row linked to the invoice, audience `client`, recipient, subject, route `array`;
  - an admin request that sends mail records `triggered_by`; a public form send records `null`;
  - a queued mailable whose job fails on the last attempt writes one `failed` row; earlier attempts write none;
  - **logging failure does not block sending:** bind a throwing `EmailLogger`, send → message still delivered (array transport has 1 message).
- [ ] **Step 2: FAIL.** - [ ] **Step 3: Implement.** - [ ] **Step 4: PASS**, full suite green.

---

### Task 12 (B4): Resend webhook

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/Webhooks/ResendWebhookController.php`, `backend/app/Services/Mail/ResendSignature.php`, `backend/app/Services/Mail/ResendEventMatcher.php`
- Modify: `routes/api.php` (`POST /v1/webhooks/resend`, own throttle group `throttle:600,1`)
- Test: `backend/tests/Feature/Mail/ResendWebhookTest.php`

**Interfaces:** `ResendSignature::verify(string $id, string $timestamp, string $signatureHeader, string $body, string $secret, ?int $now = null): bool` (HMAC-SHA256 of `"{id}.{timestamp}.{body}"` with `base64_decode(substr($secret, 6))`, header holds space-separated `v1,<base64>` entries, timestamp tolerance 300s) · `ResendEventMatcher::match(array $data): ?EmailLog` (message_id → email_id → to+subject within 10 min of `data.created_at`).

Event map: `email.sent` none · `email.delivery_delayed` → `delayed` · `email.delivered` → `delivered` · `email.bounced` → `bounced` (detail `data.bounce.message`) · `email.suppressed` → `bounced` ("Suppressed by Resend — this address bounced or complained before") · `email.failed` → `failed` · `email.complained` → `complained`. Store `provider_id` from `data.email_id` on first match.

- [ ] **Step 1: Failing tests** — valid signature → 200 + status advanced; bad signature → 401; stale timestamp → 401; each matching step; out-of-order (`delivered` after `bounced`) ignored; repeated event idempotent; unmatched event → 200, no changes; unknown event type → 200.
- [ ] **Step 2: FAIL.** - [ ] **Step 3: Implement.** - [ ] **Step 4: PASS.**

---

### Task 13 (B5): Delivery alerts

**Files:**
- Create: `backend/app/Mail/EmailDeliveryAlertMail.php` (view `emails.admin.delivery-alert`, type `system.delivery_alert`, related `log`), `backend/app/Services/Mail/DeliveryAlerts.php`
- Modify: `ResendWebhookController` + `EmailLogger::recordFailure` → call `DeliveryAlerts::maybeAlert($log)`
- Test: `backend/tests/Feature/Mail/DeliveryAlertsTest.php`

Rule: alert iff `$log->isProblem() && $log->audience !== 'admin' && $log->alerted_at === null` → `Mail::to(services.admin.email)->queue(new EmailDeliveryAlertMail($log))`, then set `alerted_at`. `recordUrl` = admin URL of the related record (`/admin/{quotations|invoices|inquiries|feedback|referrals|users|clients}/{id}`).

- [ ] **Step 1: Failing tests** — bounce on a client email queues one alert; a second bounce/complaint event does not queue another; an admin-audience bounce queues none; a `failed` row from Task B3 alerts.
- [ ] **Step 2: FAIL.** - [ ] **Step 3: Implement.** - [ ] **Step 4: PASS.**

---

### Task 14 (B6): Queue consistency

**Files:**
- Modify: `ReferralCommissionMail`, `TeamPasswordResetRequestedMail`, `PartnerResetRequestedMail` (`implements ShouldQueue`); `ReferralsController` + `Team/AuthController` + `Partner/AuthController` response messages ("queued — delivery shows on the record"); `QuoteRequestController` (`NotifyAdminJob` delay only when `! app()->isProduction()`)
- Test: update existing assertions `assertSent` → `assertQueued` for those three; add a test that production dispatches `NotifyAdminJob` without delay.

- [ ] Steps: failing tests → implement → full suite green. `PartnerPasscodeMail` stays synchronous (plaintext passcode must not enter the `jobs` table).

---

### Task 15 (B7): Scheduler

**Files:**
- Create: `backend/app/Console/Commands/ExpireOverdueQuotations.php` (`quotations:expire-overdue` → `Quotation::expireOverdue()`)
- Modify: `backend/routes/console.php` (`Schedule::command('quotations:expire-overdue')->hourly()->withoutOverlapping();`), `docker-compose.dev.yml` (`scheduler` service mirroring `worker`, command `php artisan schedule:work`), `docker-compose.prod.yml` + `docker-compose.prod.ghcr.yml` (`scheduler` service mirroring `queue`: same image/env/volumes/network, `healthcheck: disable: true`, memory limit 128M, `container_name: axelnova-scheduler`), `.github/workflows/deploy-ghcr.yml` (`up -d --no-deps backend frontend queue scheduler`), `.github/workflows/deploy.yml` (already `up -d --build` — picks the new service up; no change)
- Test: `backend/tests/Feature/Quotations/ExpireOverdueCommandTest.php` — a sent revision past `expires_at` becomes expired; a future one doesn't; `schedule:list` contains the command.

- [ ] Steps: failing test → implement → PASS. Do **not** start new containers in dev without telling the owner (it's additive; `docker compose up -d scheduler` is safe and is done at hand-off only if the owner agrees).

---

### Task 16 (B8): Admin emails endpoint

**Files:**
- Create: `backend/app/Http/Controllers/Api/V1/Admin/EmailLogsController.php`, `backend/app/Http/Resources/EmailLogResource.php`
- Modify: `routes/api.php` (`GET /emails` inside the cockpit group, name `admin.emails.index`)
- Test: `backend/tests/Feature/Mail/AdminEmailLogsTest.php`

Query: `related_type` (in morph aliases) + `related_id`, **or** `email`; newest first; paginate 25. Resource: `id, type, type_label, audience, to_email, to_name, subject, status, status_label, status_detail, route, triggered_by {id, name}|null, sent_at, status_changed_at, resend_url` (`https://resend.com/emails/{provider_id}` when known).

- [ ] **Step 1: Failing tests** — filters; pagination; cockpit-only (team token 403, guest 401); validation error without a filter.
- [ ] Steps 2–4.

---

### Task 17 (B9): `EmailHistory.vue` panel

**Files:**
- Create: `frontend/app/components/admin/EmailHistory.vue`, `frontend/app/utils/emailStatus.ts` (status → label + pill colour), `frontend/app/utils/emailStatus.test.ts`
- Modify: `frontend/app/pages/admin/{quotations,invoices,inquiries,feedback,referrals,users,clients}/[id].vue` (one panel each; client page passes `email`)
- Test: Vitest for `emailStatus.ts`

Props: `relatedType?: string`, `relatedId?: number`, `email?: string`. Uses `useApi()` like sibling admin components. Rows: status pill (`AdminStatusPill` pattern / design tokens only), type label, recipient, time, reason, "via Hostinger backup" note, "View in Resend" link. Empty state "No emails sent yet." Light + dark verified by tokens (no hex).

- [ ] Steps: failing Vitest → implement → ESLint + `nuxt typecheck` + Vitest green.

---

### Task 18 (B10): `mail:test` command

**Files:** Create `backend/app/Console/Commands/MailTest.php`, `backend/app/Mail/TestMail.php` (type `system.test`, internal layout, one paragraph). Test: command sends to the given address and prints the resulting `email_logs` row (route, message_id, provider_id).

---

### Task 19 (B11): Docs + env

**Files:**
- Create: `docs/global/EMAIL-SYSTEM.md` (catalog of all 33 types: trigger, audience, related record, status; routes + failover; log + status ranks; webhook; alerts; scheduler; template system — layouts, blocks, config, kit; `mail:preview`/`mail:test`; how to add a new email)
- Modify: `.claude/CLAUDE.md` (authoritative-docs list), `docs/global/DEPLOY.md` (scheduler container, Resend env vars, webhook URL, go-live checks), `docs/global/ARCHITECTURE.md` (table, routes, email row), `docs/global/README.md` + `docs/global/PLATFORM-OVERVIEW.md` (provider + Mailtrap-delay notes), `backend/.env.example`, `backend/.env.production.example` (`RESEND_SMTP_KEY`, `RESEND_WEBHOOK_SECRET`, `EMAIL_LOGO_URL`)

---

### Task 20 (B12): Full verification + hand-off

- [ ] `docker compose -f docker-compose.dev.yml exec -T backend ./vendor/bin/pint --test`
- [ ] `docker compose -f docker-compose.dev.yml exec -T backend php artisan test` — all green
- [ ] `docker compose -f docker-compose.dev.yml exec -T frontend npx eslint .` · `npx nuxi typecheck` · `npx vitest run`
- [ ] `php artisan mail:preview --all` → 33 messages in Mailtrap
- [ ] `cmp` every `docs/global/email-kit/*.html` against `backend/tests/Fixtures/email-kit/` (identical)
- [ ] Hand-off to owner: Mailtrap check at desktop + 390px; the copy-review list (every new/changed line, per email); no commits made.
