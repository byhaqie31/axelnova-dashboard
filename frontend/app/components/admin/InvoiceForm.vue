<script setup lang="ts">
import { formatMyr } from '~/utils/money'
// Shared invoice form — used by both the issue page (create) and the edit
// page. Create posts the full body; edit merges over the stored issue inputs
// server-side, and when `amountsLocked` (payments recorded) only notes, due
// date and the display options are submitted — the other fields render disabled.
//
// On an instalment / partner order the form follows the quotation's agreed plan
// (order.payment_plan): an "Instalment" / "Monthly fee" type bills one numbered
// payment — its amount and due date come from the schedule, and instalments
// already billed by a live invoice can't be picked again.
import type { InvoiceType, OrderPlanView } from '~/composables/paymentPlan'
import { fmtRm, fmtYmd } from '~/composables/paymentPlan'

interface OrderMoney {
  id: number
  order_number: string
  name: string | null
  final_amount_myr: string
  deposit_pct: number | null
  deposit_amount_myr?: number | string | null
  deposit_due_myr: number
  amount_paid_myr: string
  remaining_myr: number
  payment_plan?: OrderPlanView | null
  invoices?: { id: number; type: string; status: string; instalment_no?: number | null }[]
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
const plan = computed(() => props.order.payment_plan ?? null)
// A new invoice on a plan order whose deposit is already billed starts on the
// next instalment; otherwise on the deposit.
const startType: InvoiceType = init.invoiceType
  ?? (props.mode === 'create' && plan.value?.deposit_invoice && plan.value.next_instalment_no ? 'instalment' : 'deposit')
const form = reactive({
  type: startType,
  // 0 = none picked (the select needs a number, never null).
  instalmentNo: Number(init.instalmentNo ?? (startType === 'instalment' ? plan.value?.next_instalment_no : 0) ?? 0),
  amount: init.amount != null ? String(init.amount) : '',
  discountValue: init.discountValue != null ? String(init.discountValue) : '',
  discountType: (init.discountType ?? 'amount') as 'amount' | 'percent',
  discountLabel: (init.discountLabel ?? '') as string,
  promoValue: init.promoValue != null ? String(init.promoValue) : '',
  promoType: (init.promoType ?? 'amount') as 'amount' | 'percent',
  promoCode: (init.promoCode ?? '') as string,
  notes: (init.notes ?? '') as string,
  dueAt: (init.dueAt ?? '') as string,
  // Display options — layout only, never money, so they stay editable when
  // amounts lock. Hidden parts are dropped at render time; the stored figures
  // (and the invoice total) don't change.
  showSummary: init.showSummary !== false,
  showRemaining: init.showRemaining !== false,
  describeBilling: Boolean(init.billingTitle),
  billingTitle: (init.billingTitle ?? '') as string,
  billingLabel: (init.billingLabel ?? '') as string,
  billingText: (init.billingText ?? '') as string,
  addScope: Array.isArray(init.scopeItems) && init.scopeItems.length > 0,
  scopeTitle: (init.scopeTitle ?? '') as string,
  scopeText: (Array.isArray(init.scopeItems) ? init.scopeItems.join('\n') : '') as string,
})

// "What this payment covers" title, prefilled from the invoice type. A title
// still matching the old type's default follows a type change; a hand-edited
// one is left alone.
const BILLING_TITLES: Record<string, string> = {
  deposit: 'Deposit on signing and mobilisation',
  partial: 'Progress payment on delivered milestones',
  final: 'Final balance on completion and handover',
  instalment: 'Monthly payment under the agreed payment plan',
}
const SCOPE_MAX = 8
watch(() => form.describeBilling, (on) => {
  if (on && !form.billingTitle.trim()) form.billingTitle = BILLING_TITLES[form.type] ?? ''
})
watch(() => form.type, (t, prev) => {
  if (form.describeBilling && form.billingTitle === BILLING_TITLES[prev]) form.billingTitle = BILLING_TITLES[t] ?? ''
})
const scopeItems = computed(() =>
  form.scopeText.split('\n').map(l => l.trim()).filter(Boolean))

// The instalment row an `instalment` invoice bills (from the order's schedule).
const instalmentRow = computed(() =>
  plan.value?.schedule.find(r => r.n === form.instalmentNo) ?? null)

// Sensible default per invoice type, drawn from the order: deposit → deposit
// due, instalment → the plan's monthly figure, partial / final → outstanding
// balance. Always editable. (Create mode only — an edit keeps whatever amount
// the invoice was issued with.)
function defaultAmount(type: string) {
  const n = type === 'deposit'
    ? Number(props.order.deposit_due_myr)
    : type === 'instalment'
      ? Number(plan.value?.monthly_myr ?? 0)
      : Number(props.order.remaining_myr)
  return n > 0 ? String(Number(n.toFixed(2))) : ''
}
// An instalment invoice is due on its scheduled date.
function syncInstalmentDue() {
  if (props.mode === 'create' && form.type === 'instalment' && instalmentRow.value) form.dueAt = instalmentRow.value.date
}
if (props.mode === 'create' && !form.amount) form.amount = defaultAmount(form.type)
syncInstalmentDue()
watch(() => form.type, (t) => {
  if (t === 'instalment' && !form.instalmentNo) form.instalmentNo = plan.value?.next_instalment_no ?? 0
  if (props.mode === 'create') {
    form.amount = defaultAmount(t)
    if (t === 'instalment') syncInstalmentDue()
    else form.dueAt = ''
  }
})
watch(() => form.instalmentNo, syncInstalmentDue)

// The deposit can't be billed twice: it greys out once a live deposit invoice
// exists, or once less than the deposit itself remains to be paid. Stays
// selectable when it's already the current type (editing that deposit invoice).
const depositUnavailable = computed(() => {
  const billed = (props.order.invoices ?? []).some(i => i.type === 'deposit' && i.status !== 'void')
  const due = Number(props.order.deposit_due_myr) || 0
  return billed || (due > 0 && Number(props.order.remaining_myr) < due)
})
const typeItems = computed(() => {
  const items: { label: string; value: InvoiceType; disabled?: boolean }[] = [{
    label: plan.value
      ? plan.value.deposit_label
      : props.order.deposit_pct ? `Deposit (${props.order.deposit_pct}%)` : 'Deposit',
    value: 'deposit',
    disabled: depositUnavailable.value && form.type !== 'deposit',
  }]
  if (plan.value) {
    items.push({
      label: plan.value.plan === 'partner' ? 'Monthly fee' : 'Instalment',
      value: 'instalment',
      disabled: plan.value.next_instalment_no === null && form.type !== 'instalment',
    })
  }
  items.push({ label: 'Partial', value: 'partial' }, { label: 'Final', value: 'final' })
  return items
})
// Only payments still to be billed are offered — the list shrinks as each
// instalment gets its invoice (the plan card above counts what's left). The one
// this invoice already bills stays listed in edit mode.
const initialInstalmentNo = Number(init.instalmentNo ?? 0)
const instalmentItems = computed(() => (plan.value?.schedule ?? [])
  .filter(r => !r.invoice || r.n === initialInstalmentNo)
  .map(r => ({ label: `${r.label} · ${fmtYmd(r.date)}`, value: r.n })))
const billedCount = computed(() => (plan.value?.schedule ?? []).filter(r => r.invoice).length)
// Don't start a new invoice on an unavailable type.
if (props.mode === 'create' && depositUnavailable.value && form.type === 'deposit') {
  form.type = plan.value?.next_instalment_no ? 'instalment' : 'partial'
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
const billLabel = computed(() => {
  if (form.type === 'instalment') return instalmentRow.value?.label ?? 'Instalment'
  if (form.type === 'deposit' && plan.value) return plan.value.deposit_label
  return ({ deposit: 'Deposit', partial: 'Partial payment', final: 'Final balance' } as Record<string, string>)[form.type] ?? 'Amount'
})
const agreedTotal = computed(() => Number(props.order.final_amount_myr) || 0)
const paidToDate = computed(() => Number(props.order.amount_paid_myr) || 0)
const remainingAfter = computed(() =>
  agreedTotal.value > 0 ? Math.max(agreedTotal.value - paidToDate.value - netTotal.value, 0) : 0)

// Full form state — sent for previews always, and as the submit body when
// amounts are unlocked. Edit mode sends explicit nulls so a cleared field
// clears the stored input (absent keys keep their stored value server-side).
function fullBody(): Record<string, unknown> {
  const body: Record<string, unknown> = { invoiceType: form.type, amount: Number(form.amount) || 0 }
  body.instalmentNo = form.type === 'instalment' && form.instalmentNo ? form.instalmentNo : null
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
  return { ...body, ...displayBody() }
}

// Always sent in full — a toggle switched off sends nulls, so an edit clears
// the stored option instead of keeping it.
function displayBody(): Record<string, unknown> {
  const billing = form.describeBilling && form.billingTitle.trim()
  const scope = form.addScope && scopeItems.value.length > 0
  return {
    showSummary: form.showSummary,
    showRemaining: form.showRemaining,
    billingTitle: billing ? form.billingTitle.trim() : null,
    billingLabel: billing ? form.billingLabel.trim() || null : null,
    billingText: billing ? form.billingText.trim() || null : null,
    scopeTitle: scope ? form.scopeTitle.trim() || null : null,
    scopeItems: scope ? scopeItems.value : null,
  }
}

function submitBody(): Record<string, unknown> {
  if (!props.amountsLocked) return fullBody()
  // Locked: the server rejects amount-bearing fields — send only what may change.
  const body: Record<string, unknown> = { notes: form.notes || null }
  if (form.dueAt) body.dueAt = form.dueAt
  return { ...body, ...displayBody() }
}

// ── Document preview (lazy) ────────────────────────────────────────────────
// Fetched only when the preview modal opens — the old debounced fetch on
// every keystroke server-rendered a document nobody was looking at.
const previewData = ref<Record<string, any> | null>(null)
const previewLoading = ref(false)
const previewStale = ref(true)
// Latest-wins guard: rapid edits (e.g. switching invoice type) can resolve out
// of order — only the most recent request may write previewData.
let previewSeq = 0

async function fetchPreview() {
  const seq = ++previewSeq
  if (!(Number(form.amount) > 0)) { previewData.value = null; return }
  previewLoading.value = true
  try {
    const data = await apiFetch<Record<string, any>>(`/api/v1/admin/orders/${props.order.id}/documents/preview`, { method: 'POST', body: fullBody() })
    if (seq === previewSeq) {
      previewData.value = data
      previewStale.value = false
    }
  }
  catch {
    // keep last good preview (stays stale, retried on next open)
  }
  finally {
    if (seq === previewSeq) previewLoading.value = false
  }
}

watch(form, () => { previewStale.value = true }, { deep: true })

function onPreviewOpen() {
  if (previewStale.value && !previewLoading.value) fetchPreview()
}

function submit() {
  if (!props.amountsLocked && !(Number(form.amount) > 0)) {
    toast.error('Enter an amount', 'The invoice amount must be greater than zero.')
    return
  }
  if (!props.amountsLocked && form.type === 'instalment' && !instalmentRow.value) {
    toast.error('Pick an instalment', 'Choose which payment of the plan this invoice bills.')
    return
  }
  if (form.addScope && scopeItems.value.length > SCOPE_MAX) {
    toast.error('Too many scope bullets', `Keep it to ${SCOPE_MAX} or fewer — 5 reads best.`)
    return
  }
  if (form.addScope && scopeItems.value.some(l => l.length > 120)) {
    toast.error('Scope bullet too long', 'Each bullet can be up to 120 characters.')
    return
  }
  emit('submit', submitBody())
}

</script>

<template>
  <div class="space-y-5">
    <!-- Order money context -->
    <div
      class="rounded-2xl border p-5 grid grid-cols-3 max-md:grid-cols-2 gap-4 max-md:gap-x-3"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
      <div class="max-md:col-span-2">
        <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Agreed total</p>
        <p class="text-[15px] font-bold tabular-nums" style="color: var(--color-text);">{{ formatMyr(order.final_amount_myr) }}</p>
      </div>
      <div>
        <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Paid</p>
        <p class="text-[15px] font-semibold tabular-nums" style="color: var(--color-success);">{{ formatMyr(order.amount_paid_myr) }}</p>
      </div>
      <div>
        <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Remaining</p>
        <p class="text-[15px] font-bold tabular-nums" :style="{ color: Number(order.remaining_myr) > 0 ? 'var(--color-warning)' : 'var(--color-success)' }">{{ formatMyr(order.remaining_myr) }}</p>
      </div>
    </div>

    <div
      class="rounded-2xl border p-6 max-md:p-5 space-y-5"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
      <p
        v-if="amountsLocked"
        class="rounded-xl border px-3 py-2 text-[12px] flex items-center gap-2"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }">
        <UIcon name="i-lucide-lock" class="size-3.5 shrink-0" />
        Payments are recorded against this invoice — amounts are locked. Only the note, due date and display options can change.
      </p>

