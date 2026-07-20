<script setup lang="ts">
// The task lifecycle as a log — Opened → Picked up → Completed → Paid, each with
// who + when, plus a trailing muted node for the live state (in progress, awaiting
// pickup/payment) when the task hasn't reached a terminal state. Built entirely
// from timestamps already on the record (started_at is the pick-up moment).
import type { TaskRecord } from '~/data/tasks'

const props = defineProps<{ task: TaskRecord }>()

type Tone = 'accent' | 'success' | 'muted'
interface TimelineEvent {
  key: string
  label: string
  at: string | null
  who: string | null
  icon: string
  tone: Tone
}

// What's happening NOW — the trailing node, shown only while the task is still
// live (open / in progress / bonus owed). Terminal states (completed, paid) end
// the timeline on their own event.
function currentLabel(t: TaskRecord): string | null {
  switch (t.status) {
    case 'open':
      return t.assignee_id
        ? `Assigned to ${t.assignee_name ?? 'someone'} — not started yet`
        : 'In the pool — awaiting pickup'
    case 'in_progress':
      return 'In progress'
    case 'payment_pending':
      return 'Awaiting payment'
    default:
      return null
  }
}

const events = computed<TimelineEvent[]>(() => {
  const t = props.task
  const list: TimelineEvent[] = [
    { key: 'opened', label: 'Opened', at: t.created_at, who: t.created_by_name, icon: 'i-lucide-circle-plus', tone: 'accent' },
  ]
  if (t.started_at) {
    list.push({ key: 'started', label: 'Picked up', at: t.started_at, who: t.assignee_name, icon: 'i-lucide-hand', tone: 'accent' })
  }
  if (t.completed_at) {
    list.push({ key: 'completed', label: 'Completed', at: t.completed_at, who: t.assignee_name, icon: 'i-lucide-check', tone: 'success' })
  }
  if (t.paid_at) {
    list.push({ key: 'paid', label: 'Paid', at: t.paid_at, who: null, icon: 'i-lucide-banknote', tone: 'success' })
  }
  const current = currentLabel(t)
  if (current) {
    list.push({ key: 'current', label: current, at: null, who: null, icon: 'i-lucide-circle-dashed', tone: 'muted' })
  }
  return list
})

const toneStyle: Record<Tone, { bg: string, fg: string }> = {
  accent: { bg: 'var(--color-accent-soft)', fg: 'var(--color-accent)' },
  success: { bg: 'rgba(48, 209, 88, 0.12)', fg: 'var(--color-success)' },
  muted: { bg: 'var(--color-bg-secondary)', fg: 'var(--color-text-tertiary)' },
}

function fmt(iso: string): string {
  return new Date(iso).toLocaleString('en-MY', {
    day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true,
  })
}
</script>

<template>
  <ol class="relative">
    <li v-for="(ev, i) in events" :key="ev.key" class="flex gap-3">
      <!-- Rail: dot + connector to the next event -->
      <div class="flex flex-col items-center">
        <span
          class="grid place-items-center rounded-full shrink-0"
          style="width: 1.5rem; height: 1.5rem;"
          :style="{ background: toneStyle[ev.tone].bg, color: toneStyle[ev.tone].fg }"
        >
          <UIcon :name="ev.icon" class="size-3" aria-hidden="true" />
        </span>
        <span
          v-if="i < events.length - 1" class="w-px flex-1 my-1"
          :style="{ background: 'var(--color-border)' }"
        />
      </div>
      <!-- Body -->
      <div class="min-w-0 pb-4" :class="{ 'pb-0': i === events.length - 1 }">
        <p class="text-[13px] font-medium leading-tight" :style="{ color: 'var(--color-text)' }">{{ ev.label }}</p>
        <p v-if="ev.at" class="text-[12px] mt-0.5" :style="{ color: 'var(--color-text-secondary)' }">
          {{ fmt(ev.at) }}<template v-if="ev.who"><span :style="{ color: 'var(--color-text-tertiary)' }"> · {{ ev.who }}</span></template>
        </p>
      </div>
    </li>
  </ol>
</template>
