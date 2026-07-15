# Payment → invoice allocation (link after the fact)

**Date:** 2026-07-15
**Status:** Approved

## Problem

Recording a manual payment on an order without picking an invoice leaves the
payment with `invoice_id = null`. `PaymentObserver` only recomputes the invoice
*linked to the saved payment*, so the order's paid total updates but the
invoice stays `issued` forever. There is no endpoint to change a payment's
allocation after the fact — the admin's only workaround is deleting and
re-recording the payment.

## Decisions (user-confirmed)

- **Scope: full allocation management** — link an unallocated payment, move a
  wrongly-tagged one to another invoice, or unlink. One endpoint covers all
  three.
- **Over-allocation: warn, don't block** — the confirm popup flags when the
  payment exceeds the invoice's outstanding balance, but the admin may proceed.
  Ledger rows are truth; caches derive.
- **Prevention tweak included** — the record-payment form preselects the
  order's only open invoice when there is exactly one.

## Backend

### Route

`PATCH /v1/admin/payments/{payment}/allocation` — admin group in
`backend/routes/api.php`, name `payments.allocation`. Body:

```json
{ "invoice_id": 123 }   // or null to unlink
```

### Controller — `PaymentsController::allocate()`

Guards (422 on failure):

- `type === payment` — refund rows follow their parent's allocation, never
  their own.
- `status === succeeded`.
- `invoice_id`, when non-null: must exist, belong to the same `order_id` as the
  payment, not be soft-deleted, and not be `void` (the observer never touches a
  void invoice's caches, so linking there would be a silent no-op).

No-op (same `invoice_id` as current) returns 200 with the unchanged resource.
Returns the reloaded `PaymentResource` on success.

### Service — `PaymentService::allocate(Payment $payment, ?Invoice $invoice, ?int $actorId)`

One DB transaction, in this order:

1. Remember the old invoice (if any).
2. Move `invoice_id` on the payment's **refund children first**, quietly — so
   the observer's recompute of the new invoice already sees the negative rows.
3. Set `invoice_id` on the payment and `save()` — `PaymentObserver::saved()`
   fires and recomputes the new invoice (and the order, harmlessly unchanged).
4. Recompute the **old** invoice so it doesn't keep a stale `amount_paid`.
5. Update the payment's receipt row (if issued): `receipts.invoice_id` is
   display-only and follows the allocation. The receipt PDF is untouched — it
   anchors the payment, not the invoice.
6. Activity log: `payment.allocated` / `payment.unallocated` with
   `invoice_id` + `previous_invoice_id`.

### Observer refactor

Extract the invoice branch of `PaymentObserver::recompute()`
(`PaymentObserver.php:58-70`) into a public static
`PaymentObserver::recomputeInvoice(Invoice $invoice): void`. The observer's own
`recompute()` calls it; `PaymentService::allocate()` calls it for the detached
old invoice. The observer class remains the sole writer of the paid caches.

## Frontend

### Payment detail page — `pages/admin/payments/[id].vue`

In the "Client & links" card, for succeeded `payment`-type rows only:

- Unallocated → an **"Allocate to invoice"** action.
- Allocated → a small **"Change"** action next to the invoice row.

### New component — `components/admin/PaymentAllocateModal.vue`

Mirrors the `LinkQuotationModal` pattern:

- Fetches `GET /api/v1/admin/orders/{order_id}` (already returns `invoices[]`
  with number, status, `amount_total`, `amount_paid`) — no new read endpoint.
- Lists the order's non-void invoices: number, status pill,
  `RM <outstanding> of RM <total>`. Adds a "Not allocated" option when the
  payment is currently allocated (unlink path).
- Selecting + pressing **Link** opens the confirm step (existing
  `AdminConfirmDialog` via `useConfirm()`), stating the predicted outcome:
  - fully covered → "AXNI-2026-0003 will be marked **paid** (RM 500.00 fully
    covered)" — accent CTA;
  - partial → "will remain issued — RM 200.00 of RM 500.00 covered" — accent
    CTA;
  - over-allocation → "this exceeds the invoice's outstanding by RM X" —
    warning CTA, proceed allowed.
- Prediction uses the payment's net-of-refunds contribution
  (`refundable_myr`); the server recompute is the source of truth.
- On confirm: PATCH → success toast → emit so the page refetches the payment.

### Prevention tweak — `pages/admin/payments/new.vue`

After the order loads, when no `?invoice_id` was passed and the order has
exactly **one** invoice with status `issued`, preselect it in the "Allocate to
invoice" dropdown. Still changeable to "— Not allocated —". Deposits recorded
before any invoice exists stay legitimately unallocated.

## Testing

PHPUnit feature tests under `backend/tests/Feature/Payments/`:

- Link fully covering payment → invoice flips to `paid`, `paid_at` stamped.
- Partial link → invoice stays `issued`, `amount_paid` updated.
- Relink A→B → both invoices' caches recomputed (A reverts, B gains).
- Unlink → old invoice's cache reverts.
- Payment with refunds → refund children cascade; invoice sum is net.
- Issued receipt follows the allocation (`receipts.invoice_id`).
- Guards: cross-order invoice 422, void invoice 422, refund row 422,
  non-succeeded payment 422.

Frontend: ESLint + vue-tsc pass (CI gates).

## Out of scope

- Splitting one payment across multiple invoices (allocation stays 1:1).
- Editing any other payment field (amount, method, reference stay immutable).
- Gateway-driven allocation changes (webhook phases handle their own linking).
