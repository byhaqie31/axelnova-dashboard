# Payment → Invoice Allocation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an admin link, re-link, or unlink a recorded payment to an invoice on the same order after the fact, with a confirm popup stating the predicted outcome, so an invoice can no longer be stranded `issued` by an unallocated payment.

**Architecture:** One new endpoint (`PATCH /v1/admin/payments/{payment}/allocation`) backed by `PaymentService::allocate()`, which cascades the move to refund children and the receipt, then recomputes both the new and old invoice caches through `PaymentObserver` (its invoice-recompute block is extracted to a public static so the observer stays the sole cache writer). Frontend: a new `PaymentAllocateModal` on the payment detail page reusing the `LinkQuotationModal` + `useConfirm()` patterns, plus a one-open-invoice preselect on the record-payment form.

**Tech Stack:** Laravel 11 (PHP 8.4, Sanctum, PHPUnit + MySQL test DB), Nuxt 4 (Vue 3, TypeScript, CSS-variable design tokens).

**Spec:** `docs/superpowers/specs/2026-07-15-payment-invoice-allocation-design.md`

## Global Constraints

- **No commits during execution.** The user commits themselves (standing preference: implement only; commit/push/merge on request). Every "commit" checkpoint below is replaced by "leave uncommitted; report the task done".
- All backend commands run inside Docker: `docker compose -f docker-compose.dev.yml exec backend …` (repo root). Frontend likewise with `exec frontend`.
- `PaymentObserver` remains the only code that writes `invoices.amount_paid` / `invoices.status` / `orders.amount_paid_myr`.
- `void` invoices are a manual terminal state — never auto-flipped, never allocatable.
- Frontend: design tokens only (no hardcoded hex), icons via `<UIcon name="i-lucide-…">`, no emojis.
- New docs (if any) live under `docs/` per CLAUDE.md; this feature only edits `docs/global/PAYMENTS-LEDGER.md`.

---

### Task 1: Extract `PaymentObserver::recomputeInvoice()` (pure refactor)

**Files:**
- Modify: `backend/app/Observers/PaymentObserver.php:58-71`
- Test: existing `backend/tests/Feature/Payments/PaymentObserverTest.php` (no new tests — behavior is pinned already)

**Interfaces:**
- Produces: `public static PaymentObserver::recomputeInvoice(Invoice $invoice): void` — recomputes one invoice's `amount_paid`/`status`/`paid_at` from the ledger; no-ops on `void`. Task 2 calls this for the invoice a payment moved OFF of.

- [ ] **Step 1: Run the existing observer tests to confirm green baseline**

Run: `docker compose -f docker-compose.dev.yml exec backend php artisan test --filter=PaymentObserverTest`
Expected: all tests PASS.

- [ ] **Step 2: Extract the invoice branch into a public static**

In `backend/app/Observers/PaymentObserver.php`, add the import at the top:

```php
use App\Models\Invoice;
```

Replace the invoice block at the end of `recompute()` (currently lines 58–70):

```php
        $invoice = $payment->invoice()->first();
        // `void` is a manual terminal state — never auto-flip it back.
        if ($invoice && $invoice->status !== 'void') {
            $paid = (float) $invoice->payments()->succeeded()->sum('amount_myr');
            $total = (float) $invoice->amount_total;
            $fullyPaid = $total > 0 && $paid >= $total;

            $invoice->forceFill([
                'amount_paid' => max(0, $paid),
                'status' => $fullyPaid ? 'paid' : 'issued',
                'paid_at' => $fullyPaid ? ($invoice->paid_at ?? now()) : null,
            ])->saveQuietly();
        }
```

with:

