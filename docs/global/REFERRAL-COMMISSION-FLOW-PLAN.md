# Referral Commission Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire referral attribution through to commission — a `?ref` lead that gets quoted becomes a draft referral, and starts earning (derived) when its order's deposit is paid.

**Architecture:** Quotation-anchored (Approach A). The `Referral` record stays partner-facing and gains a `quotation_id` anchor + a confirmed `commission_pct` + a `draft` status; `quotations` gain `referral_partner_id` to complete the inquiry→quotation→order→payments chain. Attribution auto-flows at quote-time; the referral flips to earning in `PaymentObserver` when the deposit lands. Commission is always derived, never stored.

**Tech Stack:** Laravel 11 (PHP 8.4), Sanctum, Eloquent observers; Nuxt 4 (Vue 3 + @nuxt/ui v4). Everything runs in Docker (`axelnova-backend-dev`, `axelnova-frontend-dev`).

Spec: [REFERRAL-COMMISSION-FLOW-DESIGN.md](REFERRAL-COMMISSION-FLOW-DESIGN.md).

## Global Constraints

- **No automated payout.** Commission is **derived + displayed only**; no stored commission column anywhere. Effective rate = `commission_pct ?? commission_tier_pct`. Confirmed rate range **5–15**.
- **Deposit = first collected payment** (`order.amount_paid_myr` crossing 0 → >0), detected only in `PaymentObserver` (the sole writer of that cache).
- **New migrations** are dated `2026_07_03_*` (after the latest `2026_07_02_000005`). Use MySQL `ALTER TABLE … MODIFY … ENUM(...)` to add the `draft` status (the column is a native enum).
- **No test harness in this repo.** Verify each task by running artisan/migrations in the container and a **throwaway PHP script** (`backend/_verify_*.php`, HTTP-kernel style, `DB::beginTransaction()`/`rollBack()`, deleted after) and/or curl SSR checks against `http://localhost:3003`. Match how Phase 2/4 were verified.
- **Commits on request only** — do not auto-commit. Each task ends with a "Checkpoint" the user reviews; commit only when the user asks.
- **Frontend conventions:** CSS variables for all theming (never bind layout bg to `colorMode.value`), `<UIcon name="i-lucide-…">`, mirror existing admin/portal pages, no hardcoded hex.
- **Passcode / auth invariants unchanged** by this work.

---

## File Structure

```
backend/
  database/migrations/
    2026_07_03_000001_add_referral_anchor_to_referrals.php     (new)   quotation_id, commission_pct, 'draft' enum
    2026_07_03_000002_add_referral_partner_id_to_quotations.php (new)
    2026_07_03_000003_backfill_referral_quotation_anchor.php    (new)   quotation_id from linked_order_id
  app/Models/Referral.php        (mod)  quotation() relation, effectivePct(), fillable
  app/Models/Quotation.php       (mod)  referral_partner_id fillable, referrer() relation
  app/Services/Referrals/ReferralAttributionService.php  (new)  attribute a quotation to a referrer
  app/Http/Controllers/Api/V1/Admin/QuotationsController.php  (mod)  call service on link; confirm % on accept
  app/Http/Controllers/Api/V1/Admin/InquiriesController.php   (mod)  call service on linkQuotation
  app/Observers/PaymentObserver.php   (mod)  flip referral draft↔converted on deposit
  app/Http/Controllers/Api/V1/Admin/ReferralsController.php   (mod)  tie-to-quotation, set commission_pct
  app/Http/Controllers/Api/V1/Admin/ReferralPartnersController.php (mod)  show()
  app/Http/Controllers/Api/V1/Partner/DashboardController.php (mod)  lifecycle-aware math
  app/Http/Resources/ReferralResource.php   (mod)  quotation, effective pct, status
  app/Http/Resources/ReferrerDetailResource.php  (new)  partner + referrals + stats
  routes/api.php   (mod)  partner detail + tie + commission routes
frontend/app/
  pages/admin/referral-partners/[id].vue   (new)  detail page
  pages/admin/referral-partners/index.vue  (mod)  link rows to detail
  pages/partners/portal.vue                (mod)  draft/converted pills
  (admin quotation accept UI)              (mod)  commission % field
```

---

### Task 1: Schema — referral anchor + quotation attribution + backfill

