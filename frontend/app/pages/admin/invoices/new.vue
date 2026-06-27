<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

interface LineItem { title?: string, desc?: string, qty?: number | string, rate?: number | string }
interface Order {
  id: number
  order_number: string
  name: string | null
  final_amount_myr: string
  quotation_document?: { items?: LineItem[] } | null
  quotation_addons?: { key: string, label: string, amount_myr: string }[]
}

const orderId = computed(() => route.query.order_id ? String(route.query.order_id) : '')
const order = ref<Order | null>(null)
const loading = ref(true)
const error = ref('')
const issuing = ref(false)

const form = reactive({
  type: 'deposit' as 'deposit' | 'partial' | 'final',
  discountValue: '',
  discountType: 'amount' as 'amount' | 'percent',
  discountLabel: '',
  promoValue: '',
  promoType: 'amount' as 'amount' | 'percent',
  promoCode: '',
})

const lineItems = computed<LineItem[]>(() => {
  const items = order.value?.quotation_document?.items
  return Array.isArray(items) ? items : []
})
const subtotal = computed(() => lineItems.value.reduce((s, it) => s + (Number(it.qty) || 0) * (Number(it.rate) || 0), 0))
function adjAmount(type: 'amount' | 'percent', value: string, base: number) {
  const v = Number(value) || 0
  if (v <= 0) return 0
  return type === 'percent' ? Math.round(base * Math.min(v, 100) / 100 * 100) / 100 : v
}
const discountAmt = computed(() => adjAmount(form.discountType, form.discountValue, subtotal.value))
const promoAmt = computed(() => adjAmount(form.promoType, form.promoValue, subtotal.value))
const total = computed(() => Math.max(subtotal.value - discountAmt.value - promoAmt.value, 0))

async function fetchOrder() {
  if (!orderId.value) { error.value = 'No order specified.'; loading.value = false; return }
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Order }>(`/api/v1/admin/orders/${orderId.value}`)
    order.value = res.data
  }
  catch {
    error.value = 'Failed to load the order.'
  }
  finally {
    loading.value = false
  }
}

async function issueInvoice() {
  if (!order.value) return
  issuing.value = true
  try {
    const body: Record<string, unknown> = { type: 'invoice', invoiceType: form.type }
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
      <p class="text-[14px] mb-8" style="color: var(--color-text-secondary);">
        For order <span class="font-mono" style="color: var(--color-accent);">{{ order.order_number }}</span> · {{ order.name ?? '—' }}
      </p>

      <div class="rounded-2xl border p-6 space-y-5"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <!-- Line items (read-only, from the confirmed quotation) -->
        <div v-if="lineItems.length">
          <p class="text-[11px] font-medium uppercase tracking-wider mb-2" style="color: var(--color-text-tertiary);">Line items</p>
          <div class="space-y-2">
            <div v-for="(it, i) in lineItems" :key="i" class="flex justify-between items-baseline gap-4">
              <span class="text-[13px]" style="color: var(--color-text);">{{ it.title }}<span v-if="Number(it.qty) > 1" class="text-[12px]" style="color: var(--color-text-tertiary);"> × {{ it.qty }}</span></span>
              <span class="text-[13px] font-semibold tabular-nums whitespace-nowrap" style="color: var(--color-text);">{{ fmtMyr((Number(it.qty) || 0) * (Number(it.rate) || 0)) }}</span>
            </div>
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-3 pt-4 border-t" style="border-color: var(--color-border);">
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Invoice type</span>
            <AdminSelect v-model="form.type" class="mt-1" :items="[{ label: 'Deposit', value: 'deposit' }, { label: 'Partial', value: 'partial' }, { label: 'Final', value: 'final' }]" />
          </label>
        </div>

        <!-- Discount & promo -->
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

        <!-- Live total -->
        <div class="rounded-xl border p-3 text-[12px] space-y-1.5" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
          <div class="flex items-center justify-between">
            <span style="color: var(--color-text-secondary);">Subtotal</span>
            <span class="tabular-nums" style="color: var(--color-text);">{{ fmtMyr(subtotal) }}</span>
          </div>
          <div v-if="discountAmt > 0" class="flex items-center justify-between">
            <span style="color: var(--color-text-secondary);">{{ form.discountLabel || 'Discount' }}</span>
            <span class="tabular-nums" style="color: var(--color-text);">−{{ fmtMyr(discountAmt) }}</span>
          </div>
          <div v-if="promoAmt > 0" class="flex items-center justify-between">
            <span style="color: var(--color-text-secondary);">Promo<span v-if="form.promoCode" style="color: var(--color-text-tertiary);"> ({{ form.promoCode }})</span></span>
            <span class="tabular-nums" style="color: var(--color-text);">−{{ fmtMyr(promoAmt) }}</span>
          </div>
          <div class="flex items-center justify-between pt-1.5 border-t font-semibold" style="border-color: var(--color-border);">
            <span style="color: var(--color-text);">Total due</span>
            <span class="tabular-nums" style="color: var(--color-text);">{{ fmtMyr(total) }}</span>
          </div>
        </div>

        <p class="text-[11px]" style="color: var(--color-text-tertiary);">The invoice issues as <strong>unpaid</strong> — record payments against it from the Payments module; the paid status updates automatically.</p>

        <button type="button" class="btn-pill btn-pill-primary w-full justify-center text-[13px]"
          :class="{ 'opacity-50': issuing }" :disabled="issuing" @click="issueInvoice">
          {{ issuing ? 'Issuing…' : 'Issue invoice' }}
        </button>
      </div>
    </template>
  </div>
</template>
