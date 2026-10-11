<script setup lang="ts">
// The "Emails" panel on a record's admin page — every email the dashboard sent
// about it (or, with `email`, to that address), newest first, with delivery
// status from Resend. Feed: GET /api/v1/admin/emails. Spec §5.9 of
// docs/superpowers/specs/2026-10-11-email-foundation-design.md.
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

const { apiFetch } = useAdminAuth()

const rows = ref<EmailLogRow[]>([])
const total = ref(0)
const loading = ref(true)
const error = ref('')

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
    loading.value = false
    return
  }
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: EmailLogRow[], meta?: { total?: number } }>('/api/v1/admin/emails', { query: query.value })
    rows.value = res.data
    total.value = res.meta?.total ?? res.data.length
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

function meta(row: EmailLogRow): string {
  return [
    row.to_email,
    formatEmailTime(row.sent_at ?? row.created_at),
    triggeredByLabel(row.triggered_by),
    emailRouteNote(row.route),
  ].filter(Boolean).join(' · ')
}
</script>

<template>
  <div
    class="rounded-2xl border p-6 max-md:p-5 mt-5"
    :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
    <div class="flex items-center justify-between gap-3 mb-4">
      <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">{{ title ?? 'Emails' }}</p>
      <span v-if="total > rows.length" class="text-[11px]" style="color: var(--color-text-tertiary);">Latest {{ rows.length }} of {{ total }}</span>
    </div>

    <p v-if="loading" class="text-[13px]" style="color: var(--color-text-tertiary);">Loading…</p>
    <p v-else-if="error" class="text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-else-if="rows.length" class="space-y-2">
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

    <p v-else class="text-[13px]" style="color: var(--color-text-tertiary);">No emails sent yet.</p>
  </div>
</template>
