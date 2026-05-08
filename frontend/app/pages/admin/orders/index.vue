<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })
useHead({ title: 'Orders — Admin' })

const { apiFetch } = useAdminAuth()

interface Order {
  id: number
  order_number: string
  quotation_id: number
  reference_code: string | null
  package_key: string | null
  name: string | null
  email: string | null
  value_min_myr: string
  value_max_myr: string
  status: string
  started_at: string | null
  delivered_at: string | null
  completed_at: string | null
  created_at: string
}

const orders = ref<Order[]>([])
const meta = ref<{ current_page: number; last_page: number; total: number } | null>(null)
const loading = ref(true)
const error = ref('')

const filters = reactive({
  search: '',
  status: '',
  page: 1,
})

const statusOptions = [
  { value: '', label: 'All' },
  { value: 'pending', label: 'Pending' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'delivered', label: 'Delivered' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
]

async function fetchOrders() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filters.search) params.set('search', filters.search)
    if (filters.status) params.set('status', filters.status)
    params.set('page', String(filters.page))

    const res = await apiFetch<{ data: Order[]; meta: any }>(`/api/v1/admin/orders?${params}`)
    orders.value = res.data
    meta.value = res.meta
  }
  catch {
    error.value = 'Failed to load orders. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchOrders)

let searchTimer: ReturnType<typeof setTimeout>
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filters.page = 1; fetchOrders() }, 400)
})

watch(() => [filters.status, filters.page], () => fetchOrders())

function fmtDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}

function fmtMyr(amount: string | number) {
  const n = Number(amount)
  if (n >= 1000) return `RM ${(n / 1000).toFixed(0)}k`
  return `RM ${n.toLocaleString()}`
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-10 pb-32">

    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Admin</p>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Orders</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Accepted quotations turned into active engagements.</p>
      </div>
      <div class="flex items-center gap-3">
        <span v-if="meta" class="text-[13px]" style="color: var(--color-text-secondary);">{{ meta.total }} total</span>
      </div>
    </div>

    <div class="flex flex-wrap gap-3 mb-6">
      <input v-model="filters.search" type="search" placeholder="Search by name, email, order or reference…"
        class="contact-input" style="max-width: 320px;"
        :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />

      <div class="flex flex-wrap gap-2">
        <button v-for="opt in statusOptions" :key="opt.value" type="button"
          class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
          :style="{
            borderColor: filters.status === opt.value ? 'var(--color-accent)' : 'var(--color-border)',
            background: filters.status === opt.value ? 'var(--color-accent-soft)' : 'transparent',
            color: filters.status === opt.value ? 'var(--color-accent)' : 'var(--color-text-secondary)',
          }"
          @click="filters.status = opt.value; filters.page = 1">
          {{ opt.label }}
        </button>
      </div>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading orders…</div>

    <div v-else-if="!orders.length" class="rounded-2xl border p-12 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <UIcon name="i-lucide-package" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No orders yet</p>
      <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
        Accept a quotation from the <NuxtLink to="/admin/quotations" class="underline" :style="{ color: 'var(--color-accent)' }">Quotations</NuxtLink> page to start tracking it here.
      </p>
    </div>

    <div v-else class="rounded-2xl border overflow-hidden"
      :style="{ borderColor: 'var(--color-border)' }">
      <table class="w-full text-left">
        <thead>
          <tr style="border-bottom: 1px solid var(--color-border); background: var(--color-bg-secondary);">
            <th v-for="h in ['Order', 'Client', 'Value', 'Status', 'Started', 'Created']" :key="h"
              class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
              {{ h }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="o in orders" :key="o.id"
            class="border-b cursor-pointer transition-colors hover:bg-(--color-bg-secondary)"
            style="border-color: var(--color-border);"
            @click="navigateTo(`/admin/orders/${o.id}`)">
            <td class="px-4 py-3.5">
              <p class="font-mono text-[12px] font-medium" :style="{ color: 'var(--color-accent)' }">{{ o.order_number }}</p>
              <p v-if="o.reference_code" class="font-mono text-[10px]" :style="{ color: 'var(--color-text-tertiary)' }">from {{ o.reference_code }}</p>
            </td>
            <td class="px-4 py-3.5">
              <p class="text-[13px] font-medium" :style="{ color: 'var(--color-text)' }">{{ o.name ?? '—' }}</p>
              <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ o.email ?? '' }}</p>
            </td>
            <td class="px-4 py-3.5">
              <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">
                {{ fmtMyr(o.value_min_myr) }} – {{ fmtMyr(o.value_max_myr) }}
              </p>
              <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ o.package_key ?? '—' }}</p>
            </td>
            <td class="px-4 py-3.5">
              <AdminStatusPill :status="o.status" />
            </td>
            <td class="px-4 py-3.5 text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
              {{ fmtDate(o.started_at) }}
            </td>
            <td class="px-4 py-3.5 text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
              {{ fmtDate(o.created_at) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
      <button :disabled="filters.page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page--">← Prev</button>
      <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ filters.page }} / {{ meta.last_page }}</span>
      <button :disabled="filters.page >= meta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page++">Next →</button>
    </div>
  </div>
</template>
