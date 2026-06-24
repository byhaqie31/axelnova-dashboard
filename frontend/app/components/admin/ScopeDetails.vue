<script setup lang="ts">
import { classifyScopeFields } from '~/utils/scopeFields'

const props = withDefaults(defineProps<{
  scope: Record<string, any> | null | undefined
  /** `card` = standalone elevated card (quotation detail); `section` = a divider'd
   *  block inside another card (order Scope snapshot). */
  variant?: 'card' | 'section'
  label?: string
}>(), {
  variant: 'card',
  label: 'Scope details',
})

const fields = computed(() => classifyScopeFields(props.scope))
</script>

<template>
  <div
    v-if="fields.length"
    :class="variant === 'card' ? 'rounded-2xl border p-6' : 'mt-4 pt-4 border-t'"
    :style="variant === 'card'
      ? { background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }
      : { borderColor: 'var(--color-border)' }"
  >
    <div class="flex items-center gap-2 mb-1">
      <p
        :class="variant === 'card' ? 'text-[11px] font-semibold uppercase tracking-widest' : 'text-[11px] font-medium uppercase tracking-wider'"
        style="color: var(--color-text-tertiary);"
      >
        {{ label }}
      </p>
      <span class="text-[11px] font-medium tabular-nums rounded-full px-1.5 py-px" :style="{ background: 'var(--color-bg-secondary)', color: 'var(--color-text-tertiary)' }">{{ fields.length }}</span>
    </div>

    <!-- Ruled spec grid: label-left / value-right. Booleans read as Yes/No badges
         (Yes pops in accent, No recedes), numbers are emphasised, text wraps. -->
    <div class="grid sm:grid-cols-2 sm:gap-x-10">
      <div v-for="field in fields" :key="field.key"
        class="flex items-center justify-between gap-3 py-2.5 border-b" :style="{ borderColor: 'var(--color-border)' }">
        <span class="text-[12.5px]" style="color: var(--color-text-secondary);">{{ field.label }}</span>

        <span v-if="field.kind === 'bool'" class="inline-flex items-center gap-1 rounded-full pl-1.5 pr-2 py-0.5 text-[11px] font-semibold shrink-0"
          :style="field.on
            ? { background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
            : { background: 'var(--color-bg-secondary)', color: 'var(--color-text-tertiary)' }">
          <UIcon :name="field.on ? 'i-lucide-check' : 'i-lucide-minus'" class="size-3" />
          {{ field.value }}
        </span>
        <span v-else-if="field.kind === 'number'" class="text-[14px] font-semibold tabular-nums shrink-0" style="color: var(--color-text);">{{ field.value }}</span>
        <span v-else class="text-[13px] text-right" style="color: var(--color-text);">{{ field.value }}</span>
      </div>
    </div>
  </div>
</template>
