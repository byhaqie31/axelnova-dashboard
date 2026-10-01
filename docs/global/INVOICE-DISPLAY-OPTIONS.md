# INVOICE-DISPLAY-OPTIONS

House header redesign plus per-invoice display options for the document generator.
Written 1 October 2026 after AXNI-2026-0005 had to be hand-edited outside the dashboard.
Every change made by hand that day becomes either the new default or a switch on the invoice form, so it never needs hand surgery again.

**Status (1 Oct 2026):** Parts 1 and 2 are implemented together in one change. The live reference is now [DOCUMENT-GENERATION.md](DOCUMENT-GENERATION.md) ("Visual design system" and "Invoice display options"). This file stays as the design record. One deviation from the spec is noted inline under Part 1 §3.

**How to use:** two Claude Code sessions, in order. Paste the prompt at the bottom of each part into a fresh session. Review the diff, run the checks, commit, then start the next session. Part 1 is frontend only. Part 2 touches backend, frontend and the renderer.

Read first: [DOCUMENT-GENERATION.md](DOCUMENT-GENERATION.md), [PAYMENTS-LEDGER.md](PAYMENTS-LEDGER.md), and the DB protection rule in `.claude/CLAUDE.md`. No migration is needed for any of this.

---

## Reference: what AXNI-2026-0005 looks like after the 1 Oct edit

Top to bottom, A4, page coordinates in pt (measured from the delivered PDF):

1. Gradient top bar, unchanged.
2. **Header, left:** logo mark, then a two-line block. Line 1 `AXEL NOVA VENTURES` (one line, uppercase, letter-spaced). Line 2 `SSM Registration: 202603119899 (CA0420977-U)`. **No tagline in the header.**
3. **Header, right:** `Invoice` title, then one line per pair, label and value on the same baseline: `NO.  AXNI-2026-0005` / `DATE  14 August 2026` / `STATUS  Deposit invoice`.
4. Rule, then Bill to / Project, unchanged. The Bill to block shows the client company (`UPM Consultancy & Services`) under the email.
5. **No Summary section** (no agreed project total, no remaining).
6. **One full-width accent panel.** Left half: `RM29,032.00`, `AMOUNT DUE`, payable note. Right half: billing title `Deposit on signing and mobilisation`, label `SCOPE COVERED`, two-line note. **No balance panel.**
7. **New section `Scope covered`**, same section header style as "How to pay" (primary square + 14.5px title), five red-dot bullets.
8. How to pay, credit block and page foot, unchanged. The tagline still appears in the credit block and the page foot.

Measured baselines for verification (pt, tolerance ±0.5):

