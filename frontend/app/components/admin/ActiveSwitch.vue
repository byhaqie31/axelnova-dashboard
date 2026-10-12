<script setup lang="ts">
// Borderless Active switch for admin LISTING rows (UI-STANDARDS §12.18) — the §12.2
// switch + its label, without the row-card; the form pages keep the full toggle
// row-card. It only emits `toggle`: the parent owns the request, any confirm and
// the optimistic flip, and passes the record's state back as `on`.
defineProps<{
  on: boolean
  /** Accessible name — say what it shows, e.g. “Websites on the public services page”. */
  label: string
  busy?: boolean
}>()
const emit = defineEmits<{ toggle: [] }>()
</script>

<template>
  <button
    type="button"
    role="switch"
    :aria-checked="on"
    :aria-label="label"
    :aria-busy="busy || undefined"
    :disabled="busy"
    class="inline-flex items-center gap-2 text-[11px] font-medium py-1 max-md:py-2 rounded-md transition-colors disabled:cursor-wait"
    :style="{ color: on ? 'var(--color-text)' : 'var(--color-text-tertiary)' }"
    @click="emit('toggle')">
    <span
      class="relative inline-block rounded-full transition-colors shrink-0"
      :style="{ background: on ? 'var(--color-success)' : 'var(--color-switch-off-track)', height: '1.25rem', width: '2.25rem' }">
      <span
        class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all"
        :style="{ left: on ? '1.125rem' : '0.125rem' }" />
    </span>
    Active
  </button>
</template>