```php
        $invoice = $payment->invoice()->first();
        if ($invoice) {
            self::recomputeInvoice($invoice);
        }
    }

    /**
     * Recompute one invoice's paid cache straight from the ledger. Public so
     * PaymentService::allocate() can refresh the invoice a payment was moved
     * OFF of — the observer only sees the payment's new invoice. This class
     * remains the only writer of the paid caches.
     */
    public static function recomputeInvoice(Invoice $invoice): void
    {
        // `void` is a manual terminal state — never auto-flip it back.
        if ($invoice->status === 'void') {
            return;
        }

        $paid = (float) $invoice->payments()->succeeded()->sum('amount_myr');
        $total = (float) $invoice->amount_total;
        $fullyPaid = $total > 0 && $paid >= $total;

        $invoice->forceFill([
            'amount_paid' => max(0, $paid),
            'status' => $fullyPaid ? 'paid' : 'issued',
            'paid_at' => $fullyPaid ? ($invoice->paid_at ?? now()) : null,
        ])->saveQuietly();
```

(The method's closing brace replaces `recompute()`'s original one — final file has `recompute()` ending after the `if ($invoice)` block, then `recomputeInvoice()` as the last method.)

- [ ] **Step 3: Run the observer tests again**

Run: `docker compose -f docker-compose.dev.yml exec backend php artisan test --filter=PaymentObserverTest`
Expected: all tests PASS (identical behavior — `test_a_void_invoice_is_never_auto_flipped` proves the moved void guard).

- [ ] **Step 4: Leave uncommitted; report the task done.**

---

### Task 2: `PaymentService::allocate()` + allocation feature tests

**Files:**
- Modify: `backend/app/Services/Payments/PaymentService.php`
- Create: `backend/tests/Feature/Payments/PaymentAllocationTest.php`

**Interfaces:**
- Consumes: `PaymentObserver::recomputeInvoice(Invoice $invoice): void` (Task 1).
- Produces: `public static PaymentService::allocate(Payment $payment, ?Invoice $invoice): Payment` — moves the payment (and its refund children + receipt display link) to `$invoice` (or unlinks when null) and recomputes both invoices. Task 3's controller calls it.

- [ ] **Step 1: Write the failing service-level tests**

Create `backend/tests/Feature/Payments/PaymentAllocationTest.php`. (HTTP guard tests are added in Task 3 — this task pins the service mechanics; the `allocate()` helper here calls the service directly.)

```php
<?php

namespace Tests\Feature\Payments;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PaymentService::allocate() — moving a payment's invoice link after the
 * fact. Both sides' caches recompute, refund children and the receipt's
 * display link follow the move.
 */
class PaymentAllocationTest extends TestCase
{
    use RefreshDatabase;

    private function pay(Order $order, float $amount, array $attributes = []): Payment
    {
        return Payment::factory()->create([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'amount_myr' => $amount,
            ...$attributes,
        ]);
    }

    public function test_linking_a_covering_payment_marks_the_invoice_paid(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1000]);
        $payment = $this->pay($order, 1000);

        PaymentService::allocate($payment, $invoice);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('1000.00', $invoice->amount_paid);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame($invoice->id, $payment->refresh()->invoice_id);
    }

    public function test_linking_a_partial_payment_keeps_the_invoice_issued(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1000]);
        $payment = $this->pay($order, 400);

        PaymentService::allocate($payment, $invoice);

        $invoice->refresh();
        $this->assertSame('issued', $invoice->status);
        $this->assertSame('400.00', $invoice->amount_paid);
        $this->assertNull($invoice->paid_at);
    }

    public function test_relinking_recomputes_both_invoices(): void
    {
        $order = Order::factory()->create();
        $invoiceA = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 500]);
        $invoiceB = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 500]);
        $payment = $this->pay($order, 500, ['invoice_id' => $invoiceA->id]);
        $this->assertSame('paid', $invoiceA->refresh()->status);

        PaymentService::allocate($payment, $invoiceB);

        $invoiceA->refresh();
        $this->assertSame('issued', $invoiceA->status);
        $this->assertSame('0.00', $invoiceA->amount_paid);
        $this->assertNull($invoiceA->paid_at);

        $invoiceB->refresh();
        $this->assertSame('paid', $invoiceB->status);
        $this->assertSame('500.00', $invoiceB->amount_paid);
    }

    public function test_unlinking_reverts_the_old_invoice(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 500]);
        $payment = $this->pay($order, 500, ['invoice_id' => $invoice->id]);
        $this->assertSame('paid', $invoice->refresh()->status);

        PaymentService::allocate($payment, null);

        $invoice->refresh();
        $this->assertSame('issued', $invoice->status);
        $this->assertSame('0.00', $invoice->amount_paid);
        $this->assertNull($payment->refresh()->invoice_id);
    }

    public function test_refund_children_follow_the_allocation(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1000]);
        $payment = $this->pay($order, 1000);
        $refund = Payment::factory()->refundOf($payment, 400)->create();

        PaymentService::allocate($payment, $invoice);

        // The negative refund row moved too, so the invoice sum is net.
        $this->assertSame($invoice->id, $refund->refresh()->invoice_id);
        $invoice->refresh();
        $this->assertSame('600.00', $invoice->amount_paid);
        $this->assertSame('issued', $invoice->status);
    }

    public function test_an_issued_receipt_follows_the_allocation(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1000]);
        $payment = $this->pay($order, 1000);
        $receipt = Receipt::create([
            'order_id' => $order->id,
            'invoice_id' => null,
            'payment_id' => $payment->id,
            'receipt_number' => 'AXNR-2001-0001',
            'public_token' => str_repeat('r', 48),
            'payload' => [],
            'amount' => 1000,
            'payment_ref' => 'TEST-REF',
            'payment_method' => 'bank_transfer',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        PaymentService::allocate($payment, $invoice);

        $this->assertSame($invoice->id, $receipt->refresh()->invoice_id);
    }
}
```

