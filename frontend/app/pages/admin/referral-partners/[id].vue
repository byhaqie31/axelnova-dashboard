<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()

interface ReferralRow {
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
  referrals: ReferralRow[]
}

const partner = ref<PartnerDetail | null>(null)
const loading = ref(true)
const error = ref('')
const flash = ref('')

const tierLabels: Record<string, string> = { cold: 'Cold', warm: 'Warm', closed: 'Closed' }

type PillStyle = { label: string, color: string, bg: string }
const PILL_NEW: PillStyle = { label: 'New', color: 'var(--color-accent)', bg: 'var(--color-accent-soft)' }
const referralStatusStyle: Record<string, PillStyle> = {
  new: PILL_NEW,
  contacted: { label: 'Contacted', color: 'var(--color-warning, var(--color-accent))', bg: 'var(--color-warning-soft, var(--color-accent-soft))' },
  qualified: { label: 'Qualified', color: 'var(--color-accent)', bg: 'var(--color-accent-soft)' },
  draft: { label: 'Draft', color: 'var(--color-text-tertiary)', bg: 'var(--color-bg-secondary)' },
  converted: { label: 'Converted', color: 'var(--color-success)', bg: 'var(--color-success-soft, var(--color-bg-secondary))' },
  rejected: { label: 'Rejected', color: 'var(--color-danger)', bg: 'var(--color-danger-soft, var(--color-bg-secondary))' },
}
const pill = (s: string): PillStyle => referralStatusStyle[s] ?? PILL_NEW

type PartnerPillStyle = { label: string, color: string, bg: string }
const PILL_PENDING: PartnerPillStyle = { label: 'Pending', color: 'var(--color-warning, var(--color-accent))', bg: 'var(--color-warning-soft, var(--color-accent-soft))' }
const partnerStatusStyle: Record<string, PartnerPillStyle> = {
  pending: PILL_PENDING,
  active: { label: 'Active', color: 'var(--color-success)', bg: 'var(--color-success-soft, var(--color-bg-secondary))' },
  paused: { label: 'Paused', color: 'var(--color-text-tertiary)', bg: 'var(--color-bg-secondary)' },
}
const partnerPill = (s: string): PartnerPillStyle => partnerStatusStyle[s] ?? PILL_PENDING

const currency = new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' })
function fmtMyr(amount: number | string | null | undefined) {
  return currency.format(Number(amount ?? 0))
}

const tierBands = computed(() => {
  const tiers = partner.value?.commission_tiers
  if (!tiers) return '—'
  return Object.values(tiers).map(v => `${v}%`).join(' / ')
})

