<script setup lang="ts">
// The task payment badge (UI-STANDARDS §12.14) — payment is a card BADGE, never
// a kanban column, because most tasks carry no extra pay. Renders nothing for
// state 'none'; 'pending'/'paid' render the amount (PriceTag-compatible ms-MY
// formatting) + a state chip. Shared by the admin table, the kanban card, and
// the calendar's completed log.
const props = defineProps<{
  state: 'none' | 'pending' | 'paid'
  amount: number | null
}>()

const formatter = new Intl.NumberFormat('ms-MY', { maximumFractionDigits: 0 })
const amountLabel = computed(() => props.amount != null ? `RM ${formatter.format(props.amount)}` : '')

const styles = computed(() => props.state === 'paid'
  ? { color: 'var(--color-success)', background: 'var(--color-success-soft)' }
  : { color: 'var(--color-warning)', background: 'var(--color-warning-soft)' })
</script>

<template>
  <span
    v-if="state !== 'none' && amount != null"
    class="inline-flex items-center gap-1 h-5 px-2 rounded-full text-[11px] font-semibold tabular-nums whitespace-nowrap"
    :style="styles"
  >
    <UIcon :name="state === 'paid' ? 'i-lucide-check' : 'i-lucide-banknote'" class="size-3" aria-hidden="true" />
    {{ amountLabel }}
    <span class="font-medium opacity-80">{{ state === 'paid' ? 'paid' : 'pending' }}</span>
  </span>
</template>
