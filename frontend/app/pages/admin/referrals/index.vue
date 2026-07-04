<script setup lang="ts">
// Merged hub: referral partners (referrers) + their referral submissions.
// Replaces the standalone /admin/referral-partners/{index,[id]} pages — the
// list became the "Referrers" tab, the detail became a slideover. See
// docs/frontend/UI-STANDARDS.md §12.12 (query-param pill tabs) and §12.13
// (slideover panel) for the two patterns this page establishes.
import StatusPill from '~/components/shared/primitives/StatusPill.vue'
import ReferenceCode from '~/components/shared/primitives/ReferenceCode.vue'
import DateRange from '~/components/shared/primitives/DateRange.vue'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const router = useRouter()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

interface ListMeta { current_page: number, last_page: number, total: number }

const tierLabels: Record<string, string> = { cold: 'Cold', warm: 'Warm', closed: 'Closed' }

function fmtDate(iso?: string | null) {
  return iso ? new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
}

const currency = new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' })
function fmtMyr(amount: number | string | null | undefined) {
  return currency.format(Number(amount ?? 0))
}

// ── Tabs — query-param synced (?view=referrers|referrals). Referrers is the
// default (first tab): absent or invalid values fall back to it. `route.query`
// is populated from the request URL during SSR, so a hard refresh on a deep
// link (`/admin/referrals?view=referrers`) renders the right tab with no
// client-side flash. router.replace (not push) so tab clicks don't spam the
// back-button history. See UI-STANDARDS.md §12.12.
type ViewTab = 'referrers' | 'referrals'
const VIEW_TABS: { value: ViewTab, label: string, icon: string }[] = [
  { value: 'referrers', label: 'Referrers', icon: 'i-lucide-users' },
  { value: 'referrals', label: 'Referrals', icon: 'i-lucide-share-2' },
]

function normalizeView(v: unknown): ViewTab {
  return v === 'referrals' ? 'referrals' : 'referrers'
}

const activeView = computed<ViewTab>(() => normalizeView(route.query.view))

function setView(view: ViewTab) {
  if (activeView.value === view) return
  router.replace({ query: { ...route.query, view } })
}

// ─────────────────────────────────────────────────────────────────────────
// Referrers tab
// ─────────────────────────────────────────────────────────────────────────

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
const partnersMeta = ref<ListMeta | null>(null)
const partnersLoading = ref(false)
const partnersError = ref('')
const partnersLoaded = ref(false)

const partnersFilters = reactive({ search: '', status: '', page: 1 })

const partnerStatusOptions = [
  { value: '', label: 'All' },
  { value: 'pending', label: 'Pending' },
  { value: 'active', label: 'Active' },
  { value: 'paused', label: 'Paused' },
]

async function fetchPartners() {
  partnersLoading.value = true
  partnersError.value = ''
  try {
    const params = new URLSearchParams()
    if (partnersFilters.search) params.set('search', partnersFilters.search)
    if (partnersFilters.status) params.set('status', partnersFilters.status)
    params.set('page', String(partnersFilters.page))

    const res = await apiFetch<{ data: Partner[], meta: ListMeta }>(`/api/v1/admin/referral-partners?${params}`)
    partners.value = res.data
    partnersMeta.value = res.meta
    partnersLoaded.value = true
  }
  catch {
    partnersError.value = 'Failed to load referral partners. Check your session.'
  }
  finally {
    partnersLoading.value = false
  }
}

let partnersSearchTimer: ReturnType<typeof setTimeout>
watch(() => partnersFilters.search, () => {
  clearTimeout(partnersSearchTimer)
  partnersSearchTimer = setTimeout(() => { partnersFilters.page = 1; fetchPartners() }, 400)
})
watch(() => partnersFilters.status, () => {
  if (partnersFilters.page !== 1) partnersFilters.page = 1
  else fetchPartners()
})
watch(() => partnersFilters.page, () => fetchPartners())

// ── Referrer detail — slideover (§12.13). Promoted from the old
// /admin/referral-partners/[id].vue full page; Qie can promote it back to a
// page later if it outgrows a side panel.
interface PartnerReferralRow {
  id: number
  referral_partner_id: number
  quotation_id: number | null
  referrer_name: string
  business_name: string
  relationship_tier: 'cold' | 'warm' | 'closed'
  commission_tier_pct: number
  commission_pct: number | null
  effective_pct: number
  status: string
  quotation_reference: string | null
  order_final_amount_myr: string | null
  earned_myr: number | null
  created_at: string
}

