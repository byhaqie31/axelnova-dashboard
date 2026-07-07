# Quote Builder — Pricing & Catalog Guide

This document explains how the public `/quote` builder is wired together: where data lives, how prices are calculated, and how to add or change packages.

---

## Two-layer architecture (hybrid)

There are two sources of truth, by design:

| Layer | Storage | Owns | Managed by |
|---|---|---|---|
| **Catalog** (admin-managed) | `service_categories` + `service_packages` + `service_addons` + `service_scope_fields` tables | Categories, package names, taglines, prices, ETA, features, CTA, deep-link `quote_key`; add-on labels + prices; **per-category scope fields (slider/select/toggle) + their pricing** | Admin UI under `/admin/services` (categories, packages, add-ons, scope fields) |
| **Pricing rules** (engineered config) | `pricing_configs.config` JSON (one active row) | `rush_multiplier`, `currency`, `valid_for_days`, plus fallback `base_packages` / `addons` / `modifiers` maps for any legacy keys | SQL insert + auto cache-clear |

The `PricingEngine` merges these at request time:

1. Start with `pricing_configs.active.config.base_packages` (legacy / fallback entries).
2. Layer admin-managed `service_packages` on top, keyed by `quote_key.package`. DB rows override any matching JSON entry.
3. Add-ons merge the same way: `service_addons` rows over the JSON `addons` map. A DB row *claims* its key — an **active** row appears (in `sort_order`), an **inactive** row removes the key entirely (no JSON fallback), and a JSON key with no DB row still resolves. See `PricingEngine::buildAddons()`.
4. **Scope fields** (`service_scope_fields`) define both the builder's per-category inputs and their pricing — superseding the JSON `modifiers` map. `calculate()` evaluates the selected package's category fields (gated by `applies_to`); `buildScopeFields()` groups them by category slug for the config endpoint. The JSON `modifiers` loop is kept as a one-release fallback for legacy `modifiers` payloads. See **Scope fields** below.
5. Rush stays in `pricing_configs.config`.

A `service_packages` row counts as "quotable" only if it has both a non-null `quote_key` **and** a non-null `price_max_myr`. Custom-quote and retainer rows (null `quote_key`) appear on the public services page but never in the quote builder.

---

## How `/api/v1/quote-builder/config` responds

```jsonc
{
  "version": "2026.05.01",
  "categories": [                              // ← built from service_categories + service_packages
    {
      "key": "web",                            // service_categories.slug
      "label": "Web Presence",
      "icon": "i-lucide-globe",
      "packages": [
        { "key": "web_business", "name": "Business", "tagline": "..." }
      ]
    }
  ],
  "base_packages": {                           // ← merged: pricing_configs JSON + service_packages
    "web_business": { "min": 3500, "max": 5500, "eta_value": 2, "eta_unit": "week" }
  },
  "modifiers": { ... },                        // pricing_configs.config.modifiers
  "addons": { ... },                           // pricing_configs.config.addons
  "rush_multiplier": 1.20,
  "rush_units": ["week", "month"],             // ETAs that get the time-reduction; others only get the price multiplier
  "currency": "MYR",
  "valid_for_days": 30
}
```

The endpoint is cached for 1 hour. Cache is **automatically invalidated** when:
- A `pricing_configs` row is saved or deleted
- A `service_category` is saved or deleted
- A `service_package` is saved or deleted

(See `App\Observers\*Observer`.) You should not need to run `php artisan cache:clear` for routine pricing edits.

---

## ETA model

Every quotable package has two columns:
- `eta_value` — positive integer (1–999)
- `eta_unit` — one of `hour`, `day`, `week`, `month`

`duration_text` is a separate human-readable label (e.g. "5–6 weeks", "Up to 8 hrs / mo") shown on services-page cards. Keep both: the structured pair drives the engine, the string drives the marketing copy.

**Rush rule.** Rush always applies the price multiplier (`rush_multiplier`, default 1.20). Rush only reduces ETA for packages where `eta_unit ∈ rush_units` (currently `[week, month]`). For hour/day projects, the time-reduction is skipped silently — the rush price still applies.

---

## Calculation order (exact, don't reorder)

