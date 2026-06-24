<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const { config, loadConfig } = usePricingEngine()
const route = useRoute()

// Resolve a package slug → its human name from the pricing config.
function packageName(key: string | null): string | null {
  if (!key || !config.value) return null
  for (const c of config.value.categories) {
    const p = c.packages.find(p => p.key === key)
    if (p) return p.name
  }
  return null
}

interface Quotation {
  id: number
  reference_code: string
  name: string
  email: string
  company: string | null
  package_key: string | null
  estimate_min_myr: string
  estimate_max_myr: string
  estimate_eta_value: number
  estimate_eta_unit: 'hour' | 'day' | 'week' | 'month'
  status: string
  submitted_at: string
}

const quotations = ref<Quotation[]>([])
const meta = ref<{ current_page: number; last_page: number; total: number } | null>(null)
const loading = ref(true)
const error = ref('')

const filters = reactive({
  search: '',
  status: typeof route.query.status === 'string' ? route.query.status : '',
  page: 1,
})

// 'accepted' is intentionally absent — accepted quotations live on the Orders page.
const statusOptions = [
  { value: '', label: 'Active' },
  { value: 'draft', label: 'Draft' },
  { value: 'sent', label: 'Sent' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'expired', label: 'Expired' },
]

async function fetchQuotations() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filters.search) params.set('search', filters.search)
    if (filters.status) params.set('status', filters.status)
    params.set('page', String(filters.page))

    const res = await apiFetch<{ data: Quotation[]; meta: any }>(`/api/v1/admin/quotations?${params}`)
    quotations.value = res.data
    meta.value = res.meta
  }
  catch {
    error.value = 'Failed to load quotations. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchQuotations()
  loadConfig()
})

let searchTimer: ReturnType<typeof setTimeout>
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filters.page = 1; fetchQuotations() }, 400)
})

watch(() => filters.status, () => {
  if (filters.page !== 1) filters.page = 1
  else fetchQuotations()
})
watch(() => filters.page, () => fetchQuotations())

function fmtDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}

function fmtMyr(amount: string | number) {
  const n = Number(amount)
  if (n >= 1000) return `RM ${(n / 1000).toFixed(0)}k`
  return `RM ${n.toLocaleString()}`
}

// New quotations always start standard; the builder upgrades to the detailed
// proposal layout in place via its "Expand to detailed" action.
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Quotations</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Drafts you're building plus quotes you've sent. Accepted quotations move to <NuxtLink to="/admin/orders" class="underline" :style="{ color: 'var(--color-accent)' }">Orders</NuxtLink>.</p>
      </div>
      <div class="flex items-center gap-3">
        <NuxtLink to="/admin/quotations/new" class="btn-pill btn-pill-accent text-[12px] inline-flex items-center gap-1.5">
          <UIcon name="i-lucide-plus" class="size-3.5" />
          New quotation
        </NuxtLink>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 mb-6">
      <AdminExpandingSearch v-model="filters.search" placeholder="Search by name, email, reference…" />
      <AdminStatusFilter v-model="filters.status" :options="statusOptions" :total="meta?.total ?? null" class="ml-auto" />
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading quotations…</div>

    <div v-else-if="!quotations.length" class="text-center py-16" style="color: var(--color-text-secondary);">
      No quotations found.
    </div>

    <div v-else class="hidden md:block admin-table-card">
      <div class="overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr>
            <th v-for="h in ['Reference', 'Name', 'Package', 'Estimate', 'Status', 'Submitted']" :key="h"
              class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
              {{ h }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="q in quotations" :key="q.id"
            class="admin-table-row"
            @click="navigateTo(`/admin/quotations/${q.id}`)">
            <td class="px-4 py-3.5">
              <span class="font-mono text-[12px] font-medium" style="color: var(--color-accent);">{{ q.reference_code }}</span>
            </td>
            <td class="px-4 py-3.5">
              <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ q.name }}</p>
              <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ q.email }}</p>
            </td>
            <td class="px-4 py-3.5">
              <template v-if="packageName(q.package_key)">
                <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ packageName(q.package_key) }}</p>
                <p class="text-[11px] font-mono" style="color: var(--color-text-tertiary);">{{ q.package_key }}</p>
              </template>
              <span v-else class="text-[12px] font-mono" style="color: var(--color-text-secondary);">{{ q.package_key ?? '—' }}</span>
            </td>
            <td class="px-4 py-3.5">
              <p class="text-[13px] font-semibold" style="color: var(--color-text);">
                {{ fmtMyr(q.estimate_min_myr) }} – {{ fmtMyr(q.estimate_max_myr) }}
              </p>
            </td>
            <td class="px-4 py-3.5">
              <AdminStatusPill :status="q.status" />
            </td>
            <td class="px-4 py-3.5 text-[12px]" style="color: var(--color-text-secondary);">
              {{ fmtDate(q.submitted_at) }}
            </td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <!-- Mobile: cards -->
    <div v-if="quotations.length" class="md:hidden space-y-2.5">
      <button
        v-for="q in quotations"
        :key="q.id"
        type="button"
        class="w-full text-left rounded-xl border p-4 transition-colors hover:bg-(--color-bg-secondary)"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
        @click="navigateTo(`/admin/quotations/${q.id}`)"
      >
        <div class="flex items-start justify-between gap-3 mb-2">
          <span class="font-mono text-[12px] font-medium" :style="{ color: 'var(--color-accent)' }">{{ q.reference_code }}</span>
          <AdminStatusPill :status="q.status" />
        </div>
        <p class="text-[13px] font-medium leading-tight" :style="{ color: 'var(--color-text)' }">{{ q.name }}</p>
        <p class="text-[11px] mb-3" :style="{ color: 'var(--color-text-tertiary)' }">{{ q.email }}</p>
        <div class="pt-2 border-t space-y-1" :style="{ borderColor: 'var(--color-border)' }">
          <div class="flex items-center justify-between gap-3">
            <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">
              {{ fmtMyr(q.estimate_min_myr) }} – {{ fmtMyr(q.estimate_max_myr) }}
            </p>
            <p class="text-[11px] text-right" :style="{ color: 'var(--color-text-tertiary)' }">
              <span v-if="packageName(q.package_key)" class="block font-medium" :style="{ color: 'var(--color-text-secondary)' }">{{ packageName(q.package_key) }}</span>
              <span class="font-mono">{{ q.package_key ?? '—' }}</span>
            </p>
          </div>
          <p class="text-[11px]" :style="{ color: 'var(--color-text-secondary)' }">Submitted {{ fmtDate(q.submitted_at) }}</p>
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
