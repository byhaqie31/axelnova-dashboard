# REFERRAL-COMMISSION-FLOW-DESIGN

Design spec for wiring referral **attribution** through to **commission**, anchored on the
quotation. Drafted 2026-07-01. Extends the referral programme built in Phase 2 + Phase 4
of the [DASHBOARD-REVAMP-PLAN](DASHBOARD-REVAMP-PLAN.md).

> Status: approved design, pending implementation plan. Commission stays **derived + displayed
> only** — no automated payout is built (Section 3 of the revamp plan still holds).

---

## 1. Problem

Today two things run in parallel and never meet:

- **Attribution:** a `?ref` visit tags an **inquiry** (`inquiries.referral_partner_id`, `source='referral'`) — but only for admin analytics. It creates no referral, shows nothing in the partner portal, and earns no commission. The tag also stops at the inquiry; quotations/orders don't carry it.
- **Commission:** only an explicit `Referral` record (from the `/partners/refer` form or in-portal "refer another") earns anything, and only when an admin **manually** sets `linked_order_id`.

So a referred company that clicks a link, inquires, gets quoted, and pays — earns the partner nothing automatically. This spec joins the two tracks.

## 2. Settled decisions

- **The quotation is the anchor.** A referral ties to a `quotation_id`; the order is reached via the quotation (order → quotation is 1:1).
- **Draft on quote, earn on deposit.** A referral becomes a `draft` when its inquiry is **quoted**; it starts **earning** when the order's **deposit** (first collected payment) lands.
- **Auto-create the draft on quote** for `?ref`-tagged inquiries (quoting is the human gate, so no spam drafts).
- **Direct-form referrals are claims** — unqualified until a matching quotation ties to them; this tie is also the dedup step.
- **Approach A** — the `Referral` record stays the partner-facing entity and gains the quotation link + confirmed %; `quotations` also carry `referral_partner_id` purely to complete the attribution chain.
- **Confirmable %** — the tier is an early estimate; staff confirm/adjust the real rate (5–15%) at the quotation→order step, editable afterward (payout is manual, so no hard lock).

## 3. Data model

### `referrals` (extend)
- `quotation_id` — nullable FK → `quotations`, `nullOnDelete`. **The anchor.**
- `commission_pct` — nullable `unsignedTinyInteger`; the **confirmed** rate. `commission_tier_pct` remains the tier **estimate**. Effective rate = `commission_pct ?? commission_tier_pct`.
- `status` enum gains **`draft`** → `new, contacted, qualified, draft, converted, rejected`.
- `linked_order_id` — retained for legacy rows; no longer the source of truth.

### `quotations` (extend)
- `referral_partner_id` — nullable FK → `referral_partners`, `nullOnDelete`. The credited partner, completing **inquiry → quotation → order → payments** for revenue attribution (closes the Phase‑1 reporting gap).

### Data backfill
- For existing referrals with `linked_order_id`, set `quotation_id` from that order's `quotation_id`; set the order's `quotation.referral_partner_id` from the referral. Keeps historical referrals coherent under the new anchor.

## 4. Lifecycle & triggers

| Status | Meaning | Commission |
|---|---|---|
| `new` / `contacted` / `qualified` | **Claim** — referred (form), not yet quoted | none (no value yet) |
| `draft` | A quotation is tied (quoted) | **estimated** only |
| `converted` | Order's **deposit paid** | **earning** (rate × collected) |
| `rejected` | Lost / duplicate | none |

**Triggers:**
1. **Quote created for a `?ref` inquiry** (`QuotationsController`, where `inquiry.quotation_id` is set): propagate `inquiry.referral_partner_id` → `quotation.referral_partner_id`; **auto-create a `draft` referral** anchored to the quotation, credited to the partner, business details from the inquiry, `commission_tier_pct` from the referrer's tier. *Dedup:* if a claim already exists for that partner + company (match on business email/name), tie **that** claim (set `quotation_id`, status `draft`) instead of duplicating.
2. **Staff tie a claim to a quotation** (manual; the non-`?ref` path): set `referral.quotation_id` + `quotation.referral_partner_id`, status → `draft`.
3. **Quotation → order (accept)**: staff **confirm `commission_pct`** (default = tier estimate, range 5–15). Editable later.
4. **Deposit paid**: in `PaymentObserver`, when an order's `amount_paid_myr` crosses 0 → >0, flip the tied referral (via `order.quotation`) to `converted`.
5. **Manual override**: admin can set any status from the referrals screen / partner detail page (triage, corrections, rejecting duplicates). Auto-triggers still apply.