1. **Resolve base** — look up the merged `base_packages[packageKey]` → get `min`, `max`, `eta_value`, `eta_unit`.
2. **Apply modifiers** — for each modifier in the input:
   - Check `applies_to`; skip silently if it's an array and doesn't include the chosen package.
   - **Numeric modifier** (has `applies_after`): if input value > `applies_after`, add `(value - applies_after) × amount` to both min and max.
   - **Toggle modifier** (no `applies_after`): if value is true, add `amount` to both.
3. **Sum add-ons** — fixed-price, added to both min and max.
4. **Apply rush** — if `rush=true`, multiply both min/max by `rush_multiplier`. If `eta_unit` is in `rush_units`, also reduce `eta_value` by 30% (floor, min 1).
5. **Round** — round min/max to nearest 50 MYR.
6. **Build breakdown** — auditable `[label, min_contribution, max_contribution]` tuples.

### Example

Package `web_business` (min: 3500, max: 5500, eta_value: 2, eta_unit: week)
Modifiers: `extra_page = 7` (threshold 5, +2 × RM300 = +RM600), `cms = true` (+RM1,200)
Add-ons: `seo` (+RM600)
Rush: false

- min = 3500 + 600 + 1200 + 600 = **5,300** → rounds to **RM 5,300**
- max = 5500 + 600 + 1200 + 600 = **7,300** → rounds to **RM 7,300**
- eta = **2 weeks**

---

## Canonical `form_payload` & multi-package quotations

Quotation drafts are created three ways — the public `/quote` funnel, the admin edit form (`/admin/quotations/[id]`), and the MCP connector (`create_draft_quotation`). **All three now write ONE canonical `form_payload` shape, and every render path reads it back through one normalizer.** One write shape, three writers, one renderer.

### The canonical shape

```jsonc
{
  "packages": [
    { "package_key": "web_business", "service_package_id": 6,
      "scope_values": { "extra_page": 7 }, "modifiers": {}, "addon_keys": ["seo"] }
  ],
  "rush": false,                       // ONE flag for the whole quote (top-level)
  "breakdown": [ /* grouped per package — see below */ ],
  "source_meta": { "created_via": "quote_funnel" | "admin" | "mcp_connector" }
}
```

- **Multi-package via `packages[]`.** No new table, no pivot. The scalar `package_key` + `service_package_id` columns store the **first** package (list-display + back-compat). The public funnel stays single-package (one entry).
- **`service_package_id`** is resolved from `package_key` at write time by `PricingEngine::packageId()` (null for legacy JSON-only packages that have no DB row).
- **`breakdown` is grouped per package**: a list of `{ package_key, name, min, max, eta_value, eta_unit, lines: [[label, min, max], …] }`. The `lines` are the existing single-package tuple shape and are **pre-rush** (rush is applied to the running total, never to the pushed lines).

### The normalizer (backward compat — no migration)

`App\Services\Quoting\FormPayloadNormalizer::normalize()` maps **any** historical shape → the canonical `packages[]` view: the old funnel flat shape, the current `scope_values` admin shape, the MCP connector shape, and the new multi-package shape. Existing rows are **not** migrated — they normalize on read. Every backend render path (`DocumentMapper`, the connector view, the admin resource) reads through `Quotation::normalizedForm()`; the frontend hydrates through the TS port `normalizePackages()` in `frontend/app/composables/quoteScope.ts` (**keep the two in sync**). `FormPayloadNormalizer::flattenBreakdown()` collapses a grouped (or legacy-flat) breakdown back to plain `[label, min, max]` tuples for the customer email and the `DocumentMapper` line-item fallback.

### Multi-package pricing

