# Document Generation — Quotations, Invoices & Receipts

How Axel Nova turns a quotation or order into a branded PDF. One visual design
system renders in two content **layouts**, across three document **kinds**. PDFs
are never stored — they render on demand, token-gated, from data (live for
quotations, a frozen snapshot for invoices/receipts).

> Spans both apps. The renderer is in the frontend (Nitro + headless Chromium);
> the data and issuance live in the backend (Laravel). This doc is the single
> source of truth for the whole pipeline.

---

## The model: one design, two layouts, three kinds

- **Design** — one shared visual system (see below). Changing it changes every
  document.
- **`layout`** — the *content format*:
  - `standard` — simple parties → one scope-of-work table → terms → totals →
    deposit. The default for non-customized projects.
  - `detailed` — sectioned packages, "what's included" bullets, option cards,
    care plans, launch promotion, summary, deposit/balance panels.
- **`kind`** — `quotation` | `invoice` | `receipt`. Drives the header word, the
  hero (quote = client-as-title; invoice/receipt = Bill-to / Project), and the
  panel/summary labels.

Any kind can render in either layout. Today: quotations default to `standard`,
but the admin can author a `detailed` quotation too (see below); invoices/receipts
default to `detailed`.

### Choosing a quotation layout (admin)

There's one builder and no upfront choice. **Quotations → New quotation** always
opens [`QuotationBuilder.vue`](../../frontend/app/components/admin/QuotationBuilder.vue) — package-priced, scope → line items → totals (the **standard** layout).
Below the line items sits an **"Expand to detailed"** section: clicking it reveals
[`DetailedProposalFields.vue`](../../frontend/app/components/admin/DetailedProposalFields.vue) inline — "what's included" groups, option cards, a care plan,
plus subtitle / attn-address. Saving with that section open writes the **detailed**
layout; the line items become a single "Scope of work" section and the auto summary +
deposit/balance panels are generated. "Remove (keep standard)" collapses it and the
quote saves standard again. No screen swap, one record.

> Note: the merged builder represents the scope as the flat line-items list, so a
> detailed quote has one "Scope of work" section. Multi-section scope grouping (a
> capability of the old separate detailed builder) isn't offered here; editing a
> legacy multi-section detailed quote flattens its rows into line items (amounts
> preserved, section titles dropped).

The record remembers its layout via `document.layout`, and `/admin/quotations/[id]`
reopens a draft in the same builder (which auto-detects detailed from `document.layout`).
Standard quotations created from inquiries / the public quote form are unaffected. A
detailed quote still captures a package & scope as the **internal pricing basis** (it
drives `estimate_*` and the order value); the client-facing PDF renders the composed
`document.payload`, not that estimate.

**Storage / mapping.** A detailed save writes `document = { layout: 'detailed',
payload: {…full content…} }`. `DocumentMapper::toDocumentData` passes `payload`
straight through (stamping studio / reference number / issued / client), mirroring
the order override path. `AdminQuotationRequest` validates `document.payload` loosely
(`array`) so new section types don't need request-rule changes.

---

## Visual design system

Defined once in the `CSS` block of [template.ts](../../frontend/server/utils/pdf/template.ts).

The Axel Nova house palette: pink / purple on soft pink surfaces. Every colour is
a `:root` token; nothing below it uses a literal hex.

| Token | Value | Use |
|---|---|---|
| `--paper` | `#FFFFFF` | page background |
| `--ink` | `#1B0F1D` | headings, item titles, numbers |
| `--body` | `#4B3B4D` | paragraphs, table detail text, the SSM line |
| `--muted` | `#8A7789` | sublabels, meta labels, notes, page-foot |
| `--primary` | `#D11E72` | section squares, bullets, eyebrows, accent borders, emphasized money |
| `--accent` | `#8B3DD6` | alt section square (care plans), gradient end |
| `--surface` / `--surface-strong` | `#FDF4F8` / `#FDEDF5` | panels, option cards / eyebrow pills |
| `--hairline` | `#EFE0E9` | hairlines / row separators |
| `--green` | `#0E8A3E` | money already received ("Paid to date") |
| gradient | `--primary → --accent` | top hairline |

