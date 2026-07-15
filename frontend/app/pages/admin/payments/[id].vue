<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

interface Payment {
  id: number
  payment_number: string
  type: 'payment' | 'refund'
  gateway: string
  method: string
  status: string
  amount_myr: string
  fee_myr: string
  net_myr: string | null
  currency: string
  reference: string | null
  notes: string | null
  paid_at: string | null
  created_at: string | null
  parent_payment_id: number | null
  order_id: number
  order_number: string | null
  invoice_id: number | null
  invoice_number: string | null
  client_id: number | null
  name: string | null
  email: string | null
  recorded_by_name: string | null
  refundable_myr?: string
  receipt?: { id: number, number: string, pdf_path: string } | null
}

const payment = ref<Payment | null>(null)
const loading = ref(true)
const error = ref('')

const refundOpen = ref(false)
const refunding = ref(false)
const refundForm = reactive({ amount: '', notes: '' })
const issuingReceipt = ref(false)
const receiptPreviewData = ref<Record<string, any> | null>(null)

async function fetchReceiptPreview() {
  if (!payment.value || payment.value.type !== 'payment' || payment.value.status !== 'succeeded') return
  try {
    receiptPreviewData.value = await apiFetch(`/api/v1/admin/payments/${payment.value.id}/receipt/preview`)
  }
  catch { /* ignore — keep last good preview */ }
}

const refundable = computed(() => Number(payment.value?.refundable_myr ?? 0))
const canAct = computed(() => payment.value?.type === 'payment' && payment.value?.status === 'succeeded')

async function fetchPayment() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Payment }>(`/api/v1/admin/payments/${route.params.id}`)
    payment.value = res.data
    fetchReceiptPreview()
  }
  catch {
    error.value = 'Failed to load payment.'
  }
  finally {
    loading.value = false
  }
}

async function submitRefund() {
  if (!payment.value) return
  const amount = Number(refundForm.amount)
  if (!(amount > 0) || amount > refundable.value) {
    toast.error('Invalid amount', `Enter an amount between 0 and ${fmtMyr(refundable.value)}.`)
    return
  }
  refunding.value = true
  try {
    await apiFetch(`/api/v1/admin/payments/${payment.value.id}/refund`, {
      method: 'POST',
      body: { amount_myr: amount, notes: refundForm.notes || undefined },
    })
    toast.success('Refund recorded', `${fmtMyr(amount)} refunded.`)
    refundOpen.value = false
    refundForm.amount = ''
    refundForm.notes = ''
    await fetchPayment()
  }
  catch {
    toast.error('Couldn’t record refund', 'Please try again.')
  }
  finally {
    refunding.value = false
  }
}

async function issueReceipt() {
  if (!payment.value) return
  issuingReceipt.value = true
  try {
    const res = await apiFetch<{ receipt: { pdf_path: string } }>(`/api/v1/admin/payments/${payment.value.id}/receipt`, { method: 'POST' })
    toast.success('Receipt ready', 'Opening the PDF.')
    await fetchPayment()
    if (import.meta.client && res.receipt?.pdf_path) window.open(res.receipt.pdf_path, '_blank', 'noopener')
  }
  catch {
    toast.error('Couldn’t issue receipt', 'Please try again.')
  }
  finally {
    issuingReceipt.value = false
  }
}

onMounted(fetchPayment)

