# Quotation Revisions & Order Continuity

An accepted quotation can be **revised** without losing the order, the paid invoices, or the audit trail. "The client changed their mind after accepting" is a normal, auditable path: open a new revision with a reason, edit it, send / accept it, and the **same order** moves onto the new terms — unpaid invoices are voided, paid ones kept, and the new schedule is netted against what was already paid.

Trigger case: **AXNQ-2026-0017** (M Automobile) — accepted on a 12-month instalment plan (RM 2,700 deposit paid + 12 × RM 970 incl. Care Plan), then switched to a **40/40/20 lump sum** on RM 10,800.

> Source spec: `docs/specs/quotation-revisions-spec.md` (v1, 11 Oct 2026). This doc is what was built; [§9](#9-deviations-from-the-spec) lists every place it differs from the spec and why.

---

## 1. Model

```
quotations (FAMILY)          quotation_revisions (R1, R2, …)          orders (the PROJECT)
  reference_code  ─────1:n──▶  number, status, change_reason      ◀──  current_revision_id
  current_revision_id ──────▶  replaces_revision_id                     revised_at
  status (derived)             public_token (own PDF link)              schedule_netting, revision_flags
  …content MIRROR of the       content: document, form_payload,         └─ invoices.revision_id
     current revision           estimate_*, locale, addons (JSON)       └─ order_care_plans
```

- A **quotation family** keeps its AXNQ reference for the life of the project. Its commercial content lives in **revisions**, each a snapshot.
- **The family row mirrors the current revision.** `quotations` keeps its content columns (`document`, `form_payload`, `estimate_*`, `package_key`, `locale`, the `quotation_addons` rows, `issued_at` / `sent_at` / `expires_at`) as a copy of the *current* revision, so every pre-revision reader — lists, emails, reports, invoice mapping (`DocumentMapper::forOrder` reads `order->quotation`) — keeps working unchanged.
- **Current revision** = the accepted one if any (exactly one at a time; older ones are superseded), else the latest revision that wasn't discarded.
- **Family status** (the stored `quotations.status`, still the enum `draft|sent|accepted|rejected|expired`) is derived: accepted / rejected / expired follow the current revision; an open current revision reads `sent` once any revision has gone out, else `draft`. A family never shows `superseded`.
- **Display reference**: `AXNQ-2026-0017` on R1, `AXNQ-2026-0017 · R2` after (`QuotationRevision::displayReference()`); PDF filename `AXNQ-2026-0017-R2.pdf` (`fileStem()`).

### Revision statuses

```
draft ──send──► sent ──accept──► accepted ──(newer accepted)──► superseded
  │               ├──reject──► rejected
  └──discard──►   └──(lazy)──► expired          discarded
```

| Rule | Where |
|---|---|
| Only a **draft** is editable (admin builder, `update_draft_quotation`). | `QuotationRevisions::writeDraft` → `RevisionLocked` (422) |
| At most **one open** (draft / sent) revision per family. | `QuotationRevisions::revise` |
| **Revise** clones an accepted / sent / rejected / expired revision into a new draft (`number = max + 1`) with a required `change_reason`. A **sent** source moves to `discarded` (the client gets the replacement). | `revise()` |
| **Discard** a draft R2+ (never R1 — delete the quotation instead). Undoes the revise: a source that was sent is restored to `sent` (or `expired`). | `discard()` |
| **Accept** only the latest live revision (draft / sent / rejected / expired). First acceptance creates the order; accepting while an earlier revision is accepted runs the **supersede** (§3). | `accept()` |
| Accepted / superseded revisions are **frozen** — never edited, never re-priced; their PDF re-renders from stored content. A direct write to a frozen revision's content through the family row throws. | `isFrozen()`, `absorbRowWrite()` |
| Revise / accept are refused once the order is **completed or cancelled**. | `revise()`, `accept()` |

### Code map

| Piece | File |
|---|---|
| Revision model, statuses, `asQuotation()` view | `backend/app/Models/QuotationRevision.php` |
| Lifecycle rules (initialise, refresh/mirror, writeDraft, revise, discard, accept, reject, send, expiry) | `backend/app/Services/Quoting/QuotationRevisions.php` |
| Order side — open / supersede, money steps | `backend/app/Services/Quoting/OrderRevisions.php` |
| Netting (pure) | `backend/app/Services/Quoting/ScheduleNetting.php` |
| Care Plan periods + boundary (pure) | `backend/app/Services/Quoting/CarePlans.php`, `CarePlanBoundary.php`, `app/Models/OrderCarePlan.php` |
| Lifecycle refusals (render as 422 with the reason) | `backend/app/Services/Quoting/RevisionLocked.php` |
| Migrations | `2026_10_11_100001_create_quotation_revisions_table`, `2026_10_11_100002_add_revision_money_to_orders_and_invoices` |

**Rendering any revision**: `QuotationRevision::asQuotation()` builds a read-only `Quotation` with the family's identity and the revision's content/dates/status/token, so `DocumentMapper`, `finalAmount()`, `PaymentPlan`, `QuotationResource` and the connector view render any revision with no revision-specific code. Saving such a view throws.

**Writes always go revision → row.** Content edits land on the draft revision (`writeDraft`), status changes on a revision; `refresh()` then re-derives the family and re-mirrors the current revision onto the row (quietly). Safety net: a direct, non-quiet write to the row's content / status / dates (tinker, a seeder, legacy code) is absorbed into the current revision (`Quotation::updated` → `absorbRowWrite`), so the two never drift.

**Creators** call `QuotationRevisions::initialise()` once the row and its add-on rows exist (admin store, connector store, demo seeder — and the public funnel until it was retired; the factory does it in `afterCreating`). `Quotation::revision()` initialises lazily as a last resort.

---

## 2. Revising

Admin **Revise** (quotation page, on an accepted / sent / rejected / expired revision) — **founder only**; the MCP connector cannot open a revision, it only edits the draft one afterwards. Input: `change_reason` (required, ≤ 500 chars) — shown on the order timeline and printed under the new revision's PDF title.

1. Refused (422, naming it) if another revision is draft or sent; if the source isn't revisable; or the order is completed / cancelled.
2. The source's content is **copied verbatim** into a new draft — hand edits included, never re-priced. The founder can re-seed line items from the builder if wanted.
3. Each revision mints its own `public_token` (its own PDF link). R1 inherited the family's original token, so every link sent before revisions existed still opens the same document.
4. Nothing is sent. The founder edits, then sends and/or accepts. (Accept is allowed straight from a draft, as before.)

The old in-place **"Mark as Draft"** is gone: `POST /quotations/{id}/status` now only records a client **rejection** (`draft` → 422 "use Revise"; `sent` / `accepted` / `expired` have their own actions).

---

## 3. Supersede (accepting Rn while Rn-1 is accepted) — one transaction

1. `Rn → accepted`, `Rn-1 → superseded` (`superseded_at`); the family re-mirrors Rn.
2. **Order**: the *same* order (same id, same AXNO) re-points: `current_revision_id = Rn`, `final_amount_myr`, `value_min/max_myr`, deposit fields and the plan snapshot from Rn, `revised_at = now`.
3. **Invoices** (§4.2), **netting** (§4.3), **Care Plan** (§5) — below.
4. **Timeline**: `order.revised` activity row `{from_revision, to_revision, change_reason, voided_invoices, adjustment_myr, overpaid_myr}` with the acting user — rendered as "Revised to R2 — <reason> — by <user>".

Atomicity is tested (a failure mid-supersede leaves R1 accepted and R2 draft).

---

## 4. Money

### 4.1 Tagging

`invoices.revision_id` — the revision whose terms produced the invoice (existing invoices were tagged with their order's R1). New invoices are tagged with `order.current_revision_id` at issue. `Order::planView()` and the "already billed" guards only look at the **current** revision's invoices, so R1's paid instalment 1 is never mistaken for R2's instalment 1.

New invoice types: `adjustment` (the netting top-up), `phase` (one phase of a phased lump sum, numbered by `instalment_no`), `care_plan` (one month of a separate Care Plan, dated by `invoices.care_period`). `invoices.void_reason` records why a supersede voided an invoice.

### 4.2 On supersede

| Invoice | What happens |
|---|---|
| `issued`, nothing paid | **void**, `void_reason = "Superseded by R2"` |
| `issued`, **part-paid** | **void** too, `"Superseded by R2 — RM 3,000.00 paid on it is carried into the new schedule"`; its payments stay on the ledger and count toward paid-to-date |
| `paid` | untouched |
| `care_plan` (any) | untouched — it bills a delivered month of a separate subscription |

Numbers are never reused; voided invoices stay in the register marked void. `amount_paid_myr` is never written by the supersede — `PaymentObserver` stays its only writer ([PAYMENTS-LEDGER.md](./PAYMENTS-LEDGER.md)).

### 4.3 Netting

`P` = paid to date (succeeded ledger rows, refunds net), `T` = Rn's total, `S` = Rn's scheduled amounts in order (`PaymentPlan::scheduleItems()`: deposit + balance; or deposit + instalments; or the phases). Walk `S` consuming `P`: items fully covered are **settled**; the first part-covered item becomes an **adjustment invoice** for `s_i − P` (issued immediately, label e.g. `Deposit top-up, R1 → R2`); the rest are **due** and billed as normal when they fall due (from the order's plan card). If `P > T` the order is flagged **overpaid** (`revision_flags.overpaid_myr`) and nothing is adjusted — credit notes are manual in v1.

**Worked example (unit-tested, `ScheduleNettingTest`, and end-to-end in `OrderRevisionMoneyTest`):** P = 2,700, R2 = 40/40/20 of RM 10,800 → S = [4,320, 4,320, 2,160] → adjustment **RM 1,620** ("Deposit top-up, R1 → R2"), then RM 4,320 and RM 2,160 due.

The result is stored on the order (`orders.schedule_netting`, keyed to the revision) and read by `Order::planView()` — each instalment / phase carries its netting state; the invoice form refuses (and greys out) anything settled or covered by the adjustment.

### 4.4 Phased lump sum (dynamic split)

A lump sum can be billed **by phase** — any ordered split that adds up to 100%: 40/40/20, 50/50, 20/20/20/20/20 … (`document.phases = [{label, pct}]`, 2–10 phases). Phase 1 is the up-front payment (carried to the order as a fixed `deposit_amount_myr`); each phase is `round(total × pct / 100)` to the ringgit and the **last phase takes the remainder**, so phases always sum to the total. Phases replace deposit + balance; without them a lump sum behaves exactly as before. Rules live in `PaymentPlan` (`normalisePhases`, `phaseAmounts`, `phaseLabel`) and its TS mirror `frontend/app/composables/paymentPlan.ts` — keep the two in sync. Builder: "Bill by phase" on the lump-sum plan, with 50/50, 40/40/20 and 20×5 presets.

---

## 5. Care Plan coverage

There is no billing engine for care; care is either **included** in the instalment / partner monthly figure (`includes_care_plan`) or a **separate** monthly subscription (`document.care_plan_monthly_myr`) billed one month at a time by `care_plan` invoices. `order_care_plans` records coverage **periods** (`kind`, `monthly_myr`, `starts_on`, `ends_on`, `ended_at`, `revision_id`); first acceptance opens one when the plan has care.

On supersede (`CarePlans::supersede` + pure `CarePlanBoundary`): every active period ends on the supersede date at the **last month already covered** (paid instalments for included care; live `care_plan` invoices for separate care) — or closes empty if nothing was covered; the new revision's period (if it has care) starts **the month after** — or, if nothing was covered yet, where the old one was due to start. So `old.ends_on + 1 day == new.starts_on`: no month billed twice, none skipped. A `care_plan` invoice is refused for a month outside a separate period or already billed on a live invoice. A change of arrangement is flagged (`revision_flags.care_plan_changed`).

M Automobile: R1 care included from Nov 2026, nothing paid → R1's period closes empty on 11 Oct 2026; R2's separate RM 220/month opens **1 Nov 2026**.

---

## 6. Surfaces

- **PDF** — from R2 the header, page-foot and title show `AXNQ-2026-0017 · R2`; under the title a small-print line from the locale file: en *"Revision 2 — replaces Revision 1 (accepted 11 October 2026). Reason: …"*, bm *"Semakan 2 — menggantikan Semakan 1 (diterima 11 Oktober 2026). Sebab: …"*. The reason prints as typed. `Page x of y` is unchanged. Download `AXNQ-2026-0017-R2.pdf`. R1 prints exactly as before. Phased plans get a "Payment by Phase" / "Bayaran Berperingkat" table; a separate Care Plan a line under the plan. See [DOCUMENT-GENERATION.md](./DOCUMENT-GENERATION.md).
- **Quotation page** — revision tabs (R1 · R2 …, status chips, current bold); a draft revision opens the builder (with **Discard** on R2+), anything else the read view (with **Revise**). Accept explains the supersede when an order exists.
- **Order page** — revision timeline, overpaid / Care-Plan-changed flags, the plan card with each payment's netting state, Care Plan periods, void reasons on invoices.
- **Lists** — one row per family with its `· R2` reference and an "R3 draft" chip; a draft / sent status filter also finds families whose *open revision* has that status; **Revised after acceptance** filter (`?revised=1`).
- **Reports** — the dashboard counts families once; the Total quotations tile shows conversion (families whose current revision is accepted ÷ families that went out) and how many were revised after acceptance.

---

## 7. MCP connector (v5.2)

| Tool | Endpoint | Notes |
|---|---|---|
| `get_quotation` | `GET /v1/connector/quotations/{ref}[?revision=N]` | `family`, `current_revision`, `revisions[]` + the working revision's full record (`revision` names it). |
| `update_draft_quotation` | `PUT /v1/connector/quotations/{ref}` | Edits the family's **draft revision** (one the founder opened with Revise); 422 asking the founder to revise it when there is none. |
| `list_quotations` | `GET /v1/connector/quotations` | Rows add `display_reference`, `revision_number`, `open_revision`. |
| `get_order` (v5.1) | `GET /v1/connector/orders/{AXNO or AXNQ}` | **Read-only** money view to confirm the order tallies after the founder accepts: `tally` (agreed total = paid + open invoiced + still to bill; `over_billed_myr`, `overpaid_myr`), the netted plan, every invoice with its status / void reason / revision, payments, Care Plan periods. |

The connector never issues, edits or voids invoices — the supersede does the voiding and the adjustment automatically on accept, and the remaining payments are issued from the order page. `get_order` lets Claude check the result and tell the founder what is left to bill.

The connector **cannot open a revision** (the spec's `create_revision` shipped in v5 and was removed in v5.2 — revising is the founder's call), and `send_revision` (optional in the spec) is not built — revising, sending and accepting stay in the admin. Flow: founder **Revise** → Claude `update_draft_quotation` → founder sends / **accepts** → Claude `get_order` to check the tally. `detailed.phases` and `detailed.care_plan_monthly_myr` are accepted on create / update. Full contract: [MCP-CONNECTOR.md](./MCP-CONNECTOR.md).

---

## 8. Acceptance tests (spec §8)

| # | Test | Where |
|---|---|---|
| 1 | Migration: one R1 per quotation, pointers set, PDFs still open | `tests/Feature/Quotations/QuotationRevisionModelTest.php` (backfill, idempotency, byte-identical PDF data, rollback guard) |
| 2 | No second open revision; accepted revision not editable | `QuotationRevisionLifecycleTest` |
| 3 | Supersede: atomic flip, same order, unpaid void, paid kept, netting (2,700 → 1,620 → 4,320 → 2,160) | `QuotationRevisionLifecycleTest`, `tests/Unit/Quoting/ScheduleNettingTest.php`, `tests/Feature/Orders/OrderRevisionMoneyTest.php` |
| 4 | Care Plan boundary: no month twice, none skipped | `tests/Unit/Quoting/CarePlanBoundaryTest.php`, `OrderRevisionMoneyTest` |
| 5 | PDF revision label + reason in en / bm; `Page x of y` intact | `frontend/server/utils/pdf/revision.test.ts`, `QuotationRevisionSurfacesTest` |
| 6 | Founder Revise → connector update → admin accept → connector get_quotation (and the connector can't open a revision) | `tests/Feature/Connector/ConnectorRevisionTest.php` |
| 7 | Reports count the family once | `QuotationRevisionSurfacesTest` |

---

## 9. Deviations from the spec

| Spec | Built | Why |
|---|---|---|
| Content moves off `quotations` into revisions | Content **copied** into revisions; `quotations` keeps it as a mirror of the current revision | Every existing reader keeps working; the migration is lossless and reversible. Agreed before Phase 1. |
| Revision content as `layout`, `project`, `intro`, `scope`, `payment_plan`, `estimate_myr`, `pdf_url` columns | `document` + `form_payload` + `estimate_*` + `addons` stored verbatim; `pdf_url` derived from the revision's token | Those fields already live inside `document`; splitting them would fork the one-write-shape rule. |
| Revision statuses | adds `expired` (sent → expired, lazily) | The existing lifecycle has lazy expiry. |
| Revise from accepted / sent | also from rejected / expired | Replaces the old "Mark as Draft" re-open, which would otherwise be lost. |
| Accept only after send | accept from draft still allowed | Existing behaviour; acceptance test 6 has no send step. |
| Order `total_myr`, `paid_to_date_myr`, `balance_myr`, status `active/cancelled/completed`, `cancel_reason` | existing `final_amount_myr`, `amount_paid_myr` (observer-owned), `remaining_myr` accessor, existing 5-value status; no `cancel_reason` | Same meanings, no rename churn; nothing uses a cancel reason yet. |
| Invoice `kind` incl. `milestone`; status incl. `draft` | `type` gains `adjustment`, `phase`, `care_plan`; no draft status | `phase` matches the dynamic by-phase lump sum the founder asked for (instead of a fixed "milestone"); no invoice drafts exist, so "draft → deleted" never applies. |
| Partially paid invoices (unspecified) | voided, payments carried | Agreed before Phase 3 — otherwise the client sees the old remainder and the adjustment for the same money. |
| "Remaining items issued as normal" | only the adjustment invoice is issued at supersede; the rest are issued when due from the plan card | Invoices are issued manually as payments fall due; nothing auto-issues today. |
| 40/40/20 "lump-sum plan" | lump sum with a **dynamic** `phases` split | The plan didn't exist; built per the founder's ask that any split work (40/40/20, 50/50, 20×5). |
| Care Plan "subscription" | `order_care_plans` periods + `care_plan` invoices | No subscription entity existed; agreed minimal model before Phase 3. |
| `care_plan` invoices voided on supersede (§4.2 applies to all) | left alone | They bill a delivered month of a separate subscription; voiding them would skip months. |
| §3.7 founder WhatsApp / email notice (optional) | activity log + order timeline only | Optional in the spec. |
| §6 publish `deposit_amount_myr` / `payment_plan` / instalment fields / `locale` | already published (v4.1 / v4.2) | Verified against the live tool schema; nothing to add. |
| §6 `send_revision` (optional) | not built | The connector never sends — sending stays a founder action. |
| §6 `create_revision` on the connector | removed in v5.2 | Opening a revision is the founder's decision (admin Revise); the connector only edits the draft revision. |
| Document "re-seeded from the cloned scope" | copied verbatim (no re-price) | Re-seeding would drop hand edits and re-price at today's catalog; the builder's Seed button does it on demand. |