      <!-- The quotation's agreed plan this order is billed against -->
      <div
        v-if="plan"
        class="rounded-xl border px-3.5 py-3 flex items-start gap-2.5 text-[12px] max-md:text-[13px]"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <UIcon name="i-lucide-calendar-clock" class="size-4 shrink-0 mt-px" style="color: var(--color-accent);" />
        <div class="min-w-0 space-y-0.5">
          <p style="color: var(--color-text);">
            <span class="font-semibold">{{ plan.plan === 'partner' ? 'Partner plan' : 'Instalment plan' }}</span>
            <span class="tabular-nums"> · {{ plan.deposit_label }} RM {{ fmtRm(plan.deposit_myr) }} + {{ plan.months }} × RM {{ fmtRm(plan.monthly_myr) }}</span>
          </p>
          <p class="tabular-nums" style="color: var(--color-text-tertiary);">
            <template v-if="plan.next_instalment_no">{{ billedCount }} of {{ plan.months }} billed · next: {{ plan.schedule[plan.next_instalment_no - 1]?.label }} · due {{ fmtYmd(plan.schedule[plan.next_instalment_no - 1]?.date ?? '') }}</template>
            <template v-else>All {{ plan.months }} payments have an invoice.</template>
          </p>
        </div>
      </div>

      <div class="grid sm:grid-cols-2 gap-3">
        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Invoice type</span>
          <AdminSelect v-model="form.type" class="mt-1" :items="typeItems" :disabled="amountsLocked" />
        </label>
        <label v-if="form.type === 'instalment'" class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">{{ plan?.plan === 'partner' ? 'Which month' : 'Which instalment' }}</span>
          <AdminSelect v-model="form.instalmentNo" class="mt-1" :items="instalmentItems" placeholder="Pick a payment…" :disabled="amountsLocked" />
        </label>
        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Amount (RM)</span>
          <input v-model="form.amount" type="number" min="0" step="0.01" placeholder="0.00" class="contact-input mt-1 w-full" :disabled="amountsLocked">
          <span
            v-if="form.type === 'instalment' && instalmentRow && Number(form.amount) !== instalmentRow.amount"
            class="block text-[11px] mt-1" style="color: var(--color-warning);">
            The plan's figure is RM {{ fmtRm(instalmentRow.amount) }}.
          </span>
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

