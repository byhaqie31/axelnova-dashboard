<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

interface Referral {
  id: number
  referrer_name: string
  referrer_email: string
  referrer_phone: string | null
  business_name: string
  business_contact_name: string | null
  business_email: string | null
  business_phone: string | null
  relationship_tier: 'cold' | 'warm' | 'closed'
  commission_tier_pct: number
  commission_pct: number | null
  effective_pct: number
  notes: string | null
  status: string
  agreed_terms: boolean
  // New quotation anchor + derived figures
  quotation_id: number | null
  quotation_reference: string | null
  anchor_order_id: number | null
  anchor_order_number: string | null
  contract_myr: string | number | null
  collected_myr: string | number | null
  earned_myr: number | null
  estimated_myr: number | null
  // Legacy
  linked_order_id: number | null
  commission_email_sent_at: string | null
  created_at: string
}

const referral = ref<Referral | null>(null)
const loading = ref(true)
const error = ref('')
const statusLoading = ref(false)
const commissionSending = ref(false)

const tierLabels: Record<string, string> = { cold: 'Cold lead', warm: 'Warm intro', closed: 'Closed referral' }

useHead(() => ({
  title: referral.value ? `${referral.value.referrer_name} — Referral` : 'Referral — Admin',
}))

async function fetchReferral() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Referral }>(`/api/v1/admin/referrals/${route.params.id}`)
    referral.value = res.data
  }
  catch {
    error.value = 'Failed to load referral.'
  }
  finally {
    loading.value = false
  }
}

async function updateStatus(status: string) {
  if (!referral.value) return
  statusLoading.value = true
  try {
    await apiFetch(`/api/v1/admin/referrals/${referral.value.id}/status`, {
      method: 'POST',
      body: { status },
    })
    referral.value.status = status
    toast.success('Status updated', `Referral set to ${statusLabels[status] ?? status}.`)
  }
  catch {
    toast.error('Couldn’t update status', 'Something went wrong. Please try again.')
  }
  finally {
    statusLoading.value = false
  }
}

// Tie the referral to a quotation — the anchor. Conversion then happens
// automatically when that quotation's order has its deposit collected. Two ways in:
// type the ID, or pick from the quotation-list drawer (avoids mistyping).
const tieQuotationId = ref('')
const tying = ref(false)

async function doTie(quotationId: number) {
  if (!referral.value || !quotationId) return
  tying.value = true
  try {
    await apiFetch(`/api/v1/admin/referrals/${referral.value.id}/tie-quotation`, {
      method: 'POST',
      body: { quotation_id: quotationId },
    })
    tieQuotationId.value = ''
    listOpen.value = false
    await fetchReferral()
    toast.success('Tied to quotation', 'The referral now anchors to that quotation.')
  }
  catch (e: any) {
    toast.error('Couldn’t tie quotation', e?.data?.message ?? 'Check the quotation and try again.')
  }
  finally {
    tying.value = false
  }
}

// Quotation-list drawer.
interface QuotationRow {
  id: number
  reference_code: string
  name: string | null
  company: string | null
  status: string
  estimate_min_myr: string | number | null
  estimate_max_myr: string | number | null
}
const listOpen = ref(false)
const quotations = ref<QuotationRow[]>([])
const listLoading = ref(false)
const listSearch = ref('')
// Highlighted-but-not-yet-tied selection inside the drawer.
const selectedQuotation = ref<QuotationRow | null>(null)

async function loadQuotations() {
  listLoading.value = true
  try {
    // Include 'accepted' — those became orders and are the usual tie targets
    // (the index hides them by default). Search runs server-side.
    const params = new URLSearchParams({ status: 'draft,sent,accepted' })
    if (listSearch.value.trim()) params.set('search', listSearch.value.trim())
    const res = await apiFetch<{ data: QuotationRow[] }>(`/api/v1/admin/quotations?${params}`)
    quotations.value = res.data
  }
  catch {
    toast.error('Couldn’t load quotations', 'Please try again.')
  }
  finally {
    listLoading.value = false
  }
}

function openQuotationList() {
  listOpen.value = true
  selectedQuotation.value = null
  loadQuotations()
}

let quotationSearchTimer: ReturnType<typeof setTimeout>
watch(listSearch, () => {
  clearTimeout(quotationSearchTimer)
  quotationSearchTimer = setTimeout(loadQuotations, 300)
})

// Confirm-before-act for both tie (typed ID or drawer selection) and untie.
type PendingTie = { kind: 'tie', quotationId: number, reference: string | null } | { kind: 'untie' }
const pendingTie = ref<PendingTie | null>(null)

