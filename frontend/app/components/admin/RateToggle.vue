<script setup lang="ts">
// Segmented unit toggle for a discount/promo value: fixed currency (RM) vs
// percentage (%). Sits before the value input so the unit is chosen first.
defineProps<{ modelValue: 'amount' | 'percent' }>()
const emit = defineEmits<{ 'update:modelValue': ['amount' | 'percent'] }>()

const options = [
  { value: 'amount', label: 'RM' },
  { value: 'percent', label: '%' },
] as const
</script>

<template>
  <div class="inline-flex items-center h-[38px] p-1 rounded-[10px] shrink-0" :style="{ background: 'var(--color-bg-sunken)' }">
    <button
      v-for="o in options"
      :key="o.value"
      type="button"
      class="h-full px-3 rounded-md text-[12px] font-semibold leading-none transition-colors"
      :style="modelValue === o.value
        ? { background: 'var(--color-bg-elevated)', color: 'var(--color-accent)', boxShadow: 'var(--shadow-xs)' }
        : { color: 'var(--color-text-tertiary)' }"
      :aria-pressed="modelValue === o.value"
      @click="emit('update:modelValue', o.value)"
    >
      {{ o.label }}
    </button>
  </div>
</template>
