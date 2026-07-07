<script setup lang="ts">
// Team › Calendar (Task 5) — a month view OVER the tasks engine (no table of
// its own): deadlines land as day chips, and a "Completed" log below the grid
// covers the visible month. Chips for my tasks read accent (--calendar-chip-
// mine-*), pool tasks read neutral (--calendar-chip-pool-*) so "mine vs could
// be mine" is one glance. At <768px the grid gives way to a stacked agenda
// list (only the days with deadlines) — chosen over dots-in-a-grid because a
// list needs no tap-to-reveal step and reads naturally on 375px.
import TaskPayBadge from '~/components/shared/primitives/TaskPayBadge.vue'
import { taskPriorityMeta, type TaskRecord, type TeamTasksFeed } from '~/data/tasks'

definePageMeta({ layout: 'team', middleware: 'team-auth' })
useHead({ title: 'Calendar — Team' })

const { apiFetch } = useTeamAuth()

const pool = ref<TaskRecord[]>([])
const mine = ref<TaskRecord[]>([])
// Starts true — fetch runs in onMounted only (Task-2 SSR convention).
const loading = ref(true)
const error = ref('')

async function fetchTasks() {
  error.value = ''
  try {
    const res = await apiFetch<TeamTasksFeed>('/api/v1/team/tasks')
    pool.value = res.pool
    mine.value = res.mine
  }
  catch {
    error.value = 'Failed to load tasks. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchTasks)

// ── Month cursor ─────────────────────────────────────────────────────────
const today = new Date()
const cursor = ref(new Date(today.getFullYear(), today.getMonth(), 1))

const monthLabel = computed(() =>
  cursor.value.toLocaleDateString('en-MY', { month: 'long', year: 'numeric' }))

function shiftMonth(delta: number) {
  cursor.value = new Date(cursor.value.getFullYear(), cursor.value.getMonth() + delta, 1)
}
function goToday() {
  cursor.value = new Date(today.getFullYear(), today.getMonth(), 1)
}

// ── Grid + chip data ─────────────────────────────────────────────────────
interface CalendarChip { task: TaskRecord, source: 'mine' | 'pool' }
interface CalendarDay { key: string, date: Date, inMonth: boolean, isToday: boolean, chips: CalendarChip[] }

function dayKey(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

const chipsByDay = computed(() => {
  const map = new Map<string, CalendarChip[]>()
  const add = (task: TaskRecord, source: 'mine' | 'pool') => {
    if (!task.deadline) return
    const key = dayKey(new Date(task.deadline))
    if (!map.has(key)) map.set(key, [])
    map.get(key)!.push({ task, source })
  }
  mine.value.forEach(t => add(t, 'mine'))
  pool.value.forEach(t => add(t, 'pool'))
  return map
})

// Monday-first 7-col grid padded to full weeks with the neighbours' days.
const gridDays = computed<CalendarDay[]>(() => {
  const year = cursor.value.getFullYear()
  const month = cursor.value.getMonth()
  const first = new Date(year, month, 1)
  const lead = (first.getDay() + 6) % 7 // days shown from the previous month
  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const total = Math.ceil((lead + daysInMonth) / 7) * 7
  const todayKey = dayKey(today)

  return Array.from({ length: total }, (_, i) => {
    const date = new Date(year, month, i - lead + 1)
    const key = dayKey(date)
    return {
      key,
      date,
      inMonth: date.getMonth() === month,
      isToday: key === todayKey,
      chips: chipsByDay.value.get(key) ?? [],
    }
  })
})

const weekdayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

// Mobile agenda: only this month's days that actually carry deadlines.
const agendaDays = computed(() => gridDays.value.filter(d => d.inMonth && d.chips.length > 0))

// ── Completed log for the visible month ──────────────────────────────────
const completedLog = computed(() => {
  const year = cursor.value.getFullYear()
  const month = cursor.value.getMonth()
  return mine.value
    .filter((t) => {
      if (!t.completed_at) return false
      const d = new Date(t.completed_at)
      return d.getFullYear() === year && d.getMonth() === month
    })
    .sort((a, b) => new Date(b.completed_at!).getTime() - new Date(a.completed_at!).getTime())
})

function fmtDay(date: Date) {
  return date.toLocaleDateString('en-MY', { weekday: 'short', day: 'numeric', month: 'short' })
}
function fmtCompleted(iso: string) {
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short' })
}

// ── Task detail (click a deadline chip / agenda row) ─────────────────────
const selected = ref<CalendarChip | null>(null)
function openChip(chip: CalendarChip) {
  selected.value = chip
}
function closeChip() {
  selected.value = null
}
onKeyStroke('Escape', () => {
  if (selected.value) closeChip()
})

const taskStatusLabel: Record<TaskRecord['status'], string> = {
  open: 'Open',
  in_progress: 'In progress',
  completed: 'Completed',
  payment_pending: 'Payment pending',
  paid: 'Paid',
}

function fmtFullDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-MY', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

// "Add to Google Calendar" — a zero-backend render link. A task deadline is
// modelled as an all-day event (GCal treats the end date as exclusive, hence
// the +1 day).
function gcalUrl(task: TaskRecord): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  const ymd = (d: Date) => `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}`
  const start = new Date(task.deadline!)
  const end = new Date(start.getFullYear(), start.getMonth(), start.getDate() + 1)
  const params = new URLSearchParams({
    action: 'TEMPLATE',
    text: task.title,
    dates: `${ymd(start)}/${ymd(end)}`,
    details: `Axel Nova Ventures task deadline${task.description ? `\n\n${task.description}` : ''}`,
  })
  return `https://calendar.google.com/calendar/render?${params.toString()}`
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[24px] font-bold tracking-tight" style="color: var(--color-text);">Calendar</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
          Task deadlines by month — yours in blue, pool tasks in gray — plus what got completed.
        </p>
      </div>

      <!-- Month nav -->
      <div class="flex items-center gap-1.5">
        <button type="button" class="cal-nav-btn" aria-label="Previous month" @click="shiftMonth(-1)">
          <UIcon name="i-lucide-chevron-left" class="size-4" />
        </button>
        <button type="button" class="btn-pill btn-pill-ghost text-[12px]" @click="goToday">Today</button>
        <button type="button" class="cal-nav-btn" aria-label="Next month" @click="shiftMonth(1)">
          <UIcon name="i-lucide-chevron-right" class="size-4" />
        </button>
        <span class="ml-2 text-[15px] font-semibold tracking-tight whitespace-nowrap" style="color: var(--color-text);">{{ monthLabel }}</span>
      </div>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading calendar…</div>

    <template v-else>
      <!-- Desktop: month grid (Monday-first) -->
      <div class="hidden md:block rounded-2xl border overflow-hidden" :style="{ borderColor: 'var(--calendar-grid-border)' }">
        <div class="grid grid-cols-7">
          <div
            v-for="w in weekdayLabels" :key="w"
            class="px-2 py-2 text-center text-[11px] font-semibold uppercase tracking-wider border-b"
            :style="{ color: 'var(--color-text-tertiary)', borderColor: 'var(--calendar-grid-border)', background: 'var(--calendar-cell-muted-bg)' }">
            {{ w }}
          </div>

          <div
            v-for="day in gridDays" :key="day.key"
            class="cal-cell"
            :style="{ background: day.inMonth ? 'var(--calendar-cell-bg)' : 'var(--calendar-cell-muted-bg)' }">
            <span
              class="cal-daynum"
              :class="{ 'cal-daynum-today': day.isToday }"
              :style="{ color: day.isToday ? 'var(--calendar-today-fg)' : day.inMonth ? 'var(--color-text-secondary)' : 'var(--color-text-tertiary)' }">
              {{ day.date.getDate() }}
            </span>
            <div class="flex flex-col gap-1 mt-1 min-w-0">
              <button
                v-for="chip in day.chips.slice(0, 3)" :key="`${chip.source}-${chip.task.id}`"
                type="button" class="cal-chip cal-chip-btn"
                :style="chip.source === 'mine'
                  ? { color: 'var(--calendar-chip-mine-fg)', background: 'var(--calendar-chip-mine-bg)' }
                  : { color: 'var(--calendar-chip-pool-fg)', background: 'var(--calendar-chip-pool-bg)' }"
                :title="`${chip.task.title}${chip.source === 'pool' ? ' (pool)' : ''}`"
                @click="openChip(chip)">
                <span class="cal-chip-dot" :style="{ background: taskPriorityMeta(chip.task.priority)?.color }" />
                {{ chip.task.title }}
              </button>
              <span v-if="day.chips.length > 3" class="text-[10px] px-1" :style="{ color: 'var(--color-text-tertiary)' }">
                +{{ day.chips.length - 3 }} more
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Mobile: stacked agenda (only days with deadlines) -->
      <div class="md:hidden">
        <div
          v-if="!agendaDays.length"
          class="rounded-2xl border px-6 py-12 text-center"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[13px]" style="color: var(--color-text-secondary);">No deadlines in {{ monthLabel }}.</p>
        </div>
        <div v-else class="space-y-2.5">
          <div
            v-for="day in agendaDays" :key="day.key"
            class="rounded-xl border p-3.5"
            :style="{
              borderColor: day.isToday ? 'var(--calendar-today-ring)' : 'var(--color-border)',
              background: 'var(--calendar-cell-bg)',
            }">
            <p class="text-[11px] font-semibold uppercase tracking-wider mb-2" :style="{ color: day.isToday ? 'var(--calendar-today-ring)' : 'var(--color-text-tertiary)' }">
              {{ fmtDay(day.date) }}<span v-if="day.isToday"> · today</span>
            </p>
            <div class="flex flex-col gap-1.5">
              <button
                v-for="chip in day.chips" :key="`${chip.source}-${chip.task.id}`"
                type="button" class="cal-chip cal-chip-btn"
                :style="chip.source === 'mine'
                  ? { color: 'var(--calendar-chip-mine-fg)', background: 'var(--calendar-chip-mine-bg)' }
                  : { color: 'var(--calendar-chip-pool-fg)', background: 'var(--calendar-chip-pool-bg)' }"
                @click="openChip(chip)">
                <span class="cal-chip-dot" :style="{ background: taskPriorityMeta(chip.task.priority)?.color }" />
                {{ chip.task.title }}<span v-if="chip.source === 'pool'" class="opacity-70">&nbsp;· pool</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Completed log (visible month) -->
      <div class="mt-8">
        <h2 class="text-[13px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">
          Completed in {{ monthLabel }}
        </h2>

        <div
          v-if="!completedLog.length"
          class="rounded-2xl border px-6 py-10 text-center"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[13px]" style="color: var(--color-text-secondary);">Nothing completed this month yet.</p>
        </div>

        <div v-else class="rounded-2xl border divide-y" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
          <div
            v-for="t in completedLog" :key="t.id"
            class="flex items-center justify-between gap-3 px-4 py-3"
            :style="{ borderColor: 'var(--color-border)' }">
            <div class="min-w-0 flex items-center gap-2.5">
              <UIcon name="i-lucide-circle-check" class="size-4 shrink-0" :style="{ color: 'var(--color-success)' }" />
              <p class="text-[13px] font-medium truncate" :style="{ color: 'var(--color-text)' }">{{ t.title }}</p>
            </div>
            <div class="flex items-center gap-2.5 shrink-0">
              <TaskPayBadge :state="t.payment_state" :amount="t.pay_amount_myr" />
              <span class="text-[12px] whitespace-nowrap" :style="{ color: 'var(--color-text-secondary)' }">{{ fmtCompleted(t.completed_at!) }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Task detail (click a deadline chip / agenda row) -->
    <Teleport to="body">
      <Transition name="slideover">
        <div v-if="selected" class="slideover-scrim" @click.self="closeChip">
          <aside class="slideover-panel" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
            <div class="slideover-head">
              <div class="min-w-0">
                <p class="text-[17px] font-bold tracking-tight" style="color: var(--color-text);">{{ selected.task.title }}</p>
                <p class="text-[12px] mt-0.5" style="color: var(--color-text-secondary);">
                  {{ selected.source === 'mine' ? 'Your task' : 'Pool task — unclaimed' }}
                </p>
              </div>
              <button type="button" class="slideover-close" aria-label="Close" @click="closeChip">
                <UIcon name="i-lucide-x" class="size-4" />
              </button>
            </div>

            <div class="slideover-body space-y-5">
              <div class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                  <span class="text-[12px]" style="color: var(--color-text-tertiary);">Deadline</span>
                  <span class="text-[13px] font-medium text-right" style="color: var(--color-text);">
                    {{ selected.task.deadline ? fmtFullDate(selected.task.deadline) : '—' }}
                  </span>
                </div>
                <div class="flex items-center justify-between gap-3">
                  <span class="text-[12px]" style="color: var(--color-text-tertiary);">Priority</span>
                  <span
                    class="inline-flex items-center gap-1.5 text-[12px] font-medium px-2 py-0.5 rounded-full"
                    :style="{ color: taskPriorityMeta(selected.task.priority)?.color, background: taskPriorityMeta(selected.task.priority)?.bg }">
                    <span class="size-1.5 rounded-full" :style="{ background: taskPriorityMeta(selected.task.priority)?.color }" />
                    {{ taskPriorityMeta(selected.task.priority)?.label }}
                  </span>
                </div>
                <div class="flex items-center justify-between gap-3">
                  <span class="text-[12px]" style="color: var(--color-text-tertiary);">Status</span>
                  <span class="text-[13px] font-medium" style="color: var(--color-text);">{{ taskStatusLabel[selected.task.status] }}</span>
                </div>
                <div v-if="selected.task.payment_state !== 'none'" class="flex items-center justify-between gap-3">
                  <span class="text-[12px]" style="color: var(--color-text-tertiary);">Pay</span>
                  <TaskPayBadge :state="selected.task.payment_state" :amount="selected.task.pay_amount_myr" />
                </div>
              </div>

              <p
                v-if="selected.task.description"
                class="text-[13px] leading-relaxed whitespace-pre-line" style="color: var(--color-text-secondary);">
                {{ selected.task.description }}
              </p>

              <div class="space-y-2 pt-1">
                <NuxtLink to="/team/tasks" class="btn-pill btn-pill-primary w-full justify-center text-[13px]">
                  <UIcon name="i-lucide-arrow-right" class="size-3.5" />
                  Open in Tasks
                </NuxtLink>
                <a
                  v-if="selected.task.deadline"
                  :href="gcalUrl(selected.task)" target="_blank" rel="noopener noreferrer"
                  class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">
                  <UIcon name="i-lucide-calendar-plus" class="size-3.5" />
                  Add to Google Calendar
                </a>
              </div>
            </div>
          </aside>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* Calendar (§12.15) — first use of the --calendar-* token family (main.css). */
.cal-nav-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 9999px;
  border: 1px solid var(--color-border);
  background: var(--color-bg-elevated);
  color: var(--color-text-secondary);
  transition: background 0.15s ease, color 0.15s ease;
}
.cal-nav-btn:hover {
  background: var(--color-bg-secondary);
  color: var(--color-text);
}

.cal-cell {
  min-height: 96px;
  padding: 6px;
  border-bottom: 1px solid var(--calendar-grid-border);
  border-right: 1px solid var(--calendar-grid-border);
  overflow: hidden;
}
.cal-cell:nth-child(7n) {
  border-right: none;
}

.cal-daynum {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 22px;
  height: 22px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
.cal-daynum-today {
  background: var(--calendar-today-ring);
}

.cal-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  max-width: 100%;
  padding: 2px 6px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 500;
  line-height: 1.4;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cal-chip-dot {
  width: 5px;
  height: 5px;
  border-radius: 9999px;
  flex-shrink: 0;
}

/* Chips are buttons now (click → task detail). Reset the UA button chrome and
   add hover/focus affordances; theme-agnostic so light + dark both read. */
.cal-chip-btn {
  border: none;
  font: inherit;
  cursor: pointer;
  text-align: left;
  width: 100%;
  transition: box-shadow 0.15s ease;
}
.cal-chip-btn:hover {
  box-shadow: inset 0 0 0 1px var(--color-border-strong);
}
.cal-chip-btn:focus-visible {
  outline: none;
  box-shadow: 0 0 0 2px var(--calendar-today-ring);
}

/* Slideover (§12.13) — same class names + motion as the admin panels so a
   future promotion to main.css is a cut-paste. */
.slideover-scrim {
  position: fixed;
  inset: 0;
  z-index: 90;
  display: flex;
  justify-content: flex-end;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(3px);
}
.slideover-panel {
  width: 100%;
  max-width: 440px;
  height: 100%;
  display: flex;
  flex-direction: column;
  border-left: 1px solid var(--color-border);
  box-shadow: var(--shadow-lg);
}
.slideover-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 20px;
  border-bottom: 1px solid var(--color-border);
}
.slideover-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 9999px;
  color: var(--color-text-secondary);
  transition: background 0.15s ease, color 0.15s ease;
  flex-shrink: 0;
}
.slideover-close:hover {
  background: var(--color-bg-secondary);
  color: var(--color-text);
}
.slideover-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
}
.slideover-enter-active,
.slideover-leave-active {
  transition: opacity 0.3s ease;
}
.slideover-enter-active .slideover-panel,
.slideover-leave-active .slideover-panel {
  transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1);
}
.slideover-enter-from,
.slideover-leave-to {
  opacity: 0;
}
.slideover-enter-from .slideover-panel,
.slideover-leave-to .slideover-panel {
  transform: translateX(100%);
}
@media (prefers-reduced-motion: reduce) {
  .slideover-enter-active,
  .slideover-leave-active,
  .slideover-enter-active .slideover-panel,
  .slideover-leave-active .slideover-panel {
    transition: none;
  }
}
</style>
