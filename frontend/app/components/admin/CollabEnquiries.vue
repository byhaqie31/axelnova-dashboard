<script setup lang="ts">
// Leads from /collaborations, listed under Partners › Collaborations — the
// Applications tab (origin collaboration: studios applying to partner) and a
// partner's Enquiries section (origin partner: clients hiring that studio
// through us). Same inquiries table as Sales › Inquiries, split by origin;
// rows open AdminInquiryDetail at /admin/collaborations/enquiries/[id].
const props = defineProps<{
  origin: 'partner' | 'collaboration'
  partnerId?: number
  emptyText: string
}>()

const { apiFetch } = useAdminAuth()

interface Enquiry {
  id: number
  name: string
  email: string
  company: string | null
  project_type: string | null
  subject: string | null
  status: string
  created_at: string
}

const rows = ref<Enquiry[]>([])
const meta = ref<{ last_page: number, total: number } | null>(null)
// Starts true — the fetch only runs in onMounted, so false would flash the empty state.
const loading = ref(true)
const error = ref('')
const filters = reactive({ status: '', page: 1 })

// 'quoted' only happens once a quotation is linked — partner enquiries can get there.
const statusOptions = computed(() => [
  { value: '', label: 'All' },
  { value: 'new', label: 'New' },
  { value: 'reviewing', label: 'Reviewing' },
  ...(props.origin === 'partner' ? [{ value: 'quoted', label: 'Quoted' }] : []),
  { value: 'archived', label: 'Archived' },
])

// An application says what the studio is ("Describes themselves as"); a client
// enquiry carries a project type.
function topic(row: Enquiry): string {
  return (props.origin === 'collaboration' ? row.subject : row.project_type) ?? '—'
}

function fmtDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}

function open(row: Enquiry) {
  navigateTo(`/admin/collaborations/enquiries/${row.id}`)
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams({ origin: props.origin, page: String(filters.page) })
    if (props.partnerId) params.set('collaboration_partner_id', String(props.partnerId))
    if (filters.status) params.set('status', filters.status)
    const res = await apiFetch<{ data: Enquiry[], meta: { last_page: number, total: number } }>(`/api/v1/admin/inquiries?${params}`)
    rows.value = res.data
    meta.value = res.meta
  }
  catch {
    error.value = 'Couldn’t load these enquiries.'
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => filters.status, () => {
  if (filters.page !== 1) filters.page = 1
  else load()
})
watch(() => filters.page, load)
</script>

<template>
  <div>
    <div class="flex items-center gap-3 mb-4">
      <AdminStatusFilter v-model="filters.status" :options="statusOptions" :total="meta?.total ?? null" class="ml-auto" />
    </div>

    <p v-if="error" class="mb-4 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-12 text-[13px]" style="color: var(--color-text-secondary);">Loading…</div>

    <div v-else-if="!rows.length" class="text-center py-12 text-[13px]" style="color: var(--color-text-secondary);">
      {{ filters.status ? 'Nothing with this status.' : emptyText }}
    </div>

    <template v-else>
      <!-- Desktop: table -->
      <div class="hidden md:block admin-table-card">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr>
                <th
                  v-for="h in ['Name', 'Company', origin === 'collaboration' ? 'Describes themselves as' : 'Project', 'Status', 'Submitted']" :key="h"
                  class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
                  {{ h }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id" class="admin-table-row" @click="open(row)">
                <td class="px-4 py-3.5">
                  <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ row.name }}</p>
                  <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ row.email }}</p>
                </td>
                <td class="px-4 py-3.5 text-[13px]" style="color: var(--color-text-secondary);">{{ row.company ?? '—' }}</td>
                <td class="px-4 py-3.5 text-[13px]" style="color: var(--color-text-secondary);">{{ topic(row) }}</td>
                <td class="px-4 py-3.5"><AdminStatusPill :status="row.status" /></td>
                <td class="px-4 py-3.5 text-[12px]" style="color: var(--color-text-secondary);">{{ fmtDate(row.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Mobile: cards -->
      <div class="md:hidden space-y-2.5">
        <button
          v-for="row in rows" :key="row.id" type="button"
          class="w-full text-left rounded-xl border p-4 transition-colors hover:bg-(--color-bg-secondary) active:bg-(--color-bg-secondary)"
          :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
          @click="open(row)">
          <div class="flex items-start justify-between gap-3 mb-2">
            <span class="text-[13px] font-semibold leading-tight min-w-0 break-words" style="color: var(--color-text);">{{ row.name }}</span>
            <AdminStatusPill :status="row.status" class="shrink-0" />
          </div>
          <p class="text-[11px] mb-3 truncate" style="color: var(--color-text-tertiary);">{{ row.company ? `${row.company} · ` : '' }}{{ row.email }}</p>
          <div class="pt-2 border-t flex items-center justify-between gap-3" style="border-color: var(--color-border);">
            <p class="text-[13px] min-w-0 truncate" style="color: var(--color-text-secondary);">{{ topic(row) }}</p>
            <p class="text-[11px] shrink-0" style="color: var(--color-text-secondary);">{{ fmtDate(row.created_at) }}</p>
          </div>
        </button>
      </div>

      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
        <button :disabled="filters.page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page--">← Prev</button>
        <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ filters.page }} / {{ meta.last_page }}</span>
        <button :disabled="filters.page >= meta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page++">Next →</button>
      </div>
    </template>
  </div>
</template>
