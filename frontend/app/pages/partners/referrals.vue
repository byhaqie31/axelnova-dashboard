<script setup lang="ts">
// Referrer-only (Task 9): the referral list + "refer another" form, split out
// of the old single portal page. Wrong-type visitors bounce to /partners/home
// (partner-type middleware); the API 403s investor tokens regardless.
definePageMeta({
  layout: 'partner',
  middleware: ['partner-auth', 'partner-type'],
  partnerType: 'referrer',
})
useHead({ title: 'Referrals — Partner Portal' })
useSeoMeta({ robots: 'noindex, nofollow' })

const { data, loadError, ensure, refresh } = usePartnerDashboard()

onMounted(ensure)

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

const { apiFetch } = usePartnerAuth()

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
    await refresh()
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
    <p class="text-[14px]" style="color: var(--color-text-secondary);">We couldn't load your referrals. Please refresh the page.</p>
  </div>

  <div v-else-if="data" class="space-y-8">
    <div>
      <h1 class="text-[24px] sm:text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Referrals</h1>
      <p class="text-[13px] mt-1" style="color: var(--color-text-secondary);">
        Every business you've referred, and where each one stands.
      </p>
    </div>

    <!-- Referrals list -->
    <div>
      <div
        v-if="data.referrals.length === 0" class="rounded-2xl border p-6 text-center"
        :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }"
      >
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
              {{ r.commission_pct }}% commission<span v-if="r.earned_myr != null" style="color: var(--color-success);"> · {{ myr(r.earned_myr) }} earned</span><span v-else-if="r.has_order"> · Estimated once your client pays</span>
            </p>
          </div>
          <span
            class="text-[11px] font-medium px-2.5 py-1 rounded-full shrink-0"
            :style="{ color: referralPill(r.status).color, background: referralPill(r.status).bg }"
          >
            {{ referralPill(r.status).label }}
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
            <input
              v-model="form.business_name" type="text" required placeholder="Acme Sdn Bhd"
              class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }"
            >
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Contact name</label>
            <input
              v-model="form.business_contact_name" type="text" placeholder="Who to reach"
              class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }"
            >
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Business email</label>
            <input
              v-model="form.business_email" type="email" placeholder="hello@acme.com"
              class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }"
            >
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Business phone</label>
            <input
              v-model="form.business_phone" type="tel" placeholder="+60…"
              class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }"
            >
          </div>
        </div>

        <!-- Relationship tier — pill picker (§12.6, not a native select) -->
        <div class="space-y-1.5">
          <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">How well do you know them?</label>
          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="t in tiers"
              :key="t.value"
              type="button"
              class="standard-pill"
              :style="form.relationship_tier === t.value
                ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
                : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
              @click="form.relationship_tier = t.value"
            >
              {{ t.label }}
            </button>
          </div>
        </div>

        <div class="space-y-1.5">
          <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Notes</label>
          <textarea
            v-model="form.notes" rows="3" placeholder="Anything helpful about the referral"
            class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }"
          />
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
    <div class="h-48 rounded-2xl" style="background: var(--color-bg-secondary);" />
  </div>
</template>

<style scoped>
.partner-submit-btn {
  height: 46px;
}
</style>
