# Manage Client on Order / Quotation Detail — Design

**Date:** 2026-07-20
**Status:** Approved (build in progress; not committed/shipped until owner says so)

## Problem

Orders are created from quotations, and the client is resolved by **email**
(`Client::firstOrCreate(['email' => ...])`, `QuotationsController::resolveClient`).
When a quotation went out with a wrong or shared email, its order got welded to the
**wrong `Client` row** in production. Because both orders and quotations reference the
shared `clients` table, every document for that client then shows wrong contact info.

Admins need the flexibility to **correct the record** on the Order/Quotation detail page:
fix the client's details, and/or re-point the record at the correct client. Nothing else
on the order (status, money, dates, line items) should change.

## Data model (the constraint)

```
clients (canonical contact; email is UNIQUE, the natural match key)
  · name, email, phone, company
      ▲ client_id                 ▲ client_id
  quotations ───────────────────► orders
  · snapshot copy of name/email/  · NO contact columns — OrderResource reads
    phone/company (re-copied from    name/email/phone/company THROUGH client (live)
    client on every save)
  · quotation_id ───────────────► orders.quotation_id
```

- **Order** contact is read live through `client` (`OrderResource`), so editing the
  client is immediately reflected.
- **Quotation** contact is its own **snapshot** columns (`QuotationResource` passes them
  through); `pricedAttributes()` copies them from the client on every save. Editing the
  client therefore leaves quotation cards/PDFs **stale** unless the snapshot is re-synced.
- `clients.email` is `unique` — `PUT /clients/{id}` already validates uniqueness with
  self-ignore; a "create new client" with an existing email must **link to the existing
  client**, not error/duplicate.

## Scope

Two capabilities on both the **Order** and **Quotation** detail pages, both routing through
the shared `Client`:

1. **Edit client details** — correct name / email / company / phone (writes to `Client`;
   propagates to every doc for that client).
2. **Re-link** — point this record at the correct client (search existing, or create new),
   fixing a wrong weld.

No completion gate — the bad records may already be completed, so the tool stays available
regardless of status. Already-issued invoices/receipts are frozen snapshots and are never
rewritten by a re-link.

## UI — `ManageClientModal.vue` (new, shared)

A **"Manage client"** button on the header card of `admin/orders/[id].vue` and
`admin/quotations/[id].vue` (quotation button appears on the non-draft read view; drafts
already edit contact via the builder). Opens a modal with two modes:

- **Edit details** — name/email/company/phone inputs seeded from the current client.
  Save → `PUT /v1/admin/clients/{id}`.
- **Change client** — search box hitting the existing `GET /clients?search=`, a result
  list to pick the correct client, and a "Create new client" fallback.
  Save → the re-link endpoint.

Mirrors `ClientFormModal.vue` + the `apiFetch` / apply-lean-response / `useAdminToast`
convention. After save, the page merges the returned record into local state (no reload).

## Backend

- **Edit details:** reuse existing `PUT /v1/admin/clients/{client}` unchanged.
- **Re-link (new endpoints):**
  - `POST /v1/admin/orders/{order}/client` — body `{client_id}` OR
    `{client: {name,email,phone,company}}`. Sets `order.client_id`. **Cascade:** also
    re-links the order's **source quotation** (`order.quotation_id`) and re-syncs that
    quotation's snapshot, since a mis-matched order's quotation is mis-matched too.
  - `POST /v1/admin/quotations/{quotation}/client` — same shape; sets `quotation.client_id`
    and re-syncs the snapshot. (Standalone quotation → touches only the quotation.)
  - Create-new path is `firstOrCreate(['email'])`: an existing email **links to the
    existing client** and the response signals this so the UI can say "linked to X".
  - Return the updated resource (`OrderResource` / `QuotationResource`) for lean apply.

- **`ClientObserver@updated` (new):** when a client's contact fields change, re-sync the
  snapshot columns on that client's quotations. DRY — also fixes the pre-existing
  staleness gap on the Customers-page edit path. Matches the `PaymentObserver` /
  `FeedbackObserver` idiom. Snapshot sync is a single shared helper (e.g.
  `Quotation::syncContactFromClient()` or a small service) reused by the observer and the
  re-link endpoints.

## Validation & edge cases

- Email uniqueness → existing self-ignore rule; friendly collision message.
- Create-new with an existing email → link to existing client (no duplicate).
- Re-link never rewrites issued invoices/receipts (frozen snapshots — correct).
- Re-link body must supply exactly one of `client_id` or `client{}`.

## Testing (feature tests, MySQL test DB)

- Edit client from order page → order reflects new details.
- Edit client → its quotations' snapshot columns re-sync (observer).
- Re-link order to an existing client → `order.client_id` updated.
- Re-link order create-new (new email) → client created + linked.
- Re-link order create-new (existing email) → links to existing, no duplicate.
- Re-link order cascades to source quotation (client_id + snapshot).
- Re-link quotation updates client_id + snapshot.
- Issued invoice/receipt on the order is untouched by a re-link.

## Out of scope

Merging/deleting orphaned clients, bulk fixes, any change to order money/status/line items.
