<script setup lang="ts">
// Marketer surface (marketer + founder only — engineers are bounced by the
// role guard below; the backend has no marketing endpoints yet so there's
// nothing to leak). For now this page is a single announced module: Threads
// analytics, waiting on the Threads API setup (env keys are staged, no
// integration code yet). When the API lands, the coming-soon card below is
// replaced by the real metrics module.
definePageMeta({ layout: 'team', middleware: 'team-auth' })
useHead({ title: 'Marketing — Team' })

const { me } = useTeamMe()

// Cosmetic guard — real enforcement is the backend's role gate. The layout's
// onMounted fetch fills the shared `me` state; bounce once the role resolves.
watchEffect(() => {
  const role = me.value?.role
  if (role && role !== 'founder' && role !== 'marketer') navigateTo('/team')
})
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <div class="mb-8">
      <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Marketing</h1>
      <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Channel performance for the marketing effort — starting with Threads.</p>
    </div>

    <!-- Threads analytics — coming soon -->
    <section
      class="rounded-2xl border p-10 sm:p-14 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }"
    >
      <div
        class="size-14 rounded-2xl mx-auto mb-5 inline-flex items-center justify-center"
        :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
      >
        <UIcon name="i-lucide-at-sign" class="size-7" />
      </div>

      <h2 class="text-[20px] font-semibold tracking-tight mb-2" :style="{ color: 'var(--color-text)' }">
        Threads analytics — coming soon
      </h2>
      <p class="text-[14px] max-w-md mx-auto leading-relaxed" :style="{ color: 'var(--color-text-secondary)' }">
        We're connecting the Threads API. Post reach, engagement, and follower
        growth for the Axel Nova account will land here.
      </p>

      <span
        class="inline-flex items-center gap-1.5 mt-6 px-3 py-1 rounded-full text-[11px] font-semibold"
        :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg-secondary)' }"
      >
        <UIcon name="i-lucide-plug-zap" class="size-3.5" />
        Waiting on API setup
      </span>
    </section>
  </div>
</template>
