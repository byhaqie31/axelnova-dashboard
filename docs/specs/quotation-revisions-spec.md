# Axel Nova admin — Quotation revisions & order continuity

**Spec v1 · 11 October 2026 · for the admin + MCP connector repo(s)**

Trigger case: AXNQ-2026-0017 (M Automobile Service) was accepted on an instalment plan, then the client asked to switch to the 40/40/20 lump-sum plan. Today an accepted quotation cannot be changed, rejected or superseded, and the order created from it cannot be unwound. This spec turns "the client changed their mind after accepting" into a normal, auditable path.

---

## 1. Model

### 1.1 Quotation family and revisions

A **quotation** keeps its reference (`AXNQ-2026-0017`) for the life of the project. Its commercial content lives in **revisions**, each an immutable snapshot.

```
quotations            id, reference_code, client_id, current_revision_id (FK), status, locale,
                      created_at, updated_at
quotation_revisions   id, quotation_id, number (1,2,3…), status, change_reason,
                      layout, project, intro, scope(JSON: sections/included/options/care),
                      payment_plan(JSON), estimate_myr, document(JSON — the seeded/edited doc),
                      pdf_url, sent_at, accepted_at, superseded_at, created_by, created_at
```

- Every existing quotation migrates to one revision `R1` carrying its current content; `current_revision_id` points to it. Nothing changes for them visibly.
- Reference display: `AXNQ-2026-0017` on R1; `AXNQ-2026-0017 · R2` on later revisions (PDF header, admin lists, filenames `AXNQ-2026-0017-R2.pdf`).
- Family `status` is derived from revisions: `draft` (no revision ever sent) → `sent` → `accepted` (current revision accepted) → `rejected` / `expired`. A family never shows `superseded`; only revisions do.

### 1.2 Revision status machine

```
draft ──send──► sent ──accept──► accepted ──(newer revision accepted)──► superseded
  │                │
  └──discard───┐   └──reject──► rejected
               ▼
            discarded
```

Rules
- `draft` is the only editable state (admin editor and `update_draft_quotation`).
- At most **one** non-final revision (`draft` or `sent`) per family at a time.
- A new revision can be created from any `accepted` or `sent` revision (clone). Creating from `sent` auto-moves that one to `discarded` — the client gets the replacement instead.
- `accept` on a revision whose family already has an accepted current revision performs the **supersede** transaction in §3.
- Accepted and superseded revisions are frozen: no field edits, PDF regenerated only from stored content (never re-priced).

### 1.3 Orders

An **order** is the project, created once on first acceptance, and survives revisions.

```
orders   id, quotation_id, current_revision_id, status(active|cancelled|completed),
         total_myr, paid_to_date_myr, balance_myr, revised_at, cancel_reason
```

- Order `cancelled` means the project is off. It is **not** the way to change terms.
- On supersede, the order re-points to the new revision and recomputes totals and schedule (§3).
- Admin order page shows the revision chain with dates and change reasons.

---

## 2. Creating a revision

Admin: **"Revise"** button on an accepted or sent revision. Connector: `create_revision`.