- **Font** — **Satoshi** (400/500/700, Fontshare) for everything, tabular figures
  by default (`tnum`) so prices align; the header's SSM line alone switches to
  proportional figures. Embedded as base64 `@font-face` in
  [fonts.ts](../../frontend/server/utils/pdf/fonts.ts) so the headless render
  needs no network or font install.
- **Logo** — the Axel Nova "A" mark, inlined as a base64 data URI in
  [logo.ts](../../frontend/server/utils/pdf/logo.ts).
- **Letterhead (all kinds)** — logo, then `AXEL NOVA VENTURES` on one line
  (uppercase, letter-spaced) with `SSM Registration: 202603119899 (CA0420977-U)`
  under it. **No tagline in the header.** Right side: the kind word (`Invoice` /
  `Receipt`), then one line per meta pair, label and value on one baseline
  (`NO.  AXNI-2026-0005`, `DATE …`, `STATUS …`).
  The identity strings come from `STUDIO_IDENTITY` in `template.ts`, **not** from
  `data.studio`: payloads are frozen, and older ones carry `reg: "Reg. …"`. Same
  reasoning as `STUDIO_PAY` below. `DocumentMapper::STUDIO['reg']` holds the same
  string — keep the two in sync. `data.studio.name` is only the logo's `alt`.
- **Shared chrome** — gradient top hairline, the letterhead above, a rule with a
  primary leading segment, rounded-square section markers, dot lists,
  ITEM·DETAIL·PRICE tables, a "Designed by …" credit block (with the tagline), and
  a running page-foot (`studio · tagline · number` left, `Page X of Y` right).

> **Header and footer are chrome; the payload freezes data.** Because PDFs are
> never stored, every past document picks up the current letterhead the next time
> its link is opened. That's intended — amounts, numbers and dates never change.

---

## Data contract — `DocumentData`

Defined in [types.ts](../../frontend/server/utils/pdf/types.ts). The backend
maps a row to this shape; the renderer consumes it. Key fields:

```
layout      "standard" | "detailed"
kind        "quotation" | "invoice" | "receipt"
number, issued, validUntil, status, currency
studio      { name, tagline, logo?, email, site, reg, designedBy }   # reg not read by the header
client      { name, company?, attn?, address?, email? }
project, subtitle?, intro?

# standard
items[]     { title, desc?, qty, unit?, rate }
terms[]
discount?, taxLabel?, taxRate?, depositPct?

# detailed
sections[]      { title, rows[ {title, detail?, price|priceText, priceWas?} ], totalLabel?, total?, note? }
included[]      { eyebrow?, items[], columns?, note? }      # "what's included" red-dot groups
options         { title?, promo?, cards[ {badge, accent?, title, sub?, price, priceWas?, priceNote?} ] }
care            { title, headers?, rows[ {label, detail, price, period?} ], note? }
provide, notIncluded   { title?, items[], columns? }
timeline        { title?, text }
paymentTerms    { title?, items[] }
summary         { rows[ {label, price|priceText, negative?, total?, red?, priceMuted?, role?} ] }
panels[]        { label, value, note?, accent?, role? }     # deposit / balance cards
display         { summary?, remaining? }                    # render-time switches; absent = shown
billingFor      { title, label?, text? }                    # right half of the accent amount panel
scope           { title?, items[], columns?, note? }        # bullets between panels and How to pay
notes[]         { label, text }
pay             { online?, bank?, holder?, acct?, note? }
```

A full `payload` can also be passed straight through (the "customized builder"
override path — see Roadmap).

---

## Files

