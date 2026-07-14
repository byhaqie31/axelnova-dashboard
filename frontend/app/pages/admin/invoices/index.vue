<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const route = useRoute()

interface Invoice {
  id: number
  invoice_number: string
  order_id: number
  order_number: string | null
  quotation_id: number | null
  reference_code: string | null
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
}

const invoices = ref<Invoice[]>([])
const meta = ref<{ current_page: number; last_page: number; total: number } | null>(null)
const loading = ref(true)
const error = ref('')

const filters = reactive({
  search: '',
  status: '',
  type: '',
  order_id: route.query.order_id ? String(route.query.order_id) : '',
  page: 1,
})

function clearOrderFilter() {
  filters.order_id = ''
  filters.page = 1
  fetchInvoices()
}

const activeFilterCount = computed(() => (filters.type ? 1 : 0))
function clearSecondary() {
  filters.type = ''
}

const statusOptions = [
  { value: '', label: 'All' },
  { value: 'issued', label: 'Issued' },
  { value: 'paid', label: 'Paid' },
  { value: 'void', label: 'Void' },
  { value: 'overdue', label: 'Overdue' },
]

const typeOptions = [
  { value: '', label: 'All types' },
  { value: 'deposit', label: 'Deposit' },
  { value: 'partial', label: 'Partial' },
  { value: 'final', label: 'Final' },
]

async function fetchInvoices() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filters.search) params.set('search', filters.search)
    if (filters.status) params.set('status', filters.status)
    if (filters.type) params.set('type', filters.type)
    if (filters.order_id) params.set('order_id', filters.order_id)
    params.set('page', String(filters.page))

    const res = await apiFetch<{ data: Invoice[]; meta: any }>(`/api/v1/admin/invoices?${params}`)
    invoices.value = res.data
    meta.value = res.meta
  }
  catch {
    error.value = 'Failed to load invoices. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchInvoices)

let searchTimer: ReturnType<typeof setTimeout>
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filters.page = 1; fetchInvoices() }, 400)
})
watch(() => filters.status, () => {
  if (filters.page !== 1) filters.page = 1
  else fetchInvoices()
})
watch(() => filters.type, () => {
  if (filters.page !== 1) filters.page = 1
  else fetchInvoices()
})
watch(() => filters.page, () => fetchInvoices())

function fmtDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}