async function fetchPartner() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: PartnerDetail }>(`/api/v1/admin/referral-partners/${route.params.id}`)
    partner.value = res.data
  }
  catch {
    error.value = 'Failed to load referral partner. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchPartner)

function fmtDate(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
}

// Confirm-before-act. Approve issues the first passcode; reset invalidates the old
// one — both email the partner, so both are confirmed. The passcode is never shown.
type PendingAction = { type: 'approve' | 'reset' }
const pending = ref<PendingAction | null>(null)
const acting = ref(false)

function ask(type: 'approve' | 'reset') {
  flash.value = ''
  pending.value = { type }
}

const confirmCopy = computed(() => {
  if (!pending.value || !partner.value) return { title: '', body: '', cta: '' }
  const { type } = pending.value
  const p = partner.value
  return type === 'approve'
    ? {
        title: `Approve ${p.name}?`,
        body: `This activates their account and emails a one-time passcode to ${p.email}. The passcode is never shown here.`,
        cta: 'Approve & email passcode',
      }
    : {
        title: `Reset passcode for ${p.name}?`,
        body: `This invalidates their current passcode and emails a new one to ${p.email}. They can't log in with the old passcode afterwards.`,
        cta: 'Reset & email new passcode',
      }
})

async function confirmAction() {
  if (!pending.value || !partner.value || acting.value) return
  acting.value = true
  const { type } = pending.value
  const path = type === 'approve'
    ? `/api/v1/admin/referral-partners/${partner.value.id}/approve`
    : `/api/v1/admin/referral-partners/${partner.value.id}/reset-passcode`
  try {
    const res = await apiFetch<{ message: string }>(path, { method: 'POST' })
    flash.value = res.message
    pending.value = null
    await fetchPartner()
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
  <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <NuxtLink to="/admin/referral-partners" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All referral partners
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading partner…</div>
    <p v-else-if="error && !partner" style="color: var(--color-danger);">{{ error }}</p>

    <template v-else-if="partner">

      <p v-if="flash" class="mb-6 text-[13px] flex items-center gap-1.5" style="color: var(--color-success);">
        <UIcon name="i-lucide-check-circle" class="size-4 shrink-0" />
        {{ flash }}
      </p>
      <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

      <!-- Header card -->
      <div class="rounded-2xl border p-6 mb-8"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <div class="flex items-start justify-between flex-wrap gap-4 mb-5">
          <div>
            <div class="flex items-center gap-3 flex-wrap">
              <p class="text-[22px] font-bold tracking-tight" style="color: var(--color-text);">{{ partner.name }}</p>
              <span class="text-[11px] font-medium px-2.5 py-1 rounded-full"
                :style="{ color: partnerPill(partner.status).color, background: partnerPill(partner.status).bg }">
                {{ partnerPill(partner.status).label }}
              </span>
            </div>
            <p class="text-[14px] mt-0.5" style="color: var(--color-text-secondary);">{{ partner.email }}</p>
          </div>
          <div class="flex items-center gap-2">
            <button v-if="partner.status === 'pending'" type="button" class="btn-pill btn-pill-accent text-[13px]" @click="ask('approve')">
              Approve
            </button>
            <button v-else-if="partner.status === 'active'" type="button" class="btn-pill btn-pill-ghost text-[13px]" @click="ask('reset')">
              Reset passcode
            </button>
          </div>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4 pt-4 border-t" style="border-color: var(--color-border);">
          <div>
            <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Referral code</p>
            <span class="text-[12px] font-mono px-1.5 py-0.5 rounded" :style="{ background: 'var(--color-bg-secondary)', color: 'var(--color-text-secondary)' }">?ref={{ partner.code }}</span>
          </div>
          <div>
            <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Tier bands</p>
            <p class="text-[13px] font-semibold tabular-nums" style="color: var(--color-accent);">{{ tierBands }}</p>
          </div>
          <div>
            <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Earned</p>
            <p class="text-[13px] font-semibold tabular-nums" style="color: var(--color-success);">{{ fmtMyr(partner.stats.earned_myr) }}</p>
          </div>
          <div>
            <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Estimated</p>
            <p class="text-[13px] font-semibold tabular-nums" style="color: var(--color-text);">{{ fmtMyr(partner.stats.estimated_myr) }}</p>
          </div>
          <div>
            <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Last login</p>
            <p class="text-[13px]" style="color: var(--color-text);">{{ fmtDate(partner.last_login_at) }}</p>
          </div>
        </div>
      </div>

      <!-- Referrals table -->
      <h2 class="text-[15px] font-semibold tracking-tight mb-4" style="color: var(--color-text);">
        Referrals <span class="font-normal" style="color: var(--color-text-tertiary);">({{ partner.stats.referrals_count }})</span>
      </h2>

      <div v-if="!partner.referrals.length" class="text-center py-16 rounded-2xl border" style="color: var(--color-text-secondary); border-color: var(--color-border);">
        No referrals from this partner yet.
      </div>

      <div v-else class="admin-table-card">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr>
                <th v-for="h in ['Business', 'Status', 'Quotation', 'Rate', 'Earned']" :key="h"
                  class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
                  {{ h }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in partner.referrals" :key="r.id" class="admin-table-row" style="cursor: pointer;"
                @click="navigateTo(`/admin/referrals/${r.id}`)">
                <td class="px-4 py-3.5">
                  <p class="text-[13px] font-medium" style="color: var(--color-text);">{{ r.business_name }}</p>
                  <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ r.referrer_name }} · {{ tierLabels[r.relationship_tier] }}</p>
                </td>
                <td class="px-4 py-3.5">
                  <span class="text-[11px] font-medium px-2.5 py-1 rounded-full" :style="{ color: pill(r.status).color, background: pill(r.status).bg }">
                    {{ pill(r.status).label }}
                  </span>
                </td>
                <td class="px-4 py-3.5">
                  <span v-if="r.quotation_reference" class="text-[12px] font-mono" style="color: var(--color-text-secondary);">{{ r.quotation_reference }}</span>
                  <span v-else class="text-[12px]" style="color: var(--color-text-tertiary);">—</span>
                </td>
                <td class="px-4 py-3.5 text-[13px] tabular-nums" style="color: var(--color-text);">{{ r.effective_pct }}%</td>
                <td class="px-4 py-3.5">
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-[13px] tabular-nums" style="color: var(--color-text);">{{ r.status === 'converted' ? fmtMyr(r.earned_myr) : '—' }}</span>
                    <UIcon name="i-lucide-chevron-right" class="size-4 shrink-0" style="color: var(--color-text-tertiary);" />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
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
    </template>
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
