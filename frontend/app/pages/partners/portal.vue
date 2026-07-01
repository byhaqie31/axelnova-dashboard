<script setup lang="ts">
definePageMeta({ layout: 'partner', middleware: 'partner-auth' })
useHead({ title: 'Partner Portal — Axel Nova' })
useSeoMeta({ robots: 'noindex, nofollow' })

interface DashboardReferral {
  id: number
  business_name: string
  status: string
  relationship_tier: string
  commission_pct: number
  converted: boolean
  earned_myr: number | null
  created_at: string | null
}
interface Dashboard {
  partner: { name: string, code: string, relationship_tier: string, commission_tiers: Record<string, number> }
  stats: { earned_myr: number, pending_myr: number, referrals_count: number }
  ref_link: string
  referrals: DashboardReferral[]
}

const { apiFetch } = usePartnerAuth()

const data = ref<Dashboard | null>(null)
const loadError = ref(false)

async function load() {
  try {
    data.value = await apiFetch<Dashboard>('/api/v1/partner/dashboard')
  }
  catch {
    loadError.value = true
  }
}
onMounted(load)

const myr = (n: number) => new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(n || 0)

const tierLabels: Record<string, string> = { cold: 'Cold', warm: 'Warm', closed: 'Closed' }

// Commission varies per referral by relationship tier — surface the available bands
// (e.g. "5% / 10% / 15%") rather than a single fixed rate.
const tierPcts = computed(() =>
  Object.values(data.value?.partner.commission_tiers ?? {}).sort((a, b) => a - b),
)

// Status → pill styling (mirrors the referral lifecycle).
type PillStyle = { label: string, color: string, bg: string }
const PILL_NEW: PillStyle = { label: 'New', color: 'var(--color-text-secondary)', bg: 'var(--color-bg-secondary)' }
const statusStyle: Record<string, PillStyle> = {
  new: PILL_NEW,
  contacted: { label: 'Contacted', color: 'var(--color-accent)', bg: 'var(--color-accent-soft)' },
  qualified: { label: 'Qualified', color: 'var(--color-accent)', bg: 'var(--color-accent-soft)' },
  converted: { label: 'Converted', color: 'var(--color-success)', bg: 'var(--color-success-soft, var(--color-bg-secondary))' },
  rejected: { label: 'Not proceeding', color: 'var(--color-text-tertiary)', bg: 'var(--color-bg-secondary)' },
}
const pill = (s: string): PillStyle => statusStyle[s] ?? PILL_NEW

// Copy the ?ref link.
const copied = ref(false)
async function copyLink() {
  if (!data.value) return
  try {
    await navigator.clipboard.writeText(data.value.ref_link)
    copied.value = true
    setTimeout(() => { copied.value = false }, 1800)
  }
  catch {
    // Clipboard blocked — the input is selectable as a fallback.
  }
}

// "Refer another" — bound to the signed-in partner, so no referrer fields.
const form = reactive({
  business_name: '',
  business_contact_name: '',
  business_email: '',
  business_phone: '',
  relationship_tier: 'cold' as 'cold' | 'warm' | 'closed',
  notes: '',
})
const tiers: { value: 'cold' | 'warm' | 'closed', label: string }[] = [
  { value: 'cold', label: 'Cold — a lead I know of' },
  { value: 'warm', label: 'Warm — I can introduce them' },
  { value: 'closed', label: 'Closed — ready to talk' },
]
const submitting = ref(false)
const submitError = ref('')
const submitted = ref(false)

const canSubmit = computed(() => form.business_name.trim().length >= 2)

