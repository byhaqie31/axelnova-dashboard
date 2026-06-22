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

Any kind can render in either layout. Today: quotations default to `standard`;
invoices/receipts default to `detailed`.

---

## Visual design system

Defined once in the `CSS` block of [template.ts](../../frontend/server/utils/pdf/template.ts).

| Token | Value | Use |
|---|---|---|
| `--ink` | `#1C1C1E` | headings, item titles, numbers |
| `--body` | `#39393C` | paragraphs, table detail text |
| `--muted` | `#6B6B70` | sublabels, "one-time", notes |
| `--faint` | `#9A9AA0` | column headers, page-foot |
| `--line` | `#ECEAE7` | hairlines / row separators |
| `--strong` | `#1C1C1E` | header underline, section-total / project-total top border |
| `--red` | `#EE1C25` | section bullets, eyebrows, "Free", accent borders |
| `--red-deep` | `#C8141C` | emphasized money (section total, option B, project total) |
| gradient | `#4E7DF4 → #BE76E6` | top hairline (matches the logo) |

- **Fonts** — **Geist** (sans, 400/500/600) for everything; **Geist Mono**
  (400/500/700) for all numbers, codes, labels, and the page-foot. Embedded as
  base64 `@font-face` in [fonts.ts](../../frontend/server/utils/pdf/fonts.ts) so
  the headless render needs no network or font install.
- **Logo** — the Axel Nova "A" mark, inlined as a base64 data URI in
  [logo.ts](../../frontend/server/utils/pdf/logo.ts). The text wordmark
  ("Axel Nova / Ventures", two lines) is rendered in Geist, not part of the image.
- **Shared chrome** — gradient top hairline, logo+wordmark+tagline header with
  right-aligned doc meta, a rule with a red leading segment, red rounded-square
  section bullets, red-dot lists, ITEM·DETAIL·PRICE tables, a "Designed by …"
  credit block, and a running page-foot (`studio · tagline · number` left,
  `Page X of Y` right).

---

## Data contract — `DocumentData`

Defined in [types.ts](../../frontend/server/utils/pdf/types.ts). The backend
maps a row to this shape; the renderer consumes it. Key fields:

```
layout      "standard" | "detailed"
kind        "quotation" | "invoice" | "receipt"
number, issued, validUntil, status, currency
studio      { name, tagline, logo?, email, site, reg, designedBy }
client      { name, attn?, address?, email? }
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
summary         { rows[ {label, price|priceText, negative?, total?, red?, priceMuted?} ] }
panels[]        { label, value, note?, accent? }            # deposit / balance cards
notes[]         { label, text }
pay             { online?, bank?, acct?, note? }
```

A full `payload` can also be passed straight through (the "customized builder"
override path — see Roadmap).

---

## Files

**Frontend (renderer)** — `frontend/server/utils/pdf/`
| File | Role |
|---|---|
| `types.ts` | the `DocumentData` contract |
| `fonts.ts` | `FONT_FACES` — 6 Geist faces, base64 woff2 |
| `logo.ts` | `STUDIO_LOGO` — base64 logomark |
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
   reference, e.g. `INV-AXN-2026-0006`. A second document of the same type for
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
`fonts.ts` is the latin-subset woff2 of Geist 400/500/600 + Geist Mono 400/500/700
(Google Fonts, OFL), base64-inlined (~72 KB raw → ~98 KB). To refresh: fetch the
`css2` API with a modern UA, take the `/* latin */` woff2 per face, base64-encode,
and re-emit the `FONT_FACES` template string.

### Regenerate the logo
`logo.ts` is `axel_nova_logo.png` (1024², transparent) cropped to the mark's
alpha bbox + downscaled to 140 px tall (~9 KB), base64 data URI. The public PNG
at `frontend/public/axel_nova_logo.png` is still the **site's** OG/logo asset —
keep it; the PDF just uses an inlined copy.

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
- **Wordmark** breaks after the first two words (`Axel Nova` / `Ventures`).
- **CSS string injection** — the page-foot identity goes through a `--pgfoot-l`
  custom property; values are escaped for a CSS string literal (`cssStr`).

---

## Roadmap (not yet built)

- **Customized detailed-quotation builder UI** — the `detailed` renderer and a
  full-`payload` override path exist, but there's no admin screen yet to *compose*
  a detailed quote (sections / options / care). The standard quotation builder is
  the `QuotationBuilder` component; the detailed builder is next.
- **"Draft with AI"** — Claude (server-side, structured output → `DocumentData`)
  to draft the customized quotation prose. Own PR; never for invoices/receipts,
  which stay deterministic.
