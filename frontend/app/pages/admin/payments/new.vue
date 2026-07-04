<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

interface OrderInvoice { id: number, number: string, status: string, amount_total: string }
interface Order {
  id: number
  order_number: string
  name: string | null
  remaining_myr: number
  invoices?: OrderInvoice[]
}

const orderId = computed(() => route.query.order_id ? String(route.query.order_id) : '')
const order = ref<Order | null>(null)
const loading = ref(true)
const error = ref('')
const saving = ref(false)

const form = reactive({
  amount: '',
  method: 'fpx',
  invoice_id: '',
  reference: '',
  paid_at: '',
  notes: '',
})

const methodOptions = [
  { value: 'card', label: 'Card' },
  { value: 'fpx', label: 'FPX' },
  { value: 'duitnow', label: 'DuitNow' },
  { value: 'bank_transfer', label: 'Bank transfer' },
  { value: 'cash', label: 'Cash' },
  { value: 'ewallet', label: 'E-wallet' },
  { value: 'other', label: 'Other' },
]

const invoiceItems = computed(() => [
  { label: '— Not allocated —', value: '' },
  ...(order.value?.invoices ?? []).map(d => ({ label: `${d.number} (${fmtMyr(d.amount_total)})`, value: String(d.id) })),
])

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

async function record() {
  if (!order.value) return
  if (!(Number(form.amount) > 0)) {
    toast.error('Enter an amount', 'The payment amount must be greater than zero.')
    return
  }
  saving.value = true
  try {
    const body: Record<string, unknown> = { amount_myr: Number(form.amount), method: form.method }
    if (form.invoice_id) body.invoice_id = Number(form.invoice_id)
    if (form.reference) body.reference = form.reference
    if (form.paid_at) body.paid_at = form.paid_at
    if (form.notes) body.notes = form.notes
    const res = await apiFetch<{ payment: { data: { id: number } } | { id: number } }>(
      `/api/v1/admin/orders/${order.value.id}/payments`, { method: 'POST', body },
    )
    const id = (res.payment as any).data?.id ?? (res.payment as any).id
    toast.success('Payment recorded', 'The order and invoice totals are updated.')
    await navigateTo(`/admin/payments/${id}`)
  }
  catch {
    toast.error('Couldn’t record payment', 'Please try again.')
  }
  finally {
    saving.value = false
  }
}

onMounted(fetchOrder)

function fmtMyr(amount: string | number) {
  return `RM ${Number(amount).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <NuxtLink
:to="orderId ? `/admin/orders/${orderId}` : '/admin/payments'"
      class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70" :style="{ color: 'var(--color-text-secondary)' }">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> {{ orderId ? 'Back to order' : 'All payments' }}
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" class="text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <template v-else-if="order">
      <h1 class="text-[24px] font-bold tracking-tight mb-1" style="color: var(--color-text);">Record payment</h1>
      <p class="text-[14px] mb-8" style="color: var(--color-text-secondary);">
        For order <span class="font-mono" style="color: var(--color-accent);">{{ order.order_number }}</span> · {{ order.name ?? '—' }}
        <span v-if="Number(order.remaining_myr) > 0"> · {{ fmtMyr(order.remaining_myr) }} remaining</span>
      </p>

      <div
class="rounded-2xl border p-6 space-y-5"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <div class="grid sm:grid-cols-2 gap-3">
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Amount (RM)</span>
            <input v-model="form.amount" type="number" min="0" step="0.01" placeholder="0.00" class="contact-input mt-1 w-full">
          </label>
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Paid at</span>
            <input v-model="form.paid_at" type="date" class="contact-input mt-1 w-full">
          </label>
        </div>

        <div>
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Method</span>
          <div class="flex flex-wrap gap-1.5 mt-1.5">
            <button
v-for="m in methodOptions" :key="m.value" type="button" class="standard-pill"
              :style="form.method === m.value ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' } : {}"
              @click="form.method = m.value">{{ m.label }}</button>
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Allocate to invoice</span>
            <AdminSelect v-model="form.invoice_id" class="mt-1" :items="invoiceItems" />
          </label>
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Reference</span>
            <input v-model="form.reference" type="text" placeholder="e.g. DuitNow ref" class="contact-input mt-1 w-full">
          </label>
        </div>

        <label class="block">
          <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Note (optional)</span>
          <input v-model="form.notes" type="text" placeholder="Internal note" class="contact-input mt-1 w-full">
        </label>

        <p class="text-[11px]" style="color: var(--color-text-tertiary);">Recording a payment updates the order's paid total and any allocated invoice automatically. Issue a receipt afterward from the payment's page.</p>

        <button
type="button" class="btn-pill btn-pill-primary w-full justify-center text-[13px]"
          :class="{ 'opacity-50': saving }" :disabled="saving" @click="record">
          {{ saving ? 'Recording…' : 'Record payment' }}
        </button>
      </div>
    </template>
  </div>
</template>
