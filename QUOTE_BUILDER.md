# Quote Builder — Pricing Formula Guide

This document explains how the pricing engine works, how to update prices, and how to add new packages or add-ons — without touching application code.

---

## How the pricing formula works

All pricing is driven by a single JSON config stored in the `pricing_configs` table. Only one row has `active = true` at any time.

### Calculation order (exact, don't reorder)

1. **Load base package** — look up `base_packages[package_key]` → get `min`, `max`, `weeks`
2. **Apply modifiers** — for each modifier in the input:
   - Check `applies_to` — if it's an array and doesn't include the chosen package, skip silently
   - **Numeric modifier** (has `applies_after`): if input value > `applies_after`, add `(value - applies_after) × amount` to both min and max
   - **Toggle modifier** (no `applies_after`): if value is true, add `amount` to both min and max
3. **Sum add-ons** — add-ons are fixed-price (no min/max range), add to both
4. **Apply rush** — if `rush=true`, multiply both min and max by `rush_multiplier`, reduce weeks by 30% (floor, min 1)
5. **Round** — round both min and max to the nearest 50 MYR (so estimates feel clean)
6. **Build breakdown** — an auditable array of `[label, min_contribution, max_contribution]` tuples

### Example

Package: `web_business` (min: 3000, max: 5000, weeks: 2)
Modifiers: `extra_page = 7` (threshold is 5, so +2 × RM300 = +RM600), `cms = true` (+RM1,200)
Add-ons: `seo` (+RM600)
Rush: false

Result:
- min = 3000 + 600 + 1200 + 600 = **5,400** → rounds to **RM 5,400**
- max = 5000 + 600 + 1200 + 600 = **7,400** → rounds to **RM 7,400**
- weeks = 2

---

## How to update pricing (no code changes)

1. **Insert a new config row** in `pricing_configs`:
   ```sql
   INSERT INTO pricing_configs (version, config, active, notes, created_at, updated_at)
   VALUES ('2026.06.01', '<new config JSON>', 1, 'Price increase June 2026', NOW(), NOW());
   ```
   The `PricingConfigObserver` will automatically set `active=false` on all other rows.

2. **Clear the cache** so the API picks it up immediately:
   ```bash
   php artisan cache:clear
   ```

3. The frontend will load the new config within 1 hour (or on next full page reload after cache clears).

---

## Config structure reference

```json
{
  "base_packages": {
    "<package_key>": { "min": 3000, "max": 5000, "weeks": 2 }
  },
  "modifiers": {
    "<modifier_key>": {
      "amount": 300,
      "applies_after": 5,          // optional — makes it a numeric modifier
      "applies_to": ["web_business"] // or "all"
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

---

## How to add a new package

1. Add an entry to `base_packages` in a new `pricing_configs` row:
   ```json
   "web_enterprise": { "min": 12000, "max": 20000, "weeks": 6 }
   ```

2. If the package should be selectable in the frontend quote form, add it to the relevant category in `frontend/app/pages/quote.vue` under the `categories` array.

3. If the package should appear in the services page pricing grid, add it to `frontend/app/data/services.ts`.

---

## How to add a new add-on

Add an entry to `addons` in a new `pricing_configs` row:
```json
"video": { "amount": 2500, "label": "Product demo video" }
```

The frontend `/quote` page reads add-ons dynamically from the config API — no frontend changes needed.

---

## How to add a new modifier

Add an entry to `modifiers` in a new `pricing_configs` row. Choose the type:

**Toggle modifier** (on/off checkbox):
```json
"multilingual_cms": { "amount": 1800, "applies_to": ["web_business", "web_premium"] }
```

**Numeric modifier** (slider/stepper — adds cost per unit above threshold):
```json
"extra_integration": { "amount": 500, "applies_after": 2, "applies_to": "all" }
```

Then add the corresponding form field to the right category section in `frontend/app/pages/quote.vue` and wire it into the `modifiers` object passed to `calculate()` and the POST payload.

---

## Valid_for_days

Controls how long the quote estimate is valid. Shown in the client confirmation email. Default: 30 days. Update in the config JSON.