interface PartnerDetail {
  id: number
  code: string
  name: string
  email: string
  phone: string | null
  relationship_tier: 'cold' | 'warm' | 'closed'
  commission_tiers: Record<string, number>
  status: 'pending' | 'active' | 'paused'
  has_passcode: boolean
  last_login_at: string | null
  stats: { earned_myr: number, estimated_myr: number, referrals_count: number }
  referrals: PartnerReferralRow[]
}

const slideoverPartnerId = ref<number | null>(null)
// Shown instantly while the full detail loads, so the panel never opens blank.
const slideoverStub = ref<{ name: string, email: string } | null>(null)
const partnerDetail = ref<PartnerDetail | null>(null)
const partnerDetailLoading = ref(false)
const partnerDetailError = ref('')

const tierBands = computed(() => {
  const tiers = partnerDetail.value?.commission_tiers
  if (!tiers) return '—'
  return Object.values(tiers).map(v => `${v}%`).join(' / ')
})

async function fetchPartnerDetail(id: number) {
  partnerDetailLoading.value = true
  partnerDetailError.value = ''
  try {
    const res = await apiFetch<{ data: PartnerDetail }>(`/api/v1/admin/referral-partners/${id}`)
    partnerDetail.value = res.data
  }
  catch {
    partnerDetailError.value = 'Failed to load referrer. Check your session.'
  }
  finally {
    partnerDetailLoading.value = false
  }
}

function openSlideover(p: Partner) {
  slideoverPartnerId.value = p.id
  slideoverStub.value = { name: p.name, email: p.email }
  partnerDetail.value = null
  fetchPartnerDetail(p.id)
}

function closeSlideover() {
  slideoverPartnerId.value = null
  slideoverStub.value = null
  partnerDetail.value = null
}

// ── Approve / reset passcode — shared by the list row's quick action and the
// slideover's action button, so both funnel through one confirm-before-act
// dialog (approve issues the first passcode; reset invalidates the old one —
// both email the referrer, so both are confirmed; the passcode is never shown).
type PendingAction = { id: number, name: string, email: string, kind: 'approve' | 'reset' }
const pendingAction = ref<PendingAction | null>(null)
const acting = ref(false)

function askAction(target: { id: number, name: string, email: string }, kind: 'approve' | 'reset') {
  pendingAction.value = { id: target.id, name: target.name, email: target.email, kind }
}

const confirmCopy = computed(() => {
  if (!pendingAction.value) return { title: '', body: '', cta: '' }
  const { name, email, kind } = pendingAction.value
  return kind === 'approve'
    ? {
        title: `Approve ${name}?`,
        body: `This activates their account and emails a one-time passcode to ${email}. The passcode is never shown here.`,
        cta: 'Approve & email passcode',
      }
    : {
        title: `Reset passcode for ${name}?`,
        body: `This invalidates their current passcode and emails a new one to ${email}. They can't log in with the old passcode afterwards.`,
        cta: 'Reset & email new passcode',
      }
})

async function confirmAction() {
  const pending = pendingAction.value
  if (!pending || acting.value) return
  acting.value = true
  const path = pending.kind === 'approve'
    ? `/api/v1/admin/referral-partners/${pending.id}/approve`
    : `/api/v1/admin/referral-partners/${pending.id}/reset-passcode`
  try {
    const res = await apiFetch<{ message: string }>(path, { method: 'POST' })
    toast.success(pending.kind === 'approve' ? 'Referrer approved' : 'Passcode reset', res.message)
    pendingAction.value = null
    await fetchPartners()
    if (slideoverPartnerId.value === pending.id) await fetchPartnerDetail(pending.id)
  }
  catch (e: any) {
    toast.error('Action failed', e?.data?.message ?? 'Please try again.')
    pendingAction.value = null
  }
  finally {
    acting.value = false
  }
}

// Escape closes the topmost layer: confirm dialog first, then the slideover.
onKeyStroke('Escape', () => {
  if (pendingAction.value) { pendingAction.value = null; return }
  if (slideoverPartnerId.value !== null) closeSlideover()
})

