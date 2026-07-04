<script setup lang="ts">
// Workspace › Tasks (Task 5) — the founder's cockpit over the tasks engine.
// Author tasks, assign or leave them in the pick-up pool, set pay/duration/
// deadline/priority, track the lifecycle, and mark the extra-pay bonus paid.
// Create/edit share one slideover (§12.13, same scoped CSS as /admin/referrals);
// the state machine itself is backend-enforced — this page only offers the
// actions each status legitimately allows.
import StatusPill from '~/components/shared/primitives/StatusPill.vue'
import PriceTag from '~/components/shared/primitives/PriceTag.vue'
import TaskPayBadge from '~/components/shared/primitives/TaskPayBadge.vue'
import { taskPriorityMeta, taskPriorityOptions, taskStatusOptions, type TaskRecord } from '~/data/tasks'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

// Typed extraction of the API error message (avoids `catch (e: any)`).
function errMessage(e: unknown): string | undefined {
  return (e as { data?: { message?: string } } | null)?.data?.message
}

interface ListMeta { current_page: number, last_page: number, total: number }
interface Teammate { id: number, name: string, role: string, deactivated_at: string | null }

const tasks = ref<TaskRecord[]>([])
const meta = ref<ListMeta | null>(null)
const teammates = ref<Teammate[]>([])
// Starts true — the fetch only kicks off in onMounted (never during SSR), so a
// false default would flash the empty state before loading (Task-2 convention).
const loading = ref(true)
const error = ref('')

const filters = reactive({ q: '', status: '', priority: '', assignee_id: '', page: 1 })

const secondaryActiveCount = computed(() =>
  Number(!!filters.priority) + Number(!!filters.assignee_id))

// Deactivated accounts (Task 8 lockout) can't work tasks — the backend rejects
// assigning to them, so they're hidden from both pickers. (They may still hold
// historical tasks; those rows keep rendering the assignee's name regardless.)
const activeTeammates = computed(() => teammates.value.filter(u => !u.deactivated_at))

const assigneeFilterItems = computed(() => [
  { label: 'Anyone', value: '' },
  { label: 'Unassigned (pool)', value: 'unassigned' },
  ...activeTeammates.value.map(u => ({ label: `${u.name} (${u.role})`, value: String(u.id) })),
])

const priorityFilterOptions = [
  { value: '', label: 'All' },
  ...taskPriorityOptions.map(o => ({ value: o.value, label: o.label })),
]

async function fetchTasks() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filters.q) params.set('q', filters.q)
    if (filters.status) params.set('status', filters.status)
    if (filters.priority) params.set('priority', filters.priority)
    if (filters.assignee_id) params.set('assignee_id', filters.assignee_id)
    params.set('page', String(filters.page))

    const res = await apiFetch<{ data: TaskRecord[], meta: ListMeta }>(`/api/v1/admin/tasks?${params}`)
    tasks.value = res.data
    meta.value = res.meta
  }
  catch {
    error.value = 'Failed to load tasks. Check your session.'
  }
  finally {
    loading.value = false
  }
}

async function fetchTeammates() {
  try {
    teammates.value = await apiFetch<Teammate[]>('/api/v1/admin/users')
  }
  catch {
    // Assignee picker degrades to "Leave open" only; the list itself still loads.
  }
}

let searchTimer: ReturnType<typeof setTimeout>
watch(() => filters.q, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filters.page = 1; fetchTasks() }, 400)
})
watch(() => [filters.status, filters.priority, filters.assignee_id], () => {
  if (filters.page !== 1) filters.page = 1
  else fetchTasks()
})
watch(() => filters.page, () => fetchTasks())

function clearSecondaryFilters() {
  filters.priority = ''
  filters.assignee_id = ''
}

onMounted(() => {
  fetchTasks()
  fetchTeammates()
})

// ── Create / edit slideover (§12.13). One panel, two modes — editingId null
// means create. Assignee is a §12.7 popover dropdown fed by GET /v1/admin/users
// with "Leave open" as the unassigned/pool option; priority is a §12.6 pill group.
const slideoverOpen = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)