**Files:**
- Create: `backend/database/migrations/2026_07_03_000001_add_referral_anchor_to_referrals.php`
- Create: `backend/database/migrations/2026_07_03_000002_add_referral_partner_id_to_quotations.php`
- Create: `backend/database/migrations/2026_07_03_000003_backfill_referral_quotation_anchor.php`

**Interfaces:**
- Produces: `referrals.quotation_id` (nullable FK), `referrals.commission_pct` (nullable tinyint), `referrals.status` enum now includes `draft`; `quotations.referral_partner_id` (nullable FK).

- [ ] **Step 1: Migration — referrals anchor + confirmed pct + draft status**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->after('referral_partner_id')
                ->constrained('quotations')->nullOnDelete();
            $table->unsignedTinyInteger('commission_pct')->nullable()->after('commission_tier_pct');
        });
        DB::statement("ALTER TABLE referrals MODIFY status ENUM('new','contacted','qualified','draft','converted','rejected') NOT NULL DEFAULT 'new'");
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
            $table->dropColumn(['quotation_id', 'commission_pct']);
        });
        DB::statement("ALTER TABLE referrals MODIFY status ENUM('new','contacted','qualified','converted','rejected') NOT NULL DEFAULT 'new'");
    }
};
```

- [ ] **Step 2: Migration — quotations.referral_partner_id**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('referral_partner_id')->nullable()->after('client_id')
                ->constrained('referral_partners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['referral_partner_id']);
            $table->dropColumn('referral_partner_id');
        });
    }
};
```

