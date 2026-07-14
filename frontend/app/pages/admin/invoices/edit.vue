<script setup lang="ts">
// Edit an issued invoice in place (?id=…). The form is the same one used to
// issue; saving re-runs the server-side mapper over the stored issue inputs
// and re-freezes the payload — same AXNI number, same PDF link. Amount fields
// lock (and are withheld from the request) once payments are recorded.
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

interface Invoice {
  id: number
  invoice_number: string
  order_id: number
  status: 'issued' | 'paid' | 'void'
  inputs: Record<string, unknown> | null
  amounts_locked: boolean
}

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

const invoiceId = computed(() => route.query.id ? String(route.query.id) : '')
const invoice = ref<Invoice | null>(null)
const order = ref<Order | null>(null)
const loading = ref(true)
const error = ref('')
const saving = ref(false)

async function fetchData() {
  if (!invoiceId.value) { error.value = 'No invoice specified.'; loading.value = false; return }
  loading.value = true
  error.value = ''
  try {
    const inv = await apiFetch<{ data: Invoice }>(`/api/v1/admin/invoices/${invoiceId.value}`)
    invoice.value = inv.data
    if (inv.data.status !== 'issued') {
      error.value = inv.data.status === 'paid'
        ? 'Paid invoices are read-only.'
        : 'Void invoices are read-only.'
      return
    }
    const ord = await apiFetch<{ data: Order }>(`/api/v1/admin/orders/${inv.data.order_id}`)
    order.value = ord.data
  }
  catch {
    error.value = 'Failed to load the invoice.'
  }
  finally {
    loading.value = false
  }
}

async function saveInvoice(body: Record<string, unknown>) {
  if (!invoice.value) return
  saving.value = true
  try {
    await apiFetch(`/api/v1/admin/invoices/${invoice.value.id}`, { method: 'PUT', body })
    toast.success('Invoice updated', 'The document was re-frozen with the same invoice number.')
    await navigateTo(`/admin/invoices/${invoice.value.id}`)
  }
  catch {
    toast.error('Couldn’t update the invoice', 'Please check the fields and try again.')
  }
  finally {
    saving.value = false
  }
}

onMounted(fetchData)
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <NuxtLink
      :to="invoiceId ? `/admin/invoices/${invoiceId}` : '/admin/invoices'"
      class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70" :style="{ color: 'var(--color-text-secondary)' }">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> Back to invoice
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" class="text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <template v-else-if="invoice && order">
      <h1 class="text-[24px] font-bold tracking-tight mb-1" style="color: var(--color-text);">
        Edit <span class="font-mono">{{ invoice.invoice_number }}</span>
      </h1>
      <p class="text-[14px] mb-6" style="color: var(--color-text-secondary);">
        On order <span class="font-mono" style="color: var(--color-accent);">{{ order.order_number }}</span> · {{ order.name ?? '—' }}
      </p>

      <AdminInvoiceForm
        :order="order"
        mode="edit"
        :initial="invoice.inputs"
        :amounts-locked="invoice.amounts_locked"
        :submitting="saving"
        submit-label="Save changes"
        submitting-label="Saving…"
        @submit="saveInvoice" />
    </template>
  </div>
</template>
