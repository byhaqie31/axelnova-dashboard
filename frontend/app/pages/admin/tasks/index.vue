<script setup lang="ts">
// Workspace › Tasks (Task 5) — the founder's cockpit over the tasks engine.
// A scannable, single-line-per-record listing: title · assignee · priority ·
// deadline · status. Pay, duration and payment status live on the detail page
// (/admin/tasks/[id]); creating/editing is a full page too (§ tasks-detail). A
// row opens the detail page; the only inline action is Delete.
import StatusPill from '~/components/shared/primitives/StatusPill.vue'
import { taskPriorityMeta, taskPriorityOptions, taskStatusOptions, type TaskRecord } from '~/data/tasks'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

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

// The FILTER dropdown keeps everyone, tagged — a deactivated teammate's
// historical tasks stay filterable even though they can no longer be assigned.
const assigneeFilterItems = computed(() => [
  { label: 'Anyone', value: '' },
  { label: 'Unassigned (pool)', value: 'unassigned' },
  ...teammates.value.map(u => ({
    label: `${u.name} (${u.role})${u.deactivated_at ? ' — deactivated' : ''}`,
    value: String(u.id),
  })),
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
    // Assignee filter degrades to "Anyone" only; the list itself still loads.
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

function openTask(t: TaskRecord) {
  navigateTo(`/admin/tasks/${t.id}`)
}

// ── Delete — the only inline action; everything else lives on the detail page.
const pendingDelete = ref<TaskRecord | null>(null)
const deleting = ref(false)

async function confirmDelete() {
  const t = pendingDelete.value
  if (!t || deleting.value) return
  deleting.value = true
  try {
    await apiFetch(`/api/v1/admin/tasks/${t.id}`, { method: 'DELETE' })
    toast.success('Task deleted')
    pendingDelete.value = null
    fetchTasks()
  }
  catch (e) {
    toast.error('Couldn’t delete the task', errMessage(e) ?? 'Please try again.')
    pendingDelete.value = null
  }
  finally {
    deleting.value = false
  }
}

onKeyStroke('Escape', () => {
  if (pendingDelete.value) pendingDelete.value = null
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
          Delegate work to the team — assign directly or leave it in the pick-up pool. Open a task for its pay, timeline and payment status.
        </p>
      </div>
      <NuxtLink to="/admin/tasks/new" class="btn-pill btn-pill-primary text-[13px]">
        <UIcon name="i-lucide-plus" class="size-4" />
        New task
      </NuxtLink>
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

    <!-- Desktop: table — one clean line per task, uniform 13px. -->
    <div v-else class="hidden md:block admin-table-card">
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr>
              <th
                v-for="h in ['Task', 'Assignee', 'Priority', 'Deadline', 'Status', '']" :key="h"
                class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
                {{ h }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in tasks" :key="t.id" class="admin-table-row" @click="openTask(t)">
              <td class="px-4 py-3.5 max-w-80">
                <p class="text-[13px] font-medium truncate" style="color: var(--color-text);">{{ t.title }}</p>
              </td>
              <td class="px-4 py-3.5 text-[13px]">
                <span v-if="t.assignee_name" style="color: var(--color-text);">{{ t.assignee_name }}</span>
                <span v-else style="color: var(--color-text-tertiary);">Pool</span>
              </td>
              <td class="px-4 py-3.5">
                <span
                  class="inline-flex items-center h-6 px-2.5 rounded-full text-[11px] font-medium capitalize"
                  :style="{ color: taskPriorityMeta(t.priority)?.color, background: taskPriorityMeta(t.priority)?.bg }"
                >{{ t.priority }}</span>
              </td>
              <td class="px-4 py-3.5 text-[13px]" style="color: var(--color-text-secondary);">{{ fmtDeadline(t.deadline) }}</td>
              <td class="px-4 py-3.5">
                <StatusPill :status="t.status" type="task" />
              </td>
              <td class="px-4 py-3.5">
                <div class="flex items-center justify-end gap-1.5">
                  <button
                    type="button" class="btn-table-action is-danger" aria-label="Delete task"
                    @click.stop="pendingDelete = t">
                    <UIcon name="i-lucide-trash-2" class="size-3.5" />Delete
                  </button>
                  <UIcon name="i-lucide-chevron-right" class="size-4" :style="{ color: 'var(--color-text-tertiary)' }" />
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
        @click="openTask(t)">
        <div class="flex items-start justify-between gap-3 mb-1.5">
          <span class="text-[13px] font-semibold leading-tight" :style="{ color: 'var(--color-text)' }">{{ t.title }}</span>
          <StatusPill :status="t.status" type="task" />
        </div>
        <div class="flex items-center justify-between gap-3">
          <p class="text-[13px]" :style="{ color: 'var(--color-text-tertiary)' }">
            {{ t.assignee_name ?? 'Pool' }} · <span class="capitalize">{{ t.priority }}</span>
            <template v-if="t.deadline"> · due {{ fmtDeadline(t.deadline) }}</template>
          </p>
          <button
            type="button" class="btn-table-action is-danger shrink-0" aria-label="Delete task"
            @click.stop="pendingDelete = t">
            <UIcon name="i-lucide-trash-2" class="size-3.5" />
          </button>
        </div>
      </div>
    </div>

    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
      <button :disabled="filters.page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page--">← Prev</button>
      <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ filters.page }} / {{ meta.last_page }}</span>
      <button :disabled="filters.page >= meta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page++">Next →</button>
    </div>

    <!-- Delete confirmation -->
    <Teleport to="body">
      <Transition name="confirm-fade">
        <div v-if="pendingDelete" class="confirm-overlay" @click.self="pendingDelete = null">
          <div class="confirm-card" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
            <h2 class="text-[17px] font-bold tracking-tight mb-2" style="color: var(--color-text);">Delete "{{ pendingDelete.title }}"?</h2>
            <p class="text-[13px] leading-relaxed mb-6" style="color: var(--color-text-secondary);">
              The task disappears from every list, including the team board. (Soft delete — recoverable from the database.)
            </p>
            <div class="flex items-center justify-end gap-2">
              <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :disabled="deleting" @click="pendingDelete = null">Cancel</button>
              <button type="button" class="btn-pill btn-pill-accent text-[13px]" :disabled="deleting" @click="confirmDelete">
                {{ deleting ? 'Deleting…' : 'Delete task' }}
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
