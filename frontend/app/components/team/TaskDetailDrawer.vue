<script setup lang="ts">
// Read-only task detail drawer (§12.13 right-side slideover). The kanban card
// line-clamps the description to 2 lines, so this is where a teammate reads the
// FULL brief + the append-only notes/activity log. Purely presentational: the
// page owns the task + action logic; this emits semantic action events and never
// mutates. The contextual footer button mirrors the one the card offers — no
// editable fields (not a form).
import StatusPill from '~/components/shared/primitives/StatusPill.vue'
import TaskPayBadge from '~/components/shared/primitives/TaskPayBadge.vue'
import { taskPriorityMeta, type TaskRecord } from '~/data/tasks'

// Which single action applies, decided by the column the card lives in (pool and
// startable both have status `open` but different verbs, so status alone won't do).
type Variant = 'pool' | 'startable' | 'in_progress' | 'done'

const props = defineProps<{
  task: TaskRecord | null
  variant: Variant
  busy?: boolean
}>()

const emit = defineEmits<{
  close: []
  pickup: []
  start: []
  complete: []
  release: []
}>()

function fmtDate(iso: string | null | undefined): string {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}

// Deadline reads danger once past — unless the task is already wrapped up.
const deadlineOverdue = computed(() => {
  const t = props.task
  if (!t?.deadline) return false
  if (['completed', 'payment_pending', 'paid'].includes(t.status)) return false
  return new Date(t.deadline).getTime() < Date.now()
})
</script>

<template>
  <Teleport to="body">
    <Transition name="slideover">
      <!-- v-if on the prop: Vue animates the cached DOM out on leave, so bindings
           never dereference a null task mid-transition (same trick as the page's
           Complete dialog). -->
      <div v-if="task" class="slideover-scrim" @click.self="emit('close')">
        <aside
          class="slideover-panel" role="dialog" aria-label="Task details"
          :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
          <!-- Head -->
          <div class="slideover-head">
            <div class="min-w-0">
              <h2 class="text-[17px] font-bold tracking-tight leading-snug" style="color: var(--color-text);">
                {{ task.title }}
              </h2>
              <div class="mt-2">
                <StatusPill :status="task.status" type="task" />
              </div>
            </div>
            <button type="button" class="slideover-close" aria-label="Close" @click="emit('close')">
              <UIcon name="i-lucide-x" class="size-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="slideover-body space-y-6">
            <!-- Meta -->
            <dl class="detail-grid">
              <div class="detail-row">
                <dt>Priority</dt>
                <dd>
                  <span
                    class="capitalize font-medium px-1.5 py-0.5 rounded text-[12px]"
                    :style="{ color: taskPriorityMeta(task.priority)?.color, background: taskPriorityMeta(task.priority)?.bg }">
                    {{ task.priority }}
                  </span>
                </dd>
              </div>
              <div v-if="task.deadline" class="detail-row">
                <dt>Deadline</dt>
                <dd :style="{ color: deadlineOverdue ? 'var(--color-danger)' : 'var(--color-text)' }">
                  {{ fmtDate(task.deadline) }}<template v-if="deadlineOverdue"> · overdue</template>
                </dd>
              </div>
              <div v-if="task.duration_estimate" class="detail-row">
                <dt>Estimate</dt>
                <dd>{{ task.duration_estimate }}</dd>
              </div>
              <div v-if="task.payment_state !== 'none'" class="detail-row">
                <dt>Pay</dt>
                <dd><TaskPayBadge :state="task.payment_state" :amount="task.pay_amount_myr" /></dd>
              </div>
              <div class="detail-row">
                <dt>Assignee</dt>
                <dd>{{ task.assignee_name ?? 'Unassigned' }}</dd>
              </div>
              <div v-if="task.created_by_name" class="detail-row">
                <dt>Created by</dt>
                <dd>{{ task.created_by_name }}</dd>
              </div>
              <div class="detail-row">
                <dt>Created</dt>
                <dd>{{ fmtDate(task.created_at) }}</dd>
              </div>
              <div v-if="task.completed_at" class="detail-row">
                <dt>Completed</dt>
                <dd>{{ fmtDate(task.completed_at) }}</dd>
              </div>
              <div v-if="task.paid_at" class="detail-row">
                <dt>Paid</dt>
                <dd>{{ fmtDate(task.paid_at) }}</dd>
              </div>
            </dl>

            <!-- Description (full, untruncated) -->
            <section>
              <h3 class="detail-section-label">Description</h3>
              <p v-if="task.description" class="detail-prose">{{ task.description }}</p>
              <p v-else class="detail-empty">No description provided.</p>
            </section>

            <!-- Notes / activity log -->
            <section v-if="task.notes">
              <h3 class="detail-section-label">Notes &amp; activity</h3>
              <p class="detail-prose detail-notes">{{ task.notes }}</p>
            </section>
          </div>

          <!-- Footer: the one contextual action (still not a form). -->
          <div v-if="variant !== 'done'" class="slideover-foot">
            <button
              v-if="variant === 'pool'" type="button"
              class="btn-pill btn-pill-primary text-[13px] w-full justify-center"
              :disabled="busy" @click="emit('pickup')">Pick up</button>

            <button
              v-else-if="variant === 'startable'" type="button"
              class="btn-pill btn-pill-accent text-[13px] w-full justify-center"
              :disabled="busy" @click="emit('start')">Start</button>

            <template v-else-if="variant === 'in_progress'">
              <button
                type="button" class="btn-pill btn-pill-primary text-[13px] flex-1 justify-center"
                :disabled="busy" @click="emit('complete')">Complete…</button>
              <button
                type="button" class="btn-pill btn-pill-ghost text-[13px]"
                :disabled="busy" @click="emit('release')">Release</button>
            </template>
          </div>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* Slideover panel (§12.13) — same class names + motion as /admin/tasks so a future
   promotion to main.css stays a cut-paste; this adopter adds a pinned footer. */
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
.slideover-foot {
  display: flex;
  gap: 8px;
  padding: 16px 20px;
  border-top: 1px solid var(--color-border);
}

/* Read-only detail list: label left, value right. */
.detail-grid dt {
  font-size: 12px;
  color: var(--color-text-tertiary);
}
.detail-grid dd {
  font-size: 13px;
  color: var(--color-text);
  text-align: right;
}
.detail-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 9px 0;
  border-bottom: 1px solid var(--color-border);
}
.detail-row:last-child {
  border-bottom: 0;
}

.detail-section-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--color-text-tertiary);
  margin-bottom: 8px;
}
/* Preserve pasted line breaks; never overflow the panel on long unbroken strings. */
.detail-prose {
  font-size: 13px;
  line-height: 1.6;
  color: var(--color-text-secondary);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.detail-notes {
  background: var(--color-bg-secondary);
  border: 1px solid var(--color-border);
  border-radius: 10px;
  padding: 12px;
}
.detail-empty {
  font-size: 13px;
  font-style: italic;
  color: var(--color-text-tertiary);
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
</style>
