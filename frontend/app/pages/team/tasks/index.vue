<script setup lang="ts">
// Team › Tasks (Task 5) — the workspace kanban. Three WORK columns only:
// Available → In progress → Complete. Payment is a card badge (none/pending/
// paid via TaskPayBadge), never a column — most tasks carry no extra pay.
// Buttons move cards (premium-minimal + mobile-friendly), not drag-and-drop.
// At <768px the columns stack vertically, Available first (chosen over
// horizontal scroll-snap: a single scroll axis is calmer and the pool — the
// "what can I grab?" question — deserves the first screenful).
import StatusPill from '~/components/shared/primitives/StatusPill.vue'
import TaskPayBadge from '~/components/shared/primitives/TaskPayBadge.vue'
import { taskPriorityMeta, type TaskRecord, type TeamTasksFeed } from '~/data/tasks'
import TaskDetailDrawer from '~/components/team/TaskDetailDrawer.vue'

definePageMeta({ layout: 'team', middleware: 'team-auth' })
useHead({ title: 'Tasks — Team' })

const { apiFetch } = useTeamAuth()
const { me } = useTeamMe()
const toast = useAdminToast()

// Typed extraction of the API error message (avoids `catch (e: any)`).
function errMessage(e: unknown): string | undefined {
  return (e as { data?: { message?: string } } | null)?.data?.message
}

const pool = ref<TaskRecord[]>([])
const mine = ref<TaskRecord[]>([])
// Founder-only: everyone else's assigned tasks. The API only sends the key to
// founders, so this stays empty (and the toggle hidden) for other roles.
const team = ref<TaskRecord[]>([])
// Starts true — fetch runs in onMounted only, so a false default would render
// the empty state during SSR/first paint (Task-2 convention).
const loading = ref(true)
const error = ref('')