`PricingEngine::calculateMulti(packages[], rush)` maps over the packages, prices each with the unchanged single-package `calculate()` (order: base → modifiers → add-ons → rush → round), **sums** the min/max, and takes the **longest** ETA (units normalized to days for comparison; the winner's original value/unit is kept). Rounding stays per-package (the existing RM 50 rule), then summed. An empty `packages[]` (fully bespoke) yields RM 0 and the `0`/`week` "no ETA" sentinel.

### DocumentSeeder — the shared line-item seeder

`App\Services\Quoting\DocumentSeeder` builds the canonical **standard** `document` (the `document.items[]` shape the PDF renders) from a priced estimate. It is the single implementation behind **both** the admin "**Seed line items from scope**" button (`POST /v1/admin/quotations/seed-document`, non-persisting) **and** the MCP connector — so a connector draft arrives with a prefilled, PDF-ready document identical to what the admin button produces. Rules:

- one line per **package base**, amount = **midpoint** of that package's min/max, rounded to nearest RM 50;
- one line per active **modifier** / **add-on** at its **exact** fixed amount;
- **rush** → a single "Rush delivery (+20%)" line = the uplift on the document subtotal (rounded to RM 50);
- **deposit 50%**, the three standard terms, valid-until left null (`send()` defaults it to `valid_for_days`);
- every midpoint-seeded base line appends a matching `assumptions` note ("… seeded at range midpoint RM X — adjust before sending").

The connector **never overwrites an admin-edited document**: `DocumentSeeder::hasContent()` guards it, and the UI button confirms before replacing hand-edited lines.

### DetailedDocumentBuilder — the connector's detailed proposals

The MCP connector can also author a full **detailed** proposal (see [MCP-CONNECTOR.md](./MCP-CONNECTOR.md)). `App\Services\Quoting\DetailedDocumentBuilder` turns its structured `detailed` input (priced `sections`, "What's included" groups, option cards, a care plan) into the canonical `layout: 'detailed'` `document.payload` — the SAME shape the admin detailed builder emits, so the same `DocumentMapper` + PDF render it and the draft re-opens in the admin builder's detailed mode. Detailed quotes are priced by their own section totals (`Quotation::sumDetailedSections`), never the engine — Claude provides the prices; `estimate_min == estimate_max == Σ section amounts`.

## How to add a new offering (end-to-end)

The fastest path uses the admin UI — no code or SQL.

1. Open `/admin/services/categories/new` if you need a new category, otherwise pick an existing one.
2. Open `/admin/services/packages/new`.
3. Fill in name, tagline, prices, **ETA value + unit**, features, CTA.
4. Tick **"Wire CTA to the quote builder"** and set:
   - `quote category key` — usually matches `service_categories.slug`
   - `quote package key` — a new stable key like `web_landing` (no spaces, snake_case)
5. Save. The quote builder picks it up immediately (cache auto-clears).

If your new package needs **custom modifier inputs** in the quote builder (e.g. a checkbox specific to this package), that still requires editing `pricing_configs` JSON and `frontend/app/pages/public/quote/index.vue` — modifiers are engineered config, not yet admin-managed.

---

## How to update pricing rules (modifiers / addons / rush)

These live in `pricing_configs.config` JSON. Insert a new active row:

```sql
INSERT INTO pricing_configs (version, config, active, notes, created_at, updated_at)
VALUES ('2026.06.01', '<new config JSON>', 1, 'Price rules update', NOW(), NOW());
```

The `PricingConfigObserver` deactivates older rows and clears cache.

### Config structure (JSON)

```json
{
  "base_packages": {
    "<package_key>": { "min": 3000, "max": 5000, "eta_value": 2, "eta_unit": "week" }
  },
  "modifiers": {
    "<modifier_key>": {
      "amount": 300,
      "applies_after": 5,
      "applies_to": ["web_business"]
    }
  },
  "addons": {
    "<addon_key>": { "amount": 600, "label": "SEO setup" }
  },
  "rush_multiplier": 1.20,
  "currency": "MYR",
  "valid_for_days": 30
}
```

> Legacy entries written before the ETA refactor have `weeks` instead of `eta_value`/`eta_unit`. The engine reads `weeks` as `eta_value` with `eta_unit='week'` for backward compatibility — but write all new entries in the new shape.

`base_packages` in JSON is a **fallback** for keys that don't have a matching admin-managed `service_packages` row. The DB row always wins where both exist.

---

## Scope fields (per-category builder inputs + pricing) — admin-managed

Each category's "Scope details" in the quote builder is driven by `service_scope_fields` rows — no hardcoded UI. A field has a `type`, per-package `applies_to`, and a type-specific `config` that defines BOTH how it renders and how it prices:

- **slider** — `{min, max, default, unit, free_threshold, price_per_unit}`. Price = `max(0, value − free_threshold) × price_per_unit`. (Generalises the legacy `extra_page` "applies_after + amount" modifier. Set `price_per_unit: 0` to capture scope without charging.)
- **toggle** — `{amount, default}`. Price = `amount` when on.
- **select** — `{default, options:[{value,label,amount}]}`. Price = the chosen option's `amount`.

`applies_to` is an array of `quote_key.package` strings; **empty = all packages in the category**. The builder renders sliders left, toggles right, selects full-width; the engine (`PricingEngine::calculate()`) and the TS port (`usePricingEngine.ts`) evaluate them identically — **keep the two in sync**.

**To add/edit:** open a category under `/admin/services`, scroll to **Scope fields**, **+ New scope field** (or Edit), pick the type, set the pricing, tick which packages it applies to. Cache auto-clears via `ServiceScopeFieldObserver`; the builder reflects it on next open. No JSON edit, no deploy.

Stored on the quote as `form_payload.scope_values` (`{field_key: value}`). The legacy JSON `modifiers` map + the engine's modifier loop remain as a one-release fallback for any pre-migration `modifiers` payload; old drafts hydrate via `legacyToScopeValues()`.

## Adding an add-on

**Add-on** — admin-managed. Add or edit one under **`/admin/services` → Add-ons** (or `/admin/services/addons`): set a snake_case `key`, label, price, order, and active toggle. The builder and both validators pick it up immediately (cache auto-clears via `ServiceAddonObserver`). No JSON edit, no deploy.

> Legacy add-ons can still live in `pricing_configs.config.addons` as a fallback for any key without a `service_addons` row:
> ```json
> "video": { "amount": 2500, "label": "Product demo video" }
> ```
> Creating a `service_addons` row with the same key takes over (and lets you deactivate it).

---

## Known data-drift to clean up

These are pre-existing inconsistencies, not regressions from the hybrid wiring:

- `frontend_components`, `frontend_pages`, `frontend_full` exist only in `pricing_configs` JSON, not in `service_packages`. They still work via the JSON fallback, but admin can't edit them yet.
- The "Not sure yet" UX option in the saas category is no longer present — categories now load from DB and there's no `not_sure` row. Visitors who don't know what they want can use the contact form instead.

---

## File map

- Backend
  - `app/Services/Quoting/PricingEngine.php` — merge logic (packages + add-ons + scope fields), calculation, `packageName()`/`addons()`/`buildScopeFields()`
  - `app/Models/ServiceAddon.php` + `app/Observers/ServiceAddonObserver.php` — admin-managed add-ons, cache invalidation
  - `app/Models/ServiceScopeField.php` + `app/Observers/ServiceScopeFieldObserver.php` — admin-managed scope fields, cache invalidation
  - `app/Http/Controllers/Api/V1/Admin/ServiceAddonsController.php` — add-on CRUD
  - `app/Http/Controllers/Api/V1/Admin/ServiceScopeFieldsController.php` + `app/Http/Requests/Admin/ServiceScopeFieldRequest.php` — scope-field CRUD (type-conditional validation)
  - `database/seeders/ServiceAddonsSeeder.php` / `ServiceScopeFieldsSeeder.php` — seed the original add-ons + scope fields
  - `app/Services/Quoting/EstimateResult.php` — DTO (`etaValue`, `etaUnit`)
  - `app/Models/ServicePackage.php` — eta columns, observer wired
  - `app/Models/Quotation.php` — stores `estimate_eta_value`, `estimate_eta_unit`; has `eta_label` accessor for emails
  - `app/Observers/{Pricing,ServicePackage,ServiceCategory}Observer.php` — cache invalidation
  - `app/Http/Controllers/Api/V1/QuoteBuilderConfigController.php` — cached endpoint
- Frontend
  - `app/composables/usePricingEngine.ts` — TS port of PricingEngine (scope-field eval) + `formatEta` helper
  - `app/composables/quoteScope.ts` — `QuoteScopeState` (`scopeValues` dict), `seedScopeDefaults()`, `legacyToScopeValues()`
  - `app/components/shared/QuoteScopeFields.vue` — generic slider/toggle/select renderer (admin builder only)
  - `app/pages/admin/services/packages/[id].vue` — admin form with eta value + unit inputs
  - `app/pages/admin/services/addons/index.vue` + `[id].vue` — add-on list + editor
  - `app/pages/admin/services/categories/[id].vue` (scope-fields section) + `app/components/admin/ScopeFieldModal.vue` — right-side drawer to create/edit a scope field
