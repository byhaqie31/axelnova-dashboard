<script setup lang="ts">
/**
 * The dot/pill rating scale used by the public /feedback/{token} form and the
 * admin review pages (shared per the §7 rule of three). Selected value fills
 * `--color-accent`; the run-up below it fills `--color-accent-soft`. Optional
 * scores clear when the selected pill is clicked again. No native inputs.
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

// Denser pills for the 0–10 NPS run so it stays one calm row on desktop
// (it wraps gracefully at 375px).
const sizeClass = computed(() => (values.value.length > 6 ? 'size-8 text-[12px]' : 'size-9 text-[13px]'))

function pick(v: number) {
  if (props.readonly) return
  emit('update:modelValue', v === props.modelValue ? null : v)
}

function styleFor(v: number) {
  if (props.modelValue !== null && v === props.modelValue) {
    return { borderColor: 'var(--color-accent)', background: 'var(--color-accent)', color: 'var(--color-on-accent, #fff)' }
  }
  if (props.modelValue !== null && v < props.modelValue) {
    return { borderColor: 'var(--color-accent-soft)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
  }
  return { borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)', color: 'var(--color-text-secondary)' }
}
</script>

<template>
  <div>
    <div class="flex flex-wrap gap-1.5" role="radiogroup">
      <button
        v-for="v in values"
        :key="v"
        type="button"
        role="radio"
        class="rounded-lg border flex items-center justify-center font-medium tabular-nums transition-colors"
        :class="[sizeClass, readonly ? 'cursor-default' : 'cursor-pointer']"
        :style="styleFor(v)"
        :aria-checked="modelValue === v"
        :aria-label="`${v} of ${max}`"
        :disabled="readonly"
        @click="pick(v)"
      >
        {{ v }}
      </button>
    </div>
    <div v-if="labels" class="flex items-center justify-between mt-1.5">
      <span class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ labels[0] }}</span>
      <span class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ labels[1] }}</span>
    </div>
  </div>
</template>
