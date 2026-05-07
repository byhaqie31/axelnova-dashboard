<script setup lang="ts">
const props = withDefaults(defineProps<{
  min: number
  max?: number
  prefix?: string
  compact?: boolean
}>(), {
  compact: false,
})

const formatter = new Intl.NumberFormat('ms-MY', { maximumFractionDigits: 0 })

const minLabel = computed(() => `RM ${formatter.format(props.min)}`)
const maxLabel = computed(() => props.max != null ? `RM ${formatter.format(props.max)}` : null)
</script>

<template>
  <span class="inline-flex items-baseline gap-1.5 tabular-nums">
    <template v-if="prefix">
      <span :style="{ color: 'var(--color-text-secondary)' }">{{ prefix }}</span>
      <span aria-hidden="true" :style="{ color: 'var(--color-text-tertiary)' }">—</span>
    </template>
    <span :style="{ color: 'var(--color-text)' }">
      {{ minLabel }}<template v-if="maxLabel"> <span aria-hidden="true">–</span> {{ maxLabel }}</template>
    </span>
  </span>
</template>
