<script setup lang="ts">
// Workspace › Announcements (Task 6) — the founder's post/edit surface over
// company notices. Create/edit share one slideover (§12.13, same scoped CSS
// as /admin/referrals and /admin/tasks); publishing is a toggle row-card
// (§12.2) whose "on" color is success (visible-to-public semantics), not
// accent. There's no delete — "unpublish" (the toggle off) reverts a row to
// a draft, which is the only retraction verb the backend exposes.
import { announcementAudienceMeta, announcementAudienceOptions, type AnnouncementRecord } from '~/data/announcements'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

// Typed extraction of the API error message (avoids `catch (e: any)`).
function errMessage(e: unknown): string | undefined {
  return (e as { data?: { message?: string } } | null)?.data?.message
}

const announcements = ref<AnnouncementRecord[]>([])
// Starts true — the fetch only kicks off in onMounted (never during SSR), so a
// false default would flash the empty state before loading (Task-2 convention).
const loading = ref(true)
const error = ref('')

async function fetchAnnouncements() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: AnnouncementRecord[] }>('/api/v1/admin/announcements')
    announcements.value = res.data
  }
  catch {
    error.value = 'Failed to load announcements. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchAnnouncements)

function fmtDate(iso: string | null) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}

function statusMeta(a: AnnouncementRecord) {
  return a.published_at
    ? { label: `Published ${fmtDate(a.published_at)}`, fg: 'var(--color-success)', bg: 'var(--color-success-soft)' }
    : { label: 'Draft', fg: 'var(--color-text-tertiary)', bg: 'var(--color-bg-secondary)' }
}

// ── Create / edit slideover (§12.13). One panel, two modes — editingId null
// means create.
const slideoverOpen = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)

const form = reactive({
  title: '',
  body: '',
  audience: 'team' as AnnouncementRecord['audience'],
  published: false,
  publishedAt: null as string | null, // read-only, drives the toggle's subtitle copy
})

function openCreate() {
  editingId.value = null
  form.title = ''
  form.body = ''
  form.audience = 'team'
  form.published = false
  form.publishedAt = null
  slideoverOpen.value = true
}

function openEdit(a: AnnouncementRecord) {
  editingId.value = a.id
  form.title = a.title
  form.body = a.body
  form.audience = a.audience
  form.published = a.published_at !== null
  form.publishedAt = a.published_at
  slideoverOpen.value = true
}

function closeSlideover() {
  if (saving.value) return
  slideoverOpen.value = false
}

const toggleSubtitle = computed(() => {
  if (!form.published) return 'Saved as a draft — nothing is visible yet.'
  if (form.publishedAt) return `Published ${fmtDate(form.publishedAt)}. Turning this off reverts it to a draft.`
  return 'Visible to the matching audience the moment you save.'
})

async function save() {
  if (!form.title.trim()) {
    toast.error('Title required', 'Give the announcement a headline.')
    return
  }
  if (!form.body.trim()) {
    toast.error('Body required', 'Write the notice itself.')
    return
  }
  saving.value = true
  try {
    const body: Record<string, unknown> = {
      title: form.title.trim(),
      body: form.body.trim(),
      audience: form.audience,
      published: form.published,
    }
    if (editingId.value === null) {
      await apiFetch('/api/v1/admin/announcements', { method: 'POST', body })
      toast.success('Announcement created', form.published ? 'Published now.' : 'Saved as a draft.')
    }
    else {
      await apiFetch(`/api/v1/admin/announcements/${editingId.value}`, { method: 'PATCH', body })
      toast.success('Announcement updated')
    }
    slideoverOpen.value = false
    fetchAnnouncements()
  }
  catch (e) {
    toast.error('Couldn’t save the announcement', errMessage(e) ?? 'Please try again.')
  }
  finally {
    saving.value = false
  }
}

