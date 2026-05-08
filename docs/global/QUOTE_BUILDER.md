# Quote Builder — Pricing & Catalog Guide

This document explains how the public `/quote` builder is wired together: where data lives, how prices are calculated, and how to add or change packages.

---

## Two-layer architecture (hybrid)

There are two sources of truth, by design:

| Layer | Storage | Owns | Managed by |
|---|---|---|---|
| **Catalog** (admin-managed) | `service_categories` + `service_packages` tables | Categories, package names, taglines, prices, ETA, features, CTA, deep-link `quote_key` | Admin UI under `/admin/services` |
| **Pricing rules** (engineered config) | `pricing_configs.config` JSON (one active row) | `modifiers` (extra page, CMS, …), `addons` (SEO, logo, …), `rush_multiplier`, `currency`, `valid_for_days`, plus a fallback `base_packages` map for any legacy keys | SQL insert + auto cache-clear |

The `PricingEngine` merges these at request time:

1. Start with `pricing_configs.active.config.base_packages` (legacy / fallback entries).
2. Layer admin-managed `service_packages` on top, keyed by `quote_key.package`. DB rows override any matching JSON entry.
3. Modifiers / addons / rush stay in `pricing_configs.config` (no admin UI yet).

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

## Adding a modifier or add-on

These need both a JSON edit and (for category-specific modifiers) a frontend form input edit.

**Toggle modifier** (on/off checkbox):
```json
"multilingual_cms": { "amount": 1800, "applies_to": ["web_business", "web_premium"] }
```

**Numeric modifier** (slider/stepper):
```json
"extra_integration": { "amount": 500, "applies_after": 2, "applies_to": "all" }
```

**Add-on** (the `/quote` page reads add-ons dynamically from the config API, no frontend changes needed):
```json
"video": { "amount": 2500, "label": "Product demo video" }
```

For category-specific modifiers, also add the form field to the right scope section in `frontend/app/pages/public/quote/index.vue` and wire it into the `modifiers` object passed to `calculate()`.

---

## Known data-drift to clean up

These are pre-existing inconsistencies, not regressions from the hybrid wiring:

- The seeder uses category slug `design-frontend` (combined), but the quote page has hardcoded modifier UI for separate `design` and `frontend` slugs. Categories with non-matching slugs render in the quote builder *without* scope-input UI. Fix by either splitting the seeded category or adding a `design-frontend` scope block.
- `frontend_components`, `frontend_pages`, `frontend_full` exist only in `pricing_configs` JSON, not in `service_packages`. They still work via the JSON fallback, but admin can't edit them yet.
- The "Not sure yet" UX option in the saas category is no longer present — categories now load from DB and there's no `not_sure` row. Visitors who don't know what they want can use the contact form instead.

---

## File map

- Backend
  - `app/Services/Quoting/PricingEngine.php` — merge logic, calculation
  - `app/Services/Quoting/EstimateResult.php` — DTO (`etaValue`, `etaUnit`)
  - `app/Models/ServicePackage.php` — eta columns, observer wired
  - `app/Models/Quotation.php` — stores `estimate_eta_value`, `estimate_eta_unit`; has `eta_label` accessor for emails
  - `app/Observers/{Pricing,ServicePackage,ServiceCategory}Observer.php` — cache invalidation
  - `app/Http/Controllers/Api/V1/QuoteBuilderConfigController.php` — cached endpoint
- Frontend
  - `app/composables/usePricingEngine.ts` — TS port of PricingEngine + `formatEta` helper
  - `app/pages/public/quote/index.vue` — categories now from `config.categories`
  - `app/pages/admin/services/packages/[id].vue` — admin form with eta value + unit inputs
