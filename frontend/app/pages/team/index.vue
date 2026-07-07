<script setup lang="ts">
// Team Home (Task 4, revamped) — from a plain announcements board into a
// friendly personal base: a time-aware greeting, an availability toggle, a row
// of at-a-glance work tiles, the next deadline, then the announcements feed.
// Tiles + next-up are derived client-side from the existing /v1/team/tasks feed
// (the same one the kanban and calendar read) — no new backend.
import { availabilityOptions } from '~/data/availabilityStatuses'
import { taskPriorityMeta, type TaskRecord, type TeamTasksFeed } from '~/data/tasks'
import TaskPayBadge from '~/components/shared/primitives/TaskPayBadge.vue'

definePageMeta({ layout: 'team', middleware: 'team-auth' })
useHead({ title: 'Home — Team' })

// Shared /v1/team/me state (composables/useTeamMe.ts) — the same ref the team
// layout renders, so toggling availability here updates the header instantly.
const { me, refresh: fetchMe } = useTeamMe()
const { apiFetch } = useTeamAuth()
const toast = useAdminToast()

// ── Announcements feed ─────────────────────────────────────────────────────
interface Announcement {
  id: number
  title: string
  body: string
  published_at: string
}
const announcements = ref<Announcement[]>([])
const feedLoading = ref(true)

async function fetchAnnouncements() {
  feedLoading.value = true
  try {
    const res = await apiFetch<{ data: Announcement[] }>('/api/v1/team/announcements')
    announcements.value = res.data
  }
  catch {
    // Low-stakes surface — a failed feed degrades to the empty state, no toast.
    announcements.value = []
  }
  finally {
    feedLoading.value = false
  }
}

// ── My work snapshot (derived from /v1/team/tasks → mine) ──────────────────
const mine = ref<TaskRecord[]>([])
const tasksLoading = ref(true)

async function fetchTasks() {
  tasksLoading.value = true
  try {
    const res = await apiFetch<TeamTasksFeed>('/api/v1/team/tasks')
    mine.value = res.mine
  }
  catch {
    mine.value = []
  }
  finally {
    tasksLoading.value = false
  }
}

// ── Client-only clock (avoids an SSR/hydration mismatch on the greeting) ───
const now = ref<Date | null>(null)

onMounted(() => {
  fetchMe()
  fetchAnnouncements()
  fetchTasks()
  now.value = new Date()
  hype.value = hypeLines[Math.floor(Math.random() * hypeLines.length)]!
})

const firstName = computed(() => me.value?.name?.split(' ')[0] ?? '')

const greetingPart = computed(() => {
  const h = now.value?.getHours()
  if (h == null) return 'Welcome'
  if (h < 12) return 'Good morning'
  if (h < 18) return 'Good afternoon'
  return 'Good evening'
})

// A little motivation, in the voice of the welcome email. Seeded to a fixed
// line for SSR/first paint, then randomised on mount so both agree.
const hypeLines = [
  'Strive together, grow together. 🌱',
  'Let’s make today count. 🚀',
  'Small steps, big momentum — you’ve got this.',
  'Great work compounds. Keep shipping.',
  'One focused hour beats a scattered day.',
]
const hype = ref(hypeLines[0])

// ── Snapshot tiles ─────────────────────────────────────────────────────────
const money = new Intl.NumberFormat('ms-MY', { maximumFractionDigits: 0 })

function startOfToday(): Date {
  const d = now.value ?? new Date()
  return new Date(d.getFullYear(), d.getMonth(), d.getDate())
}

const openCount = computed(() =>
  mine.value.filter(t => t.status === 'open' || t.status === 'in_progress').length)

const dueThisWeek = computed(() => {
  const start = startOfToday().getTime()
  const end = start + 7 * 864e5
  return mine.value.filter((t) => {
    if (!t.deadline || t.completed_at) return false
    const d = new Date(t.deadline).getTime()
    return d >= start && d < end
  }).length
})

const completedThisMonth = computed(() => {
  const ref = now.value ?? new Date()
  return mine.value.filter((t) => {
    if (!t.completed_at) return false
    const d = new Date(t.completed_at)
    return d.getFullYear() === ref.getFullYear() && d.getMonth() === ref.getMonth()
  }).length
})

const pendingPay = computed(() =>
  mine.value
    .filter(t => t.payment_state === 'pending' && t.pay_amount_myr != null)
    .reduce((sum, t) => sum + (t.pay_amount_myr ?? 0), 0))