function fmtDateTime(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('en-MY', { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' })
}
function fmtMyr(amount: string | number) {
  return `RM ${Math.abs(Number(amount)).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <NuxtLink
to="/admin/payments" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      :style="{ color: 'var(--color-text-secondary)' }">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All payments
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" class="text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <template v-else-if="payment">
      <!-- Header -->
      <div class="flex items-start justify-between gap-4 flex-wrap mb-8">
        <div class="min-w-0">
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="font-mono text-[24px] font-bold tracking-tight" style="color: var(--color-text);">{{ payment.payment_number }}</h1>
            <AdminStatusPill :status="payment.status" />
            <span
v-if="payment.type === 'refund'" class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
              :style="{ color: 'var(--color-danger)', background: 'var(--color-danger-soft)' }">Refund</span>
          </div>
          <p class="text-[13px] mt-1.5" style="color: var(--color-text-secondary);">
            <span class="capitalize">{{ payment.method.replace('_', ' ') }}</span> · <span class="capitalize">{{ payment.gateway }}</span>
            <template v-if="payment.order_number">
              · <NuxtLink :to="`/admin/orders/${payment.order_id}`" class="underline" :style="{ color: 'var(--color-accent)' }">{{ payment.order_number }}</NuxtLink>
            </template>
          </p>
        </div>
        <div v-if="canAct && refundable > 0" class="shrink-0">
          <button
type="button" class="btn-pill btn-pill-primary text-[12px]" style="height: 36px; padding: 0 18px;"
            @click="refundOpen = !refundOpen">
            Refund
          </button>
        </div>
      </div>

      <!-- Refund form -->
      <div
v-if="refundOpen" class="rounded-2xl border p-6 mb-5"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Refund — up to {{ fmtMyr(refundable) }}</p>
        <div class="grid sm:grid-cols-2 gap-3">
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Amount (RM)</span>
            <input
v-model="refundForm.amount" type="number" min="0" :max="refundable" step="0.01"
              class="contact-input mt-1 w-full" placeholder="0.00">
          </label>
          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Note (optional)</span>
            <input v-model="refundForm.notes" type="text" class="contact-input mt-1 w-full" placeholder="Reason / reference">
          </label>
        </div>
        <div class="flex gap-2 mt-4">
          <button
type="button" class="btn-pill btn-pill-primary text-[12px]" style="height: 34px; padding: 0 16px;"
            :class="{ 'opacity-50': refunding }" :disabled="refunding" @click="submitRefund">Record refund</button>
          <button type="button" class="btn-pill btn-pill-ghost text-[12px]" style="height: 34px; padding: 0 16px;" @click="refundOpen = false">Cancel</button>
        </div>
      </div>

      <div class="grid lg:grid-cols-3 gap-5">
        <!-- Summary -->
        <div
class="lg:col-span-2 rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-5" style="color: var(--color-text-tertiary);">Details</p>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-4">
            <div>
              <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Amount</p>
              <p class="text-[15px] font-semibold" :style="{ color: Number(payment.amount_myr) < 0 ? 'var(--color-danger)' : 'var(--color-text)' }">
                {{ Number(payment.amount_myr) < 0 ? '−' : '' }}{{ fmtMyr(payment.amount_myr) }}
              </p>
            </div>
            <div v-if="canAct">
              <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Refundable</p>
              <p class="text-[15px] font-semibold" style="color: var(--color-text);">{{ fmtMyr(refundable) }}</p>
            </div>
            <div>
              <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Date</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ fmtDateTime(payment.paid_at) }}</p>
            </div>
            <div>
              <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Reference</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ payment.reference ?? '—' }}</p>
            </div>
            <div v-if="payment.recorded_by_name">
              <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Recorded by</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ payment.recorded_by_name }}</p>
            </div>
          </div>
          <p v-if="payment.notes" class="text-[12px] mt-4 pt-4 border-t" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }">{{ payment.notes }}</p>
        </div>

        <!-- Client & links -->
        <div
class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-5" style="color: var(--color-text-tertiary);">Client &amp; links</p>
          <p class="text-[14px] font-medium" style="color: var(--color-text);">{{ payment.name ?? '—' }}</p>
          <p class="text-[12px] mb-4" style="color: var(--color-text-tertiary);">{{ payment.email ?? '' }}</p>
          <div class="space-y-2 pt-4 border-t" style="border-color: var(--color-border);">
            <NuxtLink
v-if="payment.order_number" :to="`/admin/orders/${payment.order_id}`"
              class="flex items-center justify-between gap-2 text-[13px]">
              <span style="color: var(--color-text-secondary);">Order</span>
              <span class="font-mono" :style="{ color: 'var(--color-accent)' }">{{ payment.order_number }}</span>
            </NuxtLink>
            <NuxtLink
v-if="payment.invoice_id" :to="`/admin/invoices/${payment.invoice_id}`"
              class="flex items-center justify-between gap-2 text-[13px]">
              <span style="color: var(--color-text-secondary);">Invoice</span>
              <span class="font-mono" :style="{ color: 'var(--color-accent)' }">{{ payment.invoice_number }}</span>
            </NuxtLink>
            <div v-if="payment.parent_payment_id" class="flex items-center justify-between gap-2 text-[13px]">
              <span style="color: var(--color-text-secondary);">Refund of</span>
              <NuxtLink :to="`/admin/payments/${payment.parent_payment_id}`" class="font-mono" :style="{ color: 'var(--color-accent)' }">#{{ payment.parent_payment_id }}</NuxtLink>
            </div>
            <div v-if="payment.receipt" class="flex items-center justify-between gap-2 text-[13px]">
              <span style="color: var(--color-text-secondary);">Receipt</span>
              <a :href="payment.receipt.pdf_path" target="_blank" rel="noopener" class="font-mono" :style="{ color: 'var(--color-accent)' }">{{ payment.receipt.number }}</a>
            </div>
            <AdminPaymentAllocateModal
              v-if="canAct"
              :payment-id="payment.id"
              :order-id="payment.order_id"
              :current-invoice-id="payment.invoice_id"
              :current-invoice-number="payment.invoice_number"
              :net-amount="Number(payment.refundable_myr ?? payment.amount_myr)"
              @allocated="fetchPayment"
            />
          </div>
        </div>
      </div>

      <!-- Receipt — preview + issue (succeeded payments only) -->
      <div
v-if="canAct" class="rounded-2xl border p-6 mt-5"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <div class="flex items-center justify-between gap-3 flex-wrap">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Receipt</p>
            <p class="text-[12px] mt-0.5" style="color: var(--color-text-secondary);">{{ payment.receipt ? 'Issued — proof this payment landed.' : 'Preview the receipt, then issue it.' }}</p>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <!-- No fixed heights — all three share the .btn-pill 44px standard. -->
            <AdminDocumentPreviewModal :data="receiptPreviewData" label="Preview" :disabled="!receiptPreviewData" />
            <a
v-if="payment.receipt" :href="payment.receipt.pdf_path" target="_blank" rel="noopener"
              class="btn-pill btn-pill-ghost text-[12px]" style="padding: 0 16px;">
              <UIcon name="i-lucide-file-text" class="size-4" /> View PDF
            </a>
            <button
v-else type="button" class="btn-pill btn-pill-primary text-[12px]" style="padding: 0 16px;"
              :class="{ 'opacity-50': issuingReceipt }" :disabled="issuingReceipt" @click="issueReceipt">
              {{ issuingReceipt ? 'Issuing…' : 'Issue receipt' }}
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
