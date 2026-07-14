<script setup lang="ts">
// Shared invoice form — used by both the issue page (create) and the edit
// page. Create posts the full body; edit merges over the stored issue inputs
// server-side, and when `amountsLocked` (payments recorded) only notes and
// due date are submitted — the other fields render disabled.

interface OrderMoney {
  id: number
  order_number: string
  name: string | null
  final_amount_myr: string
  deposit_pct: number | null
  deposit_due_myr: number
  amount_paid_myr: string
  remaining_myr: number
}

const props = withDefaults(defineProps<{
  order: OrderMoney
  mode?: 'create' | 'edit'
  /** Issue-form fields to prefill (the invoice's stored `inputs`) — edit mode. */
  initial?: Record<string, unknown> | null
  /** Payments recorded — amount-bearing fields are locked server-side. */
  amountsLocked?: boolean
  submitting?: boolean
  submitLabel?: string
  submittingLabel?: string
}>(), {
  mode: 'create',
  initial: null,
  amountsLocked: false,
  submitting: false,
  submitLabel: 'Issue invoice',
  submittingLabel: 'Issuing…',
})

const emit = defineEmits<{ submit: [body: Record<string, unknown>] }>()

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

const init = (props.initial ?? {}) as Record<string, any>
const form = reactive({
  type: (init.invoiceType ?? 'deposit') as 'deposit' | 'partial' | 'final',
  amount: init.amount != null ? String(init.amount) : '',
  discountValue: init.discountValue != null ? String(init.discountValue) : '',
  discountType: (init.discountType ?? 'amount') as 'amount' | 'percent',
  discountLabel: (init.discountLabel ?? '') as string,
  promoValue: init.promoValue != null ? String(init.promoValue) : '',
  promoType: (init.promoType ?? 'amount') as 'amount' | 'percent',
  promoCode: (init.promoCode ?? '') as string,
  notes: (init.notes ?? '') as string,
  dueAt: (init.dueAt ?? '') as string,
})

// Sensible default per invoice type, drawn from the order: deposit → deposit due,
// partial / final → outstanding balance. Always editable. (Create mode only —
// an edit keeps whatever amount the invoice was issued with.)
function defaultAmount(type: string) {
  const n = type === 'deposit' ? Number(props.order.deposit_due_myr) : Number(props.order.remaining_myr)
  return n > 0 ? String(Number(n.toFixed(2))) : ''
}
if (props.mode === 'create' && !form.amount) form.amount = defaultAmount(form.type)
watch(() => form.type, (t) => {
  if (props.mode === 'create') form.amount = defaultAmount(t)
})

// A 50% deposit stops fitting once less than half the agreed total remains —
// grey it out instead of letting a second deposit be issued. Stays selectable
// when it's already the current type (editing an existing deposit invoice).
const depositUnavailable = computed(() => {
  const agreed = Number(props.order.final_amount_myr) || 0
  return agreed > 0 && Number(props.order.remaining_myr) < agreed / 2
})
const typeItems = computed(() => [
  {
    label: props.order.deposit_pct ? `Deposit (${props.order.deposit_pct}%)` : 'Deposit',
    value: 'deposit',
    disabled: depositUnavailable.value && form.type !== 'deposit',
  },
  { label: 'Partial', value: 'partial' },
  { label: 'Final', value: 'final' },
])
// Don't start a new invoice on an unavailable type.
if (props.mode === 'create' && depositUnavailable.value && form.type === 'deposit') {
  form.type = 'partial'
}

// Live total — mirrors the server: a percentage comes off the billed amount,
// a fixed value is taken as-is; both reduce the total.
function adjAmount(type: 'amount' | 'percent', value: string, base: number) {
  const v = Number(value) || 0
  if (v <= 0) return 0
  return type === 'percent' ? Math.round(base * Math.min(v, 100) / 100 * 100) / 100 : v
}
const baseAmount = computed(() => Number(form.amount) || 0)
const discountAmt = computed(() => adjAmount(form.discountType, form.discountValue, baseAmount.value))
const promoAmt = computed(() => adjAmount(form.promoType, form.promoValue, baseAmount.value))
const netTotal = computed(() => Math.max(baseAmount.value - discountAmt.value - promoAmt.value, 0))

// Payment context mirroring the PDF summary (DocumentMapper::amountDocument):
// agreed total and ledger-paid frame the bill; deposit/partial show what
// remains after this payment, final shows what's been paid.
const billLabel = computed(() =>
  ({ deposit: 'Deposit', partial: 'Partial payment', final: 'Final balance' })[form.type])
const agreedTotal = computed(() => Number(props.order.final_amount_myr) || 0)
const paidToDate = computed(() => Number(props.order.amount_paid_myr) || 0)
const remainingAfter = computed(() =>
  agreedTotal.value > 0 ? Math.max(agreedTotal.value - paidToDate.value - netTotal.value, 0) : 0)