- [ ] **Step 2: Run the new tests to verify they fail**

Run: `docker compose -f docker-compose.dev.yml exec backend php artisan test --filter=PaymentAllocationTest`
Expected: FAIL — `Call to undefined method App\Services\Payments\PaymentService::allocate()`.

- [ ] **Step 3: Implement `PaymentService::allocate()`**

In `backend/app/Services/Payments/PaymentService.php`, add imports:

```php
use App\Models\Invoice;
use App\Observers\PaymentObserver;
```

Add this method after `refund()` (before `refundableMyr()`):

```php
    /**
     * Move a payment's invoice allocation — link, re-link, or unlink (null).
     * Refund children follow their parent, the receipt's display link follows,
     * and BOTH invoices' caches end up recomputed: the observer handles the
     * new one when the payment saves; the old one is refreshed explicitly
     * because the observer can no longer see it.
     */
    public static function allocate(Payment $payment, ?Invoice $invoice): Payment
    {
        return DB::transaction(function () use ($payment, $invoice) {
            $previousInvoiceId = $payment->invoice_id;

            // Children first, quietly — so the observer recompute fired by the
            // parent's save already counts the refund rows on the new invoice.
            $payment->refunds()->update(['invoice_id' => $invoice?->id]);

            $payment->invoice_id = $invoice?->id;
            $payment->save();

            // Receipts display their allocation; the PDF anchors the payment.
            $payment->receipt()->update(['invoice_id' => $invoice?->id]);

            if ($previousInvoiceId && $previousInvoiceId !== $invoice?->id) {
                $previous = Invoice::find($previousInvoiceId);
                if ($previous) {
                    PaymentObserver::recomputeInvoice($previous);
                }
            }

            $payment->logActivity($invoice ? 'payment.allocated' : 'payment.unallocated', [
                'invoice_id' => $invoice?->id,
                'previous_invoice_id' => $previousInvoiceId,
            ]);

            return $payment;
        });
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `docker compose -f docker-compose.dev.yml exec backend php artisan test --filter=PaymentAllocationTest`
Expected: all 6 tests PASS.

Also run: `docker compose -f docker-compose.dev.yml exec backend php artisan test --filter=PaymentObserverTest`
Expected: still PASS.

- [ ] **Step 5: Leave uncommitted; report the task done.**

---

### Task 3: `PATCH /v1/admin/payments/{payment}/allocation` endpoint

**Files:**
- Modify: `backend/routes/api.php:231` (insert after the receipt route)
- Modify: `backend/app/Http/Controllers/Api/V1/Admin/PaymentsController.php` (new method after `refund()`, ~line 117)
- Test: `backend/tests/Feature/Payments/PaymentAllocationTest.php` (append HTTP tests)

**Interfaces:**
- Consumes: `PaymentService::allocate(Payment $payment, ?Invoice $invoice): Payment` (Task 2).
- Produces: `PATCH /api/v1/admin/payments/{payment}/allocation` with JSON body `{ "invoice_id": number | null }` → 200 `{ message, payment: PaymentResource }`; 422 on guard failures. Task 4's modal calls this.

- [ ] **Step 1: Append the failing HTTP tests**

Add to `backend/tests/Feature/Payments/PaymentAllocationTest.php` — imports gain `App\Models\User`, and add these helpers + tests at the end of the class:

```php
    private function adminHeaders(): array
    {
        $founder = User::factory()->founder()->create();
        $token = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function patchAllocation(Payment $payment, ?int $invoiceId)
    {
        return $this->patchJson(
            "/api/v1/admin/payments/{$payment->id}/allocation",
            ['invoice_id' => $invoiceId],
            $this->adminHeaders(),
        );
    }

    public function test_the_endpoint_links_and_returns_the_updated_payment(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1000]);
        $payment = $this->pay($order, 1000);

        $this->patchAllocation($payment, $invoice->id)
            ->assertOk()
            ->assertJsonPath('payment.invoice_id', $invoice->id)
            ->assertJsonPath('payment.invoice_number', $invoice->invoice_number);

        $this->assertSame('paid', $invoice->refresh()->status);
    }

    public function test_a_noop_reallocation_returns_ok_without_changes(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1000]);
        $payment = $this->pay($order, 400, ['invoice_id' => $invoice->id]);

        $this->patchAllocation($payment, $invoice->id)->assertOk();

        $this->assertSame('400.00', $invoice->refresh()->amount_paid);
    }

    public function test_an_invoice_from_another_order_is_rejected(): void
    {
        $order = Order::factory()->create();
        $otherInvoice = Invoice::factory()->create([
            'order_id' => Order::factory()->create()->id,
            'amount_total' => 1000,
        ]);
        $payment = $this->pay($order, 1000);

        $this->patchAllocation($payment, $otherInvoice->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('invoice_id');
    }

    public function test_a_void_invoice_is_rejected(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create([
            'order_id' => $order->id,
            'amount_total' => 1000,
            'status' => 'void',
        ]);
        $payment = $this->pay($order, 1000);

        $this->patchAllocation($payment, $invoice->id)->assertStatus(422);
    }

    public function test_a_refund_row_cannot_be_allocated(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1000]);
        $payment = $this->pay($order, 1000);
        $refund = Payment::factory()->refundOf($payment, 400)->create();

        $this->patchAllocation($refund, $invoice->id)->assertStatus(422);
    }

    public function test_a_non_succeeded_payment_cannot_be_allocated(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1000]);
        $payment = $this->pay($order, 1000, ['status' => 'pending']);

        $this->patchAllocation($payment, $invoice->id)->assertStatus(422);
    }
