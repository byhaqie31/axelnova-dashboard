<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

interface InvoicePayment {
  id: number
  payment_number: string
  type: 'payment' | 'refund'
  method: string
  status: string
  amount_myr: string
  reference: string | null
  paid_at: string | null
}

interface Invoice {
  id: number
  invoice_number: string
  order_id: number
  order_number: string | null
  quotation_id: number | null
  reference_code: string | null
  client_id: number | null
  name: string | null
  email: string | null
  type: 'deposit' | 'partial' | 'final'
  status: 'issued' | 'paid' | 'void'
  amount_total: string
  amount_paid: string | null
  due_at: string | null
  issued_at: string | null
  paid_at: string | null
  is_overdue: boolean
  pdf_path: string
  emailed_at: string | null
  emailed_to: string | null
  inputs: Record<string, unknown> | null
  amounts_locked: boolean
  payments?: InvoicePayment[]
}

const invoice = ref<Invoice | null>(null)
const loading = ref(true)
const error = ref('')

async function fetchInvoice() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Invoice }>(`/api/v1/admin/invoices/${route.params.id}`)
    invoice.value = res.data
  }
  catch {
    error.value = 'Failed to load invoice.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchInvoice)

const balance = computed(() => {
  if (!invoice.value) return 0
  return Math.max(Number(invoice.value.amount_total) - Number(invoice.value.amount_paid ?? 0), 0)
})
const paidPct = computed(() => {
  if (!invoice.value) return 0
  const total = Number(invoice.value.amount_total)
  return total > 0 ? Math.min(100, (Number(invoice.value.amount_paid ?? 0) / total) * 100) : 0
})

function fmtDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
function fmtMyr(amount: string | number) {
  return `RM ${Number(amount).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

// ── Email invoice ──────────────────────────────────────────────────────────
const emailOpen = ref(false)
const emailSending = ref(false)

async function sendInvoice(email: string) {
  if (!invoice.value) return
  emailSending.value = true
  try {
    await apiFetch(`/api/v1/admin/invoices/${invoice.value.id}/send`, { method: 'POST', body: { email } })
    emailOpen.value = false
    toast.success('Invoice email queued', `Sending to ${email} with the PDF attached.`)
    // Refresh shortly so the "last sent" stamp appears once the queue runs.
    setTimeout(fetchInvoice, 4000)
  }
  catch {
    toast.error('Couldn’t queue the email', 'Please try again.')
  }
  finally {
    emailSending.value = false
  }
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <NuxtLink
to="/admin/invoices" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      :style="{ color: 'var(--color-text-secondary)' }">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All invoices
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" class="text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <template v-else-if="invoice">
      <!-- Header -->
      <div class="flex items-start justify-between gap-4 flex-wrap mb-8">
        <div class="min-w-0">
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="font-mono text-[24px] font-bold tracking-tight" style="color: var(--color-text);">{{ invoice.invoice_number }}</h1>
            <AdminStatusPill :status="invoice.status" />
            <span
v-if="invoice.is_overdue" class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
              :style="{ color: 'var(--color-danger)', background: 'var(--color-danger-soft)' }">Overdue</span>
          </div>
          <p class="text-[13px] mt-1.5" style="color: var(--color-text-secondary);">
            <span class="uppercase tracking-wide">{{ invoice.type }}</span> invoice
            <template v-if="invoice.order_number">
              · on <NuxtLink :to="`/admin/orders/${invoice.order_id}`" class="underline" :style="{ color: 'var(--color-accent)' }">{{ invoice.order_number }}</NuxtLink>
            </template>
          </p>
        </div>
        <div class="flex items-center gap-2 shrink-0 flex-wrap">
          <!-- Only unpaid issued invoices are editable — paid/void are frozen records. -->
          <NuxtLink
            v-if="invoice.status === 'issued'" :to="`/admin/invoices/edit?id=${invoice.id}`"
            class="btn-pill btn-pill-ghost text-[12px]" style="height: 36px; padding: 0 18px;">
            <UIcon name="i-lucide-pencil" class="size-4" /> Edit
          </NuxtLink>
          <button
            v-if="invoice.status !== 'void'" type="button"
            class="btn-pill btn-pill-ghost text-[12px]" style="height: 36px; padding: 0 18px;"
            @click="emailOpen = true">
            <UIcon name="i-lucide-mail" class="size-4" /> Email invoice
          </button>
          <a
            :href="invoice.pdf_path" target="_blank" rel="noopener"
            class="btn-pill btn-pill-primary text-[12px]" style="height: 36px; padding: 0 18px;">
            <UIcon name="i-lucide-file-text" class="size-4" /> View PDF
          </a>
          <!-- Shortcut into the ledger, pre-allocated to this invoice. -->
          <NuxtLink
            v-if="invoice.status === 'issued'"
            :to="`/admin/payments/new?order_id=${invoice.order_id}&invoice_id=${invoice.id}`"
            class="btn-pill btn-pill-success text-[12px]" style="height: 36px; padding: 0 18px;">
            <UIcon name="i-lucide-wallet" class="size-4" /> Record payment
          </NuxtLink>
        </div>
      </div>

      <div class="grid lg:grid-cols-3 gap-5">
        <!-- Summary -->
        <div
class="lg:col-span-2 rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-5" style="color: var(--color-text-tertiary);">Summary</p>

          <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-4">
            <div>
              <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Total</p>
              <p class="text-[15px] font-semibold" style="color: var(--color-text);">{{ fmtMyr(invoice.amount_total) }}</p>
            </div>
            <div>
              <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Paid</p>
              <p class="text-[15px] font-semibold" style="color: var(--color-success);">{{ fmtMyr(invoice.amount_paid ?? 0) }}</p>
            </div>
            <div>
              <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Balance</p>
              <p class="text-[15px] font-semibold" :style="{ color: balance > 0 ? 'var(--color-text)' : 'var(--color-success)' }">{{ fmtMyr(balance) }}</p>
            </div>
          </div>

          <div class="mt-5 h-1.5 rounded-full overflow-hidden" style="background: var(--color-bg-secondary);">
            <div
class="h-full rounded-full transition-[width] duration-500"
              :style="{ width: `${paidPct}%`, background: 'var(--color-success)' }" />
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-4 pt-5 mt-5 border-t" style="border-color: var(--color-border);">
            <div>
              <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Issued</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ fmtDate(invoice.issued_at) }}</p>
            </div>
            <div>
              <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Due</p>
              <p class="text-[13px]" :style="{ color: invoice.is_overdue ? 'var(--color-danger)' : 'var(--color-text)' }">{{ fmtDate(invoice.due_at) }}</p>
            </div>
            <div>
              <p class="text-[11px] uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Paid at</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ fmtDate(invoice.paid_at) }}</p>
            </div>
          </div>
        </div>

        <!-- Client & order -->
        <div
class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-5" style="color: var(--color-text-tertiary);">Client &amp; order</p>
          <p class="text-[14px] font-medium" style="color: var(--color-text);">{{ invoice.name ?? '—' }}</p>
          <p class="text-[12px] mb-4" style="color: var(--color-text-tertiary);">{{ invoice.email ?? '' }}</p>
          <div class="space-y-2 pt-4 border-t" style="border-color: var(--color-border);">
            <NuxtLink
v-if="invoice.order_number" :to="`/admin/orders/${invoice.order_id}`"
              class="flex items-center justify-between gap-2 text-[13px]" :style="{ color: 'var(--color-accent)' }">
              <span style="color: var(--color-text-secondary);">Order</span>
              <span class="font-mono">{{ invoice.order_number }}</span>
            </NuxtLink>
            <NuxtLink
              v-if="invoice.reference_code && invoice.quotation_id" :to="`/admin/quotations/${invoice.quotation_id}`"
              class="flex items-center justify-between gap-2 text-[13px]" :style="{ color: 'var(--color-accent)' }">
              <span style="color: var(--color-text-secondary);">Quotation</span>
              <span class="font-mono">{{ invoice.reference_code }}</span>
            </NuxtLink>
            <div v-else-if="invoice.reference_code" class="flex items-center justify-between gap-2 text-[13px]">
              <span style="color: var(--color-text-secondary);">Quotation</span>
              <span class="font-mono" style="color: var(--color-text);">{{ invoice.reference_code }}</span>
            </div>
            <div v-if="invoice.emailed_at" class="flex items-center justify-between gap-2 text-[13px]">
              <span style="color: var(--color-text-secondary);">Emailed</span>
              <span style="color: var(--color-text);" :title="invoice.emailed_to ?? ''">{{ fmtDate(invoice.emailed_at) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Payments allocated -->
      <div
class="rounded-2xl border p-6 mt-5"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Payments</p>
        <div v-if="invoice.payments?.length" class="space-y-2">
          <div
v-for="p in invoice.payments" :key="p.id"
            class="flex items-center justify-between gap-3 rounded-xl border p-3" :style="{ borderColor: 'var(--color-border)' }">
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-mono text-[13px] font-semibold" style="color: var(--color-text);">{{ p.payment_number }}</span>
                <AdminStatusPill :status="p.status" />
              </div>
              <p class="text-[11px] mt-1" style="color: var(--color-text-tertiary);">
                {{ p.method }}<span v-if="p.reference"> · {{ p.reference }}</span> · {{ fmtDate(p.paid_at) }}
              </p>
            </div>
            <span
class="text-[13px] font-semibold shrink-0"
              :style="{ color: Number(p.amount_myr) < 0 ? 'var(--color-danger)' : 'var(--color-text)' }">{{ fmtMyr(p.amount_myr) }}</span>
          </div>
        </div>
        <p v-else class="text-[13px]" style="color: var(--color-text-tertiary);">No payments recorded against this invoice yet.</p>
      </div>

      <AdminInvoiceEmailModal
        :open="emailOpen"
        :invoice-number="invoice.invoice_number"
        :default-email="invoice.emailed_to ?? invoice.email"
        :emailed-at="invoice.emailed_at"
        :emailed-to="invoice.emailed_to"
        :sending="emailSending"
        @close="emailOpen = false"
        @send="sendInvoice" />
    </template>
  </div>
</template>
