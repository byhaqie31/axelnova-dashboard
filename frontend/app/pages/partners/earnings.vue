<script setup lang="ts">
// Referrer-only (Task 9): earnings detail — the earned/estimated totals, the
// commission tier bands, and a per-referral commission breakdown, split out of
// the old single portal page.
definePageMeta({
  layout: 'partner',
  middleware: ['partner-auth', 'partner-type'],
  partnerType: 'referrer',
})
useHead({ title: 'Earnings — Partner Portal' })
useSeoMeta({ robots: 'noindex, nofollow' })

const { data, loadError, ensure } = usePartnerDashboard()

onMounted(ensure)

// The tier → % bands, in ascending order, with the referrer's own tier flagged.
const tierBands = computed(() => {
  const tiers = data.value?.partner.commission_tiers ?? {}
  return Object.entries(tiers)
    .map(([tier, pct]) => ({ tier, pct, own: tier === data.value?.partner.relationship_tier }))
    .sort((a, b) => a.pct - b.pct)
})

const tierLabels: Record<string, string> = {
  cold: 'Cold — a lead you know of',
  warm: 'Warm — you can introduce them',
  closed: 'Closed — ready to talk',
}
</script>

<template>
  <div v-if="loadError" class="rounded-2xl border p-6 text-center" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
    <p class="text-[14px]" style="color: var(--color-text-secondary);">We couldn't load your earnings. Please refresh the page.</p>
  </div>

  <div v-else-if="data" class="space-y-8">
    <div>
      <h1 class="text-[24px] sm:text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Earnings</h1>
      <p class="text-[13px] mt-1" style="color: var(--color-text-secondary);">
        Commission is derived per referral from the collected project value.
        Payouts are arranged manually; we'll email you when a referral converts.
      </p>
    </div>

    <!-- Totals -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
        <p class="text-[12px] font-medium mb-1.5" style="color: var(--color-text-tertiary);">Earned (collected)</p>
        <p class="text-[26px] font-bold tracking-tight" style="color: var(--color-success);">{{ myr(data.stats.earned_myr) }}</p>
        <p class="text-[12px] mt-1" style="color: var(--color-text-secondary);">Your share of what converted clients have already paid.</p>
      </div>
      <div class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
        <p class="text-[12px] font-medium mb-1.5" style="color: var(--color-text-tertiary);">Estimated</p>
        <p class="text-[26px] font-bold tracking-tight" style="color: var(--color-text);">{{ myr(data.stats.estimated_myr) }}</p>
        <p class="text-[12px] mt-1" style="color: var(--color-text-secondary);">What's still to come as remaining contract value is collected.</p>
      </div>
    </div>

    <!-- Commission tier bands -->
    <div class="rounded-2xl border p-5 sm:p-6" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
      <h2 class="text-[16px] font-semibold tracking-tight mb-1" style="color: var(--color-text);">Commission bands</h2>
      <p class="text-[12px] mb-4" style="color: var(--color-text-secondary);">
        Each referral earns by how closely you're connected to the business. Your default tier is highlighted;
        individual referrals can sit in a different band.
      </p>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div
          v-for="band in tierBands"
          :key="band.tier"
          class="rounded-xl border p-4"
          :style="band.own
            ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)' }
            : { borderColor: 'var(--color-border)', background: 'var(--color-bg-secondary)' }"
        >
          <div class="flex items-center justify-between gap-2">
            <p class="text-[20px] font-bold tracking-tight" :style="{ color: band.own ? 'var(--color-accent)' : 'var(--color-text)' }">
              {{ band.pct }}%
            </p>
            <span
              v-if="band.own"
              class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full"
              :style="{ color: 'var(--color-accent)', background: 'var(--color-bg)' }"
            >
              Your tier
            </span>
          </div>
          <p class="text-[12px] mt-1.5" :style="{ color: band.own ? 'var(--color-text)' : 'var(--color-text-secondary)' }">
            {{ tierLabels[band.tier] ?? band.tier }}
          </p>
        </div>
      </div>
    </div>

    <!-- Per-referral breakdown -->
    <div>
      <h2 class="text-[16px] font-semibold tracking-tight mb-3" style="color: var(--color-text);">Per-referral breakdown</h2>
      <div
        v-if="data.referrals.length === 0" class="rounded-2xl border p-6 text-center"
        :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }"
      >
        <p class="text-[14px]" style="color: var(--color-text-secondary);">
          Nothing to break down yet — refer a business from the
          <NuxtLink to="/partners/referrals" style="color: var(--color-accent);">Referrals</NuxtLink> tab.
        </p>
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
              {{ r.commission_pct }}% band<span v-if="!r.has_order && r.earned_myr == null"> · no order linked yet</span><span v-else-if="r.earned_myr == null"> · estimated once your client pays</span>
            </p>
          </div>
          <p
            class="text-[14px] font-semibold tabular-nums shrink-0"
            :style="{ color: r.earned_myr != null ? 'var(--color-success)' : 'var(--color-text-tertiary)' }"
          >
            {{ r.earned_myr != null ? myr(r.earned_myr) : '—' }}
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Loading skeleton -->
  <div v-else class="space-y-8">
    <div class="h-8 w-56 rounded-lg" style="background: var(--color-bg-secondary);" />
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div v-for="i in 2" :key="i" class="h-28 rounded-2xl" style="background: var(--color-bg-secondary);" />
    </div>
  </div>
</template>