| Element | Baseline | Other |
|---|---|---|
| Logo box | | top 65.25, bottom 93.75. Visible mark 65.65 to 85.35 (the PNG's bottom ~30% is transparent padding) |
| `AXEL NOVA VENTURES` | 73.87 | cap top level with the visible mark's top |
| `SSM Registration: …` | 85.31 | level with the visible mark's bottom |
| `Invoice` | 82.5 | right edge 544.5 |
| `NO.` pair | 100.5 | value right edge 544.5 |
| `DATE` pair | 114.5 | rhythm 14pt between pairs |
| `STATUS` pair | 128.5 | |

---

## Part 1: house header (all document kinds)

`headHTML()` in `frontend/server/utils/pdf/template.ts` is shared by quotations, invoices and receipts, so this changes all three. That is intended: one house letterhead.

### Changes

**1. Identity comes from a template constant, not the frozen payload.**
`DocumentMapper::STUDIO` is frozen into every payload, and old payloads carry `tagline` and `reg: "Reg. 202603119899 (CA0420977-U)"`. PDFs re-render from the payload on every download, so reading the header from `data.studio` would show the old `Reg.` string on every past invoice. Do what `STUDIO_PAY` already does for bank details (same reasoning, see the comment above it): add a `STUDIO_IDENTITY` constant in `template.ts` with the literal strings `AXEL NOVA VENTURES` and `SSM Registration: 202603119899 (CA0420977-U)`, and render the header from it. Keep `data.studio.name` only for the logo `alt`. Add a KEEP IN SYNC comment on both sides, and update `DocumentMapper::STUDIO['reg']` to `SSM Registration: 202603119899 (CA0420977-U)` so new payloads match.

Format exactly `SSM Registration: 202603119899 (CA0420977-U)`. Both numbers, brackets kept, no extra brackets around it.

**2. Left block: one-line name, SSM line under it, no tagline.**
Delete the `wordmark()` two-line split from the header (keep or delete the function, but nothing in the header may break the name). Remove the tagline from `headHTML()` only. `creditHTML()` and the page foot keep it.

Starting CSS. It reproduces the reference baselines exactly in a probe, with the existing `.brand` flex row, `gap:13px` and `.logo{height:38px;margin-top:1px}` left as they are:

```css
.brand .wm{font-weight:700;font-size:15px;line-height:1;letter-spacing:.08em;
  color:var(--ink);white-space:nowrap;margin-top:-.5px;}
.brand .reg{font-size:10px;line-height:1;letter-spacing:.01em;color:var(--body);
  font-variant-numeric:proportional-nums;white-space:nowrap;margin-top:4.25px;}
```

Proportional figures on the SSM line only; prices stay tabular. Remove the now-unused `.brand .tag` rule if nothing else uses it.

**3. Right block: one line per pair.**
Change `pair()` and its CSS so label and value sit on one baseline, right-aligned, label first:

```css
.doc .pair{display:flex;justify-content:flex-end;align-items:baseline;gap:8px;margin-top:5.67px;}
.doc .val{margin-top:0;line-height:1;}
```

Keep `.kind-big{margin-bottom:9px}`. The first pair's 5.67px top margin collapses into it, which gives the reference's 18pt from `Invoice` to the first pair and 14pt between pairs.

> **As built:** `margin-bottom:10px`. The production Alpine Chromium (149) snaps these baselines to whole px, and at 9px NO. and STATUS landed at 99.75 and 127.5pt, outside tolerance. At 10px they measure 100.5 / 114.75 / 128.25pt, all within ±0.25pt. Labels keep `.doc .lab` as is (8.5px, 500, .18em, uppercase, muted).

**4. Standard layout:** its "From" block prints `studio.reg` under the email. Now that the header carries the SSM line, drop `reg` from that block so it isn't printed twice on one page.

**5. Docs.** `DOCUMENT-GENERATION.md` still describes Geist, the red palette and the two-line wordmark, and the `types.ts` header comment says Geist too. Update both to what the template actually ships (Satoshi, the pink/purple house palette, this header).

### Behaviour to be aware of

Every past document re-renders with the new header the next time anyone opens its link, because the PDF is never stored. That is fine: header and footer are chrome, the payload freezes data. Say so in the docs.

### Part 1 check

Render one invoice, one quotation and one receipt with the harness in DOCUMENT-GENERATION.md (Recipes, "Verify a render locally"). Rasterise and look at them. Then check the text layer with `pdftotext -layout` or pdfplumber:

- `AXEL NOVA VENTURES` appears in the header, on one line, exactly once
- `SSM Registration: 202603119899 (CA0420977-U)` appears exactly once
- no `Reg. 2026…` anywhere, including on an old payload that still carries it
- the tagline is not in the header but is still in the credit block and page foot
- the header baselines match the reference table within 0.5pt
- the hyphen in `CA0420977-U` and `AXNI-2026-0005` is ASCII (`calt` stays off)

### Session 1 prompt

```
Read docs/global/INVOICE-DISPLAY-OPTIONS.md (Part 1) and docs/global/DOCUMENT-GENERATION.md first.
Then read frontend/server/utils/pdf/template.ts in full (CSS block, headHTML, pair, wordmark,
creditHTML, renderStandard, renderDocumentHTML) and frontend/server/utils/pdf/types.ts, so the
change matches the existing patterns. Also read the STUDIO const in
backend/app/Services/Quoting/DocumentMapper.php.

Implement Part 1 ONLY, the house header:
- STUDIO_IDENTITY constant in template.ts (literal "AXEL NOVA VENTURES" and
  "SSM Registration: 202603119899 (CA0420977-U)"), header rendered from it, KEEP IN SYNC
  comments, DocumentMapper::STUDIO['reg'] updated to the same string.
- Left: name on one line, SSM line under it, tagline removed from the header only.
- Right: one line per label/value pair, using the CSS given in the doc as the starting point.
- Standard layout: stop repeating reg in the From block.
- Update DOCUMENT-GENERATION.md and the types.ts header comment to match what ships.

Do not touch invoice content, the mapper's amount logic, payments, or migrations.
Never run destructive database commands (see .claude/CLAUDE.md).

Verify with the render harness from DOCUMENT-GENERATION.md: render an invoice, a quotation and
a receipt (one using an old payload with studio.reg = "Reg. ..."), rasterise and look at them,
then run the text-layer checks listed under "Part 1 check" and report the measured baselines
against the reference table. Small PR, one commit.
```

---

## Part 2: invoice display options

### What Qie can switch per invoice

| Option (input key) | Default | Effect when off / when set |
|---|---|---|
| `showSummary` | `true` | Off: the whole Summary section is not rendered (agreed project total, paid to date, discount/promo lines, the due line, remaining) |
| `showRemaining` | `true` | Off: the "Remaining after this payment" row and the "Balance after this payment" panel are not rendered. The amount-due panel then spans the full width |
| `billingTitle`, `billingLabel`, `billingText` | empty | When `billingTitle` is set: the right half of the amount-due panel shows title / label / note. `billingLabel` defaults to `Scope covered` |
| `scopeTitle`, `scopeItems[]` | empty | When `scopeItems` has items: a section after the panels, before How to pay, using the existing `sectionHeaderHTML` + `bulletHTML`. `scopeTitle` defaults to `Scope covered`. Max 8 items, recommend 5 |

"Amount only", as on AXNI-2026-0005, is `showSummary: false` + `showRemaining: false`.

Defaults keep every existing invoice rendering exactly as it does today, apart from the Part 1 header.

### The trap: hide at render time, never strip data

`DocumentIssuer::payloadTotal()` reads `amount_total` from the summary rows: the row with `total: true`, else the first row. `amount_total` feeds the payments ledger and the invoice paid status (see PAYMENTS-LEDGER.md). If the mapper drops summary rows to "hide" them, `amount_total` silently becomes 0 or the wrong figure, and `PaymentObserver` can mark the invoice paid or leave it open incorrectly.

So:

- `DocumentMapper::amountDocument()` keeps building the full `summary.rows` and both `panels` exactly as today.
- It adds a `display` object to the payload, plus `billingFor` and `scope` when set.
- `template.ts` reads `display` and **does not emit** the hidden parts. Don't use `display:none`: hidden figures must not exist in the HTML at all, so they can't leak through copy, search or a screen reader.

Add a PHPUnit assertion that `amount_total` is identical with every option on or off.

### Data contract (`types.ts`)

```ts
export interface DocumentData {
  // …existing…
  /** Render-time switches. Absent = everything shown (frozen payloads predate this). */
  display?: { summary?: boolean; remaining?: boolean };
  /** Right half of the accent amount panel: what this bill is for. */
  billingFor?: { title: string; label?: string; text?: string };
  /** Bullet section between the panels and How to pay. */
  scope?: { title?: string } & BulletList;
}
```

The remaining row needs a stable way to find it at render time. Add an optional `role?: "remaining"` to `SummaryRow` (and `role?: "balance"` to `Panel`) in the mapper, rather than matching on the English label text.

### Renderer (`template.ts`, `renderDetailed`)

- Summary: skip when `display.summary === false`. When shown but `display.remaining === false`, skip rows with `role === "remaining"`.
- Panels: skip `role === "balance"` when `display.remaining === false`. One remaining panel is already full width (`.panel{flex:1}`).
- Accent panel with `billingFor`: render it as a 2-column, 3-row grid so each right-hand line shares a baseline with its left-hand partner (amount and title, label and label, note and note). Grid `align-items: baseline` does this per row:

```css
.panel.split{display:grid;grid-template-columns:1fr 1fr;grid-auto-flow:column;
  grid-template-rows:auto auto auto;align-items:baseline;column-gap:56px;}
.panel .bf-t{font-size:13px;font-weight:500;color:var(--ink);letter-spacing:-.005em;}
/* label and note reuse .panel .label (muted, not primary) and .panel .note */
```

  `column-gap:56px` puts the right column exactly where the old balance panel's text started (19px padding + 18px panel gap, mirrored). Escape all three strings.

- Scope: `parts.push(\`<div class="sec">${sectionHeaderHTML(scope.title ?? "Scope covered")}${bulletHTML(scope)}</div>\`)`, placed after the panels and before `payBlockHTML`.
- Receipts and quotations: no change.

### Backend

- **Validation**, in all three places that accept invoice inputs (`InvoicesController::update`, `OrdersController::issueDocument` and `OrdersController::previewDocument`):
  - `showSummary`, `showRemaining`: nullable boolean
  - `billingTitle`: nullable string, max 80
  - `billingLabel`: nullable string, max 40
  - `billingText`: nullable string, max 220
  - `scopeTitle`: nullable string, max 60
  - `scopeItems`: nullable array, max 8; each item string, max 120
- **Locked invoices:** these keys are display-only, so add them to the `except([...])` list beside `notes` and `dueAt` in `InvoicesController::update`. A partially paid invoice can still change its layout, not its money.
- **`DocumentIssuer::INPUT_KEYS`:** `cleanInputs()` whitelists stored inputs with `Arr::only($input, self::INPUT_KEYS)`. Add all seven new keys there. If you don't, they are silently dropped from `invoices.inputs`, and the next edit through `updateInvoice()` re-runs the mapper without them, so the invoice reverts to the defaults. Its empty-value filter keeps `false`, which the switches rely on. Keep it that way.
- **`DocumentMapper::amountDocument()`** (invoice branch only):
  - add `display`, `billingFor`, `scope` from the inputs
  - tag the remaining row `role => 'remaining'` and the balance panel `role => 'balance'`
  - drop empty `scopeItems` entries; skip `scope` if none are left
- **Bill to shows the company.** `amountDocument()` builds `client` without `company`, which is why AXNI-2026-0005 needed it patched in by hand. Add `'company' => $quotation?->company` when it differs from the name. In the template, print it as its own line under the email in the Bill to block, same style as the email line.

### Admin form (`frontend/app/components/admin/InvoiceForm.vue`)

Add a "Display" group under the existing fields, in the admin's existing component style (read one sibling form first):

- Switch: Show summary (agreed total, paid to date)
- Switch: Show remaining balance
- Toggle "Describe what this payment covers", revealing title (prefilled from the invoice type, e.g. `Deposit on signing and mobilisation` for a deposit), label (placeholder `Scope covered`) and a short note
- Toggle "Add scope bullets", revealing a section title and a textarea, one bullet per line, counter `n / 8`

Send them in the body on create and edit. They must still be editable when `amountsLocked`, so update the locked branch that currently sends only `notes`. The live preview already renders through `/documents/render` with the same renderer, so it should update as the switches change. Confirm it does.

The MCP connector has no invoice tools, so there's nothing to change there. The client email only shows the amount due, so no change there either.

### Part 2 check

- PHPUnit (`DocumentMapperTest` plus an `InvoicesController` feature test):
  - defaults give the same payload keys as today, plus `display`
  - `showRemaining: false` still stores the remaining row and the balance panel (data intact)
  - `amount_total` is unchanged across all option combinations
  - a locked invoice accepts display keys and still rejects `amount`
  - the new keys survive `cleanInputs` (including `false` switches), and an edit through `updateInvoice()` keeps them
- Render harness, three fixtures, each rasterised and looked at:
  1. defaults: identical to today's invoice apart from the header
  2. the AXNI-2026-0005 combination (fixture below): matches the reference
  3. `showSummary: true` + `showRemaining: false`: summary without the remaining row, no balance panel
- Text layer on fixture 2: the hidden values (agreed total, remaining) do not appear anywhere in the PDF text; every visible line appears exactly once.
- `vue-tsc`, ESLint, Pint and PHPUnit green (see CI-AND-TESTING.md).

### Fixture 2: the AXNI-2026-0005 combination

Use placeholders for client identity in committed fixtures, not real client names or emails.

```json
{
  "invoiceType": "deposit",
  "amount": 29032,
  "showSummary": false,
  "showRemaining": false,
  "billingTitle": "Deposit on signing and mobilisation",
  "billingLabel": "Scope covered",
  "billingText": "Project mobilisation, discovery and UX refinement, through to confirmation of the design direction.",
  "scopeTitle": "Scope covered",
  "scopeItems": [
    "Project kick-off and mobilisation with the client project team",
    "Discovery and confirmation of requirements for all four surfaces",
    "UX refinement of the core worker, organisation and admin journeys",
    "Design direction confirmed, including the three tonal registers",
    "Delivery schedule and fortnightly progress reviews agreed"
  ]
}
```

### Session 2 prompt

```
Part 1 (house header) is merged. Read docs/global/INVOICE-DISPLAY-OPTIONS.md (Part 2),
docs/global/DOCUMENT-GENERATION.md and docs/global/PAYMENTS-LEDGER.md first.

Then read, in full: backend/app/Services/Quoting/DocumentMapper.php (forOrder, amountDocument),
backend/app/Services/Quoting/DocumentIssuer.php (issueInvoice, updateInvoice, cleanInputs,
payloadTotal), backend/app/Http/Controllers/Api/V1/Admin/InvoicesController.php and the invoice
endpoints in OrdersController.php, backend/tests/Unit/Quoting/DocumentMapperTest.php,
frontend/server/utils/pdf/{types,template}.ts and frontend/app/components/admin/InvoiceForm.vue.

Implement Part 2 ONLY, invoice display options: showSummary, showRemaining, billingFor
(title/label/text in the accent panel's right half) and scope bullets, plus the Bill to company line.

Hard rules:
- Hide at RENDER time. The mapper keeps building the full summary rows and both panels, because
  payloadTotal() reads amount_total from summary rows and the payments ledger depends on it.
  Add a `display` object and role tags; the template simply does not emit hidden parts
  (no display:none).
- Defaults (everything shown) must leave existing invoices rendering exactly as now.
- Display keys stay editable on amount-locked invoices; money fields stay locked.
- Add the new keys to DocumentIssuer::INPUT_KEYS or cleanInputs() silently drops them.
- Receipts and quotations unchanged.
- No migration. Never run destructive database commands (see .claude/CLAUDE.md); repeat this
  rule to any subagent you dispatch.

Verify everything under "Part 2 check": PHPUnit cases, the three render fixtures (rasterise and
look at each), the text-layer check that hidden figures are absent, and the CI gates.
Report what you rendered and measured. Small focused commits, one PR.
```

---

## Follow-ups after both parts ship

- Re-open AXNI-2026-0005 in the admin, apply the fixture 2 options, and regenerate it from the dashboard. That retires the hand-edited PDF, whose amount-due card was patched outside the system.
- The `axelnova-quotation` skill describes a different letterhead for Cowork-built client PDFs (WhatsApp line, footer company block, three-line header meta). This doc governs the dashboard house template only. Decide whether the two should converge before changing either.
