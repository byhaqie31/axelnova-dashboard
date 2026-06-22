<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()

interface Inquiry {
  id: number
  name: string
  email: string
  company: string | null
  project_type: string | null
  budget_hint: string | null
  status: string
  created_at: string
}

const inquiries = ref<Inquiry[]>([])
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
  { value: 'new', label: 'New' },
  { value: 'reviewing', label: 'Reviewing' },
  { value: 'quoted', label: 'Quoted' },
  { value: 'archived', label: 'Archived' },
]

async function fetchInquiries() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filters.search) params.set('search', filters.search)
    if (filters.status) params.set('status', filters.status)
    params.set('page', String(filters.page))

    const res = await apiFetch<{ data: Inquiry[]; meta: any }>(`/api/v1/admin/inquiries?${params}`)
    inquiries.value = res.data
    meta.value = res.meta
  }
  catch {
    error.value = 'Failed to load inquiries. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchInquiries)

let searchTimer: ReturnType<typeof setTimeout>
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filters.page = 1; fetchInquiries() }, 400)
})

watch(() => filters.status, () => {
  if (filters.page !== 1) filters.page = 1
  else fetchInquiries()
})
watch(() => filters.page, () => fetchInquiries())

function fmtDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Inquiries</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Project inquiries from the public site. Open one and <span style="color: var(--color-text);">Build quotation</span> to price it.</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 mb-6">
      <AdminExpandingSearch v-model="filters.search" placeholder="Search by name, email, company…" />
      <div class="ml-auto flex items-center gap-2.5">
        <span v-if="meta" class="text-[13px] tabular-nums" style="color: var(--color-text-secondary);">{{ meta.total }} total</span>
        <span v-if="meta" aria-hidden="true" class="text-[13px] select-none" style="color: var(--color-text-tertiary);">|</span>
        <AdminStatusFilter v-model="filters.status" :options="statusOptions" />
      </div>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading inquiries…</div>

    <div v-else-if="!inquiries.length" class="text-center py-16" style="color: var(--color-text-secondary);">
      No inquiries found.
    </div>

    <!-- Desktop: table -->
    <div v-else class="hidden md:block admin-table-card">
      <div class="overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr>
            <th v-for="h in ['Name', 'Project type', 'Budget', 'Status', 'Submitted']" :key="h"
              class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
              {{ h }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="q in inquiries" :key="q.id"
            class="admin-table-row"
            @click="navigateTo(`/admin/inquiries/${q.id}`)">
            <td class="px-4 py-3.5">
              <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ q.name }}</p>
              <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ q.email }}</p>
            </td>
            <td class="px-4 py-3.5">
              <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ q.project_type ?? '—' }}</span>
            </td>
            <td class="px-4 py-3.5">
              <span class="text-[12px]" style="color: var(--color-text-secondary);">{{ q.budget_hint ?? '—' }}</span>
            </td>
            <td class="px-4 py-3.5">
              <AdminStatusPill :status="q.status" />
            </td>
            <td class="px-4 py-3.5 text-[12px]" style="color: var(--color-text-secondary);">
              {{ fmtDate(q.created_at) }}
            </td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <!-- Mobile: cards -->
    <div v-if="inquiries.length" class="md:hidden space-y-2.5">
      <button
        v-for="q in inquiries"
        :key="q.id"
        type="button"
        class="w-full text-left rounded-xl border p-4 transition-colors hover:bg-(--color-bg-secondary)"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
        @click="navigateTo(`/admin/inquiries/${q.id}`)"
      >
        <div class="flex items-start justify-between gap-3 mb-2">
          <span class="text-[13px] font-semibold leading-tight" :style="{ color: 'var(--color-text)' }">{{ q.name }}</span>
          <AdminStatusPill :status="q.status" />
        </div>
        <p class="text-[11px] mb-3" :style="{ color: 'var(--color-text-tertiary)' }">{{ q.email }}</p>
        <div class="pt-2 border-t flex items-center justify-between gap-3" :style="{ borderColor: 'var(--color-border)' }">
          <p class="text-[13px]" :style="{ color: 'var(--color-text-secondary)' }">{{ q.project_type ?? '—' }}</p>
          <p class="text-[11px]" :style="{ color: 'var(--color-text-secondary)' }">{{ fmtDate(q.created_at) }}</p>
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
