---
name: seed-data
description: Use when the local dev database needs realistic demo data — empty DB after a reset, testing admin flows that need inquiries/quotations/orders/invoices/payments, or demoing dashboards. Also use when asked to "seed", "create test data", or "populate the database".
---

# Seed Data (axelnova-dashboard)

## Overview

Seed realistic dev data through the SAME domain paths production uses. The
reusable tool is `backend/database/seeders/DemoDataSeeder.php` — five personas
spanning the whole lifecycle (fresh inquiry → draft → sent → accepted →
deposit-paid → fully paid).

```bash
docker compose -f docker-compose.dev.yml exec backend \
  php artisan db:seed --class=DemoDataSeeder
```

Idempotent per persona (existing demo-client email = skip). **Strictly
additive — seeding NEVER deletes, truncates, or resets anything.** The
database-protection rule in CLAUDE.md applies in full.

## Iron rules (why raw inserts break this app)

1. **Reference codes** (`AXNQ-/AXNO-/AXNI-/AXNR-/AXNP-`) only via
   `ReferenceCodeGenerator::generate(DocumentType::X)` — per-type yearly
   counters with `lockForUpdate()`. Hand-minted strings corrupt the sequence.
2. **`form_payload` must be the canonical multi-package shape** —
   `packages[]` (with resolved `service_package_id`), top-level `rush`,
   grouped `breakdown`, `source_meta.created_via`. Copy the recipe in
   `DemoDataSeeder::makeQuotation()`, which mirrors
   `QuoteRequestController::store`. Verify after seeding:
   `Quotation::first()->normalizedForm()['packages']` must be non-empty.
3. **Never write paid caches** (`orders.amount_paid_myr`,
   `invoices.amount_paid`/`status`/`paid_at`). Create Payment rows via
   `PaymentService::record($order, [...])` — `PaymentObserver` derives the rest.
4. **Invoices via `DocumentIssuer::issueInvoice($order, ['invoiceType' =>
   'deposit'|'final', 'amount' => X, 'notes' => ...])`** — freezes the payload
   snapshot the PDF renders.
5. **Orders via the accept recipe** (`QuotationsController::accept`): flip the
   quotation to `accepted`, then `Order::create` with the generator code,
   `finalAmount()`, `depositPct()`, `dueDateFrom()`.
6. **Pricing from the engine, never hardcoded**: `PricingEngine::active()` +
   `QuoteRequestInput` + `calculate()`. Package keys come from the active
   `pricing_configs.config['base_packages']` (e.g. `web_business`,
   `saas_mvp_sprint`, `dash_starter`); modifiers/addons must exist in that
   config. Catalog must be seeded first (`ServiceCategoriesSeeder` etc. via
   the default `db:seed`).

## Extending

Add personas to `DemoDataSeeder::personas()` — each needs a unique `.demo`
email (the idempotency key), a real `package_key`, and a lifecycle combo
(`inquiry_status` / `quotation_status` / `billing`). Backdate with
`forceFill(['created_at' => ...])->saveQuietly()` so observers don't refire.

## Verify after seeding

```php
// counts + observer-derived caches; run in tinker
Order::with('invoices')->get()
    ->each(fn ($o) => print("{$o->order_number} paid={$o->amount_paid_myr}\n"));
```

Expect: paid orders show non-zero `amount_paid_myr`, fully-covered invoices
show `status=paid` — written only by `PaymentObserver`.

## Common mistakes

- Raw `DB::table()->insert` for quotations → legacy-shaped `form_payload`
  that only works because the normalizer is forgiving; addons/breakdown missing.
- Setting `invoices.status = 'paid'` directly → drift bug the ledger exists
  to prevent; the next observer recompute overwrites it.
- Reusing a persona email with different data → silently skipped, not updated.
- Running with `--class=DatabaseSeeder` instead — that's the base seeder
  (catalog/admin), not demo data; it's also safe, but not what you want.