function askTie(quotationId: number, reference: string | null) {
  if (!quotationId) return
  pendingTie.value = { kind: 'tie', quotationId, reference }
}
function askUntie() {
  pendingTie.value = { kind: 'untie' }
}

const tieConfirmCopy = computed(() => {
  const p = pendingTie.value
  if (!p) return { title: '', body: '', cta: '' }
  if (p.kind === 'untie') {
    return {
      title: 'Untie this quotation?',
      body: 'The referral drops its anchor and returns to a plain claim (New). If it was earning, it stops until you re-tie it.',
      cta: 'Untie',
    }
  }
  return {
    title: 'Tie to this quotation?',
    body: `This anchors the referral to ${p.reference ?? `quotation #${p.quotationId}`}. It converts automatically once that order's deposit is collected.`,
    cta: 'Tie referral',
  }
})

async function confirmTie() {
  const p = pendingTie.value
  if (!p || tying.value) return
  if (p.kind === 'tie') await doTie(p.quotationId)
  else await doUntie()
  pendingTie.value = null
}

async function doUntie() {
  if (!referral.value) return
  tying.value = true
  try {
    await apiFetch(`/api/v1/admin/referrals/${referral.value.id}/untie-quotation`, { method: 'POST' })
    await fetchReferral()
    toast.success('Untied', 'The referral no longer anchors to a quotation.')
  }
  catch {
    toast.error('Couldn’t untie', 'Please try again.')
  }
  finally {
    tying.value = false
  }
}

async function sendCommissionEmail() {
  if (!referral.value) return
  commissionSending.value = true
  try {
    const res = await apiFetch<{ message: string, referral: { data: Referral } | Referral }>(
      `/api/v1/admin/referrals/${referral.value.id}/commission-email`,
      { method: 'POST' },
    )
    referral.value = ((res.referral as any).data ?? res.referral) as Referral
    toast.success('Request sent', 'The referrer has been emailed for their bank details.')
  }
  catch {
    toast.error('Couldn’t send email', 'The referral must be converted (deposit collected) with a priced order.')
  }
  finally {
    commissionSending.value = false
  }
}

onMounted(fetchReferral)

function fmtDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function fmtMyrExact(amount: string | number | null) {
  return `RM ${Number(amount ?? 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

// Manual triage stages. draft/converted are lifecycle-driven (tie/quote → draft,
// deposit collected → converted), so they aren't manual buttons.
const statusOptions = ['new', 'contacted', 'qualified', 'rejected']
const statusLabels: Record<string, string> = {
  new: 'New', contacted: 'Contacted', qualified: 'Qualified', draft: 'Draft', converted: 'Converted', rejected: 'Rejected',
}
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <NuxtLink
to="/admin/referrals?view=referrals" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All referrals
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" style="color: var(--color-danger);">{{ error }}</p>

    <div v-else-if="referral" class="grid lg:grid-cols-[1fr_300px] gap-8 items-start">

      <div class="space-y-6">

        <!-- Header -->
        <div
class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <div class="flex items-start justify-between flex-wrap gap-4 mb-5">
            <div>
              <p class="text-[22px] font-bold tracking-tight" style="color: var(--color-text);">{{ referral.referrer_name }}</p>
              <p class="text-[14px] mt-0.5" style="color: var(--color-text-secondary);">referred <span style="color: var(--color-text);">{{ referral.business_name }}</span></p>
            </div>
            <AdminStatusPill :status="referral.status" size="md" />
          </div>
          <div class="grid sm:grid-cols-3 gap-4 pt-4 border-t" style="border-color: var(--color-border);">
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Tier</p>
              <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ tierLabels[referral.relationship_tier] }}</p>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Effective rate</p>
              <p class="text-[13px] font-semibold tabular-nums" style="color: var(--color-accent);">
                {{ referral.effective_pct }}%
                <span v-if="referral.commission_pct === null" class="font-normal text-[11px]" style="color: var(--color-text-tertiary);">· tier estimate</span>
                <span v-else class="font-normal text-[11px]" style="color: var(--color-text-tertiary);">· confirmed</span>
              </p>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Submitted</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ fmtDate(referral.created_at) }}</p>
            </div>
          </div>
        </div>

        <!-- Commission (once there's an anchored order) -->
        <div
v-if="referral.anchor_order_id" class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <div class="flex items-center justify-between gap-3 mb-5">
            <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Commission</p>
            <span
v-if="referral.commission_email_sent_at" class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full"
              :style="{ color: 'var(--color-success)', background: 'var(--color-success-soft)' }">
              <UIcon name="i-lucide-mail-check" class="size-3" /> Requested {{ fmtDate(referral.commission_email_sent_at) }}
            </span>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Contract</p>
              <p class="text-[15px] font-semibold tabular-nums" style="color: var(--color-text);">{{ fmtMyrExact(referral.contract_myr) }}</p>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Collected</p>
              <p class="text-[15px] font-semibold tabular-nums" style="color: var(--color-text);">{{ fmtMyrExact(referral.collected_myr) }}</p>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Rate</p>
              <p class="text-[15px] font-semibold tabular-nums" style="color: var(--color-text-secondary);">{{ referral.effective_pct }}%</p>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">
                {{ referral.status === 'converted' ? 'Earned' : 'Estimated' }}
              </p>
              <p class="text-[15px] font-bold tabular-nums" style="color: var(--color-accent);">
                {{ referral.status === 'converted' ? fmtMyrExact(referral.earned_myr) : fmtMyrExact(referral.estimated_myr) }}
              </p>
            </div>
          </div>

          <p class="text-[12px] mt-4" style="color: var(--color-text-tertiary);">
            <template v-if="referral.status === 'converted'">
              Earning {{ referral.effective_pct }}% of collected payments on order
            </template>
            <template v-else>
              Estimated {{ referral.effective_pct }}% — starts earning once the deposit is collected on order
            </template>
            <NuxtLink :to="`/admin/orders/${referral.anchor_order_id}`" class="underline" :style="{ color: 'var(--color-accent)' }">{{ referral.anchor_order_number ?? `#${referral.anchor_order_id}` }}</NuxtLink>.
          </p>

          <div class="pt-5 mt-5 border-t flex items-center gap-3" style="border-color: var(--color-border);">
            <p v-if="referral.status !== 'converted'" class="text-[11px]" style="color: var(--color-text-tertiary);">
              Available once the referral converts (deposit collected).
            </p>
            <button
type="button" class="btn-pill btn-pill-accent text-[13px] ml-auto shrink-0"
              :class="{ 'opacity-50': commissionSending || referral.status !== 'converted' }"
              :disabled="commissionSending || referral.status !== 'converted'"
              @click="sendCommissionEmail">
              <UIcon name="i-lucide-send" class="size-3.5 mr-1.5" />
              {{ commissionSending ? 'Sending…' : (referral.commission_email_sent_at ? 'Resend bank-details request' : 'Email bank-details request') }}
            </button>
          </div>
        </div>

        <!-- Referrer contact -->
        <div
class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Referrer</p>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Email</p>
              <a :href="`mailto:${referral.referrer_email}`" class="text-[13px] font-medium" style="color: var(--color-accent);">{{ referral.referrer_email }}</a>
            </div>
            <div v-if="referral.referrer_phone">
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Phone</p>
              <a :href="`tel:${referral.referrer_phone}`" class="text-[13px] font-medium" style="color: var(--color-text);">{{ referral.referrer_phone }}</a>
            </div>
          </div>
        </div>

        <!-- Business -->
        <div
class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Business referred</p>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Name</p>
              <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ referral.business_name }}</p>
            </div>
            <div v-if="referral.business_contact_name">
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Contact</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ referral.business_contact_name }}</p>
            </div>
            <div v-if="referral.business_email">
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Email</p>
              <a :href="`mailto:${referral.business_email}`" class="text-[13px] font-medium" style="color: var(--color-accent);">{{ referral.business_email }}</a>
            </div>
            <div v-if="referral.business_phone">
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Phone</p>
              <a :href="`tel:${referral.business_phone}`" class="text-[13px] font-medium" style="color: var(--color-text);">{{ referral.business_phone }}</a>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div
v-if="referral.notes" class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Notes</p>
          <p class="text-[13px] leading-relaxed whitespace-pre-line" style="color: var(--color-text);">{{ referral.notes }}</p>
        </div>

      </div>

      <!-- Sidebar -->
      <div class="lg:sticky lg:top-20 space-y-4">

        <!-- Status -->
        <div
class="rounded-2xl border p-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Update status</p>
          <div class="flex flex-wrap gap-2">
            <button
v-for="s in statusOptions" :key="s" type="button"
              class="status-pill status-pill-button"
              :class="{ 'opacity-50': statusLoading }"
              :data-status="referral.status === s ? s : ''"
              :data-active="referral.status === s"
              :disabled="statusLoading || referral.status === s || referral.status === 'converted'"
              @click="updateStatus(s)">
              {{ statusLabels[s] }}
            </button>
          </div>
          <p v-if="referral.status === 'converted'" class="text-[11px] mt-3" style="color: var(--color-text-tertiary);">
            Converted — its deposit has been collected. Status is lifecycle-driven now.
          </p>
          <p v-else-if="referral.status === 'draft'" class="text-[11px] mt-3" style="color: var(--color-text-tertiary);">
            Draft — quoted and waiting on the deposit. It converts automatically when collected.
          </p>
        </div>

        <!-- Quotation anchor -->
        <div
