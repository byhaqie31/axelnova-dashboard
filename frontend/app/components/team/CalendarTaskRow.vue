<script setup lang="ts">
// The compact "inbox row" shared by every calendar view (Today / Week / Month).
// Deliberately minimal — priority dot + title only — so a day cell reads like a
// tight inbox list, not a stack of chunky chips. Pay / status / description all
// live in the detail slideover the parent opens on `select`.
import { taskPriorityMeta, type TaskRecord } from '~/data/tasks'

// `meta` renders a small trailing label (e.g. a relative date in the Upcoming
// rail); omitted in the dense day-cell views.
defineProps<{ task: TaskRecord, source: 'mine' | 'pool', meta?: string }>()
defineEmits<{ select: [] }>()
</script>

<template>
  <button
    type="button"
    class="cal-row"
    :class="source === 'pool' ? 'cal-row-pool' : 'cal-row-mine'"
    :title="`${task.title}${source === 'pool' ? ' (pool)' : ''}`"
    @click="$emit('select')"
  >
    <span class="cal-row-dot" :style="{ background: taskPriorityMeta(task.priority)?.color }" />
    <span class="cal-row-title">{{ task.title }}</span>
    <span v-if="source === 'pool'" class="cal-row-tag">pool</span>
    <span v-if="meta" class="cal-row-meta">{{ meta }}</span>
  </button>
</template>

<style scoped>
.cal-row {
  display: flex;
  align-items: center;
  gap: 6px;
  width: 100%;
  padding: 2px 6px;
  border: none;
  border-radius: 6px;
  background: transparent;
  cursor: pointer;
  text-align: left;
  font: inherit;
  line-height: 1.4;
  transition: background 0.12s ease;
}
.cal-row:hover {
  background: var(--color-bg-secondary);
}
.cal-row:focus-visible {
  outline: none;
  box-shadow: 0 0 0 2px var(--calendar-today-ring);
}
.cal-row-dot {
  width: 5px;
  height: 5px;
  border-radius: 9999px;
  flex-shrink: 0;
}
.cal-row-title {
  flex: 1;
  min-width: 0;
  font-size: 11px;
  font-weight: 500;
  color: var(--color-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cal-row-pool .cal-row-title {
  color: var(--color-text-secondary);
}
.cal-row-tag {
  flex-shrink: 0;
  font-size: 9px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-tertiary);
}
.cal-row-meta {
  flex-shrink: 0;
  font-size: 10px;
  font-weight: 500;
  color: var(--color-text-tertiary);
  font-variant-numeric: tabular-nums;
}
</style>