onKeyStroke('Escape', () => {
  if (slideoverOpen.value) closeSlideover()
})
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Announcements</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
          Post company notices to the team. Drafts stay hidden until you publish.
        </p>
      </div>
      <button type="button" class="btn-pill btn-pill-primary text-[13px]" @click="openCreate">
        <UIcon name="i-lucide-plus" class="size-4" />
        New announcement
      </button>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading announcements…</div>

    <div
      v-else-if="!announcements.length" class="rounded-2xl border p-12 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <UIcon name="i-lucide-radio" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No announcements yet</p>
      <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">Create the first one with the button above.</p>
    </div>

    <!-- Desktop: table -->
    <div v-else class="hidden md:block admin-table-card">
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr>
              <th
                v-for="h in ['Title', 'Audience', 'Status', 'Author', 'Actions']" :key="h"
                class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
                {{ h }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in announcements" :key="a.id" class="admin-table-row" @click="openEdit(a)">
              <td class="px-4 py-3.5 max-w-96">
                <p class="text-[13px] font-medium truncate" style="color: var(--color-text);">{{ a.title }}</p>
                <p class="text-[11px] truncate" style="color: var(--color-text-tertiary);">{{ a.body }}</p>
              </td>
              <td class="px-4 py-3.5">
                <span
                  class="inline-flex items-center h-6 px-2.5 rounded-full text-[11px] font-medium"
                  :style="{ color: announcementAudienceMeta(a.audience)?.color, background: announcementAudienceMeta(a.audience)?.bg }"
                >{{ announcementAudienceMeta(a.audience)?.label ?? a.audience }}</span>
              </td>
              <td class="px-4 py-3.5">
                <span
                  class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full text-[11px] font-medium whitespace-nowrap"
                  :style="{ color: statusMeta(a).fg, background: statusMeta(a).bg }"
                >
                  <span class="size-1.5 rounded-full shrink-0" :style="{ background: statusMeta(a).fg }" aria-hidden="true" />
                  {{ statusMeta(a).label }}
                </span>
              </td>
              <td class="px-4 py-3.5 text-[12px]" style="color: var(--color-text-secondary);">{{ a.created_by_name ?? '—' }}</td>
              <td class="px-4 py-3.5">
                <button type="button" class="btn-table-action" @click.stop="openEdit(a)">
                  <UIcon name="i-lucide-pencil" class="size-3.5" />Edit
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Mobile: cards -->
    <div v-if="!loading && announcements.length" class="md:hidden space-y-2.5">
      <div
        v-for="a in announcements" :key="a.id" class="rounded-xl border p-4 cursor-pointer"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
        @click="openEdit(a)">
        <div class="flex items-start justify-between gap-3 mb-1.5">
          <span class="text-[13px] font-semibold leading-tight" :style="{ color: 'var(--color-text)' }">{{ a.title }}</span>
          <span
            class="inline-flex items-center h-6 px-2.5 rounded-full text-[11px] font-medium shrink-0"
            :style="{ color: statusMeta(a).fg, background: statusMeta(a).bg }"
          >{{ a.published_at ? 'Published' : 'Draft' }}</span>
        </div>
        <p class="text-[11px] mb-3 line-clamp-2" :style="{ color: 'var(--color-text-tertiary)' }">{{ a.body }}</p>
        <div class="pt-2 border-t flex items-center justify-between gap-3" :style="{ borderColor: 'var(--color-border)' }">
          <span
            class="inline-flex items-center h-6 px-2.5 rounded-full text-[11px] font-medium"
            :style="{ color: announcementAudienceMeta(a.audience)?.color, background: announcementAudienceMeta(a.audience)?.bg }"
          >{{ announcementAudienceMeta(a.audience)?.label ?? a.audience }}</span>
          <span class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ a.created_by_name ?? '—' }}</span>
        </div>
      </div>
    </div>

    <!-- Create / edit slideover (§12.13) -->
    <Teleport to="body">
      <Transition name="slideover">
        <div v-if="slideoverOpen" class="slideover-scrim" @click.self="closeSlideover">
          <aside class="slideover-panel" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
            <div class="slideover-head">
              <div class="min-w-0">
                <p class="text-[17px] font-bold tracking-tight truncate" style="color: var(--color-text);">
                  {{ editingId === null ? 'New announcement' : 'Edit announcement' }}
                </p>
                <p class="text-[12px] mt-0.5" style="color: var(--color-text-secondary);">
                  {{ editingId === null ? 'Write it, pick who sees it, publish when ready.' : 'Changes save immediately on submit.' }}
                </p>
              </div>
              <button type="button" class="slideover-close" aria-label="Close" @click="closeSlideover">
                <UIcon name="i-lucide-x" class="size-4" />
              </button>
            </div>

            <div class="slideover-body space-y-5">
              <label class="block">
                <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Title</span>
                <input v-model="form.title" type="text" maxlength="200" placeholder="What's the headline?" class="contact-input mt-1 w-full">
              </label>

              <label class="block">
                <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Body</span>
                <textarea
                  v-model="form.body" rows="6" placeholder="The full notice…"
                  class="contact-input mt-1 w-full resize-y" />
              </label>

              <div>
                <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Audience</span>
                <div class="flex flex-wrap gap-1.5 mt-1.5">
                  <button
                    v-for="o in announcementAudienceOptions" :key="o.value" type="button" class="standard-pill"
                    :style="form.audience === o.value
                      ? { borderColor: o.color, background: o.bg, color: o.color }
                      : {}"
                    @click="form.audience = o.value">
                    {{ o.label }}
                  </button>
                </div>
                <p class="mt-1.5 text-[11px]" style="color: var(--color-text-tertiary);">
                  "Partners" is a forward hook — the partner portal doesn't read announcements yet.
                </p>
              </div>

              <!-- Publish toggle row-card (§12.2). On-color: success (visible-to-public semantics). -->
              <button
                type="button" class="w-full flex items-center gap-3 rounded-lg border px-4 py-3 transition-all text-left"
                :style="form.published
                  ? { borderColor: 'var(--color-success)', background: 'var(--color-bg-elevated)' }
                  : { borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
                @click="form.published = !form.published">
                <span
                  class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors"
                  :style="form.published
                    ? { background: 'var(--color-success-soft)', color: 'var(--color-success)' }
                    : { background: 'var(--color-bg-elevated)', color: 'var(--color-text-tertiary)' }">
                  <UIcon name="i-lucide-radio" class="size-4" />
                </span>
                <span class="flex-1 min-w-0">
                  <span class="block text-[13px] font-medium" :style="{ color: form.published ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">
                    {{ form.published ? 'Published' : 'Publish' }}
                  </span>
                  <span class="block text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ toggleSubtitle }}</span>
                </span>
                <span
                  class="relative inline-block rounded-full transition-colors shrink-0"
                  :style="{ background: form.published ? 'var(--color-success)' : '#d1d5db', height: '1.25rem', width: '2.25rem' }">
                  <span
                    class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all"
                    :style="{ left: form.published ? '1.125rem' : '0.125rem' }" />
                </span>
              </button>

              <button
                type="button" class="btn-pill btn-pill-primary w-full justify-center text-[13px]"
                :class="{ 'opacity-50': saving }" :disabled="saving" @click="save">
                {{ saving ? 'Saving…' : (editingId === null ? 'Create announcement' : 'Save changes') }}
              </button>
            </div>
          </aside>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* Slideover panel (§12.13) — third adopter (referrals, tasks, now
   announcements); same class names + motion so a future promotion to
   main.css is a cut-paste. */
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
</style>
