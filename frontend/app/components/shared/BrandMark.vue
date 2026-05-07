<script setup lang="ts">
type Variant = 'default' | 'compact' | 'mark-only'

const props = withDefaults(defineProps<{
  variant?: Variant
  to?: string
  class?: string
}>(), {
  variant: 'default',
  to: '/',
})

const iconSize = computed(() => props.variant === 'compact' ? 'size-6' : 'size-7.5')
const wordmarkSize = computed(() => props.variant === 'compact' ? 'text-[13px]' : 'text-[15px]')
</script>

<template>
  <NuxtLink
    :to="to"
    :class="[wordmarkSize, 'font-semibold tracking-tight inline-flex items-center gap-2', props.class]"
  >
    <img
      src="/axel_nova_favicon.png"
      alt=""
      aria-hidden="true"
      :class="[iconSize, 'object-contain brand-logo-glow']"
    />
    <span v-if="variant !== 'mark-only'" class="text-gradient">Axel Nova Ventures</span>
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
