<script setup lang="ts">
// `stacked` is the branding-moment pose (the homepage loading screen): an
// oversized mark sitting above the wordmark, rather than a small mark beside it.
type Variant = 'default' | 'compact' | 'mark-only' | 'stacked'

const props = withDefaults(defineProps<{
  variant?: Variant
  to?: string
  class?: string
  wordmark?: string
}>(), {
  variant: 'default',
  to: '/',
  wordmark: 'Axel Nova Ventures',
})

const iconSize = computed(() => {
  switch (props.variant) {
    case 'compact': return 'size-6'
    case 'stacked': return 'size-16 sm:size-20'
    default: return 'size-7.5'
  }
})
const wordmarkSize = computed(() => {
  switch (props.variant) {
    case 'compact': return 'text-[13px]'
    case 'stacked': return 'text-[17px] sm:text-[19px]'
    default: return 'text-[15px]'
  }
})
const layout = computed(() =>
  props.variant === 'stacked' ? 'flex-col items-center gap-3.5' : 'items-center gap-2',
)
</script>

<template>
  <NuxtLink
    :to="to"
    :class="[wordmarkSize, layout, 'font-semibold tracking-tight inline-flex', props.class]"
  >
    <img
      src="/favicon/apple-touch-icon.png"
      alt=""
      aria-hidden="true"
      :class="[iconSize, 'object-contain brand-logo-glow']"
    >
    <span v-if="variant !== 'mark-only'" class="text-gradient">{{ wordmark }}</span>
  </NuxtLink>
</template>

<style scoped>
.brand-logo-glow {
  filter:
    drop-shadow(0 1px 2px rgba(0, 113, 227, 0.25))
    drop-shadow(0 0 10px rgba(0, 113, 227, 0.35));
  transition: filter 0.25s ease;
}

.brand-logo-glow:hover {
  filter:
    drop-shadow(0 1px 3px rgba(0, 113, 227, 0.35))
    drop-shadow(0 0 14px rgba(0, 113, 227, 0.5));
}
</style>
