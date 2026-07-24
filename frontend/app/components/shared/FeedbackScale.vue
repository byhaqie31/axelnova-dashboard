<script setup lang="ts">
/**
 * The rating scale used by the public /feedback/{token} form and the admin
 * review pages (shared per the §7 rule of three). Rendered as a joined
 * segmented bar (Delighted/Typeform-style): one continuous control whose
 * cells share dividers, so widths stay uniform at any container size.
 * Selected value fills `--color-accent`; the run-up before it fills
 * `--color-accent-soft`. Optional scores clear when the selected cell is
 * clicked again. Every run — including 0–10 NPS — is a single bar on all
 * viewports; cells just get denser on narrow screens. No native inputs.
 */
const props = withDefaults(defineProps<{
  modelValue: number | null
  /** Highest value on the scale (5 for ratings, 10 for NPS). */
  max?: number
  /** Lowest value (NPS starts at 0; ratings at 1). */
  min?: number
  readonly?: boolean
  /** End captions, e.g. ['Poor', 'Excellent']. */
  labels?: [string, string]
}>(), {
  max: 5,
  min: 1,
  readonly: false,
  labels: undefined,
})

const emit = defineEmits<{ 'update:modelValue': [value: number | null] }>()

const values = computed(() => {
  const list: number[] = []
  for (let v = props.min; v <= props.max; v++) list.push(v)
  return list
})

function pick(v: number) {
  if (props.readonly) return
  emit('update:modelValue', v === props.modelValue ? null : v)
}
</script>

<template>
  <div class="w-full">
    <div
      class="flex rounded-lg border overflow-hidden"
      :style="{ borderColor: 'var(--color-border)' }"
      role="radiogroup"
    >
      <button
        v-for="v in values"
        :key="v"
        type="button"
        role="radio"
        class="scale-cell flex-1 h-10 min-w-0 text-[13px] font-medium tabular-nums"
        :class="{
          'is-active': modelValue === v,
          'is-fill': modelValue !== null && v < modelValue,
          'cursor-pointer': !readonly,
        }"
        :aria-checked="modelValue === v"
        :aria-label="`${v} of ${max}`"
        :disabled="readonly"
        @click="pick(v)"
      >
        {{ v }}
      </button>
    </div>
    <div v-if="labels" class="flex items-baseline justify-between gap-6 mt-1.5">
      <span class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ labels[0] }}</span>
      <span class="text-[11px] text-right" :style="{ color: 'var(--color-text-tertiary)' }">{{ labels[1] }}</span>
    </div>
  </div>
</template>

<style scoped>
.scale-cell {
  background: var(--color-bg);
  color: var(--color-text-secondary);
  transition: background-color 150ms ease, color 150ms ease;
}
.scale-cell + .scale-cell {
  border-left: 1px solid var(--color-border);
}
.scale-cell.is-fill {
  background: var(--color-accent-soft);
  color: var(--color-accent);
}
.scale-cell.is-active {
  background: var(--color-accent);
  color: var(--color-on-accent, #fff);
}
.scale-cell:not(:disabled):not(.is-active):not(.is-fill):hover {
  background: var(--color-bg-elevated);
}
.scale-cell:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: -2px;
}
</style>