Inputs: `change_reason` (required, free text, shown on the order timeline and in the PDF's small print as *"Rev. 2 — menggantikan Rev. 1: <reason>"* / *"replaces R1: <reason>"* per locale).

Behaviour
1. Refuse if another revision of the family is `draft` or `sent` (message names it).
2. Clone the source revision's content into a new `draft` revision with `number = max + 1`.
3. Document is re-seeded from the cloned scope (hand edits on the source are copied too, then editable).
4. Return the new revision; nothing is sent.

The founder then edits (payment plan, scope, price) exactly as with any draft, sends, and the client accepts.

---

## 3. Supersede transaction (on accepting revision Rn when Rn‑1 is accepted)

Runs atomically.

1. `Rn.status = accepted`, `Rn-1.status = superseded`, `superseded_at = now`, `quotation.current_revision_id = Rn`.
2. **Order**: keep the same order; set `current_revision_id = Rn`, `total_myr = Rn.estimate`, `revised_at = now`.
3. **Invoices** (see §4) — void unpaid, keep paid, compute the delta.
4. **Payment schedule** regenerated from `Rn.payment_plan`, netted against paid-to-date (§4.3).
5. **Care Plan subscription**: if Rn‑1 included Care Plan in the monthly amount and Rn does not (or vice versa), end the existing subscription on the supersede date and open a new one only if Rn includes/ticks it. Never double-bill the same month.
6. Timeline entry on the order: *"Revised to R2 — <change_reason> — by <user>"*.
7. Activity log + optional WhatsApp/email notice to the founder (not the client).

---

## 4. Money rules

### 4.1 Invoices belong to the order, tagged with the revision that produced them

```
invoices  …, order_id, revision_id, kind(deposit|milestone|instalment|adjustment|care_plan),
          status(draft|issued|paid|void), amount_myr, due_date, paid_at, void_reason
```

### 4.2 On supersede
- `issued` and unpaid → `void`, `void_reason = "Superseded by R{n}"`.
- `paid` → untouched, counted into `order.paid_to_date_myr`.
- `draft` → deleted.

### 4.3 Netting the new schedule
Let `P = paid_to_date`, `T = Rn.total`, and `S = [s1, s2, …]` the new plan's scheduled amounts in order (deposit first).

Walk the schedule, consuming `P`:
- while `P ≥ s_i`: mark s_i as *settled by prior payment*, `P -= s_i`;
- the first partially covered item becomes an **adjustment invoice** for `s_i − P`;
- remaining items are issued as normal.

If `P > T`, raise a flag on the order (*"overpaid by RM x — credit note required"*) and stop; credit notes are out of scope for v1 and handled manually.

**Worked example (M Automobile).** R1 instalment: deposit RM 2,700 paid. R2 lump sum 40/40/20 on RM 10,800 → schedule [4,320, 4,320, 2,160]. P = 2,700 → first item partially covered → adjustment invoice **RM 1,620** ("Deposit top-up, R1 → R2"), then RM 4,320 at demo, RM 2,160 at go-live. Care Plan: R1 included it monthly; R2 optional → subscription ends 11 Oct 2026, reopens only if ticked on R2.

### 4.4 Numbering
Invoice numbers are never reused; voided numbers stay in the register marked void (same gap-free principle we sell to clients).

---

## 5. PDF and admin surfaces

- **PDF header**: `AXNQ-2026-0017 · R2`; small print under the title in the active locale: en *"Revision 2 — replaces Revision 1 (accepted 11 Oct 2026). Reason: …"*, bm *"Semakan 2 — menggantikan Semakan 1 …"*. Locale rule from the earlier spec applies: chrome from locale strings, content as authored.
- **Admin quotation page**: revision tabs (R1 · R2 · …) with status chips; current revision bold; "Revise" on accepted/sent; "Discard" on draft.
- **Admin order page**: revision timeline, paid-to-date, netted schedule, flags (overpaid, care plan changed).
- **Lists & reports**: one row per family; conversion counts families whose current revision is accepted; a "revised after acceptance" filter.

---

## 6. MCP connector contract

Add
- `create_revision({ reference_code, change_reason })` → new draft revision (`reference_code`, `revision_number`).
- `get_quotation` returns `{ family, current_revision, revisions:[{number,status,estimate,accepted_at,change_reason}] }`.
- `update_draft_quotation` targets the family's draft revision (error if none; message says to call `create_revision`).
- `send_revision({ reference_code })` — optional for v1; founder can still send from admin.

Also advertise in the published schema the fields the server already accepts but does not list: `detailed.deposit_amount_myr`, `detailed.payment_plan`, `instalment_months`, `instalment_amount_myr`, `billing_day`, `first_instalment_date`, `includes_care_plan`, top-level `locale`.

---

## 7. Out of scope (v1)
Credit notes; client-facing self-service acceptance of revisions; revising after the order is `completed` (use a new quotation family); multi-currency.

---

## 8. Acceptance tests
1. Migration: every existing quotation has exactly one revision, current pointer set, PDFs still open.
2. Cannot create a second open revision; cannot edit an accepted revision.
3. Supersede: statuses flip atomically; order keeps its id; unpaid invoices void; paid kept; schedule netted per §4.3 (unit test the M Automobile numbers: 2,700 paid → 1,620 adjustment → 4,320 → 2,160).
4. Care Plan subscription boundary: no month billed twice, none skipped.
5. PDF shows revision label and reason in both locales; `Page x of y` intact.
6. Connector: `create_revision` → `update_draft_quotation` → admin accept → `get_quotation` shows R2 current, R1 superseded.
7. Reports count the family once.