// ─────────────────────────────────────────────────────────────────────────
// Referrals tab
// ─────────────────────────────────────────────────────────────────────────

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
const referralsMeta = ref<ListMeta | null>(null)
const referralsLoading = ref(false)
const referralsError = ref('')
const referralsLoaded = ref(false)

const referralsFilters = reactive({ search: '', status: '', page: 1 })

const referralStatusOptions = [
  { value: '', label: 'All' },
  { value: 'new', label: 'New' },
  { value: 'contacted', label: 'Contacted' },
  { value: 'qualified', label: 'Qualified' },
  { value: 'converted', label: 'Converted' },
  { value: 'rejected', label: 'Rejected' },
]

async function fetchReferrals() {
  referralsLoading.value = true
  referralsError.value = ''
  try {
    const params = new URLSearchParams()
    if (referralsFilters.search) params.set('search', referralsFilters.search)
    if (referralsFilters.status) params.set('status', referralsFilters.status)
    params.set('page', String(referralsFilters.page))

    const res = await apiFetch<{ data: Referral[], meta: ListMeta }>(`/api/v1/admin/referrals?${params}`)
    referrals.value = res.data
    referralsMeta.value = res.meta
    referralsLoaded.value = true
  }
  catch {
    referralsError.value = 'Failed to load referrals. Check your session.'
  }
  finally {
    referralsLoading.value = false
  }
}

let referralsSearchTimer: ReturnType<typeof setTimeout>
watch(() => referralsFilters.search, () => {
  clearTimeout(referralsSearchTimer)
  referralsSearchTimer = setTimeout(() => { referralsFilters.page = 1; fetchReferrals() }, 400)
})
watch(() => referralsFilters.status, () => {
  if (referralsFilters.page !== 1) referralsFilters.page = 1
  else fetchReferrals()
})
watch(() => referralsFilters.page, () => fetchReferrals())

// ── Lazy per-tab fetch — only load the active tab's data on mount, and the
// other tab's data the first time it's switched to. Filter state (search /
// status / page) still persists per tab across toggles either way.
onMounted(() => {
  if (activeView.value === 'referrers') fetchPartners()
  else fetchReferrals()
})

watch(activeView, (view) => {
  if (view === 'referrers' && !partnersLoaded.value) fetchPartners()
  if (view === 'referrals' && !referralsLoaded.value) fetchReferrals()
})
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Referrals</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
          <template v-if="activeView === 'referrers'">
            Approve new referrers and manage passcodes. Approving emails a one-time passcode — it's never shown here.
          </template>
          <template v-else>
            Partner referrals submitted from the <NuxtLink to="/partners" class="underline" :style="{ color: 'var(--color-accent)' }">Partner Program</NuxtLink>. Link a converted referral to its order to lock the commission tier.
          </template>
        </p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="mb-6">
      <div class="tab-track" role="tablist" aria-label="Referral hub view">
        <button
          v-for="tab in VIEW_TABS"
          :id="`tab-${tab.value}`"
          :key="tab.value"
          type="button"
          role="tab"
          :aria-selected="activeView === tab.value"
          :aria-controls="`panel-${tab.value}`"
          class="tab-pill"
          @click="setView(tab.value)"
        >
          <UIcon :name="tab.icon" class="size-4" />
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- Referrers tab -->
    <div v-if="activeView === 'referrers'" id="panel-referrers" role="tabpanel" aria-labelledby="tab-referrers">

      <div class="flex flex-wrap items-center gap-3 mb-6">
        <AdminExpandingSearch v-model="partnersFilters.search" placeholder="Search by name, email, code…" />
        <AdminStatusFilter v-model="partnersFilters.status" :options="partnerStatusOptions" :total="partnersMeta?.total ?? null" class="ml-auto" />
      </div>

      <p v-if="partnersError" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ partnersError }}</p>

      <div v-if="partnersLoading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading referrers…</div>

      <div v-else-if="!partners.length" class="text-center py-16" style="color: var(--color-text-secondary);">
        No referral partners found.
      </div>

      <!-- Desktop: table -->
      <div v-else class="hidden md:block admin-table-card">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr>
                <th