// Full form state — sent for previews always, and as the submit body when
// amounts are unlocked. Edit mode sends explicit nulls so a cleared field
// clears the stored input (absent keys keep their stored value server-side).
function fullBody(): Record<string, unknown> {
  const body: Record<string, unknown> = { invoiceType: form.type, amount: Number(form.amount) || 0 }
  if (Number(form.discountValue) > 0) {
    body.discountType = form.discountType
    body.discountValue = Number(form.discountValue)
    body.discountLabel = form.discountLabel || null
  }
  else if (props.mode === 'edit') {
    body.discountType = null
    body.discountValue = null
    body.discountLabel = null
  }
  if (Number(form.promoValue) > 0) {
    body.promoType = form.promoType
    body.promoValue = Number(form.promoValue)
    body.promoCode = form.promoCode || null
  }
  else if (props.mode === 'edit') {
    body.promoType = null
    body.promoValue = null
    body.promoCode = null
  }
  if (form.notes || props.mode === 'edit') body.notes = form.notes || null
  if (form.dueAt) body.dueAt = form.dueAt
  return body
}

function submitBody(): Record<string, unknown> {
  if (!props.amountsLocked) return fullBody()
  // Locked: the server rejects amount-bearing fields — send only what may change.
  const body: Record<string, unknown> = { notes: form.notes || null }
  if (form.dueAt) body.dueAt = form.dueAt
  return body
}

// ── Live preview ───────────────────────────────────────────────────────────
const previewData = ref<Record<string, any> | null>(null)
const previewLoading = ref(false)
let previewTimer: ReturnType<typeof setTimeout> | undefined
// Latest-wins guard: rapid edits (e.g. switching invoice type) can resolve out
// of order — only the most recent request may write previewData.
let previewSeq = 0

async function fetchPreview() {
  const seq = ++previewSeq
  if (!(Number(form.amount) > 0)) { previewData.value = null; return }
  previewLoading.value = true
  try {
    const data = await apiFetch<Record<string, any>>(`/api/v1/admin/orders/${props.order.id}/documents/preview`, { method: 'POST', body: fullBody() })
    if (seq === previewSeq) previewData.value = data
  }
  catch {
    // keep last good preview
  }
  finally {
    if (seq === previewSeq) previewLoading.value = false
  }
}

watch(form, () => {
  clearTimeout(previewTimer)
  previewTimer = setTimeout(fetchPreview, 350)
}, { deep: true })

onMounted(fetchPreview)
onBeforeUnmount(() => clearTimeout(previewTimer))

function submit() {
  if (!props.amountsLocked && !(Number(form.amount) > 0)) {
    toast.error('Enter an amount', 'The invoice amount must be greater than zero.')
    return
  }
  emit('submit', submitBody())
}

