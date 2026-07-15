<script setup lang="ts">
// Workspace › Tasks › Detail — full-page view/edit for one task (replaces the
// old slideover). Editable only while the task is still `open`; once a teammate
// has started it (in_progress and every state after) the fields are locked, but
// Delete + Mark paid stay available. Backend enforces the same lock (422).
import StatusPill from '~/components/shared/primitives/StatusPill.vue'
import TaskPayBadge from '~/components/shared/primitives/TaskPayBadge.vue'
import AdminTaskFormFields from '~/components/admin/TaskFormFields.vue'
import { taskPriorityMeta, type TaskFormShape, type TaskRecord } from '~/data/tasks'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

function errMessage(e: unknown): string | undefined {
  return (e as { data?: { message?: string } } | null)?.data?.message
}

interface Teammate { id: number, name: string, role: string, deactivated_at: string | null }

const task = ref<TaskRecord | null>(null)
const teammates = ref<Teammate[]>([])
const loading = ref(true)
const error = ref('')

useHead(() => ({ title: task.value ? `${task.value.title} — Tasks` : 'Task' }))

// Locked the moment work has started — only a still-open task is editable.
const locked = computed(() => !!task.value && task.value.status !== 'open')

const form = ref<TaskFormShape>({
  title: '', description: '', assignee_id: '', pay: '', duration_estimate: '', deadline: '', priority: 'medium',
})

const assigneeItems = computed(() => [
  { label: 'Leave open (pick-up pool)', value: '' },
  ...teammates.value.filter(u => !u.deactivated_at).map(u => ({ label: `${u.name} (${u.role})`, value: String(u.id) })),
])

// ISO → the datetime-local input's 'YYYY-MM-DDTHH:mm' (local time).
function toLocalInput(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function hydrateForm(t: TaskRecord) {
  form.value = {
    title: t.title,
    description: t.description ?? '',
    assignee_id: t.assignee_id ? String(t.assignee_id) : '',
    pay: t.pay_amount_myr != null ? String(t.pay_amount_myr) : '',
    duration_estimate: t.duration_estimate ?? '',
    deadline: toLocalInput(t.deadline),
    priority: t.priority,
  }
}

async function fetchTask() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: TaskRecord }>(`/api/v1/admin/tasks/${route.params.id}`)
    task.value = res.data
    hydrateForm(res.data)
  }
  catch (e: unknown) {
    const status = (e as { status?: number } | null)?.status
    if (status === 404) error.value = 'Task not found.'
    else if (status === 403) error.value = 'Founder only.'
    else error.value = 'Failed to load the task. Check your session.'
  }
  finally {
    loading.value = false
  }
}
async function fetchTeammates() {
  try {
    teammates.value = await apiFetch<Teammate[]>('/api/v1/admin/users')
  }
  catch { /* picker degrades to "Leave open" only */ }
}
onMounted(() => {
  fetchTask()
  fetchTeammates()
})

// ── Save (open tasks only) ─────────────────────────────────────────────────
const saving = ref(false)
async function save() {
  if (!task.value || saving.value) return
  if (!form.value.title.trim()) {
    toast.error('Title required', 'Give the task a name.')
    return
  }
  saving.value = true
  try {
    const body = {
      title: form.value.title.trim(),
      description: form.value.description.trim() || null,
      assignee_id: form.value.assignee_id ? Number(form.value.assignee_id) : null,
      pay_amount_myr: form.value.pay ? Math.round(Number(form.value.pay)) : null,
      duration_estimate: form.value.duration_estimate.trim() || null,
      deadline: form.value.deadline ? new Date(form.value.deadline).toISOString() : null,
      priority: form.value.priority,
    }
    const res = await apiFetch<{ data: TaskRecord }>(`/api/v1/admin/tasks/${task.value.id}`, { method: 'PATCH', body })
    task.value = res.data
    hydrateForm(res.data)
    toast.success('Task updated')
  }
  catch (e) {
    toast.error('Couldn’t save the task', errMessage(e) ?? 'Please try again.')
  }
  finally {
    saving.value = false
  }
}

// ── Mark paid / delete — confirm-before-act (mirrors the old listing). ──────
// A bonus already LINKED to a payslip is settled via the payslip, not here.
function canMarkPaid(t: TaskRecord): boolean {
  if (t.payroll_entry_id != null) return false
  return t.status === 'payment_pending' || (t.status === 'completed' && t.pay_amount_myr != null)
}
function onPayslip(t: TaskRecord): boolean {
  return t.payroll_entry_id != null && t.status === 'payment_pending'
}

const pendingAction = ref<'mark-paid' | 'delete' | null>(null)
const acting = ref(false)

const confirmCopy = computed(() => {
  const t = task.value
  if (!t || !pendingAction.value) return { title: '', body: '', cta: '' }
  return pendingAction.value === 'mark-paid'
    ? {
        title: `Mark "${t.title}" paid?`,
        body: `This records the RM ${t.pay_amount_myr ?? 0} bonus as paid out${t.assignee_name ? ` to ${t.assignee_name}` : ''} and closes the task. It can't be undone here.`,
        cta: 'Mark paid',
      }
    : {
        title: `Delete "${t.title}"?`,
        body: 'The task disappears from every list, including the team board. (Soft delete — recoverable from the database.)',
        cta: 'Delete task',
      }
})

