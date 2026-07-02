<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()

interface Partner {
  id: number
  code: string
  name: string
  email: string
  phone: string | null
  relationship_tier: 'cold' | 'warm' | 'closed'
  commission_pct: number
  status: 'pending' | 'active' | 'paused'
  agreed_terms: boolean
  has_passcode: boolean
  referrals_count: number
  last_login_at: string | null
  created_at: string
}

const partners = ref<Partner[]>([])
const meta = ref<{ current_page: number, last_page: number, total: number } | null>(null)
const loading = ref(true)
const error = ref('')
const flash = ref('')

const filters = reactive({ search: '', status: '', page: 1 })

const statusOptions = [
  { value: '', label: 'All' },
  { value: 'pending', label: 'Pending' },
  { value: 'active', label: 'Active' },
  { value: 'paused', label: 'Paused' },
]

const tierLabels: Record<string, string> = { cold: 'Cold', warm: 'Warm', closed: 'Closed' }

type PillStyle = { label: string, color: string, bg: string }
const PILL_PENDING: PillStyle = { label: 'Pending', color: 'var(--color-warning, var(--color-accent))', bg: 'var(--color-warning-soft, var(--color-accent-soft))' }
const statusStyle: Record<string, PillStyle> = {
  pending: PILL_PENDING,
  active: { label: 'Active', color: 'var(--color-success)', bg: 'var(--color-success-soft, var(--color-bg-secondary))' },
  paused: { label: 'Paused', color: 'var(--color-text-tertiary)', bg: 'var(--color-bg-secondary)' },
}
const pill = (s: string): PillStyle => statusStyle[s] ?? PILL_PENDING

async function fetchPartners() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filters.search) params.set('search', filters.search)
    if (filters.status) params.set('status', filters.status)
    params.set('page', String(filters.page))

    const res = await apiFetch<{ data: Partner[], meta: any }>(`/api/v1/admin/referral-partners?${params}`)
    partners.value = res.data
    meta.value = res.meta
  }
  catch {
    error.value = 'Failed to load referral partners. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchPartners)

let searchTimer: ReturnType<typeof setTimeout>
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filters.page = 1; fetchPartners() }, 400)
})
watch(() => filters.status, () => {
  if (filters.page !== 1) filters.page = 1
  else fetchPartners()
})
watch(() => filters.page, () => fetchPartners())

function fmtDate(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
}

// Confirm-before-act. Approve issues the first passcode; reset invalidates the old
// one — both email the partner, so both are confirmed. The passcode is never shown.
type PendingAction = { partner: Partner, type: 'approve' | 'reset' }
const pending = ref<PendingAction | null>(null)
const acting = ref(false)

function ask(partner: Partner, type: 'approve' | 'reset') {
  flash.value = ''
  pending.value = { partner, type }
}

const confirmCopy = computed(() => {
  if (!pending.value) return { title: '', body: '', cta: '' }
  const { partner, type } = pending.value
  return type === 'approve'
    ? {
        title: `Approve ${partner.name}?`,
        body: `This activates their account and emails a one-time passcode to ${partner.email}. The passcode is never shown here.`,
        cta: 'Approve & email passcode',
      }
    : {
        title: `Reset passcode for ${partner.name}?`,
        body: `This invalidates their current passcode and emails a new one to ${partner.email}. They can't log in with the old passcode afterwards.`,
        cta: 'Reset & email new passcode',
      }
})