function fmtMyr(amount: string | number) {
  return `RM ${Number(amount).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}
</script>

<template>
  <div class="space-y-5">
    <!-- Order money context -->
    <div
      class="rounded-2xl border p-5 grid grid-cols-3 gap-4"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
      <div>
        <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Agreed total</p>
        <p class="text-[15px] font-bold tabular-nums" style="color: var(--color-text);">{{ fmtMyr(order.final_amount_myr) }}</p>
      </div>
      <div>
        <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Paid</p>
        <p class="text-[15px] font-semibold tabular-nums" style="color: var(--color-success);">{{ fmtMyr(order.amount_paid_myr) }}</p>
      </div>
      <div>
        <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Remaining</p>
        <p class="text-[15px] font-bold tabular-nums" :style="{ color: Number(order.remaining_myr) > 0 ? 'var(--color-warning)' : 'var(--color-success)' }">{{ fmtMyr(order.remaining_myr) }}</p>
      </div>
    </div>

    <div
      class="rounded-2xl border p-6 space-y-5"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
      <p
        v-if="amountsLocked"
        class="rounded-xl border px-3 py-2 text-[12px] flex items-center gap-2"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }">
        <UIcon name="i-lucide-lock" class="size-3.5 shrink-0" />
        Payments are recorded against this invoice — amounts are locked. Only the note and due date can change.
      </p>

      <div class="grid sm:grid-cols-2 gap-3">
        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Invoice type</span>
          <AdminSelect v-model="form.type" class="mt-1" :items="typeItems" :disabled="amountsLocked" />
        </label>
        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Amount (RM)</span>
          <input v-model="form.amount" type="number" min="0" step="0.01" placeholder="0.00" class="contact-input mt-1 w-full" :disabled="amountsLocked">
        </label>
      </div>

      <!-- Discount & promo -->
      <div class="pt-4 border-t space-y-3" style="border-color: var(--color-border);">
        <p class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Discount &amp; promo <span class="normal-case font-normal">(optional)</span></p>
        <div class="grid sm:grid-cols-2 gap-3">
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Discount</span>
            <div class="flex gap-2 mt-1">
              <AdminRateToggle v-model="form.discountType" :disabled="amountsLocked" />
              <input v-model="form.discountValue" type="number" min="0" :max="form.discountType === 'percent' ? 100 : undefined" :step="form.discountType === 'percent' ? 1 : 0.01" placeholder="0" class="contact-input flex-1" :disabled="amountsLocked">
            </div>
          </label>
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Discount label</span>
            <input v-model="form.discountLabel" type="text" placeholder="e.g. Loyalty discount" class="contact-input mt-1 w-full" :disabled="amountsLocked">
          </label>
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Promo code</span>
            <input v-model="form.promoCode" type="text" placeholder="e.g. RAYA2026" class="contact-input mt-1 w-full" :disabled="amountsLocked">
          </label>
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Promo amount</span>
            <div class="flex gap-2 mt-1">
              <AdminRateToggle v-model="form.promoType" :disabled="amountsLocked" />
              <input v-model="form.promoValue" type="number" min="0" :max="form.promoType === 'percent' ? 100 : undefined" :step="form.promoType === 'percent' ? 1 : 0.01" placeholder="0" class="contact-input flex-1" :disabled="amountsLocked">
            </div>
          </label>
        </div>
      </div>

      <div class="grid sm:grid-cols-2 gap-3">
        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Note (optional)</span>
          <input v-model="form.notes" type="text" placeholder="Shown on the invoice" class="contact-input mt-1 w-full">
        </label>
        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Due date (optional)</span>
          <input v-model="form.dueAt" type="date" class="contact-input mt-1 w-full">
        </label>
      </div>

      <!-- Live total — mirrors the PDF summary: agreed total and paid-to-date
           frame the type-labelled bill, with the balance remaining after it. -->
      <div class="rounded-xl border p-3 text-[12px] space-y-1.5" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <div v-if="agreedTotal > 0" class="flex items-center justify-between">
          <span style="color: var(--color-text-secondary);">Agreed project total</span>
          <span class="tabular-nums" style="color: var(--color-text);">{{ fmtMyr(agreedTotal) }}</span>
        </div>
        <div v-if="paidToDate > 0" class="flex items-center justify-between">
          <span style="color: var(--color-text-secondary);">Paid to date</span>
          <span class="tabular-nums" style="color: var(--color-success);">−{{ fmtMyr(paidToDate) }}</span>
        </div>
        <div class="flex items-center justify-between">
          <span style="color: var(--color-text-secondary);">{{ billLabel }}</span>
          <span class="tabular-nums" style="color: var(--color-text);">{{ fmtMyr(baseAmount) }}</span>
        </div>
        <div v-if="discountAmt > 0" class="flex items-center justify-between">
          <span style="color: var(--color-text-secondary);">{{ form.discountLabel || 'Discount' }}<span v-if="form.discountType === 'percent'" style="color: var(--color-text-tertiary);"> ({{ Number(form.discountValue) }}%)</span></span>
          <span class="tabular-nums" style="color: var(--color-text);">−{{ fmtMyr(discountAmt) }}</span>
        </div>
        <div v-if="promoAmt > 0" class="flex items-center justify-between">
          <span style="color: var(--color-text-secondary);">Promo<span v-if="form.promoCode" style="color: var(--color-text-tertiary);"> ({{ form.promoCode }})</span></span>
          <span class="tabular-nums" style="color: var(--color-text);">−{{ fmtMyr(promoAmt) }}</span>
        </div>
        <div class="flex items-center justify-between pt-1.5 border-t font-semibold" style="border-color: var(--color-border);">
          <span style="color: var(--color-text);">Total due</span>
          <span class="tabular-nums" :style="{ color: form.type === 'final' ? 'var(--color-danger)' : 'var(--color-text)' }">{{ fmtMyr(netTotal) }}</span>
        </div>
        <div v-if="remainingAfter > 0.009" class="flex items-center justify-between">
          <span style="color: var(--color-text-tertiary);">Remaining after this payment</span>
          <span class="tabular-nums" style="color: var(--color-text-tertiary);">{{ fmtMyr(remainingAfter) }}</span>
        </div>
      </div>

      <p v-if="mode === 'create'" class="text-[11px]" style="color: var(--color-text-tertiary);">Issues as <strong>unpaid</strong> — record payments against it from the Payments module; the paid status updates automatically.</p>
      <p v-else class="text-[11px]" style="color: var(--color-text-tertiary);">Saving re-freezes the document with the <strong>same invoice number</strong> — the PDF link stays valid.</p>

      <div class="flex gap-2">
        <AdminDocumentPreviewModal :data="previewData" :disabled="!previewData" />
        <button
          type="button" class="btn-pill btn-pill-primary flex-1 justify-center text-[13px]"
          :class="{ 'opacity-50': submitting }" :disabled="submitting" @click="submit">
          {{ submitting ? submittingLabel : submitLabel }}
        </button>
      </div>
    </div>
  </div>
</template>