- [ ] **Step 3: Migration — backfill anchor from legacy linked_order_id**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Existing converted referrals point at an order; adopt that order's quotation
        // as the new anchor, and stamp the credited partner onto the quotation.
        $rows = DB::table('referrals')
            ->whereNotNull('linked_order_id')
            ->whereNull('quotation_id')
            ->get(['id', 'linked_order_id', 'referral_partner_id']);

        foreach ($rows as $r) {
            $quotationId = DB::table('orders')->where('id', $r->linked_order_id)->value('quotation_id');
            if (! $quotationId) {
                continue;
            }
            DB::table('referrals')->where('id', $r->id)->update(['quotation_id' => $quotationId]);
            if ($r->referral_partner_id) {
                DB::table('quotations')->where('id', $quotationId)
                    ->whereNull('referral_partner_id')
                    ->update(['referral_partner_id' => $r->referral_partner_id]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive backfill; nothing to reverse (columns dropped by 000001/000002 down).
    }
};
```

- [ ] **Step 4: Run migrations + verify schema**

Run:
```bash
docker exec axelnova-backend-dev php artisan migrate 2>&1 | tail -6
docker exec axelnova-backend-dev php artisan db:show --table=referrals 2>&1 | grep -iE "quotation_id|commission_pct|status"
docker exec axelnova-backend-dev php artisan db:show --table=quotations 2>&1 | grep -i "referral_partner_id"
```
Expected: three migrations `DONE`; `referrals` shows `quotation_id`, `commission_pct`, and a `status` enum including `draft`; `quotations` shows `referral_partner_id`.

- [ ] **Step 5: Checkpoint** — schema is in place. (Commit only if the user asks.)

---

### Task 2: Model relations + effective rate

**Files:**
- Modify: `backend/app/Models/Referral.php`
- Modify: `backend/app/Models/Quotation.php`

**Interfaces:**
- Produces: `Referral::quotation()` (BelongsTo), `Referral::effectivePct(): int`, `Referral::orderViaQuotation(): ?Order`; `Quotation::referrer()` (BelongsTo). `quotation_id` + `commission_pct` fillable on Referral; `referral_partner_id` fillable on Quotation.

- [ ] **Step 1: Referral — fillable + relation + effective rate**

Add `'quotation_id'` and `'commission_pct'` to `$fillable`. Cast `'commission_pct' => 'integer'`. Add:

```php
public function quotation(): BelongsTo
{
    return $this->belongsTo(Quotation::class);
}

/** Confirmed rate if set, else the tier estimate. */
public function effectivePct(): int
{
    return (int) ($this->commission_pct ?? $this->commission_tier_pct);
}

/** The order this referral earns on — reached via its quotation anchor (falls back to legacy link). */
public function orderViaQuotation(): ?Order
{
    return $this->quotation?->order ?? $this->order;
}
```

- [ ] **Step 2: Quotation — fillable + referrer relation**

Add `'referral_partner_id'` to Quotation `$fillable`. Add:

```php
public function referrer(): BelongsTo
{
    return $this->belongsTo(\App\Models\Referrer::class, 'referral_partner_id');
}
```
(Confirm `Quotation` already has `order(): HasOne` — it's eager-loaded as `'order'` in QuotationsController; if it's `hasOne(Order::class)`, no change needed.)

- [ ] **Step 3: Verify relations + effective rate**

Throwaway `backend/_verify_models.php` (HTTP-kernel bootstrap, `DB::beginTransaction()`/`rollBack()`): create a Referrer + Referral with `commission_tier_pct=10`, assert `effectivePct()===10`; set `commission_pct=12`, assert `effectivePct()===12`. Run `docker exec axelnova-backend-dev php _verify_models.php`; expect PASS; delete the file.

- [ ] **Step 4: Checkpoint.**

---

### Task 3: Attribution service — auto-create/tie draft on quote

**Files:**
- Create: `backend/app/Services/Referrals/ReferralAttributionService.php`
- Modify: `backend/app/Http/Controllers/Api/V1/Admin/QuotationsController.php` (in `store`, after the inquiry link at lines 133-136)
- Modify: `backend/app/Http/Controllers/Api/V1/Admin/InquiriesController.php` (`linkQuotation` action)

**Interfaces:**
- Produces: `ReferralAttributionService::attribute(Quotation $quotation, Inquiry $inquiry): void` — propagates `inquiry.referral_partner_id` onto the quotation and creates-or-ties a `draft` referral.

- [ ] **Step 1: Write the service**

```php
<?php
namespace App\Services\Referrals;

use App\Models\Inquiry;
use App\Models\Quotation;
use App\Models\Referral;
use App\Models\Referrer;

class ReferralAttributionService
{
    /**
     * Anchor a quotation to the referrer its inquiry came from. Stamps the partner
     * on the quotation and creates a DRAFT referral (or ties an existing claim for
     * the same partner + company — the dedup step). No-op when the inquiry isn't
     * attributed or the partner isn't active.
     */
    public function attribute(Quotation $quotation, Inquiry $inquiry): void
    {
        $partnerId = $inquiry->referral_partner_id;
        if (! $partnerId) {
            return;
        }
        $partner = Referrer::where('id', $partnerId)->where('status', 'active')->first();
        if (! $partner) {
            return;
        }

        $quotation->forceFill(['referral_partner_id' => $partnerId])->saveQuietly();

        // Dedup: reuse an untied claim from this partner for the same company.
        $referral = Referral::where('referral_partner_id', $partnerId)
            ->whereNull('quotation_id')
            ->whereNotIn('status', ['converted', 'rejected'])
            ->where(function ($q) use ($inquiry) {
                $q->where('business_email', $inquiry->email)
                    ->orWhere('business_name', $inquiry->company);
            })
            ->first();

        if (! $referral) {
            $referral = new Referral([
                'referral_partner_id' => $partnerId,
                'referrer_name' => $partner->name,
                'referrer_email' => $partner->email,
                'referrer_phone' => $partner->phone,
                'business_name' => $inquiry->company ?: $inquiry->name,
                'business_contact_name' => $inquiry->name,
                'business_email' => $inquiry->email,
                'business_phone' => $inquiry->phone,
                'relationship_tier' => $partner->relationship_tier,
                'commission_tier_pct' => Referrer::commissionPctFor($partner->relationship_tier),
                'agreed_terms' => true,
            ]);
        }

        $referral->quotation_id = $quotation->id;
        $referral->status = 'draft';
        $referral->save();
        $referral->logActivity('referral.drafted', ['quotation_id' => $quotation->id]);
    }
}
```

- [ ] **Step 2: Call it from `QuotationsController@store`**

Inside the `DB::transaction` in `store`, replace the inquiry-link block (currently lines 133-136) so it also attributes:

```php
if (! empty($data['inquiry_id'])) {
    $inquiry = Inquiry::find($data['inquiry_id']);
    if ($inquiry) {
        $inquiry->update(['quotation_id' => $quotation->id, 'status' => 'quoted']);
        app(\App\Services\Referrals\ReferralAttributionService::class)->attribute($quotation, $inquiry);
    }
}
```

- [ ] **Step 3: Call it from `InquiriesController@linkQuotation`**

In `linkQuotation`, after setting `$inquiry->quotation_id` and saving, add:

```php
app(\App\Services\Referrals\ReferralAttributionService::class)
    ->attribute($inquiry->quotation, $inquiry->fresh());
```
(Use the freshly-linked quotation instance; ensure `$inquiry->referral_partner_id` is loaded.)

- [ ] **Step 4: Verify auto-draft + dedup**

Throwaway `backend/_verify_attr.php`: create active Referrer (code X), an Inquiry with `referral_partner_id` set + `company='Acme'`; create a Quotation via `QuotationsController@store` HTTP call (founder token) with `inquiry_id`; assert (a) `quotations.referral_partner_id` set, (b) a `draft` Referral exists tied to the quotation for that partner. Then repeat with a pre-existing untied claim for Acme → assert it's **reused** (still one referral, now `draft`). Expect PASS; delete.

- [ ] **Step 5: Checkpoint.**

---

### Task 4: Confirm commission % on accept

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/Admin/QuotationsController.php` (`accept`, lines 242-280)

**Interfaces:**
- Consumes: `Referral::effectivePct()`, the quotation's tied referral (`Referral where quotation_id = quotation.id`).
- Produces: `accept` accepts optional `commission_pct` (int 5–15) and stores it on the tied referral.

- [ ] **Step 1: Validate + apply the confirmed rate**

At the top of `accept`, after the existing guards, add validation:

```php
$request->validate([
    'commission_pct' => ['nullable', 'integer', 'min:5', 'max:15'],
]);
```

Inside the `DB::transaction` (after the `Order::create`), set the confirmed rate on the tied referral, defaulting to its tier estimate:

```php
$referral = Referral::where('quotation_id', $quotation->id)->first();
if ($referral) {
    $referral->update([
        'commission_pct' => $request->integer('commission_pct') ?: $referral->commission_tier_pct,
    ]);
}
```
(Place this inside the transaction closure and return the order as before.)

- [ ] **Step 2: Verify**

Throwaway: create a draft referral tied to a quotation (tier 10), accept the quotation with `commission_pct=13` via HTTP (founder token) → assert order created + `referral.commission_pct === 13`; accept another with no pct → assert it defaults to the tier estimate. Expect PASS; delete.

- [ ] **Step 3: Checkpoint.**

---

### Task 5: Deposit trigger — flip draft ↔ converted in PaymentObserver

**Files:**
- Modify: `backend/app/Observers/PaymentObserver.php` (`recompute`, after the order cache write at lines 36-42)

**Interfaces:**
- Consumes: `order.amount_paid_myr` (just recomputed), the referral tied via `order.quotation_id`.
- Produces: tied referral flips `draft → converted` when paid > 0, and `converted → draft` when paid returns to 0.

- [ ] **Step 1: Add the status sync**

In `recompute`, after `$order->forceFill(['amount_paid_myr' => max(0, $paid)])->saveQuietly();`, add:

```php
// A referral earns once the deposit lands, and stops if fully refunded.
if ($order->quotation_id) {
    $referral = \App\Models\Referral::where('quotation_id', $order->quotation_id)->first();
    if ($referral) {
        if ($paid > 0 && $referral->status === 'draft') {
            $referral->update(['status' => 'converted']);
            $referral->logActivity('referral.converted', ['order_id' => $order->id]);
        } elseif ($paid <= 0 && $referral->status === 'converted') {
            $referral->update(['status' => 'draft']);
        }
    }
}
```

- [ ] **Step 2: Revert on order cancellation**

`PaymentObserver` doesn't fire on an order status change, so cover the spec's "order cancelled → draft" edge in `OrdersController@updateStatus` (the `orders/{order}/status` route). After the order status is saved, add:

```php
if ($order->status === 'cancelled' && $order->quotation_id) {
    \App\Models\Referral::where('quotation_id', $order->quotation_id)
        ->where('status', 'converted')
        ->update(['status' => 'draft']);
}
```
Add `app/Http/Controllers/Api/V1/Admin/OrdersController.php` to the file map.

- [ ] **Step 3: Verify**

Throwaway: create quotation + order (paid=0) + draft referral tied to the quotation; insert a succeeded Payment (amount 1000) on the order → assert referral `converted`; refund it back to 0 → assert referral back to `draft`. Separately, set the order status to `cancelled` via `OrdersController@updateStatus` while `converted` → assert referral reverts to `draft`. Expect PASS; delete.

- [ ] **Step 4: Checkpoint.**

---

### Task 6: Admin — tie-to-quotation + partner detail endpoint

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/Admin/ReferralsController.php` (allow `draft` in `updateStatus`; add `tieQuotation`)
- Modify: `backend/app/Http/Controllers/Api/V1/Admin/ReferralPartnersController.php` (add `show`)
- Create: `backend/app/Http/Resources/ReferrerDetailResource.php`
- Modify: `backend/app/Http/Resources/ReferralResource.php` (add `quotation_id`, `status`, `commission_pct`, `effective_pct`)
- Modify: `backend/routes/api.php`

**Interfaces:**
- Produces: `GET /v1/admin/referral-partners/{referralPartner}` → partner + referrals + stats; `POST /v1/admin/referrals/{referral}/tie-quotation` (body `quotation_id`); `updateStatus` accepts `draft`.

- [ ] **Step 1: `updateStatus` — allow `draft`**

In `ReferralsController@updateStatus`, widen the rule to `'in:new,contacted,qualified,draft,converted,rejected'`.

- [ ] **Step 2: `tieQuotation` action**

```php
public function tieQuotation(Request $request, Referral $referral): JsonResponse
{
    $data = $request->validate(['quotation_id' => ['required', 'exists:quotations,id']]);
    $referral->update(['quotation_id' => $data['quotation_id'], 'status' => 'draft']);
    \App\Models\Quotation::where('id', $data['quotation_id'])
        ->update(['referral_partner_id' => $referral->referral_partner_id]);
    $referral->logActivity('referral.tied_quotation', ['quotation_id' => $data['quotation_id']]);

    return response()->json(['message' => 'Referral tied to quotation.']);
}
```

- [ ] **Step 3: `ReferrerDetailResource`**

```php
<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferrerDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'relationship_tier' => $this->relationship_tier,
            'commission_tiers' => \App\Models\Referral::COMMISSION_TIERS,
            'status' => $this->status,
            'has_passcode' => filled($this->password),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'stats' => $this->additional['stats'] ?? null,
            'referrals' => ReferralResource::collection($this->whenLoaded('referrals')),
        ];
    }
}
```

- [ ] **Step 4: `ReferralPartnersController@show` with earned/estimated stats**

```php
public function show(Referrer $referralPartner): ReferrerDetailResource
{
    $referralPartner->load(['referrals' => fn ($q) => $q->with('quotation.order')->latest('created_at')]);

    $earned = 0.0; $estimated = 0.0;
    foreach ($referralPartner->referrals as $ref) {
        $order = $ref->orderViaQuotation();
        if (! $order) { continue; }
        $rate = $ref->effectivePct();
        $collected = (float) $order->amount_paid_myr;
        $contract = (float) $order->final_amount_myr;
        if ($ref->status === 'converted') { $earned += round($collected * $rate / 100, 2); }
        $estimated += max(0, round(($contract - $collected) * $rate / 100, 2));
    }

    return (new ReferrerDetailResource($referralPartner))
        ->additional(['stats' => ['earned_myr' => round($earned, 2), 'estimated_myr' => round($estimated, 2), 'referrals_count' => $referralPartner->referrals->count()]]);
}
```

- [ ] **Step 5: `ReferralResource` — add lifecycle fields**

Add to the returned array: `'referral_partner_id'`, `'quotation_id'`, `'status'`, `'commission_pct' => $this->commission_pct`, `'effective_pct' => $this->effectivePct()`, and (if a quotation is loaded) `'quotation_reference' => $this->quotation?->reference_code`.

- [ ] **Step 6: Routes**

In the `/v1/admin` group, add next to the referral-partners routes:
```php
Route::get('/referral-partners/{referralPartner}', [ReferralPartnersController::class, 'show'])->name('referral-partners.show');
```
And next to the referrals routes:
```php
Route::post('/referrals/{referral}/tie-quotation', [ReferralsController::class, 'tieQuotation'])->name('referrals.tie-quotation');
```

- [ ] **Step 7: Verify**

Throwaway (founder token): `GET /v1/admin/referral-partners/{id}` → 200 with `referrals` + `stats` (earned/estimated correct for a converted referral with a paid order); `POST /v1/admin/referrals/{id}/tie-quotation` ties + sets draft + stamps quotation; a **marketer** token → 403 on both (cockpit-only). Also assert no passcode hash appears in any body. Expect PASS; delete.

- [ ] **Step 8: Checkpoint.**

---

### Task 7: Partner dashboard — lifecycle-aware math

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/Partner/DashboardController.php` (`index`)