async function confirmAction() {
  if (!pending.value || acting.value) return
  acting.value = true
  const { partner, type } = pending.value
  const path = type === 'approve'
    ? `/api/v1/admin/referral-partners/${partner.id}/approve`
    : `/api/v1/admin/referral-partners/${partner.id}/reset-passcode`
  try {
    const res = await apiFetch<{ message: string }>(path, { method: 'POST' })
    flash.value = res.message
    pending.value = null
    await fetchPartners()
  }
  catch (e: any) {
    error.value = e?.data?.message ?? 'Action failed. Please try again.'
    pending.value = null
  }
  finally {
    acting.value = false
  }
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Referral Partners</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
          Approve new referrers and manage passcodes. Approving emails a one-time passcode — it's never shown here.
        </p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 mb-6">
      <AdminExpandingSearch v-model="filters.search" placeholder="Search by name, email, code…" />
      <AdminStatusFilter v-model="filters.status" :options="statusOptions" :total="meta?.total ?? null" class="ml-auto" />
    </div>

    <p v-if="flash" class="mb-6 text-[13px] flex items-center gap-1.5" style="color: var(--color-success);">
      <UIcon name="i-lucide-check-circle" class="size-4 shrink-0" />
      {{ flash }}
    </p>
    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading partners…</div>

    <div v-else-if="!partners.length" class="text-center py-16" style="color: var(--color-text-secondary);">
      No referral partners found.
    </div>

    <!-- Desktop: table -->
    <div v-else class="hidden md:block admin-table-card">
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr>
              <th v-for="h in ['Partner', 'Code', 'Tier', 'Referrals', 'Status', 'Last login', 'Actions']" :key="h"
                class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
                {{ h }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in partners" :key="p.id" class="admin-table-row" @click="navigateTo(`/admin/referral-partners/${p.id}`)">
              <td class="px-4 py-3.5">
                <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ p.name }}</p>
                <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ p.email }}</p>
              </td>
              <td class="px-4 py-3.5">
                <span class="text-[12px] font-mono px-1.5 py-0.5 rounded" :style="{ background: 'var(--color-bg-secondary)', color: 'var(--color-text-secondary)' }">{{ p.code }}</span>
              </td>
              <td class="px-4 py-3.5">
                <span class="text-[12px] font-medium" style="color: var(--color-text-secondary);">{{ tierLabels[p.relationship_tier] }}</span>
                <span class="text-[12px] font-semibold tabular-nums ml-1.5" style="color: var(--color-accent);">{{ p.commission_pct }}%</span>
              </td>
              <td class="px-4 py-3.5 text-[13px] tabular-nums" style="color: var(--color-text);">{{ p.referrals_count }}</td>
              <td class="px-4 py-3.5">
                <span class="text-[11px] font-medium px-2.5 py-1 rounded-full" :style="{ color: pill(p.status).color, background: pill(p.status).bg }">
                  {{ pill(p.status).label }}
                </span>
              </td>
              <td class="px-4 py-3.5 text-[12px]" style="color: var(--color-text-secondary);">{{ fmtDate(p.last_login_at) }}</td>
              <td class="px-4 py-3.5">
                <button v-if="p.status === 'pending'" type="button" class="btn-pill btn-pill-accent text-[12px]" @click.stop="ask(p, 'approve')">
                  Approve
                </button>
                <button v-else-if="p.status === 'active'" type="button" class="btn-pill btn-pill-ghost text-[12px]" @click.stop="ask(p, 'reset')">
                  Reset passcode
                </button>
                <span v-else class="text-[12px]" style="color: var(--color-text-tertiary);">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Mobile: cards -->
    <div v-if="!loading && partners.length" class="md:hidden space-y-2.5">
      <div
        v-for="p in partners"
        :key="p.id"
        class="rounded-xl border p-4 cursor-pointer"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
        @click="navigateTo(`/admin/referral-partners/${p.id}`)"
      >
        <div class="flex items-start justify-between gap-3 mb-1.5">
          <span class="text-[13px] font-semibold leading-tight" style="color: var(--color-text);">{{ p.name }}</span>
          <span class="text-[11px] font-medium px-2.5 py-1 rounded-full shrink-0" :style="{ color: pill(p.status).color, background: pill(p.status).bg }">
            {{ pill(p.status).label }}
          </span>
        </div>
        <p class="text-[11px] mb-3" style="color: var(--color-text-tertiary);">{{ p.email }}</p>
        <div class="pt-2 border-t flex items-center justify-between gap-3" :style="{ borderColor: 'var(--color-border)' }">
          <p class="text-[13px] font-semibold" style="color: var(--color-text);">
            {{ tierLabels[p.relationship_tier] }} <span style="color: var(--color-accent);">· {{ p.commission_pct }}%</span>
            <span class="text-[12px] font-normal ml-2" style="color: var(--color-text-secondary);">{{ p.referrals_count }} referrals</span>
          </p>
          <button v-if="p.status === 'pending'" type="button" class="btn-pill btn-pill-accent text-[12px]" @click.stop="ask(p, 'approve')">Approve</button>
          <button v-else-if="p.status === 'active'" type="button" class="btn-pill btn-pill-ghost text-[12px]" @click.stop="ask(p, 'reset')">Reset</button>
        </div>
      </div>
    </div>

    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
      <button :disabled="filters.page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page--">← Prev</button>
      <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ filters.page }} / {{ meta.last_page }}</span>
      <button :disabled="filters.page >= meta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page++">Next →</button>
    </div>

    <!-- Confirm modal -->
    <Teleport to="body">
      <Transition name="confirm-fade">
        <div v-if="pending" class="confirm-overlay" @click.self="pending = null">
          <div class="confirm-card" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
            <h2 class="text-[17px] font-bold tracking-tight mb-2" style="color: var(--color-text);">{{ confirmCopy.title }}</h2>
            <p class="text-[13px] leading-relaxed mb-6" style="color: var(--color-text-secondary);">{{ confirmCopy.body }}</p>
            <div class="flex items-center justify-end gap-2">
              <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :disabled="acting" @click="pending = null">Cancel</button>
              <button type="button" class="btn-pill btn-pill-accent text-[13px]" :disabled="acting" @click="confirmAction">
                {{ acting ? 'Working…' : confirmCopy.cta }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.confirm-overlay {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(3px);
}
.confirm-card {
  width: 100%;
  max-width: 420px;
  border-radius: 20px;
  border-width: 1px;
  padding: 24px;
}
.confirm-fade-enter-active,
.confirm-fade-leave-active {
  transition: opacity 0.2s ease;
}
.confirm-fade-enter-from,
.confirm-fade-leave-to {
  opacity: 0;
}
@media (prefers-reduced-motion: reduce) {
  .confirm-fade-enter-active,
  .confirm-fade-leave-active { transition: none; }
}
</style>