```

- [ ] **Step 2: Run to verify the new tests fail**

Run: `docker compose -f docker-compose.dev.yml exec backend php artisan test --filter=PaymentAllocationTest`
Expected: the six new tests FAIL with 404s (route not defined); the Task 2 tests still PASS.

- [ ] **Step 3: Add the route**

In `backend/routes/api.php`, after the `payments.receipt` line (231):

```php
        Route::patch('/payments/{payment}/allocation', [PaymentsController::class, 'allocate'])->name('payments.allocation');
```

- [ ] **Step 4: Add the controller method**

In `backend/app/Http/Controllers/Api/V1/Admin/PaymentsController.php`, add the import:

```php
use App\Models\Invoice;
```

Add after `refund()`:

```php
    /**
     * Move a payment's invoice allocation — link, re-link, or unlink (null).
     * Fixes the stranded-invoice case: a payment recorded without an invoice
     * leaves that invoice `issued` forever, since the observer only recomputes
     * the invoice a payment points at.
     */
    public function allocate(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->type === PaymentType::Payment, 422, 'Refunds follow their parent payment allocation.');
        abort_unless($payment->status === PaymentStatus::Succeeded, 422, 'Only a succeeded payment can be allocated.');

        $data = $request->validate([
            'invoice_id' => [
                'nullable', 'integer',
                Rule::exists('invoices', 'id')
                    ->where('order_id', $payment->order_id)
                    ->whereNull('deleted_at'),
            ],
        ]);

        $invoice = isset($data['invoice_id']) ? Invoice::find($data['invoice_id']) : null;
        abort_if($invoice && $invoice->status === 'void', 422, 'A void invoice cannot receive allocations.');

        if ($payment->invoice_id !== $invoice?->id) {
            PaymentService::allocate($payment, $invoice);
        }

        $payment->load(['order', 'client', 'invoice', 'refunds', 'receipt']);

        return response()->json([
            'message' => 'Allocation updated.',
            'payment' => new PaymentResource($payment),
        ]);
    }
