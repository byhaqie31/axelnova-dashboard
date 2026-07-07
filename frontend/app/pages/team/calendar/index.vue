<script setup lang="ts">
// Team › Calendar (Task 5) — an Outlook-style two-column surface OVER the tasks
// engine. Left/main: the big calendar with a Today / Week / Month switcher,
// deadlines rendered as compact "inbox rows" (dot + title; details in the click
// slideover). Right rail: a mini-month navigator (jump to any date) above an
// "Upcoming" list of the next deadlines. All deadlines are all-day (a date, no
// time), which is why Week/Today are agenda-style, not an hour grid.
import TaskPayBadge from '~/components/shared/primitives/TaskPayBadge.vue'
import CalendarTaskRow from '~/components/team/CalendarTaskRow.vue'
import { taskPriorityMeta, type TaskRecord, type TeamTasksFeed } from '~/data/tasks'

definePageMeta({ layout: 'team', middleware: 'team-auth' })
useHead({ title: 'Calendar — Team' })

const { apiFetch } = useTeamAuth()

const pool = ref<TaskRecord[]>([])
const mine = ref<TaskRecord[]>([])
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

// ── View + cursor ─────────────────────────────────────────────────────────
type CalView = 'today' | 'week' | 'month'
const views: { value: CalView, label: string }[] = [
  { value: 'today', label: 'Today' },
  { value: 'week', label: 'Week' },
  { value: 'month', label: 'Month' },
]

const today = new Date()
const todayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate())
// Always opens on Today — the view isn't persisted; the calendar defaults to
// the current day on every load.
const view = ref<CalView>('today')
// A single reference day drives every view (its month for Month, its week for
// Week, itself for Today) AND the mini-month navigator in the rail.
const cursor = ref(new Date(today.getFullYear(), today.getMonth(), today.getDate()))

function setView(v: CalView) {
  view.value = v
}

function shift(delta: number) {
  const d = new Date(cursor.value)
  if (view.value === 'today') d.setDate(d.getDate() + delta)
  else if (view.value === 'week') d.setDate(d.getDate() + delta * 7)
  else d.setMonth(d.getMonth() + delta)
  cursor.value = d
}
function goToday() {
  cursor.value = new Date(today.getFullYear(), today.getMonth(), today.getDate())
}

function dayKey(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}
const todayKey = dayKey(today)

