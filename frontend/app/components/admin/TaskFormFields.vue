<script setup lang="ts">
// Shared task field inputs for the admin create page (/admin/tasks/new) and the
// detail/edit page (/admin/tasks/[id]). Presentational only — the parent owns
// fetch/save and passes the reactive form via v-model. `disabled` renders the
// whole set read-only (the detail page uses it once a task is in progress).
import { taskPriorityOptions, type TaskFormShape } from '~/data/tasks'

withDefaults(defineProps<{
  assigneeItems: { label: string, value: string | number }[]
  disabled?: boolean
}>(), { disabled: false })

const form = defineModel<TaskFormShape>({ required: true })
</script>

<template>
  <div class="space-y-5">
    <label class="block">
      <span class="tff-label">Title</span>
      <input v-model="form.title" :disabled="disabled" type="text" maxlength="200" placeholder="What needs doing?" class="contact-input mt-1 w-full">
    </label>

    <label class="block">
      <span class="tff-label">Description (optional)</span>
      <textarea
        v-model="form.description" :disabled="disabled" rows="4" placeholder="Scope, links, acceptance criteria…"
        class="contact-input mt-1 w-full resize-y" />
    </label>

    <div>
      <span class="tff-label">Assignee</span>
      <AdminSelect v-model="form.assignee_id" :items="assigneeItems" :disabled="disabled" class="mt-1" />
      <p class="mt-1.5 text-[11px]" style="color: var(--color-text-tertiary);">Leaving it open puts the task in the team's pick-up pool.</p>
    </div>

    <div>
      <span class="tff-label">Priority</span>
      <div class="flex flex-wrap gap-1.5 mt-1.5">
        <button
          v-for="p in taskPriorityOptions" :key="p.value" type="button" class="standard-pill"
          :disabled="disabled"
          :style="form.priority === p.value ? { borderColor: p.color, background: p.bg, color: p.color } : {}"
          @click="form.priority = p.value">
          {{ p.label }}
        </button>
      </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-3">
      <label class="block">
        <span class="tff-label">Extra pay (RM, optional)</span>
        <input v-model="form.pay" :disabled="disabled" type="number" min="1" step="1" placeholder="Covered by allowance" class="contact-input mt-1 w-full">
      </label>
      <label class="block">
        <span class="tff-label">Duration estimate</span>
        <input v-model="form.duration_estimate" :disabled="disabled" type="text" maxlength="60" placeholder="e.g. 2h, 3 days" class="contact-input mt-1 w-full">
      </label>
    </div>

    <label class="block">
      <span class="tff-label">Deadline (optional)</span>
      <input v-model="form.deadline" :disabled="disabled" type="datetime-local" class="contact-input mt-1 w-full">
    </label>
  </div>
</template>

<style scoped>
.tff-label {
  font-size: 11px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-tertiary);
}
.standard-pill:disabled {
  cursor: default;
}
</style>