**Interfaces:**
- Consumes: `Referral::effectivePct()`, `Referral::orderViaQuotation()`.
- Produces: dashboard `stats.earned_myr` counts only `converted`; `stats.estimated_myr` = uncollected remainder across referrals with an order; each row gets `status`, `commission_pct` (effective), `earned_myr` (only when converted).

- [ ] **Step 1: Rewrite the earnings loop**

Replace the current `$rows`/earned/pending block with:

```php
$referrals = $referrer->referrals()->with('quotation.order')->latest('created_at')->get();
$earned = 0.0; $estimated = 0.0;

$rows = $referrals->map(function (\App\Models\Referral $referral) use (&$earned, &$estimated) {
    $order = $referral->orderViaQuotation();
    $rate = $referral->effectivePct();
    $collected = (float) ($order->amount_paid_myr ?? 0);
    $contract = (float) ($order->final_amount_myr ?? 0);

    $earnedForRef = $referral->status === 'converted' ? round($collected * $rate / 100, 2) : 0.0;
    $earned += $earnedForRef;
    $estimated += $order ? max(0, round(($contract - $collected) * $rate / 100, 2)) : 0.0;

    return [
        'id' => $referral->id,
        'business_name' => $referral->business_name,
        'status' => $referral->status,
        'commission_pct' => $rate,
        'has_order' => (bool) $order,
        'earned_myr' => $referral->status === 'converted' ? $earnedForRef : null,
        'created_at' => $referral->created_at?->toISOString(),
    ];
});
```
Then return `stats => ['earned_myr' => round($earned,2), 'estimated_myr' => round($estimated,2), 'referrals_count' => $referrals->count()]` (rename `pending_myr` → `estimated_myr`), keep `ref_link` + `partner.commission_tiers`, and `'referrals' => $rows`.