const form = reactive({
  title: '',
  description: '',
  assignee_id: '' as string,
  pay: '',
  duration_estimate: '',
  deadline: '',
  priority: 'medium' as TaskRecord['priority'],
})

const assigneeFormItems = computed(() => [
  { label: 'Leave open (pick-up pool)', value: '' },
  ...activeTeammates.value.map(u => ({ label: `${u.name} (${u.role})`, value: String(u.id) })),
])

function openCreate() {
  editingId.value = null
  form.title = ''
  form.description = ''
  form.assignee_id = ''
  form.pay = ''
  form.duration_estimate = ''
  form.deadline = ''
  form.priority = 'medium'
  slideoverOpen.value = true
}

// ISO → the datetime-local input's 'YYYY-MM-DDTHH:mm' (local time).
function toLocalInput(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function openEdit(task: TaskRecord) {
  editingId.value = task.id
  form.title = task.title
  form.description = task.description ?? ''
  form.assignee_id = task.assignee_id ? String(task.assignee_id) : ''
  form.pay = task.pay_amount_myr != null ? String(task.pay_amount_myr) : ''
  form.duration_estimate = task.duration_estimate ?? ''
  form.deadline = toLocalInput(task.deadline)
  form.priority = task.priority
  slideoverOpen.value = true
}

function closeSlideover() {
  if (saving.value) return
  slideoverOpen.value = false
}

async function save() {
  if (!form.title.trim()) {
    toast.error('Title required', 'Give the task a name.')
    return
  }
  saving.value = true
  try {
    const body: Record<string, unknown> = {
      title: form.title.trim(),
      description: form.description.trim() || null,
      assignee_id: form.assignee_id ? Number(form.assignee_id) : null,
      pay_amount_myr: form.pay ? Math.round(Number(form.pay)) : null,
      duration_estimate: form.duration_estimate.trim() || null,
      deadline: form.deadline ? new Date(form.deadline).toISOString() : null,
      priority: form.priority,
    }
    if (editingId.value === null) {
      await apiFetch('/api/v1/admin/tasks', { method: 'POST', body })
      toast.success('Task created', body.assignee_id ? 'Assigned and ready to start.' : 'Waiting in the pick-up pool.')
    }
    else {
      await apiFetch(`/api/v1/admin/tasks/${editingId.value}`, { method: 'PATCH', body })
      toast.success('Task updated')
    }
    slideoverOpen.value = false
    fetchTasks()
  }
  catch (e) {
    toast.error('Couldn’t save the task', errMessage(e) ?? 'Please try again.')
  }
  finally {
    saving.value = false
  }
}

// ── Mark paid / delete — confirm-before-act, one dialog for both kinds.
type PendingAction = { task: TaskRecord, kind: 'mark-paid' | 'delete' }
const pendingAction = ref<PendingAction | null>(null)
const acting = ref(false)

const confirmCopy = computed(() => {
  if (!pendingAction.value) return { title: '', body: '', cta: '' }
  const { task, kind } = pendingAction.value
  return kind === 'mark-paid'
    ? {
        title: `Mark "${task.title}" paid?`,
        body: `This records the RM ${task.pay_amount_myr ?? 0} bonus as paid out${task.assignee_name ? ` to ${task.assignee_name}` : ''} and closes the task. It can't be undone here.`,
        cta: 'Mark paid',
      }
    : {
        title: `Delete "${task.title}"?`,
        body: 'The task disappears from every list, including the team board. (Soft delete — recoverable from the database.)',
        cta: 'Delete task',
      }
})

async function confirmAction() {
  const pending = pendingAction.value
  if (!pending || acting.value) return
  acting.value = true
  try {
    if (pending.kind === 'mark-paid') {
      await apiFetch(`/api/v1/admin/tasks/${pending.task.id}/mark-paid`, { method: 'POST' })
      toast.success('Bonus marked paid')
    }
    else {
      await apiFetch(`/api/v1/admin/tasks/${pending.task.id}`, { method: 'DELETE' })
      toast.success('Task deleted')
    }
    pendingAction.value = null
    fetchTasks()
  }
  catch (e) {
    toast.error('Action failed', errMessage(e) ?? 'Please try again.')
    pendingAction.value = null
  }
  finally {
    acting.value = false
  }
}

// A task offers mark-paid only when the backend would accept it. A bonus already
// LINKED to a payslip (payroll_entry_id set) is frozen into that slip's gross —
// settling the payslip is the only payout path, so no ad-hoc button (Task 7).
function canMarkPaid(task: TaskRecord): boolean {
  if (task.payroll_entry_id != null) return false
  return task.status === 'payment_pending'
    || (task.status === 'completed' && task.pay_amount_myr != null)
}

// The linked-but-unsettled state — where Mark paid would sit, hint at the payslip.
function onPayslip(task: TaskRecord): boolean {
  return task.payroll_entry_id != null && task.status === 'payment_pending'
}

// Escape closes the topmost layer: confirm dialog first, then the slideover.
onKeyStroke('Escape', () => {
  if (pendingAction.value) { pendingAction.value = null; return }
  if (slideoverOpen.value) closeSlideover()
})

function fmtDeadline(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Tasks</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
          Delegate work to the team — assign directly or leave it in the pick-up pool. Extra pay rides the task as a bonus badge.
        </p>
      </div>
      <button type="button" class="btn-pill btn-pill-primary text-[13px]" @click="openCreate">
        <UIcon name="i-lucide-plus" class="size-4" />
        New task
      </button>
    </div>

    <!-- Filter row (§12.11) -->
    <div class="flex flex-wrap items-center gap-3 mb-6">
      <AdminExpandingSearch v-model="filters.q" placeholder="Search by title…" />
      <AdminFilterMenu :active-count="secondaryActiveCount" @clear="clearSecondaryFilters">
        <AdminFilterPills v-model="filters.priority" label="Priority" :options="priorityFilterOptions" />
        <div>
          <p class="text-[11px] font-medium uppercase tracking-wide mb-1.5" style="color: var(--color-text-tertiary);">Assignee</p>
          <AdminSelect v-model="filters.assignee_id" :items="assigneeFilterItems" />
        </div>
      </AdminFilterMenu>
      <AdminStatusFilter v-model="filters.status" :options="taskStatusOptions" :total="meta?.total ?? null" class="ml-auto" />
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading tasks…</div>

    <div
      v-else-if="!tasks.length" class="rounded-2xl border p-12 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <UIcon name="i-lucide-list-todo" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No tasks found</p>
      <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">Create the first one with the button above.</p>
    </div>

    <!-- Desktop: table -->
    <div v-else class="hidden md:block admin-table-card">
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr>
              <th
                v-for="h in ['Task', 'Assignee', 'Priority', 'Deadline', 'Pay', 'Status', 'Actions']" :key="h"
                class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
                {{ h }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in tasks" :key="t.id" class="admin-table-row" @click="openEdit(t)">
              <td class="px-4 py-3.5 max-w-72">
                <p class="text-[13px] font-medium truncate" style="color: var(--color-text);">{{ t.title }}</p>
                <p v-if="t.duration_estimate" class="text-[11px]" style="color: var(--color-text-tertiary);">est. {{ t.duration_estimate }}</p>
              </td>
              <td class="px-4 py-3.5">
                <span v-if="t.assignee_name" class="text-[13px]" style="color: var(--color-text);">{{ t.assignee_name }}</span>
                <span v-else class="text-[12px] italic" style="color: var(--color-text-tertiary);">Pool</span>
              </td>
              <td class="px-4 py-3.5">
                <span
                  class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full text-[11px] font-medium capitalize"
                  :style="{ color: taskPriorityMeta(t.priority)?.color, background: taskPriorityMeta(t.priority)?.bg }"
                >{{ t.priority }}</span>
              </td>
              <td class="px-4 py-3.5 text-[12px]" style="color: var(--color-text-secondary);">{{ fmtDeadline(t.deadline) }}</td>
              <td class="px-4 py-3.5">
                <PriceTag v-if="t.pay_amount_myr != null" :min="t.pay_amount_myr" compact />
                <span v-else class="text-[12px]" style="color: var(--color-text-tertiary);">—</span>
              </td>
              <td class="px-4 py-3.5">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <StatusPill :status="t.status" type="task" />
                  <TaskPayBadge :state="t.payment_state" :amount="t.pay_amount_myr" />
                </div>
              </td>
              <td class="px-4 py-3.5">
                <div class="flex items-center gap-1.5">
                  <button
                    v-if="canMarkPaid(t)" type="button" class="btn-pill btn-pill-accent text-[12px]"
                    @click.stop="pendingAction = { task: t, kind: 'mark-paid' }">
                    Mark paid
                  </button>
                  <span
                    v-else-if="onPayslip(t)" class="inline-flex items-center gap-1 text-[11px] whitespace-nowrap"
                    :style="{ color: 'var(--color-text-tertiary)' }">
                    <UIcon name="i-lucide-receipt" class="size-3" aria-hidden="true" />
                    on payslip{{ t.payroll_period_label ? ` ${t.payroll_period_label}` : '' }}
                  </span>
                  <button type="button" class="btn-pill btn-pill-ghost text-[12px]" @click.stop="openEdit(t)">Edit</button>
                  <button
                    type="button" class="inline-flex items-center justify-center size-7 rounded-full transition-colors hover:bg-(--color-danger-soft)"
                    :style="{ color: 'var(--color-text-tertiary)' }" aria-label="Delete task"
                    @click.stop="pendingAction = { task: t, kind: 'delete' }">
                    <UIcon name="i-lucide-trash-2" class="size-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Mobile: cards -->
    <div v-if="!loading && tasks.length" class="md:hidden space-y-2.5">
      <div
        v-for="t in tasks" :key="t.id" class="rounded-xl border p-4 cursor-pointer"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
        @click="openEdit(t)">
        <div class="flex items-start justify-between gap-3 mb-1.5">
          <span class="text-[13px] font-semibold leading-tight" :style="{ color: 'var(--color-text)' }">{{ t.title }}</span>
          <StatusPill :status="t.status" type="task" />
        </div>
        <p class="text-[11px] mb-3" :style="{ color: 'var(--color-text-tertiary)' }">
          {{ t.assignee_name ?? 'Pool' }} · <span class="capitalize">{{ t.priority }}</span>
          <template v-if="t.deadline"> · due {{ fmtDeadline(t.deadline) }}</template>
        </p>
        <div class="pt-2 border-t flex items-center justify-between gap-3" :style="{ borderColor: 'var(--color-border)' }">
          <TaskPayBadge v-if="t.payment_state !== 'none'" :state="t.payment_state" :amount="t.pay_amount_myr" />
          <span v-else class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">No extra pay</span>
          <button
            v-if="canMarkPaid(t)" type="button" class="btn-pill btn-pill-accent text-[12px]"
            @click.stop="pendingAction = { task: t, kind: 'mark-paid' }">Mark paid</button>
          <span
            v-else-if="onPayslip(t)" class="inline-flex items-center gap-1 text-[11px] whitespace-nowrap"
            :style="{ color: 'var(--color-text-tertiary)' }">
            <UIcon name="i-lucide-receipt" class="size-3" aria-hidden="true" />
            on payslip{{ t.payroll_period_label ? ` ${t.payroll_period_label}` : '' }}
          </span>
        </div>
      </div>
    </div>

    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
      <button :disabled="filters.page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page--">← Prev</button>
      <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ filters.page }} / {{ meta.last_page }}</span>
      <button :disabled="filters.page >= meta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page++">Next →</button>
    </div>

    <!-- Create / edit slideover (§12.13) -->
    <Teleport to="body">
      <Transition name="slideover">
        <div v-if="slideoverOpen" class="slideover-scrim" @click.self="closeSlideover">
          <aside class="slideover-panel" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
            <div class="slideover-head">
              <div class="min-w-0">
                <p class="text-[17px] font-bold tracking-tight truncate" style="color: var(--color-text);">
                  {{ editingId === null ? 'New task' : 'Edit task' }}
                </p>
                <p class="text-[12px] mt-0.5" style="color: var(--color-text-secondary);">
                  {{ editingId === null ? 'Assign it now or leave it in the pool.' : 'Lifecycle moves stay with the assignee — this edits the shape.' }}
                </p>
              </div>
              <button type="button" class="slideover-close" aria-label="Close" @click="closeSlideover">
                <UIcon name="i-lucide-x" class="size-4" />
              </button>
            </div>

            <div class="slideover-body space-y-5">
              <label class="block">
                <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Title</span>
                <input v-model="form.title" type="text" maxlength="200" placeholder="What needs doing?" class="contact-input mt-1 w-full">
              </label>

              <label class="block">
                <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Description (optional)</span>
                <textarea
                  v-model="form.description" rows="4" placeholder="Scope, links, acceptance criteria…"
                  class="contact-input mt-1 w-full resize-y" />
              </label>

              <div>
                <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Assignee</span>
                <AdminSelect v-model="form.assignee_id" class="mt-1" :items="assigneeFormItems" />
                <p class="mt-1.5 text-[11px]" style="color: var(--color-text-tertiary);">Leaving it open puts the task in the team's pick-up pool.</p>
              </div>

              <div>
                <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Priority</span>
                <div class="flex flex-wrap gap-1.5 mt-1.5">
                  <button
                    v-for="p in taskPriorityOptions" :key="p.value" type="button" class="standard-pill"
                    :style="form.priority === p.value
                      ? { borderColor: p.color, background: p.bg, color: p.color }
                      : {}"
                    @click="form.priority = p.value">
                    {{ p.label }}
                  </button>
                </div>
              </div>

              <div class="grid sm:grid-cols-2 gap-3">
                <label class="block">
                  <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Extra pay (RM, optional)</span>
                  <input v-model="form.pay" type="number" min="1" step="1" placeholder="Covered by allowance" class="contact-input mt-1 w-full">
                </label>
                <label class="block">
                  <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Duration estimate</span>
                  <input v-model="form.duration_estimate" type="text" maxlength="60" placeholder="e.g. 2h, 3 days" class="contact-input mt-1 w-full">
                </label>
              </div>

              <label class="block">
                <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Deadline (optional)</span>
                <input v-model="form.deadline" type="datetime-local" class="contact-input mt-1 w-full">
              </label>

              <button
                type="button" class="btn-pill btn-pill-primary w-full justify-center text-[13px]"
                :class="{ 'opacity-50': saving }" :disabled="saving" @click="save">
                {{ saving ? 'Saving…' : (editingId === null ? 'Create task' : 'Save changes') }}
              </button>
            </div>
          </aside>
        </div>
      </Transition>
    </Teleport>

    <!-- Mark-paid / delete confirmation (layered above the slideover) -->
    <Teleport to="body">
      <Transition name="confirm-fade">
        <div v-if="pendingAction" class="confirm-overlay" @click.self="pendingAction = null">
          <div class="confirm-card" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
            <h2 class="text-[17px] font-bold tracking-tight mb-2" style="color: var(--color-text);">{{ confirmCopy.title }}</h2>
            <p class="text-[13px] leading-relaxed mb-6" style="color: var(--color-text-secondary);">{{ confirmCopy.body }}</p>
            <div class="flex items-center justify-end gap-2">
              <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :disabled="acting" @click="pendingAction = null">Cancel</button>
              <button type="button" class="btn-pill btn-pill-accent text-[13px]" :disabled="acting" @click="confirmAction">
                {{ acting ? 'Working…' : confirmCopy.cta }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* Slideover panel (§12.13) — second adopter of the pattern established at
   /admin/referrals (same class names + motion so a future promotion to
   main.css is a cut-paste). */
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
  max-width: 480px;
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

/* Fast dashboard motion (0.3–0.5s per UI-STANDARDS §8). */
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
  max-width: 420px;
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
