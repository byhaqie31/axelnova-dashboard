<script setup lang="ts">
defineProps<{
  eyebrow?: string
  title: string
  subtitle?: string
  action?: { label: string, to: string }
}>()
</script>

<template>
  <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-12">
    <div class="max-w-2xl">
      <p v-if="eyebrow" class="eyebrow mb-3">
        {{ eyebrow }}
      </p>
      <h2
        class="text-4xl md:text-5xl font-semibold tracking-tight"
        style="color: var(--color-text);"
      >
        {{ title }}
      </h2>
      <p
        v-if="subtitle"
        class="mt-4 text-[17px] leading-relaxed"
        style="color: var(--color-text-secondary);"
      >
        {{ subtitle }}
      </p>
    </div>

    <NuxtLink
      v-if="action"
      :to="action.to"
      class="text-[14px] font-medium whitespace-nowrap inline-flex items-center gap-1.5 transition-all hover:gap-2.5"
      style="color: var(--color-accent);"
    >
      {{ action.label }} <span aria-hidden>→</span>
    </NuxtLink>
  </div>
</template>