async function fetchBoard() {
  error.value = ''
  try {
    const res = await apiFetch<TeamTasksFeed>('/api/v1/team/tasks')
    pool.value = res.pool
    mine.value = res.mine
    team.value = res.team ?? []
  }
  catch {
    error.value = 'Failed to load the board. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchBoard)

// ── Scope — founders can flip the board from "Mine" to "Whole team". In team
// scope the In progress / Complete columns also show everyone else's cards
// (read-only, labelled with the assignee) and Available gains their
// admin-assigned-but-unstarted tasks. The choice sticks per browser.
const isFounder = computed(() => me.value?.role === 'founder')
const scope = ref<'mine' | 'team'>('mine')
const SCOPE_KEY = 'team-tasks-scope'
onMounted(() => {
  try {
    if (localStorage.getItem(SCOPE_KEY) === 'team') scope.value = 'team'
  }
  catch { /* storage unavailable — default to mine */ }
})
watch(scope, (v) => {
  try { localStorage.setItem(SCOPE_KEY, v) }
  catch { /* ignore */ }
})
const teamScope = computed(() => isFounder.value && scope.value === 'team')

// Column split. "Available" = the shared pool PLUS my admin-assigned tasks that
// I haven't started (status open) — those render with a "Start" action instead
// of "Pick up" and an "Assigned to you" tag.
const myStartable = computed(() => mine.value.filter(t => t.status === 'open'))
const inProgress = computed(() => mine.value.filter(t => t.status === 'in_progress'))
const complete = computed(() => mine.value.filter(t => ['completed', 'payment_pending', 'paid'].includes(t.status)))

// Team-scope extras — someone else's cards, never actionable from here.
const teamStartable = computed(() => teamScope.value ? team.value.filter(t => t.status === 'open') : [])
const teamInProgress = computed(() => teamScope.value ? team.value.filter(t => t.status === 'in_progress') : [])
const teamComplete = computed(() => teamScope.value ? team.value.filter(t => ['completed', 'payment_pending', 'paid'].includes(t.status)) : [])

const availableCount = computed(() => pool.value.length + myStartable.value.length + teamStartable.value.length)
const inProgressCount = computed(() => inProgress.value.length + teamInProgress.value.length)
const completeCount = computed(() => complete.value.length + teamComplete.value.length)

// ── Actions ──────────────────────────────────────────────────────────────
const actingId = ref<number | null>(null)

// ── Detail drawer — read-only full view of a card (§12.13 slideover). The
// variant decides which single action the footer offers (pool and startable
// both have status `open` but different verbs). Acting closes the drawer and
// hands off to the shared handler below.
type DetailVariant = 'pool' | 'startable' | 'in_progress' | 'done' | 'team'
const detailTask = ref<TaskRecord | null>(null)
const detailVariant = ref<DetailVariant>('pool')

function openDetail(task: TaskRecord, variant: DetailVariant) {
  detailTask.value = task
  detailVariant.value = variant
}

function onDetailAction(fn: (t: TaskRecord) => void) {
  const t = detailTask.value
  detailTask.value = null
  if (t) fn(t)
}

async function claim(task: TaskRecord) {
  if (actingId.value !== null) return
  actingId.value = task.id
  try {
    await apiFetch(`/api/v1/team/tasks/${task.id}/claim`, { method: 'POST' })
    toast.success('Task picked up', `"${task.title}" is now in progress.`)
  }
  catch (e) {
    // 409 = someone got there first; refetch below clears the stale card.
    toast.error('Couldn’t pick it up', errMessage(e) ?? 'Please try again.')
  }
  finally {
    actingId.value = null
    await fetchBoard()
  }
}

async function start(task: TaskRecord) {
  if (actingId.value !== null) return
  actingId.value = task.id
  try {
    await apiFetch(`/api/v1/team/tasks/${task.id}/status`, { method: 'PATCH', body: { status: 'in_progress' } })
    toast.success('Task started')
  }
  catch (e) {
    toast.error('Couldn’t start it', errMessage(e) ?? 'Please try again.')
  }
  finally {
    actingId.value = null
    await fetchBoard()
  }
}

async function release(task: TaskRecord) {
  if (actingId.value !== null) return
  actingId.value = task.id
  try {
    await apiFetch(`/api/v1/team/tasks/${task.id}/status`, { method: 'PATCH', body: { status: 'open' } })
    toast.success('Task released', 'It’s back in the pool for someone else.')
  }
  catch (e) {
    toast.error('Couldn’t release it', errMessage(e) ?? 'Please try again.')
  }
  finally {
    actingId.value = null
    await fetchBoard()
  }
}

// ── Complete… dialog — the note is required (it becomes the timestamped log
// line on the task), so completion always says what was done.
const completing = ref<TaskRecord | null>(null)
const completionNote = ref('')
const submittingComplete = ref(false)

function openComplete(task: TaskRecord) {
  completing.value = task
  completionNote.value = ''
}

async function submitComplete() {
  const task = completing.value
  if (!task || submittingComplete.value) return
  if (!completionNote.value.trim()) {
    toast.error('Add a completion note', 'Say what was done — it goes on the task log.')
    return
  }
  submittingComplete.value = true
  try {
    await apiFetch(`/api/v1/team/tasks/${task.id}/status`, {
      method: 'PATCH',
      body: { status: 'completed', note: completionNote.value.trim() },
    })
    toast.success(
      'Task completed',
      task.pay_amount_myr != null ? 'Bonus payment is now pending with the founder.' : 'Nice work.',
    )
    completing.value = null
  }
  catch (e) {
    toast.error('Couldn’t complete it', errMessage(e) ?? 'Please try again.')
  }
  finally {
    submittingComplete.value = false
    await fetchBoard()
  }
}

// Escape closes the topmost layer: the Complete dialog first, then the drawer.
onKeyStroke('Escape', () => {
  if (completing.value) {
    if (!submittingComplete.value) completing.value = null
    return
  }
  if (detailTask.value) detailTask.value = null
})

// Relative deadline label; overdue reads danger.
function deadlineInfo(iso: string | null): { label: string, overdue: boolean } | null {
  if (!iso) return null
  const days = Math.ceil((new Date(iso).getTime() - Date.now()) / 86400000)
  if (days < 0) return { label: `${Math.abs(days)}d overdue`, overdue: true }
  if (days === 0) return { label: 'due today', overdue: false }
  if (days === 1) return { label: 'due tomorrow', overdue: false }
  return { label: `due in ${days}d`, overdue: false }
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <div class="mb-8 flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-[24px] font-bold tracking-tight" style="color: var(--color-text);">Tasks</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
          {{ teamScope ? 'Everything in flight across the team — your own cards stay actionable.' : 'Pick up work from the pool, move it along, complete it with a note.' }}
        </p>
      </div>
      <!-- Founder-only scope toggle -->
      <div v-if="isFounder" class="flex gap-1.5" role="group" aria-label="Board scope">
        <button
          v-for="opt in ([['mine', 'Mine'], ['team', 'Whole team']] as const)" :key="opt[0]"
          type="button" class="standard-pill"
          :style="scope === opt[0] ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' } : {}"
          :aria-pressed="scope === opt[0]"
          @click="scope = opt[0]">
          <UIcon :name="opt[0] === 'mine' ? 'i-lucide-user' : 'i-lucide-users'" class="size-3.5" />
          {{ opt[1] }}
        </button>
      </div>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading the board…</div>

    <!-- Kanban — 3-col on desktop, stacked (Available first) at <768px -->
    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">

      <!-- Available -->
      <section class="kanban-col">
        <header class="kanban-col-head">
          <UIcon name="i-lucide-inbox" class="size-4" :style="{ color: 'var(--color-text-tertiary)' }" />
          <h2>Available</h2>
          <span class="kanban-count">{{ availableCount }}</span>
        </header>

        <p v-if="!availableCount" class="kanban-empty">Nothing waiting — the pool is empty.</p>

        <div v-else class="flex flex-col gap-2.5">
          <!-- My admin-assigned, not-yet-started tasks come first: they're mine to start. -->
          <article
            v-for="t in myStartable" :key="`mine-${t.id}`" class="kanban-card"
            role="button" tabindex="0" @click="openDetail(t, 'startable')"
            @keydown.enter="openDetail(t, 'startable')" @keydown.space.prevent="openDetail(t, 'startable')">
            <div class="flex items-start justify-between gap-2 mb-1.5">
              <h3 class="kanban-card-title">{{ t.title }}</h3>
              <span
                class="shrink-0 text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
                :style="{ color: 'var(--color-accent)', background: 'var(--color-accent-soft)' }">Assigned to you</span>
            </div>
            <p v-if="t.description" class="kanban-card-desc">{{ t.description }}</p>
            <div class="kanban-card-meta">
              <span
                class="capitalize font-medium px-1.5 py-0.5 rounded"
                :style="{ color: taskPriorityMeta(t.priority)?.color, background: taskPriorityMeta(t.priority)?.bg }">{{ t.priority }}</span>
              <span v-if="deadlineInfo(t.deadline)" :style="{ color: deadlineInfo(t.deadline)!.overdue ? 'var(--color-danger)' : 'var(--color-text-tertiary)' }">
                {{ deadlineInfo(t.deadline)!.label }}
              </span>
              <span v-if="t.duration_estimate" :style="{ color: 'var(--color-text-tertiary)' }">est. {{ t.duration_estimate }}</span>
              <TaskPayBadge :state="t.payment_state" :amount="t.pay_amount_myr" />
            </div>
            <button
              type="button" class="btn-pill btn-pill-accent text-[12px] w-full justify-center mt-3"
              :disabled="actingId !== null" @click.stop="start(t)">
              {{ actingId === t.id ? 'Starting…' : 'Start' }}
            </button>
          </article>

          <article
            v-for="t in pool" :key="t.id" class="kanban-card"
            role="button" tabindex="0" @click="openDetail(t, 'pool')"
            @keydown.enter="openDetail(t, 'pool')" @keydown.space.prevent="openDetail(t, 'pool')">
            <h3 class="kanban-card-title mb-1.5">{{ t.title }}</h3>
            <p v-if="t.description" class="kanban-card-desc">{{ t.description }}</p>
            <div class="kanban-card-meta">
              <span
                class="capitalize font-medium px-1.5 py-0.5 rounded"
                :style="{ color: taskPriorityMeta(t.priority)?.color, background: taskPriorityMeta(t.priority)?.bg }">{{ t.priority }}</span>
              <span v-if="deadlineInfo(t.deadline)" :style="{ color: deadlineInfo(t.deadline)!.overdue ? 'var(--color-danger)' : 'var(--color-text-tertiary)' }">
                {{ deadlineInfo(t.deadline)!.label }}
              </span>
              <span v-if="t.duration_estimate" :style="{ color: 'var(--color-text-tertiary)' }">est. {{ t.duration_estimate }}</span>
              <TaskPayBadge :state="t.payment_state" :amount="t.pay_amount_myr" />
            </div>
            <button
              type="button" class="btn-pill btn-pill-primary text-[12px] w-full justify-center mt-3"
              :disabled="actingId !== null" @click.stop="claim(t)">
              {{ actingId === t.id ? 'Picking up…' : 'Pick up' }}
            </button>
          </article>

          <!-- Team scope: someone else's assigned-but-unstarted tasks (read-only). -->
          <article
            v-for="t in teamStartable" :key="`team-${t.id}`" class="kanban-card kanban-card-team"
            role="button" tabindex="0" @click="openDetail(t, 'team')"
            @keydown.enter="openDetail(t, 'team')" @keydown.space.prevent="openDetail(t, 'team')">
            <div class="flex items-start justify-between gap-2 mb-1.5">
              <h3 class="kanban-card-title">{{ t.title }}</h3>
              <span class="kanban-assignee"><UIcon name="i-lucide-user" class="size-3" />{{ t.assignee_name }}</span>
            </div>
            <p v-if="t.description" class="kanban-card-desc">{{ t.description }}</p>
            <div class="kanban-card-meta">
              <span
                class="capitalize font-medium px-1.5 py-0.5 rounded"
                :style="{ color: taskPriorityMeta(t.priority)?.color, background: taskPriorityMeta(t.priority)?.bg }">{{ t.priority }}</span>
              <span v-if="deadlineInfo(t.deadline)" :style="{ color: deadlineInfo(t.deadline)!.overdue ? 'var(--color-danger)' : 'var(--color-text-tertiary)' }">
                {{ deadlineInfo(t.deadline)!.label }}
              </span>
              <span :style="{ color: 'var(--color-text-tertiary)' }">not started</span>
              <TaskPayBadge :state="t.payment_state" :amount="t.pay_amount_myr" />
            </div>
          </article>
        </div>
      </section>

      <!-- In progress -->
      <section class="kanban-col">
        <header class="kanban-col-head">
          <UIcon name="i-lucide-loader-circle" class="size-4" :style="{ color: 'var(--color-accent)' }" />
          <h2>In progress</h2>
          <span class="kanban-count">{{ inProgressCount }}</span>
        </header>

        <p v-if="!inProgressCount" class="kanban-empty">{{ teamScope ? 'Nobody has anything in flight.' : 'Nothing in flight. Pick something up.' }}</p>

        <div v-else class="flex flex-col gap-2.5">
          <article
            v-for="t in inProgress" :key="t.id" class="kanban-card"
            role="button" tabindex="0" @click="openDetail(t, 'in_progress')"
            @keydown.enter="openDetail(t, 'in_progress')" @keydown.space.prevent="openDetail(t, 'in_progress')">
            <h3 class="kanban-card-title mb-1.5">{{ t.title }}</h3>
            <p v-if="t.description" class="kanban-card-desc">{{ t.description }}</p>
            <div class="kanban-card-meta">
              <span
                class="capitalize font-medium px-1.5 py-0.5 rounded"
                :style="{ color: taskPriorityMeta(t.priority)?.color, background: taskPriorityMeta(t.priority)?.bg }">{{ t.priority }}</span>
              <span v-if="deadlineInfo(t.deadline)" :style="{ color: deadlineInfo(t.deadline)!.overdue ? 'var(--color-danger)' : 'var(--color-text-tertiary)' }">
                {{ deadlineInfo(t.deadline)!.label }}
              </span>
              <span v-if="t.duration_estimate" :style="{ color: 'var(--color-text-tertiary)' }">est. {{ t.duration_estimate }}</span>
              <TaskPayBadge :state="t.payment_state" :amount="t.pay_amount_myr" />
            </div>
            <div class="flex gap-2 mt-3">
              <button
                type="button" class="btn-pill btn-pill-primary text-[12px] flex-1 justify-center"
                :disabled="actingId !== null" @click.stop="openComplete(t)">
                Complete…
              </button>
              <button
                type="button" class="btn-pill btn-pill-ghost text-[12px]"
                :disabled="actingId !== null" @click.stop="release(t)">
                {{ actingId === t.id ? '…' : 'Release' }}
              </button>
            </div>
          </article>

          <!-- Team scope: everyone else's in-flight work (read-only). -->
          <article
            v-for="t in teamInProgress" :key="`team-${t.id}`" class="kanban-card kanban-card-team"
            role="button" tabindex="0" @click="openDetail(t, 'team')"
            @keydown.enter="openDetail(t, 'team')" @keydown.space.prevent="openDetail(t, 'team')">
            <div class="flex items-start justify-between gap-2 mb-1.5">
              <h3 class="kanban-card-title">{{ t.title }}</h3>
              <span class="kanban-assignee"><UIcon name="i-lucide-user" class="size-3" />{{ t.assignee_name }}</span>
            </div>
            <p v-if="t.description" class="kanban-card-desc">{{ t.description }}</p>
            <div class="kanban-card-meta">
              <span
                class="capitalize font-medium px-1.5 py-0.5 rounded"
                :style="{ color: taskPriorityMeta(t.priority)?.color, background: taskPriorityMeta(t.priority)?.bg }">{{ t.priority }}</span>
              <span v-if="deadlineInfo(t.deadline)" :style="{ color: deadlineInfo(t.deadline)!.overdue ? 'var(--color-danger)' : 'var(--color-text-tertiary)' }">
                {{ deadlineInfo(t.deadline)!.label }}
              </span>
              <span v-if="t.started_at" :style="{ color: 'var(--color-text-tertiary)' }">
                since {{ new Date(t.started_at).toLocaleDateString('en-MY', { day: 'numeric', month: 'short' }) }}
              </span>
              <TaskPayBadge :state="t.payment_state" :amount="t.pay_amount_myr" />
            </div>
          </article>
        </div>
      </section>

      <!-- Complete (read-only) -->
      <section class="kanban-col">
        <header class="kanban-col-head">
          <UIcon name="i-lucide-circle-check" class="size-4" :style="{ color: 'var(--color-success)' }" />
          <h2>Complete</h2>
          <span class="kanban-count">{{ completeCount }}</span>
        </header>

        <p v-if="!completeCount" class="kanban-empty">Completed tasks land here.</p>

        <div v-else class="flex flex-col gap-2.5">
          <article
            v-for="t in complete" :key="t.id" class="kanban-card kanban-card-done"
            role="button" tabindex="0" @click="openDetail(t, 'done')"
            @keydown.enter="openDetail(t, 'done')" @keydown.space.prevent="openDetail(t, 'done')">
            <div class="flex items-start justify-between gap-2 mb-1.5">
              <h3 class="kanban-card-title">{{ t.title }}</h3>
              <StatusPill :status="t.status" type="task" />
            </div>
            <div class="kanban-card-meta">
              <span v-if="t.completed_at" :style="{ color: 'var(--color-text-tertiary)' }">
                done {{ new Date(t.completed_at).toLocaleDateString('en-MY', { day: 'numeric', month: 'short' }) }}
              </span>
              <TaskPayBadge :state="t.payment_state" :amount="t.pay_amount_myr" />
            </div>
          </article>

          <!-- Team scope: everyone else's finished work (read-only). -->
          <article
            v-for="t in teamComplete" :key="`team-${t.id}`" class="kanban-card kanban-card-done kanban-card-team"
            role="button" tabindex="0" @click="openDetail(t, 'team')"
            @keydown.enter="openDetail(t, 'team')" @keydown.space.prevent="openDetail(t, 'team')">
            <div class="flex items-start justify-between gap-2 mb-1.5">
              <h3 class="kanban-card-title">{{ t.title }}</h3>
              <StatusPill :status="t.status" type="task" />
            </div>
            <div class="kanban-card-meta">
              <span class="kanban-assignee"><UIcon name="i-lucide-user" class="size-3" />{{ t.assignee_name }}</span>
              <span v-if="t.completed_at" :style="{ color: 'var(--color-text-tertiary)' }">
                done {{ new Date(t.completed_at).toLocaleDateString('en-MY', { day: 'numeric', month: 'short' }) }}
              </span>
              <TaskPayBadge :state="t.payment_state" :amount="t.pay_amount_myr" />
            </div>
          </article>
        </div>
      </section>
    </div>

    <!-- Read-only detail drawer — full description + notes + the one contextual
         action. Acting closes it and hands off to the shared card handlers. -->
    <TaskDetailDrawer
      :task="detailTask"
      :variant="detailVariant"
      :busy="actingId !== null"
      @close="detailTask = null"
      @pickup="onDetailAction(claim)"
      @start="onDetailAction(start)"
      @complete="onDetailAction(openComplete)"
      @release="onDetailAction(release)" />

    <!-- Complete… dialog — required note -->
    <Teleport to="body">
      <Transition name="confirm-fade">
        <div v-if="completing" class="confirm-overlay" @click.self="!submittingComplete && (completing = null)">
          <div class="confirm-card" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
            <h2 class="text-[17px] font-bold tracking-tight mb-1" style="color: var(--color-text);">Complete "{{ completing.title }}"?</h2>
            <p class="text-[13px] leading-relaxed mb-4" style="color: var(--color-text-secondary);">
              <template v-if="completing.pay_amount_myr != null">
                This sends the RM {{ completing.pay_amount_myr }} bonus to the founder as payment pending.
              </template>
              <template v-else>
                Marks the task done — it moves to your Complete column.
              </template>
            </p>
            <label class="block mb-5">
              <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Completion note (required)</span>
              <textarea
                v-model="completionNote" rows="3" maxlength="2000"
                placeholder="What was done? Links, results, anything the founder should know."
                class="contact-input mt-1 w-full resize-y" />
            </label>
            <div class="flex items-center justify-end gap-2">
              <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :disabled="submittingComplete" @click="completing = null">Cancel</button>
              <button type="button" class="btn-pill btn-pill-accent text-[13px]" :disabled="submittingComplete" @click="submitComplete">
                {{ submittingComplete ? 'Completing…' : 'Complete task' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* Kanban board (§12.14) — first use of the --kanban-* token family (main.css).
   Columns are quiet sunken wells; cards pop off them. */
.kanban-col {
  border-radius: 16px;
  border: 1px solid var(--kanban-col-border);
  background: var(--kanban-col-bg);
  padding: 12px;
}
.kanban-col-head {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 6px 12px;
}
.kanban-col-head h2 {
  font-size: 13px;
  font-weight: 600;
  letter-spacing: -0.01em;
  color: var(--color-text);
}
.kanban-count {
  margin-left: auto;
  font-size: 11px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  color: var(--color-text-tertiary);
  background: var(--kanban-card-bg);
  border: 1px solid var(--kanban-card-border);
  border-radius: 9999px;
  min-width: 22px;
  height: 22px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 6px;
}
.kanban-empty {
  font-size: 12px;
  color: var(--color-text-tertiary);
  text-align: center;
  padding: 28px 8px;
}
.kanban-card {
  border-radius: 12px;
  border: 1px solid var(--kanban-card-border);
  background: var(--kanban-card-bg);
  box-shadow: var(--shadow-xs);
  padding: 14px;
  /* Whole card opens the read-only detail drawer; inner buttons @click.stop. */
  cursor: pointer;
  transition: box-shadow 0.15s ease, transform 0.15s ease;
}
.kanban-card:hover {
  box-shadow: var(--shadow-sm);
  transform: translateY(-1px);
}
.kanban-card:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}
@media (prefers-reduced-motion: reduce) {
  .kanban-card { transition: none; }
  .kanban-card:hover { transform: none; }
}
.kanban-card-done {
  opacity: 0.82;
}
/* Someone else's card (founder "Whole team" scope) — a dashed edge marks it as
   observe-only; the assignee chip says whose it is. */
.kanban-card-team {
  border-style: dashed;
}
.kanban-assignee {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
  max-width: 45%;
  padding: 2px 6px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.02em;
  color: var(--color-text-secondary);
  background: var(--color-bg-secondary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.kanban-card-title {
  font-size: 13px;
  font-weight: 600;
  letter-spacing: -0.01em;
  line-height: 1.35;
  color: var(--color-text);
}
.kanban-card-desc {
  font-size: 12px;
  line-height: 1.5;
  color: var(--color-text-secondary);
  margin-bottom: 8px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.kanban-card-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 10px;
  font-size: 11px;
}

.confirm-overlay {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(3px);
}
.confirm-card {
  width: 100%;
  max-width: 440px;
  border-radius: 20px;
  border-width: 1px;
  padding: 24px;
}
.confirm-fade-enter-active,
.confirm-fade-leave-active {
  transition: opacity 0.2s ease;
}
.confirm-fade-enter-from,
.confirm-fade-leave-to {
  opacity: 0;
}
@media (prefers-reduced-motion: reduce) {
  .confirm-fade-enter-active,
  .confirm-fade-leave-active { transition: none; }
}
</style>