async function confirmAction() {
  const t = task.value
  if (!t || !pendingAction.value || acting.value) return
  acting.value = true
  try {
    if (pendingAction.value === 'mark-paid') {
      const res = await apiFetch<{ data: TaskRecord }>(`/api/v1/admin/tasks/${t.id}/mark-paid`, { method: 'POST' })
      task.value = res.data
      hydrateForm(res.data)
      toast.success('Bonus marked paid')
      pendingAction.value = null
    }
    else {
      await apiFetch(`/api/v1/admin/tasks/${t.id}`, { method: 'DELETE' })
      toast.success('Task deleted')
      await navigateTo('/admin/tasks')
    }
  }
  catch (e) {
    toast.error('Action failed', errMessage(e) ?? 'Please try again.')
    pendingAction.value = null
  }
  finally {
    acting.value = false
  }
}
onKeyStroke('Escape', () => {
  if (pendingAction.value) pendingAction.value = null
})

function fmtDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <NuxtLink to="/admin/tasks" class="inline-flex items-center gap-1.5 text-[13px] mb-6 transition-colors hover:opacity-80" style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All tasks
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading task…</div>

    <div v-else-if="error || !task" class="rounded-2xl border p-12 text-center" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <UIcon name="i-lucide-list-todo" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
      <p class="text-[14px] font-medium" :style="{ color: 'var(--color-text)' }">{{ error || 'Not found' }}</p>
    </div>

    <template v-else>
      <!-- Header -->
      <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div class="min-w-0">
          <h1 class="text-[24px] font-bold tracking-tight" style="color: var(--color-text);">{{ task.title }}</h1>
          <div class="flex items-center gap-1.5 flex-wrap mt-2">
            <StatusPill :status="task.status" type="task" />
            <span
              class="inline-flex items-center h-6 px-2.5 rounded-full text-[11px] font-medium capitalize"
              :style="{ color: taskPriorityMeta(task.priority)?.color, background: taskPriorityMeta(task.priority)?.bg }">{{ task.priority }}</span>
            <TaskPayBadge :state="task.payment_state" :amount="task.pay_amount_myr" />
          </div>
        </div>
      </div>

      <!-- Lock banner (full width) -->
      <div
        v-if="locked" class="rounded-xl border px-4 py-3 mb-6 flex items-start gap-2.5"
        :style="{ borderColor: 'var(--color-warning)', background: 'var(--color-warning-soft)' }">
        <UIcon name="i-lucide-lock" class="size-4 mt-0.5 shrink-0" :style="{ color: 'var(--color-warning)' }" />
        <p class="text-[12px] leading-relaxed" style="color: var(--color-text);">
          This task is <span class="font-semibold">in progress</span> — its details are locked so they can't change under the person working it. Delete it to pull it back to the pool.
        </p>
      </div>

      <!-- Two-column: the editable shape on the left, payment/lifecycle + actions in a side rail. -->
      <div class="grid lg:grid-cols-[1fr_320px] gap-8 items-start">
        <!-- Main: fields (read-only when locked) -->
        <div class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <AdminTaskFormFields v-model="form" :assignee-items="assigneeItems" :disabled="locked" />
          <div v-if="!locked" class="mt-6">
            <button type="button" class="btn-pill btn-pill-primary text-[13px]" :disabled="saving" @click="save">
              {{ saving ? 'Saving…' : 'Save changes' }}
            </button>
          </div>
        </div>

        <!-- Side rail (sticky) -->
        <div class="lg:sticky lg:top-20 space-y-4">
          <!-- Payment -->
          <div class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <h2 class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Payment</h2>
            <div class="flex items-center gap-2 mb-3">
              <TaskPayBadge v-if="task.payment_state !== 'none'" :state="task.payment_state" :amount="task.pay_amount_myr" />
              <span v-else class="text-[13px]" style="color: var(--color-text-tertiary);">No extra pay</span>
            </div>
            <button
              v-if="canMarkPaid(task)" type="button" class="btn-pill btn-pill-accent text-[13px] w-full justify-center"
              @click="pendingAction = 'mark-paid'">
              <UIcon name="i-lucide-banknote" class="size-4" /> Mark paid
            </button>
            <span
              v-else-if="onPayslip(task)" class="inline-flex items-center gap-1 text-[12px]"
              :style="{ color: 'var(--color-text-tertiary)' }">
              <UIcon name="i-lucide-receipt" class="size-3.5" aria-hidden="true" />
              on payslip{{ task.payroll_period_label ? ` ${task.payroll_period_label}` : '' }}
            </span>
          </div>

          <!-- Details -->
          <div class="rounded-2xl border p-5 space-y-3" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <h2 class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Details</h2>
            <div>
              <p class="text-[11px] uppercase tracking-wider mb-0.5" style="color: var(--color-text-tertiary);">Assignee</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ task.assignee_name ?? 'Pool (unassigned)' }}</p>
            </div>
            <div>
              <p class="text-[11px] uppercase tracking-wider mb-0.5" style="color: var(--color-text-tertiary);">Created by</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ task.created_by_name ?? '—' }}</p>
            </div>
            <div>
              <p class="text-[11px] uppercase tracking-wider mb-0.5" style="color: var(--color-text-tertiary);">Created</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ fmtDate(task.created_at) }}</p>
            </div>
            <div v-if="task.completed_at">
              <p class="text-[11px] uppercase tracking-wider mb-0.5" style="color: var(--color-text-tertiary);">Completed</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ fmtDate(task.completed_at) }}</p>
            </div>
            <div v-if="task.paid_at">
              <p class="text-[11px] uppercase tracking-wider mb-0.5" style="color: var(--color-text-tertiary);">Paid</p>
              <p class="text-[13px]" style="color: var(--color-text);">{{ fmtDate(task.paid_at) }}</p>
            </div>
          </div>

          <!-- Delete -->
          <button type="button" class="btn-pill btn-pill-danger text-[13px] w-full justify-center" @click="pendingAction = 'delete'">
            <UIcon name="i-lucide-trash-2" class="size-4" /> Delete task
          </button>
        </div>
      </div>
    </template>

    <!-- Mark-paid / delete confirmation -->
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
