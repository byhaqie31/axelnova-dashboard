<script setup lang="ts">
// Shared /partners/home dashboard (Task 9) — one landing for both partner
// kinds, moved off bare /partners because that route is owned by the public
// marketing landing (pages/public/partners/index.vue). Referrers get the
// stats trio + their ?ref link (referral list + submit form moved to
// /partners/referrals, earnings detail to /partners/earnings); investors get
// a welcome overview with a premium "portfolio coming online" empty state
// (no investor content model yet).
definePageMeta({ layout: 'partner', middleware: 'partner-auth' })
useHead({ title: 'Partner Portal — Axel Nova' })
useSeoMeta({ robots: 'noindex, nofollow' })

const { me, refresh: fetchMe } = usePartnerMe()
const { data, loadError, ensure: loadDashboard } = usePartnerDashboard()

onMounted(async () => {
  const current = me.value ?? await fetchMe()
  if (current?.type === 'referrer') await loadDashboard()
})

// Commission varies per referral by relationship tier — surface the available
// bands (e.g. "5% / 10% / 15%") rather than a single fixed rate.
const tierPcts = computed(() =>
  Object.values(data.value?.partner.commission_tiers ?? {}).sort((a, b) => a - b),
)

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

const firstName = computed(() => me.value?.profile?.name?.split(' ')[0] ?? '')
</script>

<template>
  <!-- Investor overview -->
  <div v-if="me?.type === 'investor'" class="space-y-8">
    <div>
      <h1 class="text-[24px] sm:text-[28px] font-bold tracking-tight" style="color: var(--color-text);">
        Welcome{{ firstName ? `, ${firstName}` : '' }}
      </h1>
      <p class="text-[13px] mt-1" style="color: var(--color-text-secondary);">
        Your investor workspace with Axel Nova Ventures.
      </p>
    </div>

    <div
      class="rounded-2xl border p-10 sm:p-12 text-center"
      :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }"
    >
      <div
        class="inline-flex items-center justify-center size-12 rounded-2xl mb-4"
        :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
      >
        <UIcon name="i-lucide-briefcase" class="size-6" />
      </div>
      <p class="text-[15px] font-semibold tracking-tight mb-1" :style="{ color: 'var(--color-text)' }">
        Your portfolio is coming online
      </p>
      <p class="text-[13px] max-w-md mx-auto" :style="{ color: 'var(--color-text-secondary)' }">
        Deal rooms, project documents, and performance reports will appear here as they're
        shared with you. Check the Documents and Reports tabs — we'll email you when
        something new lands.
      </p>
    </div>
  </div>

  <!-- Referrer dashboard -->
  <div v-else-if="me?.type === 'referrer'">
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
          <p class="text-[12px] font-medium mb-1.5" style="color: var(--color-text-tertiary);">Estimated</p>
          <p class="text-[26px] font-bold tracking-tight" style="color: var(--color-text);">{{ myr(data.stats.estimated_myr) }}</p>
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
          <input
            :value="data.ref_link" readonly class="contact-input flex-1"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }"
            @focus="($event.target as HTMLInputElement).select()"
          >
          <button type="button" class="btn-pill btn-pill-accent partner-copy-btn justify-center" @click="copyLink">
            <UIcon :name="copied ? 'i-lucide-check' : 'i-lucide-copy'" class="size-4" />
            {{ copied ? 'Copied' : 'Copy' }}
          </button>
        </div>
      </div>

      <!-- Jump-offs to the split pages -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <NuxtLink
          to="/partners/referrals"
          class="rounded-2xl border p-5 flex items-center justify-between gap-4 transition-shadow hover:shadow-md"
          :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }"
        >
          <div>
            <p class="text-[13px] font-semibold" style="color: var(--color-text);">Your referrals</p>
            <p class="text-[12px] mt-0.5" style="color: var(--color-text-secondary);">Track every business you've referred, or add another.</p>
          </div>
          <UIcon name="i-lucide-arrow-right" class="size-4 shrink-0" :style="{ color: 'var(--color-text-tertiary)' }" />
        </NuxtLink>
        <NuxtLink
          to="/partners/earnings"
          class="rounded-2xl border p-5 flex items-center justify-between gap-4 transition-shadow hover:shadow-md"
          :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }"
        >
          <div>
            <p class="text-[13px] font-semibold" style="color: var(--color-text);">Earnings</p>
            <p class="text-[12px] mt-0.5" style="color: var(--color-text-secondary);">Commission bands and the per-referral breakdown.</p>
          </div>
          <UIcon name="i-lucide-arrow-right" class="size-4 shrink-0" :style="{ color: 'var(--color-text-tertiary)' }" />
        </NuxtLink>
      </div>
    </div>

    <!-- Loading skeleton -->
    <div v-else class="space-y-8">
      <div class="h-8 w-56 rounded-lg" style="background: var(--color-bg-secondary);" />
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div v-for="i in 3" :key="i" class="h-24 rounded-2xl" style="background: var(--color-bg-secondary);" />
      </div>
    </div>
  </div>

  <!-- /me still resolving -->
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
</style>
