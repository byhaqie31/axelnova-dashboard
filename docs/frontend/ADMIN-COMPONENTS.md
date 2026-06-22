# Admin components

Components scoped to the admin portal only.

Rules:
- Used in 1 portal only — if used in 2+, move to `components/shared/`
- Genuine domain primitives live in `components/shared/primitives/`
- We don't wrap @nuxt/ui components as atoms — the library is our primitive layer
- Extract a shared component when used in 3+ places (rule of three)

## Components

### `components/shared/QuoteScopeFields.vue`

The reusable "pick package → modifiers → add-ons → rush → live estimate" unit,
extracted from the old public `/quote` page so the admin builder reuses the exact
same scope/pricing logic (single source of truth — `composables/usePricingEngine.ts`).

- **Props:** `state: QuoteScopeState` (a reactive object the component mutates in place; shape + `defaultQuoteScope()` / `deriveModifiers()` / `scopeToPayload()` live in `composables/quoteScope.ts`).
- **Emits:** `update:estimate` (`EstimateResult | null`), `update:modifiers` (the derived `{key: value}` map the server re-prices with).
- Renders category cards, package cards, per-category scope inputs, add-on toggles, and the rush toggle. Branches on the real config category keys — **`web` / `dashboard` / `design-frontend` / `saas`** (the `design-frontend` branch is the fix for the legacy data-drift where the UI assumed split `design`/`frontend` slugs).
- In `components/shared/` (not `admin/`) so it could also back a future public/portal surface. Import it **explicitly** (`import QuoteScopeFields from '~/components/shared/QuoteScopeFields.vue'`) — Nuxt would otherwise auto-name it `<SharedQuoteScopeFields>`.

### `components/admin/QuotationBuilder.vue`

The admin quotation generator. Used by `pages/admin/quotations/new.vue` (create) and
`pages/admin/quotations/[id].vue` (edit while `status === 'draft'`).

- **Props:** `quotation?` (existing row → edit mode) and `inquiryId?` (prefill the client + project from an inquiry).
- **Emits:** `saved(id)`, `sent`, `accepted(orderId)` — the page wrappers handle navigation / refetch.
- Sections: **Client** (typeahead against `GET /v1/admin/clients`, or new-client fields), **Package & scope** (`<QuoteScopeFields>`), **Quotation document** (project title, intro, editable line items seeded from the package + add-ons, terms, deposit %), and a sticky **sidebar** (live engine estimate as a guide + the line-items total + Save / Send / Accept / View-PDF).
- Re-prices server-side via `PricingEngine` on save; the committed **line items** drive the PDF (`DocumentMapper` → `DocumentData`). Import explicitly (auto-name would be `<AdminQuotationBuilder>`).

### `pages/admin/orders/[id].vue` — Documents panel

The order detail page carries the **invoice/receipt builder**. A "Documents"
card lists the order's issued documents (number, type, total/paid, status,
issued date, View-PDF link) and an **issue form** — type (invoice/receipt),
amount paid, payment method, payment ref — that `POST`s to
`/v1/admin/orders/{order}/documents`. Issuance freezes a `DocumentData` snapshot
(`DocumentIssuer`) and assigns a derived number (`INV-`/`RCP-` + quote ref); the
panel refetches the order to show the new row. View-PDF opens the public
`pdf_path` (`/api/documents/{token}/pdf`). Issuance is **manual** — issue the
invoice when the deposit/full payment lands, the receipt on full payment. Full
pipeline: [DOCUMENT-GENERATION.md](../global/DOCUMENT-GENERATION.md).

### Referrals / Inquiries pages

`pages/admin/referrals/{index,[id]}.vue` and `pages/admin/inquiries/{index,[id]}.vue`
follow the standard list+detail pattern (UI-STANDARDS §12): `AdminExpandingSearch`,
`AdminStatusFilter`, desktop table + mobile cards, status-pill button group, sticky
action sidebar. New status-pill tokens were added in `main.css` + `AdminStatusPill.vue`:
`qualified`, `converted` (referrals); `reviewing`, `quoted`, `archived` (inquiries);
`draft`, `sent`, `declined`, `expired` (quotation lifecycle).
