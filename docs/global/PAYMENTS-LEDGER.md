# Payments Ledger

Turns "payment" from a number derived across three tables into a first-class **ledger**, then surfaces Invoices and Payments as standalone admin modules.

## The model

```
CLIENTS ─ places ─→ ORDERS ─ requests ─→ INVOICES        (the ask: deposit/partial/final)
                       │                     │
                       │ receives            │ allocated to
                       ▼                     ▼
                    PAYMENTS  ◀── produces ── GATEWAY_EVENTS   (raw webhook log, idempotency)
                       │
                       │ generates (1 payment : 1 receipt)
                       ▼
                    RECEIPTS  (proof money landed; frozen snapshot)
```

Design rules:

- **`payments` is the single source of truth for money.** One row per movement, including refunds and failed attempts.
- **Refunds are rows, not status flips.** The original stays `succeeded`; a `type: refund` row carries a **negative** `amount_myr` and a `parent_payment_id`. `SUM(amount_myr)` nets out and history is preserved.
- **Receipts anchor to the payment, never the invoice.** Trigger is `payment.succeeded`. `receipts.invoice_id` is kept for display/allocation only — so a deposit paid before any invoice exists still produces a receipt.
- **Order/invoice paid amounts are derived caches.** Exactly one writer — `PaymentObserver`. No endpoint edits a paid amount directly (that was the old drift bug).
- **No `transactions` table.** `payments` *is* the ledger; no double-entry machinery.
- **`payments` is client REVENUE only.** Team compensation (payslips) lives in `payroll_entries` (Task 7 — allowance snapshot + settled task extras) and is deliberately **not** part of this ledger; the settled payslip is itself the team-comp expense record. See [ARCHITECTURE.md](./ARCHITECTURE.md#team-workspace-portal-restructure-task-567).

## Build status

| Phase | Scope | State |
|-------|-------|-------|
| 1 | Ledger data layer — tables, enums, models, observer, numbering, backfill | **done** |
| 2 | Invoices module — cross-order index endpoint + admin list page + nav | **done** |
| 3 | Payments module — ledger index, record/refund/issue-receipt, order-detail refactor | **done** |
| 4–5 | Gateways — Billplz then Stripe webhooks → `gateway_events` → `payments` (separate handoff; read live provider docs) | pending |

## Phase 1 — data layer (shipped)

### Tables

- **`payments`** — signed `amount_myr decimal(12,2)` (negative for refunds), `fee_myr`/`net_myr` for gateway settlement, `softDeletes`. String columns + PHP enum casts (not MySQL enums) so a new method/gateway/status is a code change, not an ALTER. Unique `(gateway, gateway_payment_id)` (one ledger row per charge; manual NULLs don't collide) and unique `idempotency_key`.
- **`gateway_events`** — raw inbound webhook body keyed by unique `event_id`; the idempotency gate for the gateway phases. Empty until Billplz/Stripe land.
- **`receipts.payment_id`** — nullable FK; receipts anchor to the payment that produced them.

### Enums — `app/Enums/`

`PaymentType` (payment·refund) · `PaymentGateway` (stripe·billplz·manual) · `PaymentMethod` (card·fpx·duitnow·bank_transfer·cash·ewallet·other) · `PaymentStatus` (pending·succeeded·failed·refunded·cancelled). All `: string` backed.

### Observer — the only writer of paid caches

`App\Observers\PaymentObserver` (registered in `AppServiceProvider::boot()`). On every payment `saved` / `deleted` / `restored` it recomputes from the ledger:

- `orders.amount_paid_myr` = `max(0, SUM(amount_myr))` over the order's **succeeded** rows (refunds subtract).
- `invoices.amount_paid` + `invoices.status` — flips `issued ⇄ paid` automatically when fully allocated; **never** touches a `void` invoice (manual terminal state).

It writes via `saveQuietly()` on Order/Invoice, and the observer is on Payment, so it does not recurse. Only `succeeded` rows count — a `pending` gateway row is ignored until its webhook flips it.

### Numbering

Documents use the standalone AXN family, each type its own yearly counter minted atomically by `ReferenceCodeGenerator::generate(DocumentType::X)`:

| Type | Code | DocumentType |
|------|------|--------------|
| Quotation | `AXNQ-2026-0012` | `Quotation = 'Q'` |
| Order | `AXNO-2026-0012` | `Order = 'O'` |
| Invoice | `AXNI-2026-0001` | `Invoice = 'I'` |
| Receipt | `AXNR-2026-0001` | `Receipt = 'R'` |
| Payment | `AXNP-2026-0001` | `Payment = 'P'` |

`DocumentIssuer` mints invoice/receipt numbers via the generator (it no longer derives `INV-`/`RCP-{quotation}` strings); payments mint `AXNP` the same way. Already-issued `INV-`/`RCP-` rows stay frozen.

### Backfill (`...000004_backfill_payments_from_legacy_amounts`)

Converts legacy derived money into real rows, then lets the observer recompute caches:

1. One `manual`/`succeeded` payment per existing non-void receipt, linked back via `receipts.payment_id`.
2. One catch-up row for any legacy `amount_paid_myr` not covered by a receipt.

Idempotent: linked receipts are skipped, and the catch-up gap is measured against the **current ledger sum** (not the legacy figure), so a re-run inserts nothing. `down()` drops backfilled rows (`notes LIKE 'Backfilled%'`) under `withoutEvents` so the recomputed caches are left intact.

> A backfill that finds a receipt without a matching `amount_paid_myr` **heals** the drift — the ledger (receipt = proof money landed) becomes the truth and the cache is corrected. This happened on the first production-data run (an order with a RM7,000 receipt but `amount_paid_myr = 0`).

## Phase 2 — invoices module (shipped)

- **`invoices.due_at`** — new nullable date (overdue is now a real per-invoice property). Set to `issued_at + 14 days` at issue time (`DocumentIssuer::issueInvoice`, overridable via `dueAt`); existing rows backfilled to the same.
- **`GET /v1/admin/invoices`** (`InvoicesController@index`) — cross-order list, paginated 20. Filters: `status` (`issued`/`paid`/`void`/**`overdue`** = issued + `due_at < today`), `type`, `order_id`, `search` (invoice #, payment ref, order #, client name/email). `GET /v1/admin/invoices/{invoice}` for detail. `InvoiceResource` carries an `is_overdue` flag + client/order context.
- **`pages/admin/invoices/index.vue`** — mirrors the orders list (`AdminExpandingSearch` + status/type `AdminStatusFilter`, desktop `admin-table-card` table, mobile card list, pagination). Rows link to the parent order. Overdue tinted via `--color-danger`. `AdminStatusPill` + `main.css` gained `issued`/`paid`/`void` tones (light + dark). Nav: **Invoices** (`i-lucide-receipt-text`) after Orders.

### Not in Phase 1 (deliberately)

Removing the direct paid-amount write paths (`OrdersController::updatePayment`, the order-detail "Mark paid" block) is **Phase 3** — done together with the frontend refactor and the new record-payment endpoint, so nothing breaks mid-transition. Phase 1 is purely additive.

## Phase 3 — payments module (shipped)

The money ledger as a first-class admin module, plus the order page reduced to a read-only financial hub.

- **`PaymentService`** (record / refund / refundable) + **`PaymentsController`** (`index`/`show`/`store`/`refund`/`issueReceipt`) + **`PaymentResource`**. `DocumentIssuer::receiptForPayment()` mints an `AXNR` receipt from a payment (1:1, works with no invoice).
- **Endpoints:** `GET /v1/admin/payments` (+ `/{id}`); `POST /v1/admin/orders/{order}/payments` (record); `POST /v1/admin/payments/{id}/refund`; `POST /v1/admin/payments/{id}/receipt` (idempotent).
- **Pages:** `/admin/payments` (ledger list — gateway/method/type/status filters, refunds as `−RM`), `/admin/payments/{id}` (detail + refund + issue-receipt). Nav: **Payments** (`i-lucide-wallet`).
- **Create flows live in the modules**, reached from the order via shortcut (chosen UX): `pages/admin/invoices/new.vue` (issue invoice, quotation-derived) and `pages/admin/payments/new.vue` (record payment) both read `?order_id`. Index pages honour `?order_id` (filtered view + clear).
- **Order detail is now read-only + shortcuts** (`orders/[id].vue`): a derived payment summary, read-only invoice + payment mini-lists (rows link to their detail), and "Issue invoice" / "Record payment" / "View all" buttons into the modules. No inline create forms.
- **Direct paid-amount writes removed:** `OrdersController::updatePayment` (route + method) deleted; `DocumentIssuer::issueInvoice` no longer accrues a payment (the old `accruePayment` is gone) — invoices issue **unpaid**, and payment is recorded separately through the ledger. The observer remains the sole writer of the paid caches.

## Phase 4 — invoice editing + client email (shipped)

Invoices are editable **in place** and can be emailed to the client. The frozen-snapshot rule survives via re-freezing, not mutation:

- **`invoices.inputs`** (JSON) — the validated issue-form fields (`invoiceType`, `amount`, discounts, promo, `notes`, `dueAt`), stored at issue time. Editing merges new fields over these and re-runs `DocumentMapper::forOrder` — the payload is always a pure function of (order, inputs, number, issued). Same AXNI number, same public token, same issued date. Legacy invoices (no `inputs`) fall back to `DocumentIssuer::effectiveInputs()` — amount from `amount_total`, notes flattened from the payload.
- **`PUT /v1/admin/invoices/{invoice}`** (`InvoicesController@update` → `DocumentIssuer::updateInvoice`). Guards: `paid` and `void` → 409 fully read-only (frozen records); a **partially-paid issued invoice locks its amount-bearing fields** (`Invoice::amountsLocked()`) — those 422, only `notes`/`dueAt` may change. Editing totals with money recorded would contradict the payments and their receipts.
- **`POST /v1/admin/invoices/{invoice}/send`** — queues `SendInvoiceEmail` (`InvoiceMail`, markdown `mail.client-invoice`): summary + "View invoice" button to the public PDF link, with the PDF fetched from the frontend Nitro renderer (`services.frontend.url`, 30s timeout) and attached. **Render failure degrades to link-only** — never blocks the send. The typed recipient is used for that send only (never written back to the client); `emailed_at`/`emailed_to` stamp the last send.
- **Pages:** `invoices/edit.vue?id=` (same `AdminInvoiceForm` as issuing, pre-filled from `inputs`, locked fields disabled), Edit/Email buttons + quotation link on `invoices/[id].vue`, and an Invoices card on the quotation detail sidebar. The invoices list shows just the invoice number — order/quotation context lives on the detail page.

## Out of scope (by design)

MyInvois / LHDN e-invoicing (invoice-side, separate), multi-currency (MYR only), installment scheduling, dunning/reminders.