- [ ] **Step 2: Verify**

Throwaway: partner with a `converted` referral (order paid 4000, contract 10000, rate 5) and a `draft` referral (order paid 0, contract 20000, rate 15): assert `earned=200`, `estimated = 300 + 3000 = 3300`; a claim with no order contributes 0 and `has_order=false`. Expect PASS; delete.

- [ ] **Step 3: Checkpoint.**

---

### Task 8: Frontend — admin partner detail page

**Files:**
- Create: `frontend/app/pages/admin/referral-partners/[id].vue`
- Modify: `frontend/app/pages/admin/referral-partners/index.vue` (rows navigate to detail)

**Interfaces:**
- Consumes: `GET /api/v1/admin/referral-partners/{id}`, `POST /api/v1/admin/referrals/{id}/status`, `POST /api/v1/admin/referrals/{id}/tie-quotation`, existing approve/reset endpoints. Uses `useAdminAuth().apiFetch`.

- [ ] **Step 1: Build the detail page**

`definePageMeta({ layout: 'admin', middleware: 'admin-auth' })`. Mirror the structure of `frontend/app/pages/admin/referral-partners/index.vue` (same auth composable, table classes `admin-table-card`/`admin-table-row`, CSS-var styling). Fetch on mount from `/api/v1/admin/referral-partners/${route.params.id}`. Render:
- **Header card:** name, email, `?ref` code (mono chip), status pill, tier bands (`commission_tiers`), `stats.earned_myr` + `stats.estimated_myr`, and Approve / Reset-passcode buttons (reuse the confirm-modal pattern already in `index.vue`).
- **Referrals table:** columns Business · Status · Quotation · Rate · Earned. Status cell is a `<select v-model>` bound to a `changeStatus(referral, value)` that POSTs `/api/v1/admin/referrals/${id}/status` then refetches. Options: `new, contacted, qualified, draft, converted, rejected`.
- Status pills reuse the `PillStyle` + `PILL_*` fallback pattern (concrete non-undefined fallback, per the fix in `index.vue`).

