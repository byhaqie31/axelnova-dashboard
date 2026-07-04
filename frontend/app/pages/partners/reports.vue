<script setup lang="ts">
// Investor-only (Task 9): performance reports. Premium empty state — there is
// no investor content model yet; reports land here in a later phase.
definePageMeta({
  layout: 'partner',
  middleware: ['partner-auth', 'partner-type'],
  partnerType: 'investor',
})
useHead({ title: 'Reports — Partner Portal' })
useSeoMeta({ robots: 'noindex, nofollow' })
</script>

<template>
  <div class="space-y-8">
    <div>
      <h1 class="text-[24px] sm:text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Reports</h1>
      <p class="text-[13px] mt-1" style="color: var(--color-text-secondary);">
        Periodic performance and progress reports across your investments.
      </p>
    </div>

    <div
      class="rounded-2xl border p-10 sm:p-14 text-center"
      :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }"
    >
      <div
        class="inline-flex items-center justify-center size-12 rounded-2xl mb-4"
        :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
      >
        <UIcon name="i-lucide-chart-line" class="size-6" />
      </div>
      <p class="text-[15px] font-semibold tracking-tight mb-1" :style="{ color: 'var(--color-text)' }">
        Reports are on their way
      </p>
      <p class="text-[13px] max-w-md mx-auto" :style="{ color: 'var(--color-text-secondary)' }">
        As projects you're invested in hit their milestones, performance reports will be
        published here — traction, financials, and what's next. We'll notify you by email.
      </p>
    </div>
  </div>
</template>