async function submitReferral() {
  if (!canSubmit.value || submitting.value) return
  submitting.value = true
  submitError.value = ''
  try {
    await apiFetch('/api/v1/partner/referrals', {
      method: 'POST',
      body: {
        business_name: form.business_name,
        business_contact_name: form.business_contact_name || null,
        business_email: form.business_email || null,
        business_phone: form.business_phone || null,
        relationship_tier: form.relationship_tier,
        notes: form.notes || null,
      },
    })
    submitted.value = true
    form.business_name = ''
    form.business_contact_name = ''
    form.business_email = ''
    form.business_phone = ''
    form.relationship_tier = 'cold'
    form.notes = ''
    await load()
    setTimeout(() => { submitted.value = false }, 4000)
  }
  catch {
    submitError.value = 'Something went wrong. Please try again.'
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <div v-if="loadError" class="rounded-2xl border p-6 text-center" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
    <p class="text-[14px]" style="color: var(--color-text-secondary);">We couldn't load your dashboard. Please refresh the page.</p>
  </div>

  <div v-else-if="data" class="space-y-8">
    <!-- Header -->
    <div>
      <h1 class="text-[24px] sm:text-[28px] font-bold tracking-tight" style="color: var(--color-text);">
        Welcome back, {{ data.partner.name.split(' ')[0] }}
      </h1>
      <p class="text-[13px] mt-1" style="color: var(--color-text-secondary);">
        Commission is earned <span style="color: var(--color-text);">per referral</span> — {{ tierPcts.map(p => `${p}%`).join(' / ') }} of the collected
        project value, depending on how closely you're connected to each business you refer.
        Payouts are arranged manually; we'll email you when a referral converts.
      </p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
        <p class="text-[12px] font-medium mb-1.5" style="color: var(--color-text-tertiary);">Earned (collected)</p>
        <p class="text-[26px] font-bold tracking-tight" style="color: var(--color-success);">{{ myr(data.stats.earned_myr) }}</p>
      </div>
      <div class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
        <p class="text-[12px] font-medium mb-1.5" style="color: var(--color-text-tertiary);">Pending (contracted)</p>
        <p class="text-[26px] font-bold tracking-tight" style="color: var(--color-text);">{{ myr(data.stats.pending_myr) }}</p>
      </div>
      <div class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
        <p class="text-[12px] font-medium mb-1.5" style="color: var(--color-text-tertiary);">Referrals</p>
        <p class="text-[26px] font-bold tracking-tight" style="color: var(--color-text);">{{ data.stats.referrals_count }}</p>
      </div>
    </div>

    <!-- Referral link -->
    <div class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
      <p class="text-[13px] font-semibold mb-1" style="color: var(--color-text);">Your referral link</p>
      <p class="text-[12px] mb-3" style="color: var(--color-text-secondary);">
        Share this link. Anyone who reaches out within 90 days is credited to you.
      </p>
      <div class="flex flex-col sm:flex-row gap-2">
        <input :value="data.ref_link" readonly class="contact-input flex-1"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }"
          @focus="($event.target as HTMLInputElement).select()" />
        <button type="button" class="btn-pill btn-pill-accent partner-copy-btn justify-center" @click="copyLink">
          <UIcon :name="copied ? 'i-lucide-check' : 'i-lucide-copy'" class="size-4" />
          {{ copied ? 'Copied' : 'Copy' }}
        </button>
      </div>
    </div>

    <!-- Referrals list -->
    <div>
      <h2 class="text-[16px] font-semibold tracking-tight mb-3" style="color: var(--color-text);">Your referrals</h2>
      <div v-if="data.referrals.length === 0" class="rounded-2xl border p-6 text-center"
        :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
        <p class="text-[14px]" style="color: var(--color-text-secondary);">No referrals yet. Share your link or refer a business below.</p>
      </div>
      <div v-else class="overflow-hidden rounded-2xl border" :style="{ borderColor: 'var(--color-border)' }">
        <div
          v-for="(r, i) in data.referrals"
          :key="r.id"
          class="px-4 sm:px-5 py-3.5 flex items-center justify-between gap-4"
          :class="i < data.referrals.length - 1 ? 'border-b' : ''"
          :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
        >
          <div class="min-w-0">
            <p class="text-[14px] font-medium truncate" style="color: var(--color-text);">{{ r.business_name }}</p>
            <p class="text-[12px] mt-0.5" style="color: var(--color-text-secondary);">
              {{ tierLabels[r.relationship_tier] ?? r.relationship_tier }} · {{ r.commission_pct }}% commission<span v-if="r.earned_myr" style="color: var(--color-success);"> · {{ myr(r.earned_myr) }} earned</span>
            </p>
          </div>
          <span class="text-[11px] font-medium px-2.5 py-1 rounded-full shrink-0"
            :style="{ color: pill(r.status).color, background: pill(r.status).bg }">
            {{ pill(r.status).label }}
          </span>
        </div>
      </div>
    </div>

    <!-- Refer another -->
    <div class="rounded-2xl border p-5 sm:p-6" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
      <h2 class="text-[16px] font-semibold tracking-tight mb-1" style="color: var(--color-text);">Refer another business</h2>
      <p class="text-[12px] mb-5" style="color: var(--color-text-secondary);">You're signed in, so we've already got your details.</p>

      <p v-if="submitted" class="text-[13px] mb-4 flex items-center gap-1.5" style="color: var(--color-success);">
        <UIcon name="i-lucide-check-circle" class="size-4 shrink-0" />
        Referral submitted — thank you!
      </p>

      <form class="space-y-4" @submit.prevent="submitReferral">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Business name *</label>
            <input v-model="form.business_name" type="text" required placeholder="Acme Sdn Bhd"
              class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }" />
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Contact name</label>
            <input v-model="form.business_contact_name" type="text" placeholder="Who to reach"
              class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }" />
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Business email</label>
            <input v-model="form.business_email" type="email" placeholder="hello@acme.com"
              class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }" />
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Business phone</label>
            <input v-model="form.business_phone" type="tel" placeholder="+60…"
              class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }" />
          </div>
        </div>

        <div class="space-y-1.5">
          <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">How well do you know them?</label>
          <select v-model="form.relationship_tier" class="contact-input"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }">
            <option v-for="t in tiers" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>

        <div class="space-y-1.5">
          <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Notes</label>
          <textarea v-model="form.notes" rows="3" placeholder="Anything helpful about the referral"
            class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }" />
        </div>

        <p v-if="submitError" class="text-[12px] flex items-center gap-1.5" style="color: var(--color-danger);">
          <UIcon name="i-lucide-alert-circle" class="size-4 shrink-0" />
          {{ submitError }}
        </p>

        <button type="submit" class="btn-pill btn-pill-accent partner-submit-btn justify-center" :disabled="!canSubmit || submitting">
          {{ submitting ? 'Submitting…' : 'Submit referral →' }}
        </button>
      </form>
    </div>
  </div>

  <!-- Loading skeleton -->
  <div v-else class="space-y-8">
    <div class="h-8 w-56 rounded-lg" style="background: var(--color-bg-secondary);" />
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div v-for="i in 3" :key="i" class="h-24 rounded-2xl" style="background: var(--color-bg-secondary);" />
    </div>
  </div>
</template>

<style scoped>
.partner-copy-btn {
  height: 44px;
  padding: 0 18px;
}
.partner-submit-btn {
  height: 46px;
}
</style>