```

- [ ] **Step 5: Run the full payments test file**

Run: `docker compose -f docker-compose.dev.yml exec backend php artisan test --filter=PaymentAllocationTest`
Expected: all 12 tests PASS.

- [ ] **Step 6: Run Pint on the touched files**

Run: `docker compose -f docker-compose.dev.yml exec backend ./vendor/bin/pint app/Observers/PaymentObserver.php app/Services/Payments/PaymentService.php app/Http/Controllers/Api/V1/Admin/PaymentsController.php tests/Feature/Payments/PaymentAllocationTest.php`
Expected: PASS (or auto-fixed; re-run tests if it changed anything).

- [ ] **Step 7: Leave uncommitted; report the task done.**

---

### Task 4: `PaymentAllocateModal` component

**Files:**
- Create: `frontend/app/components/admin/PaymentAllocateModal.vue`

**Interfaces:**
- Consumes: `PATCH /api/v1/admin/payments/{id}/allocation` (Task 3); `GET /api/v1/admin/orders/{id}` (existing — returns `data.invoices[]` with `id`, `number`, `status`, `amount_total`, `amount_paid`); `useConfirm()` + `AdminConfirmDialog`; `useAdminAuth().apiFetch`; `useAdminToast()`.
- Produces: `<AdminPaymentAllocateModal :payment-id :order-id :current-invoice-id :current-invoice-number :net-amount @allocated />` — renders its own trigger button ("Allocate to invoice" / "Change invoice allocation"); emits `allocated` after a successful PATCH. Task 5 mounts it.

- [ ] **Step 1: Create the component**

Create `frontend/app/components/admin/PaymentAllocateModal.vue` (auto-registered as `AdminPaymentAllocateModal`):

```vue
<script setup lang="ts">
// Move a payment's invoice allocation after the fact — link an unallocated
// payment, re-link a wrongly-tagged one, or unlink. A confirm step states the
// predicted outcome (paid / stays issued / over-allocated) before the PATCH;
// the server-side observer recompute stays the source of truth.
const props = defineProps<{
  paymentId: number
  orderId: number
  currentInvoiceId: number | null
  currentInvoiceNumber: string | null
  /** Net-of-refunds amount this payment contributes to an invoice. */
  netAmount: number
}>()

const emit = defineEmits<{ allocated: [] }>()

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()
const { confirmOpen, confirmConfig, confirm, resolveConfirm } = useConfirm()

interface OrderInvoice { id: number, number: string, status: string, amount_total: string, amount_paid: string | null }

const open = ref(false)
const loading = ref(false)
const error = ref('')
const invoices = ref<OrderInvoice[]>([])
const selectedId = ref<number | null>(null)
const saving = ref(false)

const changed = computed(() => selectedId.value !== props.currentInvoiceId)

async function fetchInvoices() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: { invoices?: OrderInvoice[] } }>(`/api/v1/admin/orders/${props.orderId}`)
    // Void invoices are frozen — the endpoint rejects them, so don't offer them.
    invoices.value = (res.data.invoices ?? []).filter(d => d.status !== 'void')
  }
  catch {
    error.value = 'Failed to load the order’s invoices.'
  }
  finally {
    loading.value = false
  }
}

function openModal() {
  open.value = true
  selectedId.value = props.currentInvoiceId
  fetchInvoices()
}

function outstanding(inv: OrderInvoice) {
  return Math.max(Number(inv.amount_total) - Number(inv.amount_paid ?? 0), 0)
}