// Full amount, two decimals — accurate, transaction-style (e.g. RM 12,000.00).
function fmtMyr(amount: string | number) {
  return `RM ${Number(amount).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Invoices</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Every deposit, partial and final bill across all orders.</p>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-6">
      <AdminExpandingSearch v-model="filters.search" placeholder="Search by invoice #, client, email or order…" />
      <AdminFilterMenu :active-count="activeFilterCount" @clear="clearSecondary">
        <AdminFilterPills v-model="filters.type" label="Type" :options="typeOptions" />
      </AdminFilterMenu>
      <AdminStatusFilter v-model="filters.status" :options="statusOptions" :total="meta?.total ?? null" class="ml-auto" />
    </div>

    <div
v-if="filters.order_id" class="flex items-center justify-between gap-3 mb-5 rounded-xl border px-4 py-2.5"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
      <p class="text-[12px]" style="color: var(--color-text-secondary);">Filtered to one order.</p>
      <div class="flex items-center gap-2">
        <NuxtLink :to="`/admin/invoices/new?order_id=${filters.order_id}`" class="btn-pill btn-pill-primary text-[12px]" style="height: 30px; padding: 0 14px;">Issue invoice</NuxtLink>
        <button type="button" class="btn-pill btn-pill-ghost text-[12px]" style="height: 30px; padding: 0 14px;" @click="clearOrderFilter">Clear</button>
      </div>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading invoices…</div>

    <div
v-else-if="!invoices.length" class="rounded-2xl border p-12 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <UIcon name="i-lucide-receipt-text" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No invoices yet</p>
      <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
        Issue one from an <NuxtLink to="/admin/orders" class="underline" :style="{ color: 'var(--color-accent)' }">order</NuxtLink>'s detail page.
      </p>
    </div>

    <div v-else class="hidden md:block admin-table-card">
      <div class="overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr>
            <th
v-for="h in ['Invoice', 'Client', 'Type', 'Total', 'Status', 'Due', 'Issued']" :key="h"
              class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
              {{ h }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
v-for="i in invoices" :key="i.id"
            class="admin-table-row"
            @click="navigateTo(`/admin/invoices/${i.id}`)">
            <td class="px-4 py-3.5">
              <p class="font-mono text-[12px] font-medium" :style="{ color: 'var(--color-accent)' }">{{ i.invoice_number }}</p>
            </td>
            <td class="px-4 py-3.5">
              <p class="text-[13px] font-medium" :style="{ color: 'var(--color-text)' }">{{ i.name ?? '—' }}</p>
              <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ i.email ?? '' }}</p>
            </td>
            <td class="px-4 py-3.5">
              <span
class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
                :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }">{{ i.type }}</span>
            </td>
            <td class="px-4 py-3.5">
              <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">{{ fmtMyr(i.amount_total) }}</p>
              <p v-if="i.amount_paid && Number(i.amount_paid) > 0" class="text-[11px]" :style="{ color: 'var(--color-success)' }">
                paid {{ fmtMyr(i.amount_paid) }}
              </p>
            </td>
            <td class="px-4 py-3.5">
              <AdminStatusPill :status="i.status" />
            </td>
            <td class="px-4 py-3.5">
              <p class="text-[12px]" :style="{ color: i.is_overdue ? 'var(--color-danger)' : 'var(--color-text-secondary)' }">
                {{ fmtDate(i.due_at) }}
              </p>
              <p v-if="i.is_overdue" class="text-[10px] font-semibold" :style="{ color: 'var(--color-danger)' }">Overdue</p>
            </td>
            <td class="px-4 py-3.5 text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
              {{ fmtDate(i.issued_at) }}
            </td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <!-- Mobile: cards -->
    <div v-if="invoices.length" class="md:hidden space-y-2.5">
      <button
        v-for="i in invoices"
        :key="i.id"
        type="button"
        class="w-full text-left rounded-xl border p-4 transition-colors hover:bg-(--color-bg-secondary)"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
        @click="navigateTo(`/admin/invoices/${i.id}`)"
      >
        <div class="flex items-start justify-between gap-3 mb-2">
          <div class="min-w-0">
            <p class="font-mono text-[12px] font-medium" :style="{ color: 'var(--color-accent)' }">{{ i.invoice_number }}</p>
          </div>
          <AdminStatusPill :status="i.status" />
        </div>
        <div class="flex items-center gap-2 mb-1">
          <span
class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
            :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }">{{ i.type }}</span>
          <p class="text-[13px] font-medium leading-tight truncate" :style="{ color: 'var(--color-text)' }">{{ i.name ?? '—' }}</p>
        </div>
        <p class="text-[11px] mb-3" :style="{ color: 'var(--color-text-tertiary)' }">{{ i.email ?? '' }}</p>
        <div class="pt-2 border-t space-y-1" :style="{ borderColor: 'var(--color-border)' }">
          <div class="flex items-center justify-between gap-3">
            <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">{{ fmtMyr(i.amount_total) }}</p>
            <p v-if="i.amount_paid && Number(i.amount_paid) > 0" class="text-[11px]" :style="{ color: 'var(--color-success)' }">
              paid {{ fmtMyr(i.amount_paid) }}
            </p>
          </div>
          <div class="flex items-center justify-between gap-3 text-[11px]" :style="{ color: 'var(--color-text-secondary)' }">
            <span :style="{ color: i.is_overdue ? 'var(--color-danger)' : 'var(--color-text-secondary)' }">
              Due {{ fmtDate(i.due_at) }}<span v-if="i.is_overdue" class="font-semibold"> · Overdue</span>
            </span>
            <span>Issued {{ fmtDate(i.issued_at) }}</span>
          </div>
        </div>
      </button>
    </div>

    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
      <button :disabled="filters.page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page--">← Prev</button>
      <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ filters.page }} / {{ meta.last_page }}</span>
      <button :disabled="filters.page >= meta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page++">Next →</button>
    </div>
  </div>
</template>
