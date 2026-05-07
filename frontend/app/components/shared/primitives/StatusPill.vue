<script setup lang="ts">
type EntityType = 'lead' | 'quotation' | 'project' | 'invoice' | 'milestone'
type Tone = 'neutral' | 'info' | 'warn' | 'success' | 'danger'

const props = defineProps<{
  status: string
  type: EntityType
}>()

const map: Record<EntityType, Record<string, Tone>> = {
  lead: {
    new: 'info',
    viewed: 'neutral',
    contacted: 'warn',
    converted: 'success',
    rejected: 'danger',
    spam: 'danger',
  },
  quotation: {
    draft: 'neutral',
    sent: 'info',
    viewed: 'warn',
    accepted: 'success',
    rejected: 'danger',
    expired: 'neutral',
  },
  project: {
    draft: 'neutral',
    quoted: 'info',
    active: 'success',
    on_hold: 'warn',
    completed: 'neutral',
    cancelled: 'danger',
  },
  invoice: {
    draft: 'neutral',
    sent: 'info',
    paid: 'success',
    partial: 'warn',
    overdue: 'danger',
    cancelled: 'neutral',
  },
  milestone: {
    pending: 'neutral',
    in_progress: 'info',
    review: 'warn',
    completed: 'success',
    blocked: 'danger',
  },
}

const tone = computed<Tone>(() => map[props.type]?.[props.status] ?? 'neutral')

const toneStyles: Record<Tone, { bg: string; fg: string; dot: string }> = {
  neutral: { bg: 'var(--color-bg-secondary)', fg: 'var(--color-text-secondary)', dot: 'var(--color-text-tertiary)' },
  info:    { bg: 'var(--color-accent-soft)',  fg: 'var(--color-accent)',         dot: 'var(--color-accent)' },
  warn:    { bg: 'rgba(255, 159, 10, 0.12)',  fg: 'var(--color-warning)',        dot: 'var(--color-warning)' },
  success: { bg: 'rgba(48, 209, 88, 0.12)',   fg: 'var(--color-success)',        dot: 'var(--color-success)' },
  danger:  { bg: 'rgba(255, 59, 48, 0.12)',   fg: 'var(--color-danger)',         dot: 'var(--color-danger)' },
}

const styles = computed(() => toneStyles[tone.value])
const label = computed(() => props.status.replace(/_/g, ' '))
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full text-[11px] font-medium capitalize"
    :style="{ background: styles.bg, color: styles.fg }"
  >
    <span class="size-1.5 rounded-full" :style="{ background: styles.dot }" aria-hidden="true" />
    {{ label }}
  </span>
</template>