// The confirm copy states what the recompute WILL do, so the admin approves
// an outcome, not a mutation.
function outcomeConfig() {
  const inv = invoices.value.find(d => d.id === selectedId.value)
  if (!inv) {
    return {
      title: 'Unlink this payment?',
      message: `It will no longer count toward ${props.currentInvoiceNumber ?? 'its invoice'} — the invoice’s paid total recalculates immediately.`,
      confirmLabel: 'Unlink',
      variant: 'warning' as const,
    }
  }
  const due = outstanding(inv)
  if (props.netAmount > due) {
    return {
      title: `Link to ${inv.number}?`,
      message: `This payment exceeds the invoice’s outstanding ${fmtMyr(due)} by ${fmtMyr(props.netAmount - due)}. The invoice will be marked paid.`,
      confirmLabel: 'Link anyway',
      variant: 'warning' as const,
    }
  }
  if (props.netAmount === due) {
    return {
      title: `Link to ${inv.number}?`,
      message: `${fmtMyr(props.netAmount)} fully covers the outstanding balance — the invoice will be marked paid.`,
      confirmLabel: 'Link payment',
      variant: 'accent' as const,
    }
  }
  return {
    title: `Link to ${inv.number}?`,
    message: `${fmtMyr(props.netAmount)} of ${fmtMyr(due)} outstanding will be covered — the invoice stays issued.`,
    confirmLabel: 'Link payment',
    variant: 'accent' as const,
  }
}

async function submit() {
  if (!changed.value || saving.value) return
  if (!(await confirm(outcomeConfig()))) return
  saving.value = true
  try {
    await apiFetch(`/api/v1/admin/payments/${props.paymentId}/allocation`, {
      method: 'PATCH',
      body: { invoice_id: selectedId.value },
    })
    toast.success('Allocation updated', 'The invoice totals are recalculated.')
    open.value = false
    emit('allocated')
  }
  catch {
    toast.error('Couldn’t update allocation', 'Please try again.')
  }
  finally {
    saving.value = false
  }
}

// The confirm dialog registers its own Escape handler (z-100, above us).
onKeyStroke('Escape', () => { if (open.value && !confirmOpen.value) open.value = false })

