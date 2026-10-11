<script setup lang="ts">
// Revision tabs on the quotation page: R1 · R2 · … each with its status chip.
// The CURRENT revision (the one the family presents, and the order runs on) is
// bold; the selected tab is accent-tinted. Hidden while a quotation has only R1.
export interface RevisionTab {
  number: number
  label: string
  status: string
  is_current: boolean
  change_reason: string | null
}

defineProps<{ revisions: RevisionTab[], selected: number }>()
const emit = defineEmits<{ select: [number: number] }>()
</script>

<template>
  <div
    v-if="revisions.length > 1"
    data-lenis-prevent
    class="flex items-center gap-1.5 overflow-x-auto pb-1 -mx-1 px-1"
    role="tablist"
    aria-label="Quotation revisions"
  >
    <button
      v-for="r in revisions" :key="r.number"
      type="button"
      role="tab"
      :aria-selected="r.number === selected"
      :title="r.change_reason ?? undefined"
      class="shrink-0 inline-flex items-center gap-2 rounded-xl border px-3 py-1.5 max-md:py-2 transition-colors"
      :style="r.number === selected
        ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)' }
        : { borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }"
      @click="emit('select', r.number)"
    >
      <span
        class="font-mono text-[13px]"
        :class="r.is_current ? 'font-bold' : 'font-medium'"
        :style="{ color: r.number === selected ? 'var(--color-accent)' : 'var(--color-text)' }"
      >{{ r.label }}</span>
      <AdminStatusPill :status="r.status" />
      <span v-if="r.is_current" class="sr-only">(current)</span>
    </button>
  </div>
</template>