Reference `index.vue` verbatim for the approve/reset modal + auth; the only new pieces are the header stats and the per-row status `<select>`.

- [ ] **Step 2: Link list rows to detail**

In `index.vue`, make each row/card navigate to `/admin/referral-partners/${p.id}` (wrap the row in a click handler `@click="navigateTo(...)"`, matching `pages/team/referrals/index.vue`), keeping the Approve/Reset buttons with `@click.stop` so they don't trigger navigation.

- [ ] **Step 3: Verify**

Restart `axelnova-frontend-dev`; `curl -s -o /dev/null -w "%{http_code}" http://localhost:3003/admin/referral-partners/1` → 200 (SSR renders; `admin-auth` is client-only). Scan `docker logs axelnova-frontend-dev` for compile errors (none). Expect 200, no errors.

- [ ] **Step 4: Checkpoint.**

---

### Task 9: Frontend — commission % on quotation accept

**Files:**
- Modify: the admin quotation accept UI (locate with `grep -rln "quotations/.*/accept\|'accept'" frontend/app/pages/admin/quotations`).

**Interfaces:**
- Consumes: `POST /api/v1/admin/quotations/{id}/accept` now optionally takes `commission_pct`.

- [ ] **Step 1: Add the field to the accept action**

Where the admin accepts a quotation (the "Accept → create order" button/modal), when the quotation payload includes a `referral_partner_id` (attributed), show a small numeric input **Commission %** defaulting to the referrer's tier estimate, constrained 5–15, and include it in the accept POST body as `commission_pct`. If the quotation has no referral, omit the field entirely (no behavior change for non-referral quotes). Follow the page's existing input styling (`contact-input`, CSS vars).