function fmtMyr(amount: string | number) {
  return `RM ${Number(amount).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}
</script>

<template>
  <button type="button" class="btn-pill btn-pill-ghost w-full justify-center text-[13px]" @click="openModal">
    {{ currentInvoiceId ? 'Change invoice allocation' : 'Allocate to invoice' }}
  </button>

  <Teleport to="body">
    <Transition name="link-modal">
      <div v-if="open" class="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-6" @click.self="open = false">
        <div class="absolute inset-0" style="background: rgba(0,0,0,0.55); backdrop-filter: blur(2px);" @click="open = false" />

        <div
class="relative w-full max-w-[520px] max-h-[85vh] flex flex-col rounded-2xl border shadow-2xl"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }" @click.stop>
          <!-- Header -->
          <div class="flex items-center justify-between px-5 pt-5 pb-3">
            <p class="text-[15px] font-semibold" style="color: var(--color-text);">Allocate to invoice</p>
            <button type="button" class="transition-opacity hover:opacity-70" style="color: var(--color-text-tertiary);" @click="open = false">
              <UIcon name="i-lucide-x" class="size-5" />
            </button>
          </div>

          <!-- List -->
          <div class="flex-1 overflow-y-auto px-5 pb-2 min-h-[120px] space-y-1.5">
            <p v-if="error" class="text-[13px] py-6 text-center" style="color: var(--color-danger);">{{ error }}</p>
            <p v-else-if="loading" class="text-[13px] py-6 text-center" style="color: var(--color-text-secondary);">Loading…</p>
            <template v-else>
              <p v-if="!invoices.length" class="text-[13px] py-6 text-center" style="color: var(--color-text-secondary);">No invoices on this order yet — issue one from the order page first.</p>
              <button
v-for="inv in invoices" :key="inv.id" type="button"
                class="w-full flex items-center justify-between gap-3 rounded-xl border px-3.5 py-3 text-left transition-colors"
                :style="{
                  background: selectedId === inv.id ? 'var(--color-accent-soft)' : 'var(--color-bg)',
                  borderColor: selectedId === inv.id ? 'var(--color-accent)' : 'var(--color-border)',
                }"
                @click="selectedId = inv.id">
                <span class="flex items-center gap-2 min-w-0">
                  <span class="font-mono text-[13px] truncate" style="color: var(--color-text);">{{ inv.number }}</span>
                  <AdminStatusPill :status="inv.status" />
                </span>
                <span class="text-[12px] shrink-0" style="color: var(--color-text-secondary);">
                  {{ fmtMyr(outstanding(inv)) }} of {{ fmtMyr(inv.amount_total) }} outstanding
                </span>
              </button>
              <button
v-if="currentInvoiceId" type="button"
                class="w-full flex items-center gap-2 rounded-xl border px-3.5 py-3 text-left transition-colors"
                :style="{
                  background: selectedId === null ? 'var(--color-accent-soft)' : 'var(--color-bg)',
                  borderColor: selectedId === null ? 'var(--color-accent)' : 'var(--color-border)',
                }"
                @click="selectedId = null">
                <UIcon name="i-lucide-unlink" class="size-4" style="color: var(--color-text-tertiary);" />
                <span class="text-[13px]" style="color: var(--color-text-secondary);">Not allocated — unlink from {{ currentInvoiceNumber }}</span>
              </button>
            </template>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-2 px-5 py-4 border-t" style="border-color: var(--color-border);">
            <button type="button" class="btn-pill btn-pill-ghost text-[13px]" @click="open = false">Cancel</button>
            <button
type="button" class="btn-pill btn-pill-primary text-[13px]"
              :class="{ 'opacity-50': !changed || saving }" :disabled="!changed || saving" @click="submit">
              {{ saving ? 'Updating…' : 'Update allocation' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <AdminConfirmDialog :open="confirmOpen" :config="confirmConfig" @resolve="resolveConfirm" />
</template>
```

- [ ] **Step 2: Lint + typecheck**

Run: `docker compose -f docker-compose.dev.yml exec frontend npm run lint`
Expected: no errors.
Run: `docker compose -f docker-compose.dev.yml exec frontend npm run typecheck`
Expected: no new errors (the component isn't mounted yet — that's Task 5).

- [ ] **Step 3: Leave uncommitted; report the task done.**

---

### Task 5: Wire the modal into the payment detail page

**Files:**
- Modify: `frontend/app/pages/admin/payments/[id].vue:231-253` (the "Client & links" card)

**Interfaces:**
- Consumes: `AdminPaymentAllocateModal` (Task 4); the page's existing `payment` ref, `canAct` computed, `refundable` computed, and `fetchPayment()`.

- [ ] **Step 1: Mount the modal in the "Client & links" card**

In `frontend/app/pages/admin/payments/[id].vue`, inside the links card's `<div class="space-y-2 pt-4 border-t" …>` block, add after the receipt row (after the closing `</div>` of the `v-if="payment.receipt"` block, before the card's closing `</div>`):

```vue
            <AdminPaymentAllocateModal
              v-if="canAct"
              class="mt-3"
              :payment-id="payment.id"
              :order-id="payment.order_id"
              :current-invoice-id="payment.invoice_id"
              :current-invoice-number="payment.invoice_number"
              :net-amount="Number(payment.refundable_myr ?? payment.amount_myr)"
              @allocated="fetchPayment"
            />
```

(`refundable_myr` is the payment's amount net of refunds — the exact contribution the invoice will receive, matching what the backend recompute sums. It's present on the show endpoint since `refunds` is eager-loaded; the raw amount is the fallback.)

- [ ] **Step 2: Lint + typecheck**

Run: `docker compose -f docker-compose.dev.yml exec frontend npm run lint`
Expected: no errors.
Run: `docker compose -f docker-compose.dev.yml exec frontend npm run typecheck`
Expected: no errors.

- [ ] **Step 3: Manual smoke check (no screenshots — user verifies visually)**

