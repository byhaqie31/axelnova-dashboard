<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const route = useRoute()

interface Payment {
  id: number
  payment_number: string
  type: 'payment' | 'refund'
  gateway: string
  method: string
  status: string
  amount_myr: string
  paid_at: string | null
  order_id: number
  order_number: string | null
  invoice_number: string | null
  name: string | null
  email: string | null
}

const payments = ref<Payment[]>([])
const meta = ref<{ current_page: number; last_page: number; total: number } | null>(null)
const loading = ref(true)
const error = ref('')

const filters = reactive({
  search: '',
  status: '',
  type: '',
  method: '',
  gateway: '',
  order_id: route.query.order_id ? String(route.query.order_id) : '',
  page: 1,
})

function clearOrderFilter() {
  filters.order_id = ''
  filters.page = 1
  fetchPayments()
}

const activeFilterCount = computed(() => [filters.type, filters.method, filters.gateway].filter(Boolean).length)
function clearSecondary() {
  filters.type = ''
  filters.method = ''
  filters.gateway = ''
}

const statusOptions = [
  { value: '', label: 'All' },
  { value: 'succeeded', label: 'Succeeded' },
  { value: 'pending', label: 'Pending' },
  { value: 'failed', label: 'Failed' },
  { value: 'refunded', label: 'Refunded' },
  { value: 'cancelled', label: 'Cancelled' },
]
const typeOptions = [
  { value: '', label: 'All' },
  { value: 'payment', label: 'Payments' },
  { value: 'refund', label: 'Refunds' },
]
const methodOptions = [
  { value: '', label: 'All methods' },
  { value: 'card', label: 'Card' },
  { value: 'fpx', label: 'FPX' },
  { value: 'duitnow', label: 'DuitNow' },
  { value: 'bank_transfer', label: 'Bank transfer' },
  { value: 'cash', label: 'Cash' },
  { value: 'ewallet', label: 'E-wallet' },
  { value: 'other', label: 'Other' },
]
const gatewayOptions = [
  { value: '', label: 'All gateways' },
  { value: 'manual', label: 'Manual' },
  { value: 'stripe', label: 'Stripe' },
  { value: 'billplz', label: 'Billplz' },
]

const methodLabels: Record<string, string> = Object.fromEntries(methodOptions.map(o => [o.value, o.label]))

async function fetchPayments() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filters.search) params.set('search', filters.search)
    if (filters.status) params.set('status', filters.status)
    if (filters.type) params.set('type', filters.type)
    if (filters.method) params.set('method', filters.method)
    if (filters.gateway) params.set('gateway', filters.gateway)
    if (filters.order_id) params.set('order_id', filters.order_id)
    params.set('page', String(filters.page))

    const res = await apiFetch<{ data: Payment[]; meta: any }>(`/api/v1/admin/payments?${params}`)
    payments.value = res.data
    meta.value = res.meta
  }
  catch {
    error.value = 'Failed to load payments. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchPayments)

let searchTimer: ReturnType<typeof setTimeout>
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filters.page = 1; fetchPayments() }, 400)
})
watch([() => filters.status, () => filters.type, () => filters.method, () => filters.gateway], () => {
  if (filters.page !== 1) filters.page = 1
  else fetchPayments()
})
watch(() => filters.page, () => fetchPayments())

function fmtDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
// Signed amount: refunds render as −RM x in the danger tone, never a new colour.
function fmtMyr(amount: string | number) {
  return `RM ${Math.abs(Number(amount)).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}
function methodLabel(v: string) {
  return methodLabels[v] ?? v
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Payments</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">The money ledger — every payment and refund across all orders.</p>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-6">
      <AdminExpandingSearch v-model="filters.search" placeholder="Search by payment #, ref, order or client…" />
      <AdminFilterMenu :active-count="activeFilterCount" @clear="clearSecondary">
        <AdminFilterPills v-model="filters.type" label="Type" :options="typeOptions" />
        <AdminFilterPills v-model="filters.method" label="Method" :options="methodOptions" />
        <AdminFilterPills v-model="filters.gateway" label="Gateway" :options="gatewayOptions" />
      </AdminFilterMenu>
      <AdminStatusFilter v-model="filters.status" :options="statusOptions" :total="meta?.total ?? null" class="ml-auto" />
    </div>

    <div v-if="filters.order_id" class="flex items-center justify-between gap-3 mb-5 rounded-xl border px-4 py-2.5"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
      <p class="text-[12px]" style="color: var(--color-text-secondary);">Filtered to one order.</p>
      <div class="flex items-center gap-2">
        <NuxtLink :to="`/admin/payments/new?order_id=${filters.order_id}`" class="btn-pill btn-pill-primary text-[12px]" style="height: 30px; padding: 0 14px;">Record payment</NuxtLink>
        <button type="button" class="btn-pill btn-pill-ghost text-[12px]" style="height: 30px; padding: 0 14px;" @click="clearOrderFilter">Clear</button>
      </div>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading payments…</div>

    <div v-else-if="!payments.length" class="rounded-2xl border p-12 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <UIcon name="i-lucide-wallet" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No payments yet</p>
      <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
        Record one from an <NuxtLink to="/admin/orders" class="underline" :style="{ color: 'var(--color-accent)' }">order</NuxtLink>'s detail page.
      </p>
    </div>

    <div v-else class="hidden md:block admin-table-card">
      <div class="overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr>
            <th v-for="h in ['Payment', 'Client', 'Order', 'Amount', 'Method', 'Status', 'Date']" :key="h"
              class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
              {{ h }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in payments" :key="p.id"
            class="admin-table-row"
            @click="navigateTo(`/admin/payments/${p.id}`)">
            <td class="px-4 py-3.5">
              <p class="font-mono text-[12px] font-medium" :style="{ color: 'var(--color-accent)' }">{{ p.payment_number }}</p>
              <p v-if="p.type === 'refund'" class="text-[10px] font-semibold uppercase tracking-wider" :style="{ color: 'var(--color-danger)' }">Refund</p>
            </td>
            <td class="px-4 py-3.5">
              <p class="text-[13px] font-medium" :style="{ color: 'var(--color-text)' }">{{ p.name ?? '—' }}</p>
              <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ p.email ?? '' }}</p>
            </td>
            <td class="px-4 py-3.5">
              <span class="font-mono text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">{{ p.order_number ?? '—' }}</span>
            </td>
            <td class="px-4 py-3.5">
              <p class="text-[13px] font-semibold"
                :style="{ color: Number(p.amount_myr) < 0 ? 'var(--color-danger)' : 'var(--color-text)' }">
                {{ Number(p.amount_myr) < 0 ? '−' : '' }}{{ fmtMyr(p.amount_myr) }}
              </p>
            </td>
            <td class="px-4 py-3.5">
              <p class="text-[13px]" :style="{ color: 'var(--color-text)' }">{{ methodLabel(p.method) }}</p>
              <p class="text-[11px] capitalize" :style="{ color: 'var(--color-text-tertiary)' }">{{ p.gateway }}</p>
            </td>
            <td class="px-4 py-3.5">
              <AdminStatusPill :status="p.status" />
            </td>
            <td class="px-4 py-3.5 text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
              {{ fmtDate(p.paid_at) }}
            </td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <!-- Mobile: cards -->
    <div v-if="payments.length" class="md:hidden space-y-2.5">
      <button
        v-for="p in payments"
        :key="p.id"
        type="button"
        class="w-full text-left rounded-xl border p-4 transition-colors hover:bg-(--color-bg-secondary)"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
        @click="navigateTo(`/admin/payments/${p.id}`)"
      >
        <div class="flex items-start justify-between gap-3 mb-2">
          <div class="min-w-0">
            <p class="font-mono text-[12px] font-medium" :style="{ color: 'var(--color-accent)' }">{{ p.payment_number }}</p>
            <p v-if="p.order_number" class="font-mono text-[10px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ p.order_number }}</p>
          </div>
          <AdminStatusPill :status="p.status" />
        </div>
        <p class="text-[13px] font-medium leading-tight" :style="{ color: 'var(--color-text)' }">{{ p.name ?? '—' }}</p>
        <div class="flex items-center justify-between gap-3 pt-2 mt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
          <p class="text-[14px] font-semibold"
            :style="{ color: Number(p.amount_myr) < 0 ? 'var(--color-danger)' : 'var(--color-text)' }">
            {{ Number(p.amount_myr) < 0 ? '−' : '' }}{{ fmtMyr(p.amount_myr) }}
          </p>
          <span class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ methodLabel(p.method) }} · {{ fmtDate(p.paid_at) }}</span>
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