class="rounded-2xl border p-5 space-y-3"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Quotation anchor</p>

          <template v-if="referral.quotation_id">
            <div
class="rounded-xl border px-4 py-3"
              :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }">
              <p class="text-[11px]" style="color: var(--color-text-tertiary);">Tied quotation</p>
              <NuxtLink :to="`/admin/quotations/${referral.quotation_id}`" class="text-[13px] font-mono font-medium" style="color: var(--color-accent);">
                {{ referral.quotation_reference ?? `#${referral.quotation_id}` }}
              </NuxtLink>
              <p v-if="referral.anchor_order_number" class="text-[11px] mt-1.5" style="color: var(--color-text-tertiary);">
                Order
                <NuxtLink :to="`/admin/orders/${referral.anchor_order_id}`" class="font-mono" style="color: var(--color-accent);">{{ referral.anchor_order_number }}</NuxtLink>
              </p>
            </div>
            <button type="button" class="btn-pill btn-pill-ghost w-full justify-center" :disabled="tying" @click="askUntie">
              <UIcon name="i-lucide-unlink" class="size-4 mr-1.5" /> Untie quotation
            </button>
          </template>

          <template v-else>
            <p class="text-[12px]" style="color: var(--color-text-secondary);">
              Anchor this referral to the quotation its lead became — it converts automatically once the deposit is collected.
            </p>
            <input v-model="tieQuotationId" type="number" inputmode="numeric" placeholder="Quotation ID" class="contact-input text-[13px]" >
            <button
type="button" class="btn-pill btn-pill-accent w-full justify-center"
              :disabled="tying || !tieQuotationId"
              @click="askTie(Number(tieQuotationId), null)">
              Tie
            </button>

            <div class="anchor-or"><span>or</span></div>

            <button type="button" class="btn-pill btn-pill-ghost w-full justify-center" @click="openQuotationList">
              <UIcon name="i-lucide-list" class="size-4 mr-1.5" /> View quotation list
            </button>

            <NuxtLink
v-if="referral.linked_order_id" :to="`/admin/orders/${referral.linked_order_id}`"
              class="text-[11px] inline-flex items-center gap-1 pt-1" style="color: var(--color-text-tertiary);">
              Legacy linked order #{{ referral.linked_order_id }} →
            </NuxtLink>
          </template>
        </div>

        <!-- Actions -->
        <div
class="rounded-2xl border p-5 space-y-3"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Actions</p>

          <a
:href="`mailto:${referral.referrer_email}?subject=Your%20referral%20to%20Axel%20Nova`"
            class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">
            Email referrer
          </a>

          <a
v-if="referral.referrer_phone"
            :href="`https://wa.me/${referral.referrer_phone.replace(/\D/g, '')}?text=Hi%20${encodeURIComponent(referral.referrer_name)}%2C%20thanks%20for%20referring%20${encodeURIComponent(referral.business_name)}.`"
            target="_blank" rel="noopener"
            class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">
            WhatsApp
          </a>
        </div>

        <!-- Audit -->
        <div