With the dev stack up, open `http://127.0.0.1:3003/admin/payments/<id of an unallocated succeeded payment>`:
- "Allocate to invoice" button appears in the Client & links card.
- Opening it lists the order's non-void invoices with outstanding amounts.
- Picking one and pressing "Update allocation" shows the outcome confirm; confirming updates the page (invoice row appears) and the invoice's status flips where fully covered.
Report what you observed in the task summary — the user does their own visual pass.

- [ ] **Step 4: Leave uncommitted; report the task done.**

---

### Task 6: Preselect the only open invoice on the record form

**Files:**
- Modify: `frontend/app/pages/admin/payments/new.vue:49-68` (`fetchOrder`)

**Interfaces:**
- Consumes: existing `form`, `order` refs and the `OrderInvoice` interface in that file.

- [ ] **Step 1: Add the preselect in `fetchOrder()`**

In `frontend/app/pages/admin/payments/new.vue`, inside `fetchOrder()` after the block that drops an invalid `?invoice_id` (after the `if (form.invoice_id && …)` statement, before `prefillAmount()`):

```ts
    // No explicit allocation requested: preselect the order's only open
    // invoice so a plain "record payment" can't silently strand it unpaid.
    if (!form.invoice_id) {
      const openInvoices = (res.data.invoices ?? []).filter(d => d.status === 'issued')
      if (openInvoices.length === 1 && openInvoices[0]) form.invoice_id = String(openInvoices[0].id)
    }
```

(Still user-changeable to "— Not allocated —" in the dropdown; `prefillAmount()` then defaults the amount to that invoice's outstanding balance, which is the existing behavior for a preselected invoice.)

- [ ] **Step 2: Lint + typecheck**

Run: `docker compose -f docker-compose.dev.yml exec frontend npm run lint`
Expected: no errors.
Run: `docker compose -f docker-compose.dev.yml exec frontend npm run typecheck`
Expected: no errors.

- [ ] **Step 3: Leave uncommitted; report the task done.**

---

### Task 7: Docs + full gates

**Files:**
- Modify: `docs/global/PAYMENTS-LEDGER.md` (add the allocation endpoint + behavior)
- Check: `docs/global/ARCHITECTURE.md` (add the route only if it lists the other payment routes — `grep -n "payments" docs/global/ARCHITECTURE.md` first)

**Interfaces:** none — documentation and verification only.

- [ ] **Step 1: Document the allocation flow in PAYMENTS-LEDGER.md**

Add a section after the Phase 4 section (adjust heading level to match the file):

```markdown
## Reallocation — moving a payment between invoices

`PATCH /v1/admin/payments/{payment}/allocation` (`{ invoice_id: number | null }`) moves a
payment's invoice link after the fact — link an unallocated payment, re-link a
wrongly-tagged one, or unlink. `PaymentService::allocate()` cascades the move to the
payment's refund children and its receipt's display link, then recomputes **both** the new
invoice (via the observer, on save) and the old one (via the extracted
`PaymentObserver::recomputeInvoice()` — the observer only sees the payment's new invoice).
Guards: original `payment` rows only (refunds follow their parent), `succeeded` only,
same-order invoices only, `void` invoices never. Over-allocation is allowed — the admin UI
warns in its confirm step but the ledger row is truth.

UI: "Allocate to invoice" / "Change invoice allocation" on the payment detail page
(`AdminPaymentAllocateModal`, outcome-stating confirm popup). The record-payment form also
preselects the order's only `issued` invoice when exactly one exists.
```

If `grep -n "payments" docs/global/ARCHITECTURE.md` shows a payment-routes list, add one line for the new route in the same style; otherwise skip.

- [ ] **Step 2: Run the full backend suite**

Run: `docker compose -f docker-compose.dev.yml exec backend php artisan test`
Expected: entire suite PASSES.

- [ ] **Step 3: Run the full frontend gates**

Run: `docker compose -f docker-compose.dev.yml exec frontend npm run lint`
Expected: clean.
Run: `docker compose -f docker-compose.dev.yml exec frontend npm run typecheck`
Expected: clean.

- [ ] **Step 4: Leave uncommitted; report the run complete with a summary of every gate's result.**