      <!-- Display — what the PDF shows. Layout only: hidden sections are
           left out of the document, the invoice total never changes. -->
      <div class="pt-4 border-t space-y-3" style="border-color: var(--color-border);">
        <p class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Display</p>
        <div class="grid sm:grid-cols-2 gap-3">
          <label class="flex items-center gap-2.5 cursor-pointer select-none max-md:py-1">
            <input v-model="form.showSummary" type="checkbox" class="size-4 shrink-0" style="accent-color: var(--color-accent);">
            <span class="text-[13px]" style="color: var(--color-text);">Show summary <span style="color: var(--color-text-tertiary);">(agreed total, paid to date)</span></span>
          </label>
          <label class="flex items-center gap-2.5 cursor-pointer select-none max-md:py-1">
            <input v-model="form.showRemaining" type="checkbox" class="size-4 shrink-0" style="accent-color: var(--color-accent);">
            <span class="text-[13px]" style="color: var(--color-text);">Show remaining balance</span>
          </label>
        </div>

        <label class="flex items-center gap-2.5 cursor-pointer select-none max-md:py-1">
          <input v-model="form.describeBilling" type="checkbox" class="size-4 shrink-0" style="accent-color: var(--color-accent);">
          <span class="text-[13px]" style="color: var(--color-text);">Describe what this payment covers</span>
        </label>
        <div v-if="form.describeBilling" class="grid sm:grid-cols-2 gap-3 pl-6 max-md:pl-3">
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Title</span>
            <input v-model="form.billingTitle" type="text" maxlength="80" placeholder="e.g. Deposit on signing and mobilisation" class="contact-input mt-1 w-full">
          </label>
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Label</span>
            <input v-model="form.billingLabel" type="text" maxlength="40" placeholder="Scope covered" class="contact-input mt-1 w-full">
          </label>
          <label class="block sm:col-span-2">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Short note</span>
            <textarea v-model="form.billingText" rows="2" maxlength="220" placeholder="One or two lines on what this payment covers" class="contact-input mt-1 resize-none w-full" />
          </label>
        </div>