- [ ] **Step 2: Verify**

Reload the admin quotation detail for a referral-attributed quote; the field appears and accept sends `commission_pct`. `curl` the page → 200, no console/compile errors in `docker logs`.

- [ ] **Step 3: Checkpoint.**

---

### Task 10: Frontend — partner portal draft/converted pills

**Files:**
- Modify: `frontend/app/pages/partners/portal.vue`

**Interfaces:**
- Consumes: dashboard `stats.estimated_myr` (renamed from `pending_myr`), per-row `status` + `has_order`.

- [ ] **Step 1: Update interfaces + labels**

Change `DashboardReferral` to include `status`, `commission_pct`, `has_order`, `earned_myr`. Change `stats` to `{ earned_myr, estimated_myr, referrals_count }`. Rename the "Pending (contracted)" stat card to "Estimated" bound to `stats.estimated_myr`.

- [ ] **Step 2: Lifecycle pills**

Extend the `statusStyle` map (keep the concrete `PILL_*` fallback pattern) with `draft: { label: 'Draft', ... }` and `converted: { label: 'Earning', color: var(--color-success), ... }`. In each referral row, show the status pill + `commission_pct%`; show `{{ myr(r.earned_myr) }} earned` only when `r.earned_myr` is set (converted), otherwise show "Estimated once your client pays" for `has_order` drafts, or nothing for claims.

- [ ] **Step 3: Verify**

`curl` `http://localhost:3003/partners/portal` → 200; `docker logs` clean. (Behavior with live data is confirmed via the Task 7 backend verify.)

- [ ] **Step 4: Checkpoint.**

---

### Task 11 (optional): Phase-1 attribution report upgrade

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/Admin/AnalyticsController.php` (`attribution`)

**Interfaces:**
- Consumes: the now-complete `quotations.referral_partner_id` → `orders.quotation_id` → `payments` chain.

- [ ] **Step 1: Re-point the query to the normalized chain**

Replace the flat-table grouping (currently reads `referrals` by `referrer_email`) with: group **succeeded payments** by the referrer reached through `payments.order → order.quotation → quotation.referral_partner_id`, summing collected `amount_myr` per referrer, alongside contracted `orders.final_amount_myr`. Keep the same response shape (`referrer`, `contracted`, `collected`) so the frontend needs no change. Leave a fallback bucket for null (Public/organic).

- [ ] **Step 2: Verify**

Throwaway: one referrer with a paid order reached via the quotation chain → `attribution` returns that referrer with the correct collected total; unattributed payments land in Public. Expect PASS; delete.

- [ ] **Step 3: Checkpoint.**

---

## Verification summary

- Backend tasks 1–7, 11: throwaway HTTP-kernel scripts in `backend/` (transaction-rolled-back, `Mail::fake()` where mail fires), deleted after running. Guard isolation + role gating asserted where relevant.
- Frontend tasks 8–10: restart `axelnova-frontend-dev`, poll pages for `200`, scan `docker logs` for compile errors (the established pattern).
- No files committed until the user asks.
