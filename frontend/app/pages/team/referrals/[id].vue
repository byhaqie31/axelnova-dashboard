<script setup lang="ts">
definePageMeta({ layout: 'team', middleware: 'team-auth' })

const route = useRoute()
const { apiFetch } = useTeamAuth()
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
  notes: string | null
  status: string
  agreed_terms: boolean
  created_at: string
}

const referral = ref<Referral | null>(null)
const loading = ref(true)
const error = ref('')
const statusLoading = ref(false)

useHead(() => ({
  title: referral.value ? `${referral.value.referrer_name} — Referral` : 'Referral — Team',
}))

async function fetchReferral() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Referral }>(`/api/v1/team/referrals/${route.params.id}`)
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
    await apiFetch(`/api/v1/team/referrals/${referral.value.id}/status`, {
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

onMounted(fetchReferral)

function fmtDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'long', year: 'numeric' })
}

const tierLabels: Record<string, string> = { cold: 'Cold', warm: 'Warm', closed: 'Closed' }
// Full lifecycle — the marketer works a lead end to end. Linking the converted
// referral to its order (the money step) stays a cockpit action.
const statusOptions = ['new', 'contacted', 'qualified', 'converted', 'rejected']
const statusLabels: Record<string, string> = {
  new: 'New', contacted: 'Contacted', qualified: 'Qualified', converted: 'Converted', rejected: 'Rejected',
}
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <NuxtLink to="/team/referrals" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All referrals
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" style="color: var(--color-danger);">{{ error }}</p>

    <div v-else-if="referral" class="grid lg:grid-cols-[1fr_300px] gap-8 items-start">

      <div class="space-y-6">

        <!-- Referrer -->
        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <div class="flex items-start justify-between flex-wrap gap-4 mb-5">
            <div>
              <p class="text-[22px] font-bold tracking-tight" style="color: var(--color-text);">{{ referral.referrer_name }}</p>
              <p class="text-[13px] mt-0.5" style="color: var(--color-text-secondary);">
                {{ tierLabels[referral.relationship_tier] }} tier
                <span style="color: var(--color-accent);">· {{ referral.commission_tier_pct }}%</span>
              </p>
            </div>
            <AdminStatusPill :status="referral.status" size="md" />
          </div>
          <div class="grid sm:grid-cols-2 gap-4 pt-4 border-t" style="border-color: var(--color-border);">
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Email</p>
              <a :href="`mailto:${referral.referrer_email}`" class="text-[13px] font-medium break-all" style="color: var(--color-accent);">{{ referral.referrer_email }}</a>
            </div>
            <div v-if="referral.referrer_phone">
              <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Phone</p>
              <a :href="`tel:${referral.referrer_phone}`" class="text-[13px] font-medium" style="color: var(--color-text);">{{ referral.referrer_phone }}</a>
            </div>
          </div>
        </div>

        <!-- Referred business -->
        <div class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Referred business</p>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Company</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ referral.business_name }}</p>
            </div>
            <div v-if="referral.business_contact_name">
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Contact</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ referral.business_contact_name }}</p>
            </div>
            <div v-if="referral.business_email">
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Email</p>
              <a :href="`mailto:${referral.business_email}`" class="text-[13px] break-all" style="color: var(--color-accent);">{{ referral.business_email }}</a>
            </div>
            <div v-if="referral.business_phone">
              <p class="text-[11px] mb-0.5" style="color: var(--color-text-tertiary);">Phone</p>
              <a :href="`tel:${referral.business_phone}`" class="text-[13px]" style="color: var(--color-text);">{{ referral.business_phone }}</a>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div v-if="referral.notes" class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Notes</p>
          <p class="text-[14px] leading-relaxed whitespace-pre-line" style="color: var(--color-text);">{{ referral.notes }}</p>
        </div>

      </div>

      <!-- Sidebar -->
      <div class="lg:sticky lg:top-20 space-y-4">

        <!-- Status -->
        <div class="rounded-2xl border p-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Update status</p>
          <div class="flex flex-wrap gap-2">
            <button v-for="s in statusOptions" :key="s" type="button"
              class="status-pill status-pill-button"
              :class="{ 'opacity-50': statusLoading }"
              :data-status="referral.status === s ? s : ''"
              :data-active="referral.status === s"
              :disabled="statusLoading || referral.status === s"
              @click="updateStatus(s)">
              {{ statusLabels[s] }}
            </button>
          </div>
        </div>

        <!-- Meta -->
        <div class="rounded-xl border px-4 py-3.5 space-y-2"
          :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }">
          <div class="flex justify-between">
            <span class="text-[11px]" style="color: var(--color-text-tertiary);">Submitted</span>
            <span class="text-[11px]" style="color: var(--color-text-secondary);">{{ fmtDate(referral.created_at) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-[11px]" style="color: var(--color-text-tertiary);">Agreed terms</span>
            <span class="text-[11px]" style="color: var(--color-text-secondary);">{{ referral.agreed_terms ? 'Yes' : 'No' }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
