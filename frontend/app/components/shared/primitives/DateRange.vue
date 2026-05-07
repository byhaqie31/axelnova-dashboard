<script setup lang="ts">
type Format = 'short' | 'long' | 'relative'

const props = withDefaults(defineProps<{
  date: string | Date
  format?: Format
  prefix?: string
}>(), {
  format: 'short',
})

const parsed = computed(() => props.date instanceof Date ? props.date : new Date(props.date))

const shortFmt = new Intl.DateTimeFormat('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
const longFmt = new Intl.DateTimeFormat('en-MY', { day: 'numeric', month: 'long', year: 'numeric' })
const relativeFmt = new Intl.RelativeTimeFormat('en-MY', { numeric: 'auto' })

const label = computed(() => {
  const d = parsed.value
  if (Number.isNaN(d.getTime())) return ''

  if (props.format === 'relative') {
    const diffMs = d.getTime() - Date.now()
    const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24))
    if (Math.abs(diffDays) < 30) return relativeFmt.format(diffDays, 'day')
    const diffMonths = Math.round(diffDays / 30)
    if (Math.abs(diffMonths) < 12) return relativeFmt.format(diffMonths, 'month')
    return relativeFmt.format(Math.round(diffDays / 365), 'year')
  }

  return (props.format === 'long' ? longFmt : shortFmt).format(d)
})
</script>

<template>
  <span class="inline-flex items-baseline gap-1.5">
    <span v-if="prefix" :style="{ color: 'var(--color-text-secondary)' }">{{ prefix }}</span>
    <span :style="{ color: 'var(--color-text)' }">{{ label }}</span>
  </span>
</template>