class="rounded-xl border px-4 py-3.5 space-y-2"
          :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }">
          <div class="flex justify-between">
            <span class="text-[11px]" style="color: var(--color-text-tertiary);">Submitted</span>
            <span class="text-[11px]" style="color: var(--color-text-secondary);">{{ fmtDate(referral.created_at) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-[11px]" style="color: var(--color-text-tertiary);">Agreed to terms</span>
            <span class="text-[11px]" style="color: var(--color-text-secondary);">{{ referral.agreed_terms ? 'Yes' : 'No' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Quotation picker drawer -->
    <Teleport to="body">
      <Transition name="drawer">
        <div v-if="listOpen" class="drawer-scrim" @click.self="listOpen = false">
          <aside class="drawer-panel" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
            <div class="drawer-head">
              <div>
                <p class="text-[15px] font-semibold tracking-tight" style="color: var(--color-text);">Choose a quotation</p>
                <p class="text-[12px] mt-0.5" style="color: var(--color-text-secondary);">Tie this referral to its quotation.</p>
              </div>
              <button type="button" class="drawer-close" aria-label="Close" @click="listOpen = false">
                <UIcon name="i-lucide-x" class="size-4" />
              </button>
            </div>
            <div class="px-5 pb-3">
              <input v-model="listSearch" type="search" placeholder="Search by ref, name, company…" class="contact-input text-[13px]" >
            </div>
            <div class="drawer-list">
              <p v-if="listLoading" class="text-[13px] px-5 py-6 text-center" style="color: var(--color-text-tertiary);">Loading quotations…</p>
              <p v-else-if="!quotations.length" class="text-[13px] px-5 py-6 text-center" style="color: var(--color-text-tertiary);">No quotations found.</p>
              <button
                v-for="q in quotations"
                :key="q.id"
                type="button"
                class="drawer-item"
                :class="{ 'drawer-item-active': selectedQuotation?.id === q.id }"
                @click="selectedQuotation = q"
              >
                <div class="min-w-0">
                  <p class="text-[13px] font-mono font-medium truncate" style="color: var(--color-accent);">{{ q.reference_code }}</p>
                  <p class="text-[12px] truncate" style="color: var(--color-text-secondary);">{{ q.company || q.name || '—' }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <AdminStatusPill :status="q.status" size="sm" />
                  <UIcon v-if="selectedQuotation?.id === q.id" name="i-lucide-check" class="size-4" style="color: var(--color-accent);" />
                </div>
              </button>
            </div>
            <div class="drawer-foot" :style="{ borderColor: 'var(--color-border)' }">
              <button
type="button" class="btn-pill btn-pill-accent w-full justify-center"
                :disabled="!selectedQuotation || tying"
                @click="selectedQuotation && askTie(selectedQuotation.id, selectedQuotation.reference_code)">
                {{ selectedQuotation ? `Tie ${selectedQuotation.reference_code}` : 'Select a quotation to tie' }}
              </button>
            </div>
          </aside>
        </div>
      </Transition>
    </Teleport>

    <!-- Tie / untie confirmation (layered above the drawer) -->
    <Teleport to="body">
      <Transition name="confirm-fade">
        <div v-if="pendingTie" class="confirm-overlay" @click.self="pendingTie = null">
          <div class="confirm-card" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
            <h2 class="text-[17px] font-bold tracking-tight mb-2" style="color: var(--color-text);">{{ tieConfirmCopy.title }}</h2>
            <p class="text-[13px] leading-relaxed mb-6" style="color: var(--color-text-secondary);">{{ tieConfirmCopy.body }}</p>
            <div class="flex items-center justify-end gap-2">
              <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :disabled="tying" @click="pendingTie = null">Cancel</button>
              <button type="button" class="btn-pill btn-pill-accent text-[13px]" :disabled="tying" @click="confirmTie">
                {{ tying ? 'Working…' : tieConfirmCopy.cta }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.anchor-or {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--color-text-tertiary);
}
.anchor-or::before,
.anchor-or::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--color-border);
}

.drawer-scrim {
  position: fixed;
  inset: 0;
  z-index: 90;
  display: flex;
  justify-content: flex-end;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(3px);
}
.drawer-panel {
  width: 100%;
  max-width: 420px;
  height: 100%;
  display: flex;
  flex-direction: column;
  border-left: 1px solid var(--color-border);
  box-shadow: var(--shadow-lg);
}
.drawer-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 20px 20px 12px;
}
.drawer-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 9999px;
  color: var(--color-text-secondary);
  transition: background 0.15s ease, color 0.15s ease;
}
.drawer-close:hover {
  background: var(--color-bg-secondary);
  color: var(--color-text);
}
.drawer-list {
  flex: 1;
  overflow-y: auto;
  padding: 8px 12px 16px;
}
.drawer-item {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 11px 14px;
  border-radius: 12px;
  text-align: left;
  transition: background 0.12s ease;
}
.drawer-item:hover {
  background: var(--color-bg-secondary);
}
.drawer-item:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.drawer-item-active,
.drawer-item-active:hover {
  background: var(--color-accent-soft);
}

.drawer-foot {
  padding: 14px 16px;
  border-top: 1px solid var(--color-border);
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

.drawer-enter-active,
.drawer-leave-active {
  transition: opacity 0.2s ease;
}
.drawer-enter-active .drawer-panel,
.drawer-leave-active .drawer-panel {
  transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1);
}
.drawer-enter-from,
.drawer-leave-to {
  opacity: 0;
}
.drawer-enter-from .drawer-panel,
.drawer-leave-to .drawer-panel {
  transform: translateX(100%);
}
@media (prefers-reduced-motion: reduce) {
  .drawer-enter-active,
  .drawer-leave-active,
  .drawer-enter-active .drawer-panel,
  .drawer-leave-active .drawer-panel {
    transition: none;
  }
}
</style>