v-for="h in ['Partner', 'Code', 'Tier', 'Referrals', 'Status', 'Last login', 'Actions']" :key="h"
                  class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
                  {{ h }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in partners" :key="p.id" class="admin-table-row" @click="openSlideover(p)">
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
                  <StatusPill :status="p.status" type="referral_partner" />
                </td>
                <td class="px-4 py-3.5 text-[12px]" style="color: var(--color-text-secondary);">{{ fmtDate(p.last_login_at) }}</td>
                <td class="px-4 py-3.5">
                  <button v-if="p.status === 'pending'" type="button" class="btn-pill btn-pill-accent text-[12px]" @click.stop="askAction(p, 'approve')">
                    Approve
                  </button>
                  <button v-else-if="p.status === 'active'" type="button" class="btn-pill btn-pill-ghost text-[12px]" @click.stop="askAction(p, 'reset')">
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
      <div v-if="!partnersLoading && partners.length" class="md:hidden space-y-2.5">
        <div
          v-for="p in partners"
          :key="p.id"
          class="rounded-xl border p-4 cursor-pointer"
          :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
          @click="openSlideover(p)"
        >
          <div class="flex items-start justify-between gap-3 mb-1.5">
            <span class="text-[13px] font-semibold leading-tight" style="color: var(--color-text);">{{ p.name }}</span>
            <StatusPill :status="p.status" type="referral_partner" />
          </div>
          <p class="text-[11px] mb-3" style="color: var(--color-text-tertiary);">{{ p.email }}</p>
          <div class="pt-2 border-t flex items-center justify-between gap-3" :style="{ borderColor: 'var(--color-border)' }">
            <p class="text-[13px] font-semibold" style="color: var(--color-text);">
              {{ tierLabels[p.relationship_tier] }} <span style="color: var(--color-accent);">· {{ p.commission_pct }}%</span>
              <span class="text-[12px] font-normal ml-2" style="color: var(--color-text-secondary);">{{ p.referrals_count }} referrals</span>
            </p>
            <button v-if="p.status === 'pending'" type="button" class="btn-pill btn-pill-accent text-[12px]" @click.stop="askAction(p, 'approve')">Approve</button>
            <button v-else-if="p.status === 'active'" type="button" class="btn-pill btn-pill-ghost text-[12px]" @click.stop="askAction(p, 'reset')">Reset</button>
          </div>
        </div>
      </div>

      <div v-if="partnersMeta && partnersMeta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
        <button :disabled="partnersFilters.page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="partnersFilters.page--">← Prev</button>
        <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ partnersFilters.page }} / {{ partnersMeta.last_page }}</span>
        <button :disabled="partnersFilters.page >= partnersMeta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="partnersFilters.page++">Next →</button>
      </div>
    </div>

    <!-- Referrals tab -->
    <div v-else id="panel-referrals" role="tabpanel" aria-labelledby="tab-referrals">

      <div class="flex flex-wrap items-center gap-3 mb-6">
        <AdminExpandingSearch v-model="referralsFilters.search" placeholder="Search by referrer, business, email…" />
        <AdminStatusFilter v-model="referralsFilters.status" :options="referralStatusOptions" :total="referralsMeta?.total ?? null" class="ml-auto" />
      </div>

      <p v-if="referralsError" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ referralsError }}</p>

      <div v-if="referralsLoading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading referrals…</div>

      <div v-else-if="!referrals.length" class="text-center py-16" style="color: var(--color-text-secondary);">
        No referrals found.
      </div>

      <!-- Desktop: table -->
      <div v-else class="hidden md:block admin-table-card">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr>
                <th
