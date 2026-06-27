<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

interface Order {
  id: number
  order_number: string
  name: string | null
  final_amount_myr: string
  deposit_pct: number | null
  deposit_due_myr: number
  amount_paid_myr: string
  remaining_myr: number
}

const orderId = computed(() => route.query.order_id ? String(route.query.order_id) : '')
const order = ref<Order | null>(null)
const loading = ref(true)
const error = ref('')
const issuing = ref(false)

const form = reactive({
  type: 'deposit' as 'deposit' | 'partial' | 'final',
  amount: '',
  discountValue: '',
  discountType: 'amount' as 'amount' | 'percent',
  discountLabel: '',
  promoValue: '',
  promoType: 'amount' as 'amount' | 'percent',
  promoCode: '',
  notes: '',
})

// Sensible default per invoice type, drawn from the order: deposit → deposit due,
// partial / final → outstanding balance. Always editable.
function defaultAmount(type: string) {
  if (!order.value) return ''
  const n = type === 'deposit' ? Number(order.value.deposit_due_myr) : Number(order.value.remaining_myr)
  return n > 0 ? String(Number(n.toFixed(2))) : ''
}

const typeItems = computed(() => [
  { label: order.value?.deposit_pct ? `Deposit (${order.value.deposit_pct}%)` : 'Deposit', value: 'deposit' },
  { label: 'Partial', value: 'partial' },
  { label: 'Final', value: 'final' },
])

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

async function fetchOrder() {
  if (!orderId.value) { error.value = 'No order specified.'; loading.value = false; return }
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Order }>(`/api/v1/admin/orders/${orderId.value}`)
    order.value = res.data
    form.amount = defaultAmount(form.type)
  }
  catch {
    error.value = 'Failed to load the order.'
  }
  finally {
    loading.value = false
  }
}

watch(() => form.type, t => { form.amount = defaultAmount(t) })

async function issueInvoice() {
  if (!order.value) return
  if (!(Number(form.amount) > 0)) {
    toast.error('Enter an amount', 'The invoice amount must be greater than zero.')
    return
  }
  issuing.value = true
  try {
    const body: Record<string, unknown> = {
      type: 'invoice',
      invoiceType: form.type,
      amount: Number(form.amount),
    }
    if (Number(form.discountValue) > 0) {
      body.discountType = form.discountType
      body.discountValue = Number(form.discountValue)
      if (form.discountLabel) body.discountLabel = form.discountLabel
    }
    if (Number(form.promoValue) > 0) {
      body.promoType = form.promoType
      body.promoValue = Number(form.promoValue)
      if (form.promoCode) body.promoCode = form.promoCode
    }
    if (form.notes) body.notes = form.notes
    const res = await apiFetch<{ document: { id: number } }>(`/api/v1/admin/orders/${order.value.id}/documents`, { method: 'POST', body })
    toast.success('Invoice issued', 'Record payments against it from the Payments module.')
    await navigateTo(`/admin/invoices/${res.document.id}`)
  }
  catch {
    toast.error('Couldn’t issue invoice', 'Please try again.')
  }
  finally {
    issuing.value = false
  }
}

onMounted(fetchOrder)

