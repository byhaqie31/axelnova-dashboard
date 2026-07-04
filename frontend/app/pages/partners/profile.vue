<script setup lang="ts">
// Shared /partners/profile (Task 9): account identity for both partner kinds —
// type badge + email for everyone; referrers also see code/tier/commission,
// investors see company. Read-only: partner details are managed by staff.
import type { PartnerInvestorProfile, PartnerReferrerProfile } from '~/composables/usePartnerMe'

definePageMeta({ layout: 'partner', middleware: 'partner-auth' })
useHead({ title: 'Profile — Partner Portal' })
useSeoMeta({ robots: 'noindex, nofollow' })

const { me, refresh } = usePartnerMe()

onMounted(refresh)

const referrerProfile = computed(() =>
  me.value?.type === 'referrer' ? me.value.profile as PartnerReferrerProfile | null : null,
)
const investorProfile = computed(() =>
  me.value?.type === 'investor' ? me.value.profile as PartnerInvestorProfile | null : null,
)

const typeMeta = computed(() => me.value?.type === 'investor'
  ? { label: 'Investor', icon: 'i-lucide-briefcase' }
  : { label: 'Referral partner', icon: 'i-lucide-users' },
)

const tierLabel = computed(() => {
  const t = referrerProfile.value?.relationship_tier
  return t ? t.charAt(0).toUpperCase() + t.slice(1) : '—'
})
</script>

<template>
  <div class="max-w-2xl">
    <h1 class="text-[24px] sm:text-[28px] font-bold tracking-tight mb-1" style="color: var(--color-text);">Profile</h1>
    <p class="text-[13px] mb-8" style="color: var(--color-text-secondary);">Your partner account details. Changes are managed by our team — just reply to any of our emails.</p>

    <div v-if="!me" class="space-y-4">
      <div class="h-40 rounded-2xl" style="background: var(--color-bg-secondary);" />
    </div>

    <div
      v-else
      class="rounded-2xl border p-5 sm:p-6 space-y-6"
      :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }"
    >
      <!-- Identity block -->
      <div class="flex items-center gap-4">
        <span
          class="size-14 rounded-full inline-flex items-center justify-center shrink-0"
          :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
        >
          <UIcon :name="typeMeta.icon" class="size-6" />
        </span>
        <div class="min-w-0">
          <p class="text-[15px] font-semibold tracking-tight truncate" :style="{ color: 'var(--color-text)' }">
            {{ me.profile?.name ?? me.email }}
          </p>
          <p class="text-[13px] truncate" :style="{ color: 'var(--color-text-tertiary)' }">{{ me.email }}</p>
        </div>
      </div>

      <hr class="border-0 border-t" :style="{ borderColor: 'var(--color-border)' }">

      <!-- Account type badge -->
      <div>
        <p class="text-[12px] font-medium mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Account type</p>
        <div
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-semibold"
          :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
        >
          <UIcon :name="typeMeta.icon" class="size-3.5" />
          {{ typeMeta.label }}
        </div>
      </div>

      <!-- Referrer detail -->
      <template v-if="referrerProfile">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <p class="text-[12px] font-medium mb-1" :style="{ color: 'var(--color-text-secondary)' }">Referral code</p>
            <p class="text-[14px] font-mono font-semibold" :style="{ color: 'var(--color-text)' }">{{ referrerProfile.code }}</p>
          </div>
          <div>
            <p class="text-[12px] font-medium mb-1" :style="{ color: 'var(--color-text-secondary)' }">Relationship tier</p>
            <p class="text-[14px] font-medium" :style="{ color: 'var(--color-text)' }">{{ tierLabel }}</p>
          </div>
          <div>
            <p class="text-[12px] font-medium mb-1" :style="{ color: 'var(--color-text-secondary)' }">Default commission</p>
            <p class="text-[14px] font-medium" :style="{ color: 'var(--color-text)' }">{{ referrerProfile.commission_pct }}%</p>
          </div>
        </div>
        <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">
          Commission is applied per referral by its own tier — see
          <NuxtLink to="/partners/earnings" style="color: var(--color-accent);">Earnings</NuxtLink> for the bands.
        </p>
      </template>

      <!-- Investor detail -->
      <template v-else-if="investorProfile">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <p class="text-[12px] font-medium mb-1" :style="{ color: 'var(--color-text-secondary)' }">Name</p>
            <p class="text-[14px] font-medium" :style="{ color: 'var(--color-text)' }">{{ investorProfile.name }}</p>
          </div>
          <div>
            <p class="text-[12px] font-medium mb-1" :style="{ color: 'var(--color-text-secondary)' }">Company</p>
            <p class="text-[14px] font-medium" :style="{ color: 'var(--color-text)' }">{{ investorProfile.company ?? '—' }}</p>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