**Frontend (renderer)** — `frontend/server/utils/pdf/`
| File | Role |
|---|---|
| `types.ts` | the `DocumentData` contract |
| `fonts.ts` | `FONT_FACES` — Satoshi 400/500/700, base64 woff2 |
| `logo.ts` | `STUDIO_LOGO` — base64 logomark |
| `qr.ts` | `DUITNOW_QR` — base64 DuitNow QR payment card (invoices only) |
| `template.ts` | shared CSS + `renderStandard` / `renderDetailed` + `renderDocumentHTML(data)` dispatch on `data.layout` |
| `pdf.ts` | `renderDocumentPDF(data)` — Playwright-core → system Chromium, `preferCSSPageSize` |

**Frontend (route)** — `frontend/server/api/documents/[token]/pdf.get.ts`
fetches the data from the backend by token and streams the rendered PDF.

**Backend** — `backend/app/`
| File | Role |
|---|---|
| `Services/Quoting/DocumentMapper.php` | `toDocumentData(Quotation)` (live quotation) + `forOrder(Order, type, input)` (invoice/receipt) |
| `Services/Quoting/DocumentIssuer.php` | issues an invoice/receipt: atomic number + frozen snapshot |
| `Models/Document.php` | a frozen, issued invoice/receipt (`payload` is immutable) |
| `Http/Controllers/Api/V1/DocumentController.php` | token → data (frozen payload for documents, live map for quotations) |

---

## Render pipeline

```
Browser → GET /api/documents/{token}/pdf            (Nuxt Nitro)
            └─ $fetch  GET /api/v1/documents/{token} (Laravel DocumentController)
                 ├─ Document by public_token?  → return its FROZEN payload
                 └─ Quotation by public_token? → DocumentMapper::toDocumentData() (live)
            └─ renderDocumentPDF(data)               (template.ts → Chromium)
            └─ stream application/pdf
```

The token (48-char random) is the only credential — unguessable, shared via the
document link. `Cache-Control: private, no-store`.

**`@page` is honoured** via `preferCSSPageSize: true` in `pdf.ts` — do **not**
pass `width`/`height`/`margin` to `page.pdf()`, or the CSS margins and the
running page-number footer (`@bottom-left` / `@bottom-right` margin boxes) are
dropped on multi-page documents.

---

## Invoices & receipts (orders)

Issued from an **order** (orders are the post-acceptance object). Two design
decisions:

1. **Frozen snapshots.** When an invoice/receipt is issued, the exact
   `DocumentData` is stored in `documents.payload` and never recomputed.
   Regenerating the PDF later can't drift even if the order changes — essential
   for financial/audit correctness. The PDF binary itself is *not* stored; it
   re-renders from the frozen payload.
2. **Derived, atomic numbering.** `INV-` / `RCP-` + the order's quotation
   reference, e.g. `INV-AXNQ-2026-0006`. A second document of the same type for
   the same order gets a `-2`, `-3` … suffix. Locked with `lockForUpdate()`
   inside a transaction (`DocumentIssuer::nextNumber`).

**`documents` table:** `order_id`, `type` (invoice/receipt), `number` (unique),
`public_token`, `payload` (JSON), `amount_total`, `amount_paid`, `payment_ref`,
`payment_method`, `status` (issued/paid/void), `issued_at`, soft deletes.

**Issuance is manual** — there is no payments table or webhook. The admin issues
a deposit invoice when the deposit lands, and a receipt on full payment,
entering the paid amount + method + ref. `DocumentMapper::forOrder` builds the
panels from those: invoice → "Deposit received" + accent "Balance due on
completion"; receipt → "Paid in full".

### Invoice display options

Per-invoice switches on the invoice form ("Display" group), stored in
`invoices.inputs` and mapped by `DocumentMapper::amountDocument()` (amount-based
invoices only — receipts and quotations ignore them):

| Input | Default | Effect |
|---|---|---|
| `showSummary` | `true` | off → the whole Summary section is not rendered |
| `showRemaining` | `true` | off → the "Remaining after this payment" row and the "Balance after this payment" panel are not rendered; the amount-due panel spans the full width |
| `billingTitle` / `billingLabel` / `billingText` | empty | title set → the accent panel's right half shows title / label (default `Scope covered`) / note, each line on its left partner's baseline |
| `scopeTitle` / `scopeItems[]` | empty | items set → a "Scope covered" bullet section after the panels, before How to pay (max 8, 5 reads best) |

