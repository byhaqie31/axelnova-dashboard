<script setup lang="ts">
// Catalog price: "RM 2,500 – 4,000", "From RM 20,000", then the unit from data
// ("per project", "per month", "custom quote"). Formatting lives in
// utils/priceTag.ts. The amount inherits the parent's font size; the unit stays
// small and muted, inline by default or on its own line with `stacked`.
import { formatPriceTag } from '~/utils/priceTag'

const props = withDefaults(defineProps<{
  min: number | string
  max?: number | string | null
  unit?: string | null
  /** Currency symbol for indicative conversions; amounts arrive converted. */
  symbol?: string
  stacked?: boolean
}>(), {
  max: null,
  unit: null,
  symbol: 'RM',
  stacked: false,
})

const label = computed(() => formatPriceTag({ min: props.min, max: props.max, symbol: props.symbol }))
</script>

<template>
  <span
    class="tabular-nums"
    :class="stacked ? 'flex flex-col gap-1' : 'inline-flex flex-wrap items-baseline gap-x-1.5'"
  >
    <span :style="{ color: 'var(--color-text)' }">{{ label }}</span>
    <span
      v-if="unit"
      class="text-[12px] font-normal tracking-normal"
      :style="{ color: 'var(--color-text-secondary)' }"
    >{{ unit }}</span>
  </span>
</template>