v-for="h in ['Referrer', 'Business', 'Tier', 'Status', 'Submitted']" :key="h"
                  class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
                  {{ h }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
v-for="r in referrals" :key="r.id"
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
                  <StatusPill :status="r.status" type="referral" />
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
      <div v-if="!referralsLoading && referrals.length" class="md:hidden space-y-2.5">
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
            <StatusPill :status="r.status" type="referral" />
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

      <div v-if="referralsMeta && referralsMeta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
        <button :disabled="referralsFilters.page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="referralsFilters.page--">← Prev</button>
        <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ referralsFilters.page }} / {{ referralsMeta.last_page }}</span>
        <button :disabled="referralsFilters.page >= referralsMeta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="referralsFilters.page++">Next →</button>
      </div>
    </div>

    <!-- Referrer detail slideover (§12.13) -->
    <Teleport to="body">
      <Transition name="slideover">
        <div v-if="slideoverPartnerId !== null" class="slideover-scrim" @click.self="closeSlideover">
          <aside class="slideover-panel" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
            <div class="slideover-head">
              <div class="min-w-0">
                <p class="text-[17px] font-bold tracking-tight truncate" style="color: var(--color-text);">
                  {{ partnerDetail?.name ?? slideoverStub?.name }}
                </p>
                <p class="text-[12px] mt-0.5 truncate" style="color: var(--color-text-secondary);">
                  {{ partnerDetail?.email ?? slideoverStub?.email }}
                </p>
              </div>
              <button type="button" class="slideover-close" aria-label="Close" @click="closeSlideover">
                <UIcon name="i-lucide-x" class="size-4" />
              </button>
            </div>

            <div class="slideover-body">
              <div v-if="partnerDetailLoading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading referrer…</div>
              <p v-else-if="partnerDetailError" style="color: var(--color-danger);">{{ partnerDetailError }}</p>

              <template v-else-if="partnerDetail">
                <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
                  <StatusPill :status="partnerDetail.status" type="referral_partner" />
                  <button
v-if="partnerDetail.status === 'pending'" type="button" class="btn-pill btn-pill-accent text-[13px]"
                    @click="askAction(partnerDetail, 'approve')">
                    Approve
                  </button>
                  <button
v-else-if="partnerDetail.status === 'active'" type="button" class="btn-pill btn-pill-ghost text-[13px]"
                    @click="askAction(partnerDetail, 'reset')">
                    Reset passcode
                  </button>
                </div>

                <div class="grid grid-cols-2 gap-4 pb-5 mb-5 border-b" style="border-color: var(--color-border);">
                  <div>
                    <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Referral code</p>
                    <p class="text-[13px] font-mono inline-flex items-center" style="color: var(--color-text);">
                      ?ref=<ReferenceCode :code="partnerDetail.code" />
                    </p>
                  </div>
                  <div>
                    <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Tier bands</p>
                    <p class="text-[13px] font-semibold tabular-nums" style="color: var(--color-accent);">{{ tierBands }}</p>
                  </div>
                  <div>
                    <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Earned</p>
                    <p class="text-[13px] font-semibold tabular-nums" style="color: var(--color-success);">{{ fmtMyr(partnerDetail.stats.earned_myr) }}</p>
                  </div>
                  <div>
                    <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Estimated</p>
                    <p class="text-[13px] font-semibold tabular-nums" style="color: var(--color-text);">{{ fmtMyr(partnerDetail.stats.estimated_myr) }}</p>
                  </div>
                  <div>
                    <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Last login</p>
                    <p class="text-[13px]" style="color: var(--color-text);">
                      <DateRange v-if="partnerDetail.last_login_at" :date="partnerDetail.last_login_at" format="short" />
                      <template v-else>—</template>
                    </p>
                  </div>
                </div>

                <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">
                  Referrals <span class="font-normal normal-case" style="color: var(--color-text-tertiary);">({{ partnerDetail.stats.referrals_count }})</span>
                </p>

                <div v-if="!partnerDetail.referrals.length" class="text-center py-10 rounded-xl border text-[13px]" style="color: var(--color-text-secondary); border-color: var(--color-border);">
                  No referrals from this referrer yet.
                </div>

                <div v-else class="space-y-2">
                  <button
                    v-for="r in partnerDetail.referrals"
                    :key="r.id"
                    type="button"
                    class="w-full text-left rounded-xl border p-3.5 transition-colors hover:bg-(--color-bg-secondary)"
                    :style="{ borderColor: 'var(--color-border)' }"
                    @click="navigateTo(`/admin/referrals/${r.id}`)"
                  >
                    <div class="flex items-start justify-between gap-3 mb-1.5">
                      <span class="text-[13px] font-medium" style="color: var(--color-text);">{{ r.business_name }}</span>
                      <StatusPill :status="r.status" type="referral" />
                    </div>
                    <p class="text-[11px] mb-2" style="color: var(--color-text-tertiary);">{{ r.referrer_name }} · {{ tierLabels[r.relationship_tier] }}</p>
                    <div class="flex items-center justify-between gap-2 pt-2 border-t" style="border-color: var(--color-border);">
                      <span v-if="r.quotation_reference" @click.stop>
                        <ReferenceCode :code="r.quotation_reference" />
                      </span>
                      <span v-else class="text-[12px]" style="color: var(--color-text-tertiary);">—</span>
                      <span class="text-[12px] tabular-nums" style="color: var(--color-text);">
                        {{ r.status === 'converted' ? fmtMyr(r.earned_myr) : `${r.effective_pct}%` }}
                      </span>
                    </div>
                  </button>
                </div>
              </template>
            </div>
          </aside>
        </div>
      </Transition>
    </Teleport>

    <!-- Approve / reset confirmation (layered above the slideover) -->
    <Teleport to="body">
      <Transition name="confirm-fade">
        <div v-if="pendingAction" class="confirm-overlay" @click.self="pendingAction = null">
          <div class="confirm-card" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
            <h2 class="text-[17px] font-bold tracking-tight mb-2" style="color: var(--color-text);">{{ confirmCopy.title }}</h2>
            <p class="text-[13px] leading-relaxed mb-6" style="color: var(--color-text-secondary);">{{ confirmCopy.body }}</p>
            <div class="flex items-center justify-end gap-2">
              <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :disabled="acting" @click="pendingAction = null">Cancel</button>
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
/* Query-param pill tabs (§12.12) — a bounded segmented track, same visual
   language as .standard-pill (§12.6) but grouped so it reads as a single
   control rather than a loose filter row. First use; promote to a global
   class in main.css if a second page needs it (mirrors the §12.9 mobile-
   drawer precedent). */