"Amount only" (as on AXNI-2026-0005) is `showSummary: false` + `showRemaining: false`.

**Hide at render time, never strip data.** `DocumentIssuer::payloadTotal()`
derives `amount_total` — which the payments ledger and the invoice paid status
depend on — from the summary rows. So the mapper always builds the full rows and
both panels, adds a `display` object, and tags the hideable parts with
`role: "remaining"` (summary row) / `role: "balance"` (panel). The template simply
does not emit hidden parts — no `display:none`, so hidden figures never reach the
PDF text layer. Match on `role`, never on label text.

The options are display-only, so they stay editable on an amount-locked invoice
(`InvoicesController::update` lets them through beside `notes` / `dueAt`). They
are part of `DocumentIssuer::INPUT_KEYS` — drop them from there and
`cleanInputs()` silently loses them, reverting the invoice to defaults on the next
edit. Validation lives in `DocumentIssuer::DISPLAY_RULES`, shared by issue,
preview and update.

The **Bill to** block prints the client company on its own line under the email,
when it differs from the display name.

### "How to pay" block (invoices)

Invoices — and only invoices — render a **How to pay** block under the
deposit/balance panels: the DuitNow QR on the left, then *Card & online banking*
and *Bank transfer* (account, holder, and the invoice number as the payment
reference) on the right. Receipts are already settled and quotations aren't
payable yet, so neither gets it.

Two things about it are deliberate:

- **The bank details are owned by the renderer** (`STUDIO_PAY` in `template.ts`),
  not frozen into `documents.payload`. Every invoice, however old, must point at
  the account currently being paid into — a frozen copy would send clients to a
  dead account the day it changes. This is the one intentional exception to the
  frozen-snapshot rule, and it applies to payment *instructions* only, never to
  amounts. `data.pay` still wins when a payload supplies it. `STUDIO_PAY` must
  stay in sync with `DocumentMapper::BANK`, which words the same account into the
  panel notes.
- **The QR is static**, so it carries no amount — the caption tells the payer to
  key in the total. A per-invoice dynamic QR would mean generating an EMVCo
  payload per document; not built.

Stripe and Billplz are named as available methods, but the gateways aren't wired
yet (see [PAYMENTS-LEDGER.md](PAYMENTS-LEDGER.md)) — the block asks the client to
email for a payment link. Replace that line with a real checkout URL when the
gateway phases land.

The block is `break-inside: avoid`: a QR split across a page break is
unscannable, so it moves whole to the next page rather than splitting. On a short
invoice that can add a second page.

**Orders-page Documents panel** —
[`admin/orders/[id].vue`](../../frontend/app/pages/admin/orders/%5Bid%5D.vue)
lists issued documents (with View-PDF links) and an issue form. See
[ADMIN-COMPONENTS.md](../frontend/ADMIN-COMPONENTS.md).

**Routes:**
```
POST /api/v1/admin/orders/{order}/documents   Sanctum — issue invoice/receipt
GET  /api/v1/documents/{token}                 Public — document data (JSON)
GET  /api/documents/{token}/pdf                 Public — rendered PDF (Nitro)
```

---

## Recipes

### Verify a render locally (no Playwright on the host)
The prod image has Playwright; locally use the bundled Chrome for Testing +
`esbuild` to render any `DocumentData` to PDF:

```bash
# 1. bundle a harness that imports renderDocumentHTML and writes HTML
node_modules/.bin/esbuild harness.ts --bundle --platform=node --format=esm --outfile=h.mjs
node h.mjs                                   # writes doc.html
# 2. print to PDF with the same Chromium prod uses
CHROME="$HOME/Library/Caches/ms-playwright/chromium-1228/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing"
"$CHROME" --headless --disable-gpu --no-pdf-header-footer --print-to-pdf=doc.pdf file://$PWD/doc.html
```
Chrome's `--print-to-pdf` honours the CSS `@page` (A4, margins, page-foot) the
same way `preferCSSPageSize` does in Playwright.

