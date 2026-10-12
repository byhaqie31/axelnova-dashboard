<script setup lang="ts">
// The "Emails" card in a record page's right sidebar — a compact summary of
// every email the dashboard sent about the record (or, with `email`, to that
// address): how many, a warning when any bounced / was marked spam / failed,
// and the latest few with their delivery status. "View all" opens the full,
// detailed history in a dialog. Feed: GET /api/v1/admin/emails (newest first,
// 25 a page; `meta.problems` counts problems across every page).
import {
  emailPillKey,
  emailRouteNote,
  formatEmailTime,
  isEmailProblem,
  triggeredByLabel,
  type EmailLogRow,
} from '~/utils/emailStatus'

const props = defineProps<{
  /** EmailLog related alias: quotation · invoice · inquiry · feedback · referral · user … */
  relatedType?: string
  relatedId?: number | string | null
  /** List everything sent to this address instead (client page). */
  email?: string | null
  title?: string
}>()

/** Rows on the card itself — the sidebar is sticky, so it stays short. */
const SUMMARY_ROWS = 3

const { apiFetch } = useAdminAuth()

const rows = ref<EmailLogRow[]>([])
const total = ref(0)
const problems = ref(0)
const loading = ref(true)
const error = ref('')
const open = ref(false)

const query = computed((): Record<string, string> | null => {
  const q: Record<string, string> = {}
  if (props.email) {
    q.email = props.email
  }
  else if (props.relatedType && props.relatedId != null && props.relatedId !== '') {
    q.related_type = props.relatedType
    q.related_id = String(props.relatedId)
  }
  return Object.keys(q).length ? q : null
})

async function load() {
  if (!query.value) {
    rows.value = []
    total.value = 0
    problems.value = 0
    loading.value = false
    return
  }
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: EmailLogRow[], meta?: { total?: number, problems?: number } }>('/api/v1/admin/emails', { query: query.value })
    rows.value = res.data
    total.value = res.meta?.total ?? res.data.length
    problems.value = res.meta?.problems ?? res.data.filter(r => isEmailProblem(r.status)).length
  }
  catch {
    error.value = 'Failed to load email history.'
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
watch(query, load)

const latest = computed(() => rows.value.slice(0, SUMMARY_ROWS))
const problemLabel = computed(() => `${problems.value} ${problems.value === 1 ? 'email needs' : 'emails need'} attention`)

function meta(row: EmailLogRow): string {
  return [
    row.to_email,
    formatEmailTime(row.sent_at ?? row.created_at),
    triggeredByLabel(row.triggered_by),
    emailRouteNote(row.route),
  ].filter(Boolean).join(' · ')
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') open.value = false
}
watch(open, (isOpen) => {
  if (isOpen) window.addEventListener('keydown', onKeydown)
  else window.removeEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div
    class="rounded-2xl border p-5"
    :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
    <div class="flex items-center justify-between gap-3 mb-3">
      <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">{{ title ?? 'Emails' }}</p>
      <span v-if="!loading && total" class="text-[11px] tabular-nums" style="color: var(--color-text-tertiary);">{{ total }} sent</span>
    </div>

    <p v-if="loading" class="text-[13px]" style="color: var(--color-text-tertiary);">Loading…</p>
    <p v-else-if="error" class="text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <template v-else-if="rows.length">
      <p v-if="problems" class="flex items-center gap-1.5 text-[12px] font-medium mb-3" style="color: var(--color-danger);">
        <UIcon name="i-lucide-triangle-alert" class="size-3.5 shrink-0" />
        {{ problemLabel }}
      </p>

      <ul class="space-y-2.5">
        <li v-for="row in latest" :key="row.id" class="min-w-0">
          <div class="flex items-center justify-between gap-2">
            <span
              class="text-[13px] font-medium truncate"
              :style="{ color: isEmailProblem(row.status) ? 'var(--color-danger)' : 'var(--color-text)' }">{{ row.type_label }}</span>
            <AdminStatusPill :status="emailPillKey(row.status)" class="shrink-0" />
          </div>
          <p class="text-[11px] mt-0.5" style="color: var(--color-text-tertiary);">{{ formatEmailTime(row.sent_at ?? row.created_at) }}</p>
        </li>
      </ul>

      <button
        type="button"
        class="inline-flex items-center gap-1 mt-3 text-[12px] font-medium transition-opacity hover:opacity-70 max-md:py-2"
        style="color: var(--color-accent);"
        @click="open = true">
        {{ total > latest.length ? `View all ${total}` : 'View details' }}
        <UIcon name="i-lucide-arrow-right" class="size-3.5" />
      </button>
    </template>

    <p v-else class="text-[13px]" style="color: var(--color-text-tertiary);">No emails sent yet.</p>
  </div>

  <!-- Full history — every loaded row with recipient, trigger, route, reason and the Resend link. -->
  <Teleport to="body">
    <Transition name="confirm-fade">
      <div v-if="open" class="confirm-overlay email-history-overlay" @click.self="open = false">
        <div
          class="confirm-card email-history-card" role="dialog" aria-modal="true" :aria-label="title ?? 'Emails'"
          :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
          <div class="flex items-start justify-between gap-3 mb-4">
            <div class="min-w-0">
              <h2 class="text-[17px] font-bold tracking-tight" style="color: var(--color-text);">{{ title ?? 'Emails' }}</h2>
              <p class="text-[12px] mt-0.5" style="color: var(--color-text-tertiary);">
                {{ total > rows.length ? `Latest ${rows.length} of ${total}` : `${total} sent` }}, newest first
              </p>
            </div>
            <button
              type="button" class="inline-flex items-center justify-center size-9 -mr-2 -mt-1 rounded-full shrink-0 transition-opacity hover:opacity-70"
              style="color: var(--color-text-secondary);" aria-label="Close" @click="open = false">
              <UIcon name="i-lucide-x" class="size-4" />
            </button>
          </div>

          <div class="space-y-2">
            <div
              v-for="row in rows" :key="row.id"
              class="rounded-xl border p-3"
              :style="{ borderColor: isEmailProblem(row.status) ? 'var(--color-danger)' : 'var(--color-border)' }">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[13px] font-semibold" style="color: var(--color-text);">{{ row.type_label }}</span>
                    <AdminStatusPill :status="emailPillKey(row.status)" />
                  </div>
                  <p class="text-[11px] mt-1 break-words" style="color: var(--color-text-tertiary);">{{ meta(row) }}</p>
                  <p
                    v-if="row.status_detail" class="text-[12px] mt-1 break-words"
                    :style="{ color: isEmailProblem(row.status) ? 'var(--color-danger)' : 'var(--color-text-secondary)' }">
                    {{ row.status_detail }}
                  </p>
                </div>
                <a
                  v-if="row.resend_url" :href="row.resend_url" target="_blank" rel="noopener noreferrer"
                  class="inline-flex items-center gap-1 text-[12px] shrink-0" style="color: var(--color-accent);">
                  View in Resend
                  <UIcon name="i-lucide-external-link" class="size-3.5" />
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* Wider than a confirm dialog, and scrolls inside once the list is long. */
.email-history-card {
  max-width: 560px;
  max-height: 80vh;
  overflow-y: auto;
}

/* Below md: a bottom sheet (UI-STANDARDS — mobile conventions). */
@media (max-width: 767.98px) {
  .email-history-overlay {
    align-items: flex-end;
    padding: 0;
  }
  .email-history-card {
    max-width: none;
    max-height: 90dvh;
    border-radius: 20px 20px 0 0;
    padding: 20px 16px calc(20px + env(safe-area-inset-bottom));
  }
}
</style>