const tiles = computed(() => [
  { key: 'open', label: 'Open tasks', value: String(openCount.value), icon: 'i-lucide-list-todo', fg: 'var(--color-accent)', bg: 'var(--color-accent-soft)', to: '/team/tasks' },
  { key: 'due', label: 'Due this week', value: String(dueThisWeek.value), icon: 'i-lucide-alarm-clock', fg: 'var(--color-warning)', bg: 'var(--color-warning-soft)', to: '/team/calendar' },
  { key: 'done', label: 'Completed this month', value: String(completedThisMonth.value), icon: 'i-lucide-circle-check', fg: 'var(--color-success)', bg: 'var(--color-success-soft)', to: '/team/calendar' },
  { key: 'pay', label: 'Pending pay', value: `RM ${money.format(pendingPay.value)}`, icon: 'i-lucide-banknote', fg: 'var(--color-accent)', bg: 'var(--color-accent-soft)', to: '/team/payslips' },
])

// ── Next up (soonest upcoming, still-open deadline) ────────────────────────
const nextUp = computed<TaskRecord | null>(() => {
  const start = startOfToday().getTime()
  return mine.value
    .filter(t => t.deadline && !t.completed_at && new Date(t.deadline).getTime() >= start)
    .sort((a, b) => new Date(a.deadline!).getTime() - new Date(b.deadline!).getTime())[0] ?? null
})

function dayDiffFromToday(iso: string): number {
  const d = new Date(iso)
  const day = new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime()
  return Math.round((day - startOfToday().getTime()) / 864e5)
}
function relativeDue(iso: string): string {
  const days = dayDiffFromToday(iso)
  if (days <= 0) return 'Due today'
  if (days === 1) return 'Due tomorrow'
  if (days < 7) return `Due in ${days} days`
  return `Due ${new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short' })}`
}

// ── Availability toggle (same PATCH /v1/team/me the profile page uses) ─────
const savingAvail = ref(false)
async function setAvailability(value: 'available' | 'busy') {
  if (!me.value || savingAvail.value || me.value.availability === value) return
  const previous = me.value.availability
  me.value = { ...me.value, availability: value } // optimistic
  savingAvail.value = true
  try {
    me.value = await apiFetch<NonNullable<typeof me.value>>('/api/v1/team/me', {
      method: 'PATCH',
      body: { name: me.value.name, availability: value },
    })
  }
  catch (e: any) {
    if (me.value) me.value = { ...me.value, availability: previous } // revert
    toast.error('Couldn’t update availability', e?.data?.message ?? 'Please try again.')
  }
  finally {
    savingAvail.value = false
  }
}

function fmtDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <!-- Greeting hero + availability toggle -->
    <div class="flex items-start justify-between gap-4 flex-wrap mb-8">
      <div class="min-w-0">
        <h1 class="text-[26px] sm:text-[30px] font-bold tracking-tight" style="color: var(--color-text);">
          <template v-if="firstName">{{ greetingPart }}, <span class="text-gradient">{{ firstName }}</span></template>
          <template v-else>{{ greetingPart === 'Welcome' ? 'Team Workspace' : greetingPart }}</template>
        </h1>
        <p class="text-[14px] mt-1.5" style="color: var(--color-text-secondary);">{{ hype }}</p>
      </div>

      <div v-if="me" class="flex items-center gap-1.5 shrink-0">
        <span class="text-[11px] font-medium uppercase tracking-wider mr-1 hidden sm:inline" style="color: var(--color-text-tertiary);">Status</span>
        <button
          v-for="opt in availabilityOptions" :key="opt.value" type="button"
          class="standard-pill" :disabled="savingAvail"
          :style="me.availability === opt.value
            ? { borderColor: opt.color, background: opt.bg, color: opt.color }
            : { borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
          @click="setAvailability(opt.value)"
        >
          <span class="size-1.5 rounded-full" :style="{ background: opt.color }" />
          {{ opt.label }}
        </button>
      </div>
    </div>

    <!-- Snapshot tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-8">
      <NuxtLink
        v-for="tile in tiles" :key="tile.key" :to="tile.to"
        class="dash-tile"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
      >
        <span class="size-9 rounded-xl inline-flex items-center justify-center mb-3" :style="{ background: tile.bg, color: tile.fg }">
          <UIcon :name="tile.icon" class="size-[18px]" />
        </span>
        <span v-if="tasksLoading" class="dash-skeleton" />
        <span v-else class="block text-[22px] sm:text-[26px] font-bold tracking-tight tabular-nums" style="color: var(--color-text);">{{ tile.value }}</span>
        <span class="block text-[12px] mt-0.5" style="color: var(--color-text-secondary);">{{ tile.label }}</span>
      </NuxtLink>
    </div>

    <!-- Next up -->
    <section class="mb-8">
      <h2 class="text-[13px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Next up</h2>

      <div v-if="tasksLoading" class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <span class="dash-skeleton" style="width: 55%;" />
      </div>

      <NuxtLink
        v-else-if="nextUp" to="/team/calendar"
        class="dash-row flex items-center justify-between gap-3 rounded-2xl border p-5"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
      >
        <div class="min-w-0 flex items-center gap-3">
          <span class="size-2.5 rounded-full shrink-0" :style="{ background: taskPriorityMeta(nextUp.priority)?.color }" />
          <div class="min-w-0">
            <p class="text-[15px] font-semibold tracking-tight truncate" style="color: var(--color-text);">{{ nextUp.title }}</p>
            <p class="text-[12px] mt-0.5" style="color: var(--color-text-secondary);">{{ relativeDue(nextUp.deadline!) }}</p>
          </div>
        </div>
        <TaskPayBadge :state="nextUp.payment_state" :amount="nextUp.pay_amount_myr" />
      </NuxtLink>

      <div v-else class="rounded-2xl border px-6 py-8 text-center" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <p class="text-[14px] font-semibold tracking-tight" style="color: var(--color-text);">You’re all caught up 🎉</p>
        <p class="text-[12px] mt-1" style="color: var(--color-text-secondary);">No upcoming deadlines on your plate.</p>
      </div>
    </section>

    <!-- Announcements -->
    <section>
      <h2 class="text-[13px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Announcements</h2>

      <div v-if="feedLoading" class="text-center py-12" style="color: var(--color-text-secondary);">Loading announcements…</div>

      <div
        v-else-if="!announcements.length"
        class="rounded-2xl border px-6 py-14 text-center"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
      >
        <span class="size-12 rounded-2xl inline-flex items-center justify-center mb-4" :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }">
          <UIcon name="i-lucide-radio" class="size-6" />
        </span>
        <p class="text-[15px] font-semibold tracking-tight mb-1" style="color: var(--color-text);">No announcements yet</p>
        <p class="text-[13px] max-w-sm mx-auto leading-relaxed" style="color: var(--color-text-secondary);">
          Company-wide notices will show up here as soon as one is posted.
        </p>
      </div>

      <div v-else class="flex flex-col gap-4">
        <article
          v-for="item in announcements" :key="item.id"
          class="dash-note rounded-2xl border p-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <div class="flex items-start gap-3">
            <span class="size-9 rounded-xl inline-flex items-center justify-center shrink-0 mt-0.5" :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }">
              <UIcon name="i-lucide-megaphone" class="size-[18px]" />
            </span>
            <div class="min-w-0 flex-1">
              <div class="flex items-start justify-between gap-3 mb-1">
                <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--color-text);">{{ item.title }}</h3>
                <span class="text-[11px] shrink-0 whitespace-nowrap mt-0.5" style="color: var(--color-text-tertiary);">{{ fmtDate(item.published_at) }}</span>
              </div>
              <p class="text-[13px] leading-relaxed whitespace-pre-line max-w-2xl" style="color: var(--color-text-secondary);">{{ item.body }}</p>
            </div>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* Dashboard tiles + rows (team home). Elevated surfaces that lift on hover —
   motion within the §8 0.2s band, disabled under reduced-motion. */
.dash-tile {
  border-width: 1px;
  border-radius: 16px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}
.dash-tile:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
  border-color: var(--color-border-strong);
}

.dash-row {
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.dash-row:hover {
  border-color: var(--color-border-strong);
  box-shadow: var(--shadow-sm);
}

/* Colored accent rail on announcement cards. */
.dash-note {
  border-left-width: 3px;
  border-left-color: var(--color-accent);
}

.dash-skeleton {
  display: block;
  height: 26px;
  width: 40%;
  border-radius: 6px;
  background: var(--color-bg-secondary);
  animation: dash-pulse 1.2s ease-in-out infinite;
}
@keyframes dash-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
@media (prefers-reduced-motion: reduce) {
  .dash-tile,
  .dash-row { transition: none; }
  .dash-tile:hover { transform: none; }
  .dash-skeleton { animation: none; }
}
</style>
