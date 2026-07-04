<script setup lang="ts">
// Investor-only (Task 9): the deal-room document shelf. Premium empty state —
// there is no investor content model yet; documents land here in a later phase.
definePageMeta({
  layout: 'partner',
  middleware: ['partner-auth', 'partner-type'],
  partnerType: 'investor',
})
useHead({ title: 'Documents — Partner Portal' })
useSeoMeta({ robots: 'noindex, nofollow' })
</script>

<template>
  <div class="space-y-8">
    <div>
      <h1 class="text-[24px] sm:text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Documents</h1>
      <p class="text-[13px] mt-1" style="color: var(--color-text-secondary);">
        Pitch decks, investment packages, and agreements shared with you.
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
        <UIcon name="i-lucide-folder-lock" class="size-6" />
      </div>
      <p class="text-[15px] font-semibold tracking-tight mb-1" :style="{ color: 'var(--color-text)' }">
        Your deal room is being prepared
      </p>
      <p class="text-[13px] max-w-md mx-auto" :style="{ color: 'var(--color-text-secondary)' }">
        Documents shared with you — pitch decks, investment packages, financial summaries —
        will appear here. We'll email you the moment something is ready to review.
      </p>
    </div>
  </div>
</template>