const headerLabel = computed(() => {
  const c = cursor.value
  if (view.value === 'today') return c.toLocaleDateString('en-MY', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
  if (view.value === 'week') {
    const { start, end } = weekRange.value
    const sameMonth = start.getMonth() === end.getMonth()
    const s = start.toLocaleDateString('en-MY', sameMonth ? { day: 'numeric' } : { day: 'numeric', month: 'short' })
    const e = end.toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
    return `${s} – ${e}`
  }
  return c.toLocaleDateString('en-MY', { month: 'long', year: 'numeric' })
})

// ── Shared chip index (all tasks, keyed by day) ────────────────────────────
interface CalendarChip { task: TaskRecord, source: 'mine' | 'pool' }
interface CalendarDay { key: string, date: Date, inMonth: boolean, isToday: boolean, chips: CalendarChip[] }

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

// Highest-priority colour among a day's chips — drives the mini-month dot.
function topPriorityColor(chips: CalendarChip[]): string | undefined {
  const rank = { high: 3, medium: 2, low: 1 } as const
  let best: CalendarChip | undefined
  for (const c of chips) {
    if (!best || rank[c.task.priority] > rank[best.task.priority]) best = c
  }
  return best ? taskPriorityMeta(best.task.priority)?.color : undefined
}

const weekdayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const weekdayInitials = ['M', 'T', 'W', 'T', 'F', 'S', 'S']

// ── Month grid (Monday-first, padded to full weeks) ────────────────────────
const gridDays = computed<CalendarDay[]>(() => {
  const year = cursor.value.getFullYear()
  const month = cursor.value.getMonth()
  const first = new Date(year, month, 1)
  const lead = (first.getDay() + 6) % 7
  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const total = Math.ceil((lead + daysInMonth) / 7) * 7

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

// Mobile month agenda — only this month's days that carry deadlines.
const agendaDays = computed(() => gridDays.value.filter(d => d.inMonth && d.chips.length > 0))

// ── Week (7 day-columns) ───────────────────────────────────────────────────
const weekRange = computed(() => {
  const c = cursor.value
  const lead = (c.getDay() + 6) % 7
  const start = new Date(c.getFullYear(), c.getMonth(), c.getDate() - lead)
  const end = new Date(start.getFullYear(), start.getMonth(), start.getDate() + 6)
  return { start, end }
})
const weekDays = computed<CalendarDay[]>(() => {
  const { start } = weekRange.value
  return Array.from({ length: 7 }, (_, i) => {
    const date = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i)
    const key = dayKey(date)
    return { key, date, inMonth: true, isToday: key === todayKey, chips: chipsByDay.value.get(key) ?? [] }
  })
})

// ── Today (focused single day) ─────────────────────────────────────────────
const cursorIsToday = computed(() => dayKey(cursor.value) === todayKey)
const dayChips = computed(() => chipsByDay.value.get(dayKey(cursor.value)) ?? [])
const overdueChips = computed<CalendarChip[]>(() => {
  const out: CalendarChip[] = []
  const scan = (arr: TaskRecord[], source: 'mine' | 'pool') => arr.forEach((t) => {
    if (t.deadline && !t.completed_at && new Date(t.deadline).getTime() < todayStart.getTime()) out.push({ task: t, source })
  })
  scan(mine.value, 'mine')
  scan(pool.value, 'pool')
  return out.sort((a, b) => new Date(a.task.deadline!).getTime() - new Date(b.task.deadline!).getTime())
})

// ── Mini-month navigator (rail) ────────────────────────────────────────────
interface MiniCell { date: Date, inMonth: boolean, has: boolean, isToday: boolean, isSelected: boolean, top?: string }
const miniLabel = computed(() => cursor.value.toLocaleDateString('en-MY', { month: 'long', year: 'numeric' }))
const miniDays = computed<MiniCell[]>(() => {
  const year = cursor.value.getFullYear()
  const month = cursor.value.getMonth()
  const first = new Date(year, month, 1)
  const lead = (first.getDay() + 6) % 7
  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const total = Math.ceil((lead + daysInMonth) / 7) * 7
  const selKey = dayKey(cursor.value)
  return Array.from({ length: total }, (_, i) => {
    const date = new Date(year, month, i - lead + 1)
    const inMonth = date.getMonth() === month
    const chips = inMonth ? (chipsByDay.value.get(dayKey(date)) ?? []) : []
    return {
      date,
      inMonth,
      has: chips.length > 0,
      isToday: dayKey(date) === todayKey,
      isSelected: inMonth && dayKey(date) === selKey,
      top: topPriorityColor(chips),
    }
  })
})
function shiftMiniMonth(delta: number) {
  const d = new Date(cursor.value)
  d.setMonth(d.getMonth() + delta)
  cursor.value = d
}
function pickDay(date: Date) {
  cursor.value = new Date(date.getFullYear(), date.getMonth(), date.getDate())
}

// ── Upcoming (rail) — flat soonest-first list from today forward ───────────
const UPCOMING_CAP = 10
const upcomingAll = computed<CalendarChip[]>(() => {
  const out: CalendarChip[] = []
  const scan = (arr: TaskRecord[], source: 'mine' | 'pool') => arr.forEach((t) => {
    if (t.deadline && !t.completed_at && new Date(t.deadline).getTime() >= todayStart.getTime()) out.push({ task: t, source })
  })
  scan(mine.value, 'mine')
  scan(pool.value, 'pool')
  return out.sort((a, b) => new Date(a.task.deadline!).getTime() - new Date(b.task.deadline!).getTime())
})
const upcoming = computed(() => upcomingAll.value.slice(0, UPCOMING_CAP))
const upcomingOverflow = computed(() => Math.max(0, upcomingAll.value.length - UPCOMING_CAP))

function relativeShort(iso: string): string {
  const d = new Date(iso)
  const day = new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime()
  const diff = Math.round((day - todayStart.getTime()) / 864e5)
  if (diff <= 0) return 'today'
  if (diff === 1) return 'tomorrow'
  if (diff < 7) return d.toLocaleDateString('en-MY', { weekday: 'short' })
  return d.toLocaleDateString('en-MY', { day: 'numeric', month: 'short' })
}

// ── Completed log (Month view) ─────────────────────────────────────────────
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

// ── Task detail (click a row) ──────────────────────────────────────────────
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

// "Add to Google Calendar" — a zero-backend render link. A task deadline is an
// all-day event (GCal treats the end date as exclusive, hence the +1 day).
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
    <div class="mb-6">
      <h1 class="text-[24px] font-bold tracking-tight" style="color: var(--color-text);">Calendar</h1>
      <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
        Your deadlines and open pool tasks — click any to see the details.
      </p>
    </div>

    <div class="lg:flex lg:gap-6">
      <!-- ══ MAIN ══════════════════════════════════════════════════════════ -->
      <div class="flex-1 min-w-0">
        <!-- Toolbar -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
          <div class="cal-viewswitch">
            <button
              v-for="v in views" :key="v.value" type="button"
              class="cal-viewbtn" :data-active="view === v.value"
              @click="setView(v.value)">
              {{ v.label }}
            </button>
          </div>
          <div class="flex items-center gap-1.5">
            <button type="button" class="cal-nav-btn" aria-label="Previous" @click="shift(-1)">
              <UIcon name="i-lucide-chevron-left" class="size-4" />
            </button>
            <button type="button" class="cal-nav-btn" aria-label="Next" @click="shift(1)">
              <UIcon name="i-lucide-chevron-right" class="size-4" />
            </button>
          </div>
        </div>

        <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>
        <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading calendar…</div>

        <template v-else>
          <!-- TODAY -->
          <div v-if="view === 'today'" class="space-y-6">
            <div v-if="cursorIsToday && overdueChips.length">
              <h2 class="cal-section-label" style="color: var(--color-danger);">Overdue</h2>
              <div class="cal-list">
                <div v-for="chip in overdueChips" :key="`od-${chip.source}-${chip.task.id}`" class="flex items-center gap-2">
                  <CalendarTaskRow :task="chip.task" :source="chip.source" :meta="fmtCompleted(chip.task.deadline!)" @select="openChip(chip)" />
                </div>
              </div>
            </div>

            <div>
              <h2 class="cal-section-label" style="color: var(--color-text-tertiary);">
                {{ cursorIsToday ? 'Today' : fmtDay(cursor) }}
              </h2>
              <div v-if="dayChips.length" class="cal-list">
                <CalendarTaskRow v-for="chip in dayChips" :key="`d-${chip.source}-${chip.task.id}`" :task="chip.task" :source="chip.source" @select="openChip(chip)" />
              </div>
              <div v-else class="rounded-2xl border px-6 py-10 text-center" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
                <p class="text-[13px]" style="color: var(--color-text-secondary);">Nothing due{{ cursorIsToday ? ' today' : ' this day' }}.</p>
              </div>
            </div>
          </div>

          <!-- WEEK -->
          <div v-else-if="view === 'week'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-2">
            <div
              v-for="day in weekDays" :key="day.key"
              class="cal-weekcol"
              :style="{ background: day.isToday ? 'var(--calendar-cell-muted-bg)' : 'var(--calendar-cell-bg)', borderColor: day.isToday ? 'var(--calendar-today-ring)' : 'var(--calendar-grid-border)' }">
              <div class="flex items-baseline justify-between mb-2 px-1">
                <span class="text-[11px] font-semibold uppercase tracking-wider" :style="{ color: day.isToday ? 'var(--calendar-today-ring)' : 'var(--color-text-tertiary)' }">
                  {{ day.date.toLocaleDateString('en-MY', { weekday: 'short' }) }}
                </span>
                <span class="cal-daynum" :class="{ 'cal-daynum-today': day.isToday }" :style="{ color: day.isToday ? 'var(--calendar-today-fg)' : 'var(--color-text-secondary)' }">
                  {{ day.date.getDate() }}
                </span>
              </div>
              <div v-if="day.chips.length" class="flex flex-col gap-0.5">
                <CalendarTaskRow v-for="chip in day.chips" :key="`w-${chip.source}-${chip.task.id}`" :task="chip.task" :source="chip.source" @select="openChip(chip)" />
              </div>
              <p v-else class="text-[11px] px-1 py-1" style="color: var(--color-text-tertiary);">—</p>
            </div>
          </div>

          <!-- MONTH -->
          <template v-else>
            <!-- Desktop grid -->
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
                  <div class="flex flex-col gap-0.5 mt-1 min-w-0">
                    <CalendarTaskRow
                      v-for="chip in day.chips.slice(0, 3)" :key="`${chip.source}-${chip.task.id}`"
                      :task="chip.task" :source="chip.source" @select="openChip(chip)" />
                    <span v-if="day.chips.length > 3" class="text-[10px] px-1.5" :style="{ color: 'var(--color-text-tertiary)' }">
                      +{{ day.chips.length - 3 }} more
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Mobile agenda -->
            <div class="md:hidden">
              <div
                v-if="!agendaDays.length"
                class="rounded-2xl border px-6 py-12 text-center"
                :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
                <p class="text-[13px]" style="color: var(--color-text-secondary);">No deadlines in {{ headerLabel }}.</p>
              </div>
              <div v-else class="space-y-2.5">
                <div
                  v-for="day in agendaDays" :key="day.key"
                  class="rounded-xl border p-3"
                  :style="{
                    borderColor: day.isToday ? 'var(--calendar-today-ring)' : 'var(--color-border)',
                    background: 'var(--calendar-cell-bg)',
                  }">
                  <p class="text-[11px] font-semibold uppercase tracking-wider mb-1.5 px-1" :style="{ color: day.isToday ? 'var(--calendar-today-ring)' : 'var(--color-text-tertiary)' }">
                    {{ fmtDay(day.date) }}<span v-if="day.isToday"> · today</span>
                  </p>
                  <div class="flex flex-col gap-0.5">
                    <CalendarTaskRow v-for="chip in day.chips" :key="`m-${chip.source}-${chip.task.id}`" :task="chip.task" :source="chip.source" @select="openChip(chip)" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Completed log -->
            <div class="mt-8">
              <h2 class="cal-section-label" style="color: var(--color-text-tertiary);">Completed in {{ headerLabel }}</h2>
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
        </template>
      </div>

      <!-- ══ RIGHT RAIL ════════════════════════════════════════════════════ -->
      <aside class="lg:w-[300px] lg:shrink-0 mt-8 lg:mt-0 space-y-6">
        <!-- Mini-month navigator -->
        <div class="rounded-2xl border p-4" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <div class="flex items-center justify-between mb-3 gap-2">
            <span class="text-[13px] font-semibold tracking-tight truncate" style="color: var(--color-text);">{{ miniLabel }}</span>
            <div class="flex items-center gap-1 shrink-0">
              <button type="button" class="cal-mini-today" @click="goToday">Today</button>
              <button type="button" class="cal-mini-nav" aria-label="Previous month" @click="shiftMiniMonth(-1)">
                <UIcon name="i-lucide-chevron-left" class="size-3.5" />
              </button>
              <button type="button" class="cal-mini-nav" aria-label="Next month" @click="shiftMiniMonth(1)">
                <UIcon name="i-lucide-chevron-right" class="size-3.5" />
              </button>
            </div>
          </div>
          <div class="cal-mini-grid">
            <span v-for="(wd, i) in weekdayInitials" :key="`wd-${i}`" class="cal-mini-wd">{{ wd }}</span>
            <button
              v-for="(cell, i) in miniDays" :key="`c-${i}`"
              type="button" class="cal-mini-day"
              :class="{ 'cal-mini-today': cell.isToday, 'cal-mini-sel': cell.isSelected }"
              :style="{ color: cell.isSelected ? '#fff' : cell.isToday ? 'var(--color-accent)' : cell.inMonth ? 'var(--color-text-secondary)' : 'var(--color-text-tertiary)', opacity: cell.inMonth ? 1 : 0.4 }"
              @click="pickDay(cell.date)">
              {{ cell.date.getDate() }}
              <span v-if="cell.has" class="cal-mini-dot" :style="{ background: cell.isSelected ? '#fff' : cell.top }" />
            </button>
          </div>
        </div>

        <!-- Upcoming -->
        <div class="rounded-2xl border p-4" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <h2 class="text-[11px] font-semibold uppercase tracking-widest mb-2" style="color: var(--color-text-tertiary);">Upcoming</h2>
          <div v-if="upcoming.length" class="flex flex-col gap-0.5">
            <CalendarTaskRow
              v-for="chip in upcoming" :key="`up-${chip.source}-${chip.task.id}`"
              :task="chip.task" :source="chip.source" :meta="relativeShort(chip.task.deadline!)"
              @select="openChip(chip)" />
            <p v-if="upcomingOverflow" class="text-[11px] px-1.5 pt-1" style="color: var(--color-text-tertiary);">+{{ upcomingOverflow }} more</p>
          </div>
          <p v-else class="text-[13px] py-2 px-1.5" style="color: var(--color-text-secondary);">Nothing upcoming.</p>
        </div>
      </aside>
    </div>

    <!-- Task detail (click a row) -->
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
/* Calendar (§12.15) — the --calendar-* token family (main.css). */
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

/* Segmented view switcher (Today / Week / Month). */
.cal-viewswitch {
  display: inline-flex;
  padding: 3px;
  gap: 2px;
  border-radius: 9999px;
  border: 1px solid var(--color-border);
  background: var(--color-bg-elevated);
}
.cal-viewbtn {
  padding: 4px 12px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 500;
  color: var(--color-text-secondary);
  transition: background 0.15s ease, color 0.15s ease;
}
.cal-viewbtn:hover {
  color: var(--color-text);
}
.cal-viewbtn[data-active='true'] {
  background: var(--color-accent);
  color: #fff;
}

.cal-section-label {
  font-size: 13px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 12px;
}
.cal-list {
  display: flex;
  flex-direction: column;
  gap: 2px;
  border-radius: 16px;
  border: 1px solid var(--color-border);
  background: var(--color-bg-elevated);
  padding: 8px;
}

/* Month grid cell — shorter now that chips are compact inbox rows. */
.cal-cell {
  min-height: 88px;
  padding: 6px;
  border-bottom: 1px solid var(--calendar-grid-border);
  border-right: 1px solid var(--calendar-grid-border);
  overflow: hidden;
}
.cal-cell:nth-child(7n) {
  border-right: none;
}

/* Week day-column. */
.cal-weekcol {
  min-height: 140px;
  padding: 8px;
  border-radius: 14px;
  border: 1px solid var(--calendar-grid-border);
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

/* Mini-month navigator (rail). */
.cal-mini-today {
  padding: 3px 10px;
  border-radius: 9999px;
  border: 1px solid var(--color-border);
  font-size: 11px;
  font-weight: 500;
  color: var(--color-text-secondary);
  transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.cal-mini-today:hover {
  background: var(--color-bg-secondary);
  color: var(--color-text);
  border-color: var(--color-border-strong);
}
.cal-mini-nav {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border-radius: 9999px;
  color: var(--color-text-secondary);
  transition: background 0.15s ease, color 0.15s ease;
}
.cal-mini-nav:hover {
  background: var(--color-bg-secondary);
  color: var(--color-text);
}
.cal-mini-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
}
.cal-mini-wd {
  text-align: center;
  font-size: 9px;
  font-weight: 600;
  color: var(--color-text-tertiary);
  padding-bottom: 4px;
}
.cal-mini-day {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 28px;
  border-radius: 8px;
  font-size: 11px;
  font-variant-numeric: tabular-nums;
  cursor: pointer;
  transition: background 0.12s ease;
}
.cal-mini-day:hover {
  background: var(--color-bg-secondary);
}
/* Today = a soft pastel-blue fill so it stays clearly visible even when another
   day is selected. The selected day takes the solid accent (below) and always
   wins when today itself is selected. */
.cal-mini-today,
.cal-mini-today:hover {
  background: var(--color-accent-soft);
  font-weight: 600;
}
.cal-mini-sel,
.cal-mini-sel:hover {
  background: var(--calendar-today-ring);
}
.cal-mini-dot {
  position: absolute;
  bottom: 3px;
  left: 50%;
  transform: translateX(-50%);
  width: 3px;
  height: 3px;
  border-radius: 9999px;
}

/* Slideover (§12.13) — same class names + motion as the admin panels. */
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
