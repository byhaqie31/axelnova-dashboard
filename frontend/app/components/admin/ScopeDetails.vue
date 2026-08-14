<script setup lang="ts">
import { classifyScopeFields, type ScopeField } from '~/utils/scopeFields'

/** One curated scope group from the API (ScopeSummary::forQuotation). */
export interface ScopeGroup {
  package_key: string | null
  label: string | null
  scope: Record<string, any>
}

const props = withDefaults(defineProps<{
  /** Curated group list (preferred) or a legacy flat payload map. */
  scope: ScopeGroup[] | Record<string, any> | null | undefined
  /** `card` = standalone elevated card (quotation detail); `section` = a divider'd
   *  block inside another card (order Scope snapshot). */
  variant?: 'card' | 'section'
  label?: string
}>(), {
  variant: 'card',
  label: 'Scope details',
})

// Normalise both accepted shapes into non-empty field groups. A flat map (the
// pre-curation shape) becomes one unlabelled group; groups whose scope
// classifies to nothing are dropped so a lone package chip never renders.
const groups = computed(() => {
  const s = props.scope
  if (!s) return []
  const raw: ScopeGroup[] = Array.isArray(s)
    ? s
    : [{ package_key: null, label: null, scope: s }]
  return raw
    .map(g => ({ ...g, fields: classifyScopeFields(g.scope) as ScopeField[] }))
    .filter(g => g.fields.length)
})

const totalFields = computed(() => groups.value.reduce((n, g) => n + g.fields.length, 0))
</script>

<template>
  <div
    v-if="groups.length"
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
      <span class="text-[11px] font-medium tabular-nums rounded-full px-1.5 py-px" :style="{ background: 'var(--color-bg-secondary)', color: 'var(--color-text-tertiary)' }">{{ totalFields }}</span>
    </div>

    <div v-for="(group, gi) in groups" :key="group.package_key ?? gi">
      <!-- Package sub-header — only when the quote spans multiple packages. -->
      <p
        v-if="groups.length > 1 && group.label"
        class="text-[12px] font-semibold mt-3 mb-1"
        style="color: var(--color-text);"
      >
        {{ group.label }}
      </p>

      <!-- Ruled spec grid: label-left / value-right. Booleans read as Yes/No badges
           (Yes pops in accent, No recedes), numbers are emphasised, text wraps. -->
      <div class="grid sm:grid-cols-2 sm:gap-x-10">
        <div
v-for="field in group.fields" :key="field.key"
          class="flex items-center justify-between gap-3 py-2.5 border-b" :style="{ borderColor: 'var(--color-border)' }">
          <span class="text-[12.5px]" style="color: var(--color-text-secondary);">{{ field.label }}</span>

          <span
v-if="field.kind === 'bool'" class="inline-flex items-center gap-1 rounded-full pl-1.5 pr-2 py-0.5 text-[11px] font-semibold shrink-0"
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
  </div>
</template>
