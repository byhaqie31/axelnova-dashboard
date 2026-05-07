<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })
useHead({ title: 'Leads — Admin' })

const { apiFetch } = useAdminAuth()

interface Lead {
  id: number
  reference_code: string
  name: string
  email: string
  company: string | null
  package_key: string | null
  estimate_min_myr: string
  estimate_max_myr: string
  estimate_weeks: number
  status: string
  submitted_at: string
}

const leads = ref<Lead[]>([])
const meta = ref<{ current_page: number; last_page: number; total: number } | null>(null)
const loading = ref(true)
const error = ref('')

const filters = reactive({
  search: '',
  status: '',
  page: 1,
})

const statusOptions = [
  { value: '', label: 'All statuses' },
  { value: 'new', label: 'New' },
  { value: 'viewed', label: 'Viewed' },
  { value: 'contacted', label: 'Contacted' },
  { value: 'converted', label: 'Converted' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'spam', label: 'Spam' },
]

const statusColors: Record<string, string> = {
  new: 'var(--color-accent)',
  viewed: '#A855F7',
  contacted: 'var(--color-success)',
  converted: '#22c55e',
  rejected: 'var(--color-danger)',
  spam: 'var(--color-text-tertiary)',
}

async function fetchLeads() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filters.search) params.set('search', filters.search)
    if (filters.status) params.set('status', filters.status)
    params.set('page', String(filters.page))

    const res = await apiFetch<{ data: Lead[]; meta: any }>(`/api/v1/admin/leads?${params}`)
    leads.value = res.data
    meta.value = res.meta
  }
  catch {
    error.value = 'Failed to load leads. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchLeads)

let searchTimer: ReturnType<typeof setTimeout>
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filters.page = 1; fetchLeads() }, 400)
})

watch(() => [filters.status, filters.page], () => fetchLeads())

function fmtDate(iso: string) {
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

    <!-- Header -->
    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Admin</p>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Orders</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Quote requests submitted from the public site.</p>
      </div>
      <div class="flex items-center gap-3">
        <span v-if="meta" class="text-[13px]" style="color: var(--color-text-secondary);">{{ meta.total }} total</span>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-6">
      <input v-model="filters.search" type="search" placeholder="Search by name, email, reference…"
        class="contact-input" style="max-width: 300px;"
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

    <!-- Error -->
    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading leads…</div>

    <!-- Empty -->
    <div v-else-if="!leads.length" class="text-center py-16" style="color: var(--color-text-secondary);">
      No leads found.
    </div>

    <!-- Table -->
    <div v-else class="rounded-2xl border overflow-hidden"
      :style="{ borderColor: 'var(--color-border)' }">
      <table class="w-full text-left">
        <thead>
          <tr style="border-bottom: 1px solid var(--color-border); background: var(--color-bg-secondary);">
            <th v-for="h in ['Reference', 'Name', 'Package', 'Estimate', 'Status', 'Submitted']" :key="h"
              class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
              {{ h }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="lead in leads" :key="lead.id"
            class="border-b cursor-pointer transition-colors hover:bg-(--color-bg-secondary)"
            style="border-color: var(--color-border);"
            @click="navigateTo(`/admin/leads/${lead.id}`)">
            <td class="px-4 py-3.5">
              <span class="font-mono text-[12px] font-medium" style="color: var(--color-accent);">{{ lead.reference_code }}</span>
            </td>
            <td class="px-4 py-3.5">
              <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ lead.name }}</p>
              <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ lead.email }}</p>
            </td>
            <td class="px-4 py-3.5">
              <span class="text-[12px] font-mono" style="color: var(--color-text-secondary);">{{ lead.package_key ?? '—' }}</span>
            </td>
            <td class="px-4 py-3.5">
              <p class="text-[13px] font-semibold" style="color: var(--color-text);">
                {{ fmtMyr(lead.estimate_min_myr) }} – {{ fmtMyr(lead.estimate_max_myr) }}
              </p>
            </td>
            <td class="px-4 py-3.5">
              <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full"
                :style="{
                  color: statusColors[lead.status] ?? 'var(--color-text-secondary)',
                  background: `${statusColors[lead.status] ?? 'var(--color-text-secondary)'}20`,
                }">
                {{ lead.status }}
              </span>
            </td>
            <td class="px-4 py-3.5 text-[12px]" style="color: var(--color-text-secondary);">
              {{ fmtDate(lead.submitted_at) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
      <button :disabled="filters.page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page--">← Prev</button>
      <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ filters.page }} / {{ meta.last_page }}</span>
      <button :disabled="filters.page >= meta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page++">Next →</button>
    </div>
  </div>
</template>