.tab-track {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.25rem;
  border-radius: 9999px;
  background: var(--color-bg-secondary);
  border: 1px solid var(--color-border);
}
.tab-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 13px;
  font-weight: 500;
  font-family: inherit;
  padding: 0.5rem 1rem;
  border-radius: 9999px;
  border: none;
  color: var(--color-text-secondary);
  background: transparent;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
}
.tab-pill:hover {
  color: var(--color-text);
}
.tab-pill[aria-selected="true"] {
  background: var(--color-bg-elevated);
  color: var(--color-text);
  box-shadow: var(--shadow-sm);
}

/* Slideover panel (§12.13) — right-edge overlay for a "promote to a page
   later" detail view. Same Teleport + scrim + sliding-panel shape as the
   quotation-picker drawer on referrals/[id].vue, generalised into a named
   pattern. First use; promote to a global class in main.css if reused. */
.slideover-scrim {
  position: fixed;
  inset: 0;
  z-index: 90;
  display: flex;
  justify-content: flex-end;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(3px);
}
.slideover-panel {
  width: 100%;
  max-width: 480px;
  height: 100%;
  display: flex;
  flex-direction: column;
  border-left: 1px solid var(--color-border);
  box-shadow: var(--shadow-lg);
}
.slideover-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 20px;
  border-bottom: 1px solid var(--color-border);
}
.slideover-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 9999px;
  color: var(--color-text-secondary);
  transition: background 0.15s ease, color 0.15s ease;
  flex-shrink: 0;
}
.slideover-close:hover {
  background: var(--color-bg-secondary);
  color: var(--color-text);
}
.slideover-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
}

/* Fast dashboard motion (0.3–0.5s per UI-STANDARDS §8). */
.slideover-enter-active,
.slideover-leave-active {
  transition: opacity 0.3s ease;
}
.slideover-enter-active .slideover-panel,
.slideover-leave-active .slideover-panel {
  transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1);
}
.slideover-enter-from,
.slideover-leave-to {
  opacity: 0;
}
.slideover-enter-from .slideover-panel,
.slideover-leave-to .slideover-panel {
  transform: translateX(100%);
}
@media (prefers-reduced-motion: reduce) {
  .slideover-enter-active,
  .slideover-leave-active,
  .slideover-enter-active .slideover-panel,
  .slideover-leave-active .slideover-panel {
    transition: none;
  }
}

.confirm-overlay {
  position: fixed;
  inset: 0;
  z-index: 100;
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
