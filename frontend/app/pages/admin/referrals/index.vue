<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()

interface Referral {
  id: number
  referrer_name: string
  referrer_email: string
  business_name: string
  relationship_tier: 'cold' | 'warm' | 'closed'
  commission_tier_pct: number
  status: string
  created_at: string
}

const referrals = ref<Referral[]>([])
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
  { value: 'contacted', label: 'Contacted' },
  { value: 'qualified', label: 'Qualified' },
  { value: 'converted', label: 'Converted' },
  { value: 'rejected', label: 'Rejected' },
]

const tierLabels: Record<string, string> = { cold: 'Cold', warm: 'Warm', closed: 'Closed' }

async function fetchReferrals() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filters.search) params.set('search', filters.search)
    if (filters.status) params.set('status', filters.status)
    params.set('page', String(filters.page))

    const res = await apiFetch<{ data: Referral[]; meta: any }>(`/api/v1/admin/referrals?${params}`)
    referrals.value = res.data
    meta.value = res.meta
  }
  catch {
    error.value = 'Failed to load referrals. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchReferrals)

let searchTimer: ReturnType<typeof setTimeout>
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filters.page = 1; fetchReferrals() }, 400)
})

watch(() => filters.status, () => {
  if (filters.page !== 1) filters.page = 1
  else fetchReferrals()
})
watch(() => filters.page, () => fetchReferrals())

function fmtDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Referrals</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Partner referrals submitted from the <NuxtLink to="/partners" class="underline" :style="{ color: 'var(--color-accent)' }">Partner Program</NuxtLink>. Link a converted referral to its order to lock the commission tier.</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 mb-6">
      <AdminExpandingSearch v-model="filters.search" placeholder="Search by referrer, business, email…" />
      <AdminStatusFilter v-model="filters.status" :options="statusOptions" :total="meta?.total ?? null" class="ml-auto" />
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading referrals…</div>

    <div v-else-if="!referrals.length" class="text-center py-16" style="color: var(--color-text-secondary);">
      No referrals found.
    </div>

    <!-- Desktop: table -->
    <div v-else class="hidden md:block admin-table-card">
      <div class="overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr>
            <th v-for="h in ['Referrer', 'Business', 'Tier', 'Status', 'Submitted']" :key="h"
              class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
              {{ h }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in referrals" :key="r.id"
            class="admin-table-row"
            @click="navigateTo(`/admin/referrals/${r.id}`)">
            <td class="px-4 py-3.5">
              <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ r.referrer_name }}</p>
              <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ r.referrer_email }}</p>
            </td>
            <td class="px-4 py-3.5">
              <span class="text-[13px]" style="color: var(--color-text);">{{ r.business_name }}</span>
            </td>
            <td class="px-4 py-3.5">
              <span class="text-[12px] font-medium" style="color: var(--color-text-secondary);">{{ tierLabels[r.relationship_tier] }}</span>
              <span class="text-[12px] font-semibold tabular-nums ml-1.5" style="color: var(--color-accent);">{{ r.commission_tier_pct }}%</span>
            </td>
            <td class="px-4 py-3.5">
              <AdminStatusPill :status="r.status" />
            </td>
            <td class="px-4 py-3.5 text-[12px]" style="color: var(--color-text-secondary);">
              {{ fmtDate(r.created_at) }}
            </td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <!-- Mobile: cards -->
    <div v-if="referrals.length" class="md:hidden space-y-2.5">
      <button
        v-for="r in referrals"
        :key="r.id"
        type="button"
        class="w-full text-left rounded-xl border p-4 transition-colors hover:bg-(--color-bg-secondary)"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
        @click="navigateTo(`/admin/referrals/${r.id}`)"
      >
        <div class="flex items-start justify-between gap-3 mb-2">
          <span class="text-[13px] font-semibold leading-tight" :style="{ color: 'var(--color-text)' }">{{ r.referrer_name }}</span>
          <AdminStatusPill :status="r.status" />
        </div>
        <p class="text-[13px] leading-tight" :style="{ color: 'var(--color-text-secondary)' }">{{ r.business_name }}</p>
        <p class="text-[11px] mb-3" :style="{ color: 'var(--color-text-tertiary)' }">{{ r.referrer_email }}</p>
        <div class="pt-2 border-t flex items-center justify-between gap-3" :style="{ borderColor: 'var(--color-border)' }">
          <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">
            {{ tierLabels[r.relationship_tier] }} <span :style="{ color: 'var(--color-accent)' }">· {{ r.commission_tier_pct }}%</span>
          </p>
          <p class="text-[11px]" :style="{ color: 'var(--color-text-secondary)' }">{{ fmtDate(r.created_at) }}</p>
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