function fmtMyr(amount: string | number) {
  return `RM ${Number(amount).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <NuxtLink :to="orderId ? `/admin/orders/${orderId}` : '/admin/invoices'"
      class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70" :style="{ color: 'var(--color-text-secondary)' }">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> {{ orderId ? 'Back to order' : 'All invoices' }}
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" class="text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <template v-else-if="order">
      <h1 class="text-[24px] font-bold tracking-tight mb-1" style="color: var(--color-text);">Issue invoice</h1>
      <p class="text-[14px] mb-6" style="color: var(--color-text-secondary);">
        For order <span class="font-mono" style="color: var(--color-accent);">{{ order.order_number }}</span> · {{ order.name ?? '—' }}
      </p>

      <!-- Order money context -->
      <div class="rounded-2xl border p-5 mb-5 grid grid-cols-3 gap-4"
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

      <div class="rounded-2xl border p-6 space-y-5"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <div class="grid sm:grid-cols-2 gap-3">
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Invoice type</span>
            <AdminSelect v-model="form.type" class="mt-1" :items="typeItems" />
          </label>
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Amount (RM)</span>
            <input v-model="form.amount" type="number" min="0" step="0.01" placeholder="0.00" class="contact-input mt-1 w-full">
          </label>
        </div>

        <!-- Discount & promo — reductions off the billed amount -->
        <div class="pt-4 border-t space-y-3" style="border-color: var(--color-border);">
          <p class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Discount &amp; promo <span class="normal-case font-normal">(optional)</span></p>
          <div class="grid sm:grid-cols-2 gap-3">
            <label class="block">
              <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Discount</span>
              <div class="flex gap-2 mt-1">
                <AdminRateToggle v-model="form.discountType" />
                <input v-model="form.discountValue" type="number" min="0" :max="form.discountType === 'percent' ? 100 : undefined" :step="form.discountType === 'percent' ? 1 : 0.01" placeholder="0" class="contact-input flex-1">
              </div>
            </label>
            <label class="block">
              <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Discount label</span>
              <input v-model="form.discountLabel" type="text" placeholder="e.g. Loyalty discount" class="contact-input mt-1 w-full">
            </label>
            <label class="block">
              <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Promo code</span>
              <input v-model="form.promoCode" type="text" placeholder="e.g. RAYA2026" class="contact-input mt-1 w-full">
            </label>
            <label class="block">
              <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Promo amount</span>
              <div class="flex gap-2 mt-1">
                <AdminRateToggle v-model="form.promoType" />
                <input v-model="form.promoValue" type="number" min="0" :max="form.promoType === 'percent' ? 100 : undefined" :step="form.promoType === 'percent' ? 1 : 0.01" placeholder="0" class="contact-input flex-1">
              </div>
            </label>
          </div>
        </div>

        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Note (optional)</span>
          <input v-model="form.notes" type="text" placeholder="Shown on the invoice" class="contact-input mt-1 w-full">
        </label>

        <!-- Live total -->
        <div class="rounded-xl border p-3 text-[12px] space-y-1.5" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
          <div class="flex items-center justify-between">
            <span style="color: var(--color-text-secondary);">Amount</span>
            <span class="tabular-nums" style="color: var(--color-text);">{{ fmtMyr(baseAmount) }}</span>
          </div>
          <div v-if="discountAmt > 0" class="flex items-center justify-between">
            <span style="color: var(--color-text-secondary);">{{ form.discountLabel || 'Discount' }}<span v-if="form.discountType === 'percent'" style="color: var(--color-text-tertiary);"> ({{ Number(form.discountValue) }}%)</span></span>
            <span class="tabular-nums" style="color: var(--color-text);">−{{ fmtMyr(discountAmt) }}</span>
          </div>
          <div v-if="promoAmt > 0" class="flex items-center justify-between">
            <span style="color: var(--color-text-secondary);">Promo<span v-if="form.promoCode" style="color: var(--color-text-tertiary);"> ({{ form.promoCode }})</span><span v-else-if="form.promoType === 'percent'" style="color: var(--color-text-tertiary);"> ({{ Number(form.promoValue) }}%)</span></span>
            <span class="tabular-nums" style="color: var(--color-text);">−{{ fmtMyr(promoAmt) }}</span>
          </div>
          <div class="flex items-center justify-between pt-1.5 border-t font-semibold" style="border-color: var(--color-border);">
            <span style="color: var(--color-text);">Total due</span>
            <span class="tabular-nums" style="color: var(--color-text);">{{ fmtMyr(netTotal) }}</span>
          </div>
        </div>

        <p class="text-[11px]" style="color: var(--color-text-tertiary);">Issues as <strong>unpaid</strong> — record payments against it from the Payments module; the paid status updates automatically.</p>

        <button type="button" class="btn-pill btn-pill-primary w-full justify-center text-[13px]"
          :class="{ 'opacity-50': issuing }" :disabled="issuing" @click="issueInvoice">
          {{ issuing ? 'Issuing…' : 'Issue invoice' }}
        </button>
      </div>
    </template>
  </div>
</template>