## 5. Commission math (derived, never stored)

- **Effective rate** = `commission_pct ?? commission_tier_pct`.
- **Earned** = Σ over `converted` referrals of `rate × collected` (`order.amount_paid_myr`).
- **Estimated (pending)** = Σ over referrals that **have an order** and aren't fully collected of `rate × (final_amount_myr − amount_paid_myr)` — this covers both accepted drafts (order exists, no deposit yet) and partially-collected converted ones.
- A **draft with only a quotation** (no order yet) is listed as `draft` with **no dollar figure** — the value isn't fixed until an order does (`final_amount_myr`); the quotation itself is a range.
- A refund lowers `amount_paid_myr`, so earned recomputes automatically. No stored commission column anywhere.

## 6. UX

### Admin — Referral Partner **detail** page (new) — `/admin/referral-partners/[id]`
- **Header:** name, email, `?ref` code, status (pending/active/paused), tier bands, total earned + estimated; **Approve / Reset-passcode** actions available here too (the list keeps its quick action).
- **Their referrals:** each referred company — business name, **status pill** (claim / draft / converted / rejected), tied quotation, confirmed %, estimated-vs-earned.
- **Manual status control** per referral (dropdown, full override).
- **"Tie to quotation"** action on a claim (dedup resolution).

### Admin — quotation accept
- A **Commission %** field (default tier estimate, 5–15) when accepting a quotation that has a tied referral.

### Admin — Referral Partners list
- Rows link to the new detail page.

### Partner portal
- Referrals list gains **Draft** vs **Converted** pills; headline "earned" counts only converted (deposit-paid), "estimated" covers referrals with an order that isn't fully collected. Quotation-only drafts show as `draft` without a figure.

## 7. Edge cases

- **Dedup:** staff tie one claim to the quotation; mark the others `rejected`.
- **Order cancelled:** referral drops back to `draft` (no longer earning).
- **`?ref` inquiry never quoted:** stays attribution-only on the inquiry; no referral.
- **Re-quote:** credit follows the **accepted** quotation.
- **Claim never quoted:** stays a claim; never earns.
- **Refund after deposit:** earned recomputes down (derived).

## 8. Out of scope

- No automated payout / funds-transfer — commission stays derived + displayed, paid manually via the existing commission-email flow.
- No change to the `?ref` cookie / consent intake (already shipped in Phase 2).

## 9. Affected files (map)

```
backend/
  database/migrations/    + referrals: quotation_id, commission_pct, 'draft' status
                          + quotations: referral_partner_id
                          + backfill quotation_id from linked_order_id
  app/Models/Referral.php     ~ quotation() relation, effectivePct(), order-via-quotation
  app/Models/Quotation.php     ~ referral_partner_id fillable + referrer() relation
  app/Http/Controllers/Api/V1/Admin/QuotationsController.php
                               ~ propagate ref on quote-create; auto-create/tie draft;
                                 confirm commission_pct on accept
  app/Observers/PaymentObserver.php   ~ flip referral → converted on first collected payment
  app/Http/Controllers/Api/V1/Admin/ReferralsController.php
                               ~ tie-to-quotation; set/edit commission_pct (updateStatus exists)
  app/Http/Controllers/Api/V1/Admin/ReferralPartnersController.php
                               + show() → partner + their referrals + stats
  app/Http/Controllers/Api/V1/Partner/DashboardController.php
                               ~ lifecycle-aware earned/estimated math
  app/Http/Controllers/Api/V1/Admin/AnalyticsController.php
                               ~ attribution via the completed inquiry→…→payments chain
  app/Http/Resources/          ~ ReferralResource (quotation, pct, status), ReferrerResource (referrals, stats)

frontend/app/
  pages/admin/referral-partners/[id].vue     + partner detail (referrals, manual status, tie-to-quotation)
  pages/admin/referral-partners/index.vue    ~ rows link to detail
  pages/admin/quotations/…                    ~ commission % field on accept
  pages/partners/portal.vue                   ~ draft/converted pills, estimated vs earned
```
