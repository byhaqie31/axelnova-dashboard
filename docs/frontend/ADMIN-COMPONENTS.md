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

`pages/admin/inquiries/{index,[id]}.vue` follows the standard list+detail
pattern (UI-STANDARDS §12): `AdminExpandingSearch`, `AdminStatusFilter`,
desktop table + mobile cards, status-pill button group, sticky action
sidebar. New status-pill tokens were added in `main.css` + `AdminStatusPill.vue`:
`qualified`, `converted` (referrals); `reviewing`, `quoted`, `archived` (inquiries);
`draft`, `sent`, `declined`, `expired` (quotation lifecycle).

**`pages/admin/referrals/index.vue` — the Referrals hub.** Merges the old
standalone `/admin/referral-partners/{index,[id]}` pages in as a "Referrers"
tab alongside "Referrals" (Task 2 of the portal restructure — both tables
(`referrals`, `referral_partners`) and their backend endpoints are unchanged;
this was a UI-only merge). Two new patterns established here, documented in
UI-STANDARDS §12.12–§12.13:

- **Tabs** — a query-param pill tab group (`?view=referrers|referrals`,
  default `referrers`). See §12.12.
- **Referrer detail** — opens as a **slideover** from the Referrers tab
  instead of navigating to a separate page (Qie can promote it back to a full
  page later if it outgrows the panel). Includes the approve / reset-passcode
  actions the old detail page had, funnelled through the same confirm-dialog
  as the list row's quick actions. See §12.13.
- `pages/admin/referrals/[id].vue` (a referral **submission**, not a
  referrer) is unchanged — it stays a full page. Its "All referrals" back-link
  now points at `/admin/referrals?view=referrals` (not the bare hub URL,
  which defaults to the Referrers tab).
- First real adoption of the `shared/primitives/StatusPill` domain primitive
  (`type="referral"` / `type="referral_partner"`) and of `ReferenceCode` /
  `DateRange` from the same folder — see §7 "Domain primitives". The dense
  desktop table's "Code" column keeps its existing plain mono badge rather
  than `ReferenceCode`'s copy-button treatment, reserved for the slideover's
  more spacious detail context.

**Inquiry → quotation (build _or_ link).** On `inquiries/[id].vue`, an unquoted
inquiry offers two paths: **Build new quotation** (the builder, prefilled — see
`QuotationBuilder` `inquiryId` above) or **Link existing quotation** via
`AdminLinkQuotationModal` (`components/admin/LinkQuotationModal.vue`) — a searchable
picker over `GET /v1/admin/quotations` (`include_accepted=1`, so any quote is
linkable). Linking `POST`s `/v1/admin/inquiries/{inquiry}/quotation` (sets
`quotation_id`, status → `quoted`); the sidebar then shows the linked quote plus an
**Unlink** action → `DELETE /v1/admin/inquiries/{inquiry}/quotation` (clears the link,
status → `reviewing`). Building still links on save via `QuotationsController@store`.
The `inquiries.quotation_id` FK is one-to-many by design (a quote can cover related
inquiries) — no uniqueness guard.

### `pages/admin/mockups.vue` — Mockups page

A dedicated page (sidebar: Overview → Mockups, right below Dashboard) listing
**every** public client prototype from the registry at
`https://axelnova.my/projects/registry.json` (CORS-open, fetched client-side on
mount; fetch/filter/sort/fallback live in `composables/useMockupRegistry.ts`,
shared with the public landing showcase — the page passes `limit: Infinity`
where the landing keeps the featured six).

- Excludes any registry row with `internal: true` (admin-only mockups must never
  render), sorts by `updatedAt` desc.
- Each card links to `https://axelnova.my/{slug}/` in a new tab; an **Open
  listing** button in the header links to `https://axelnova.my/projects/`.
- Card accent comes from the registry's `tint {h, c}` → `hsl(h, c*400%, 55%)`,
  applied as a soft wash on the icon chip (full strength for the icon only).
- Registry statuses reuse the `AdminStatusPill` vocabulary (`in-review` → `reviewing`,
  `approved` → `accepted`) so `main.css` pill tokens stay the single source of truth.
- If the live fetch fails, a frozen 6-item snapshot renders instead — the page
  never breaks. Empty registry → quiet empty state; loading → shimmer skeleton
  (disabled under reduced motion).

### Sidebar "View more" launchpad (`layouts/admin.vue`)

The desktop rail is **user-customizable**. **Overview** is `mandatory: true`
in `data/adminNav.ts` (always in the rail, no pin control); every other group
carries a pin: pinned groups sit in the rail, unpinned ones live only in the
launchpad. Data defaults come from `defaultPinned` (omitted = pinned;
currently **Growth**, **Partners**, and **Workspace** start unpinned — the
latter two are Task 1's regroup of the former **Business** group, which no
longer exists); the user's own choices
are stored in the `axn_admin_nav_pinned` cookie — cookie-backed like the
other sidebar prefs so it's SSR-resolved with no flash (a DB-backed pref can
replace the cookie once Phase 0 user profiles land).

A **View more** button pinned at the rail's bottom (chevron points right,
toward the reveal) **transforms the whole bar**: the aside animates wider
(464px) and the rail list swaps for a launchpad view — *every* group rendered
as 4-column icon-tile grids with eyebrow labels, all on one surface. Each
customizable group's header carries a pin toggle (accent = pinned) that
updates the rail live. Toggling again (or Esc / click-away / navigating)
restores the rail. Active tile highlighted; the View more button lights up
when the current route lives in an unpinned group. Works from both expanded
and collapsed rail states. The mobile drawer is unaffected — it scrolls and
always lists every group.