        <label class="flex items-center gap-2.5 cursor-pointer select-none max-md:py-1">
          <input v-model="form.addScope" type="checkbox" class="size-4 shrink-0" style="accent-color: var(--color-accent);">
          <span class="text-[13px]" style="color: var(--color-text);">Add scope bullets</span>
        </label>
        <div v-if="form.addScope" class="space-y-3 pl-6 max-md:pl-3">
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Section title</span>
            <input v-model="form.scopeTitle" type="text" maxlength="60" placeholder="Scope covered" class="contact-input mt-1 w-full">
          </label>
          <label class="block">
            <span class="flex items-center justify-between">
              <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Bullets <span class="normal-case font-normal">(one per line)</span></span>
              <span class="text-[11px] tabular-nums" :style="{ color: scopeItems.length > SCOPE_MAX ? 'var(--color-danger)' : 'var(--color-text-tertiary)' }">{{ scopeItems.length }} / {{ SCOPE_MAX }}</span>
            </span>
            <textarea v-model="form.scopeText" rows="5" placeholder="One bullet per line…" class="contact-input mt-1 resize-none w-full" />
          </label>
        </div>
      </div>

      <!-- Live total — mirrors the PDF summary: agreed total and paid-to-date
           frame the type-labelled bill, with the balance remaining after it. -->
      <div class="live-total rounded-xl border p-3 text-[12px] max-md:text-[13px] space-y-1.5" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <div v-if="agreedTotal > 0" class="flex items-center justify-between">
          <span style="color: var(--color-text-secondary);">Agreed project total</span>
          <span class="tabular-nums" style="color: var(--color-text);">{{ formatMyr(agreedTotal) }}</span>
        </div>
        <div v-if="paidToDate > 0" class="flex items-center justify-between">
          <span style="color: var(--color-text-secondary);">Paid to date</span>
          <span class="tabular-nums" style="color: var(--color-success);">−{{ formatMyr(paidToDate) }}</span>
        </div>
        <div class="flex items-center justify-between">
          <span style="color: var(--color-text-secondary);">{{ billLabel }}</span>
          <span class="tabular-nums" style="color: var(--color-text);">{{ formatMyr(baseAmount) }}</span>
        </div>
        <div v-if="discountAmt > 0" class="flex items-center justify-between">
          <span style="color: var(--color-text-secondary);">{{ form.discountLabel || 'Discount' }}<span v-if="form.discountType === 'percent'" style="color: var(--color-text-tertiary);"> ({{ Number(form.discountValue) }}%)</span></span>
          <span class="tabular-nums" style="color: var(--color-text);">−{{ formatMyr(discountAmt) }}</span>
        </div>
        <div v-if="promoAmt > 0" class="flex items-center justify-between">
          <span style="color: var(--color-text-secondary);">Promo<span v-if="form.promoCode" style="color: var(--color-text-tertiary);"> ({{ form.promoCode }})</span></span>
          <span class="tabular-nums" style="color: var(--color-text);">−{{ formatMyr(promoAmt) }}</span>
        </div>
        <div class="flex items-center justify-between pt-1.5 border-t font-semibold" style="border-color: var(--color-border);">
          <span style="color: var(--color-text);">Total due</span>
          <span class="tabular-nums" :style="{ color: form.type === 'final' ? 'var(--color-danger)' : 'var(--color-text)' }">{{ formatMyr(netTotal) }}</span>
        </div>
        <div v-if="remainingAfter > 0.009" class="flex items-center justify-between">
          <span style="color: var(--color-text-tertiary);">Remaining after this payment</span>
          <span class="tabular-nums" style="color: var(--color-text-tertiary);">{{ formatMyr(remainingAfter) }}</span>
        </div>
      </div>

      <p v-if="mode === 'create'" class="text-[11px]" style="color: var(--color-text-tertiary);">Issues as <strong>unpaid</strong> — record payments against it from the Payments module; the paid status updates automatically.</p>
      <p v-else class="text-[11px]" style="color: var(--color-text-tertiary);">Saving re-freezes the document with the <strong>same invoice number</strong> — the PDF link stays valid.</p>

      <div class="flex gap-2">
        <AdminDocumentPreviewModal :data="previewData" @open="onPreviewOpen" />
        <button
          type="button" class="btn-pill btn-pill-primary flex-1 justify-center text-[13px]"
          :class="{ 'opacity-50': submitting }" :disabled="submitting" @click="submit">
          {{ submitting ? submittingLabel : submitLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media (max-width: 767.98px) {
  /* ≥16px form text stops iOS Safari zooming the page on focus. */
  input:not([type='checkbox'], [type='radio']),
  textarea,
  :deep(input:not([type='checkbox'], [type='radio'])) { font-size: 16px; }
  /* Live total: long discount/promo labels wrap; amounts never split. */
  .live-total > div { gap: 12px; }
  .live-total > div > span:last-child { white-space: nowrap; }
}
</style>