### Regenerate the embedded fonts
`fonts.ts` is the latin-subset woff2 of Satoshi 400/500/700 (Fontshare, ITF
Fontshare EULA), base64-inlined. To refresh: download the woff2 per weight from
Fontshare, subset to latin, base64-encode, and re-emit the `FONT_FACES` template
string.

### Regenerate the logo
`logo.ts` is `axel_nova_logo.png` (1024², transparent) cropped to the mark's
alpha bbox + downscaled to 140 px tall (~9 KB), base64 data URI. The public PNG
at `frontend/public/axel_nova_logo.png` is still the **site's** OG/logo asset —
keep it; the PDF just uses an inlined copy.

### Regenerate the DuitNow QR
`qr.ts` is `frontend/public/axn-duitnow-qr.png` cropped to its content box, given
back a 30px quiet zone, and quantised to 8 colours (~32 KB → ~42 KB base64).

**Never resample a QR** — it stays at native resolution so module edges are
pixel-exact. And use 8 colours, not 4: a 4-colour median-cut palette is spent
entirely on pink shades and silently drops the black "DuitNow" / merchant-name
text.

After regenerating, verify no module flipped — sample both images on the 77×77
grid (QR version 15) at module centres and diff the matrices. Watch the grid
size: fitting the wrong module count makes an intact QR look corrupted.

The rendered width is set in CSS (`.hp-qr`, 36mm) and is a scannability
constraint as much as a layout one — 36mm puts the 77 modules at ~0.42mm each,
not far above the ~0.33mm floor phone cameras need on a QR this dense. It is
sized to match the height of the three methods beside it. Don't shrink it
further to save page space; crop the card instead (below).

### Add a detailed section type
Add the interface to `types.ts`, a render partial + CSS in `template.ts`, and
push it into `renderDetailed`'s `parts[]`. Re-render a fixture to check one-page
fit before reflowing spacing.

---

## Gotchas

- **Top gradient bar is a flow element** (page 1 top), not `position: fixed`.
  Chrome positions `fixed` relative to the content box in print, so a fixed bar
  repeats *inside* the body on later pages. The page-foot + page numbers repeat
  correctly because they're `@page` margin boxes.
- **Money formatting** — table/summary use no decimals (`RM1,800`); panels use 2
  (`RM1,300.00`). `money(n, cur, dec)`.
- **Letterhead identity is not in the payload** — `STUDIO_IDENTITY` in
  `template.ts`, kept in sync with `DocumentMapper::STUDIO['reg']`. Nothing in the
  header may break the name onto two lines.
- **Header pair baselines are px-snapped** — Chromium snaps them to whole px, so
  `.kind-big` uses `margin-bottom:10px` to land NO./DATE/STATUS at 100.5 / 114.75 /
  128.25pt. Re-measure (pdfplumber, char matrix) after touching header spacing.
- **CSS string injection** — the page-foot identity goes through a `--pgfoot-l`
  custom property; values are escaped for a CSS string literal (`cssStr`).

---

## Roadmap

- ✅ **Customized detailed-quotation builder UI** — shipped as the inline
  [`DetailedProposalFields.vue`](../../frontend/app/components/admin/DetailedProposalFields.vue)
  section inside [`QuotationBuilder.vue`](../../frontend/app/components/admin/QuotationBuilder.vue),
  revealed via "Expand to detailed". Covers "what's included",
  option cards, care plan, auto summary + deposit/balance panels. Future polish:
  multi-section scope grouping, editable summary rows, `provide` / `notIncluded` /
  `timeline` / `notes` blocks (the renderer already supports them — just no editor yet).
- **"Draft with AI"** (not yet built) — Claude (server-side, structured output →
  `DocumentData`) to draft the customized quotation prose. Own PR; never for
  invoices/receipts, which stay deterministic.
