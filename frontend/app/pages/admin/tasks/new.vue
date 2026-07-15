<script setup lang="ts">
// Workspace › Tasks › New — full-page create (replaces the old slideover).
import AdminTaskFormFields from '~/components/admin/TaskFormFields.vue'
import type { TaskFormShape } from '~/data/tasks'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })
useHead({ title: 'New task — Tasks' })

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

function errMessage(e: unknown): string | undefined {
  return (e as { data?: { message?: string } } | null)?.data?.message
}

interface Teammate { id: number, name: string, role: string, deactivated_at: string | null }

const teammates = ref<Teammate[]>([])
const saving = ref(false)

const form = ref<TaskFormShape>({
  title: '',
  description: '',
  assignee_id: '',
  pay: '',
  duration_estimate: '',
  deadline: '',
  priority: 'medium',
})

// Deactivated teammates can't work tasks (backend rejects assigning them), so
// the picker is active-only.
const assigneeItems = computed(() => [
  { label: 'Leave open (pick-up pool)', value: '' },
  ...teammates.value.filter(u => !u.deactivated_at).map(u => ({ label: `${u.name} (${u.role})`, value: String(u.id) })),
])

async function fetchTeammates() {
  try {
    teammates.value = await apiFetch<Teammate[]>('/api/v1/admin/users')
  }
  catch {
    // Picker degrades to "Leave open" only.
  }
}
onMounted(fetchTeammates)

async function create() {
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
    const created = await apiFetch<{ data: { id: number } }>('/api/v1/admin/tasks', { method: 'POST', body })
    toast.success('Task created', body.assignee_id ? 'Assigned and ready to start.' : 'Waiting in the pick-up pool.')
    await navigateTo(`/admin/tasks/${created.data.id}`)
  }
  catch (e) {
    toast.error('Couldn’t create the task', errMessage(e) ?? 'Please try again.')
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <NuxtLink to="/admin/tasks" class="inline-flex items-center gap-1.5 text-[13px] mb-6 transition-colors hover:opacity-80" style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All tasks
    </NuxtLink>

    <h1 class="text-[24px] font-bold tracking-tight mb-1" style="color: var(--color-text);">New task</h1>
    <p class="text-[13px] mb-8" style="color: var(--color-text-secondary);">Assign it now or leave it in the pick-up pool.</p>

    <AdminTaskFormFields v-model="form" :assignee-items="assigneeItems" />

    <div class="flex items-center gap-2 mt-8">
      <button type="button" class="btn-pill btn-pill-primary text-[13px]" :disabled="saving" @click="create">
        {{ saving ? 'Creating…' : 'Create task' }}
      </button>
      <NuxtLink to="/admin/tasks" class="btn-pill btn-pill-ghost text-[13px]">Cancel</NuxtLink>
    </div>
  </div>
</template>
