<script setup lang="ts">
// One studio partner (Partners › Collaborations): Live/Hidden, the public
// profile, the projects built together and the clients who asked to hire
// them through us. The slug is locked — the hand-built public page and the
// partner's colour scope hang off it. Public pages pick edits up within ~5
// minutes (route cache). See docs/global/COLLABORATIONS.md §Admin.
import StatusPill from '~/components/shared/primitives/StatusPill.vue'
import {
  collaborationModeLabels,
  sharedProjectHiddenReasonLabels,
  type AdminCollaborationPartner,
  type AdminSharedProject,
  type CollaborationMode,
} from '~/data/collaborations'
import { isSitePath, partnerSinceLabel, servicesFromText, servicesToText } from '~/utils/collabAdmin'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()
const { confirmOpen, confirmConfig, confirm, resolveConfirm } = useConfirm()

type ApiError = { data?: { message?: string, errors?: Record<string, string[]> } }
function errMessage(e: unknown): string | undefined {
  return (e as ApiError | null)?.data?.message
}

const partner = ref<AdminCollaborationPartner | null>(null)
const loading = ref(true)
const error = ref('')
const endpoint = computed(() => `/api/v1/admin/collaboration-partners/${route.params.id}`)

useHead(() => ({ title: partner.value ? `${partner.value.name} — Collaborations` : 'Collaboration partner — Admin' }))

const modeItems = (Object.keys(collaborationModeLabels) as CollaborationMode[]).map(value => ({ value, label: collaborationModeLabels[value] }))

// ── Profile form ────────────────────────────────────────────────────────────
const form = reactive({
  name: '',
  tagline: '',
  summary: '',
  story: '',
  founder_name: '',
  founder_role: '',
  location: '',
  website_url: '',
  services: '',
  partner_since: '',
  logo_url: '',
  symbol_url: '',
  sort_order: 0,
})
const snapshot = ref('')
const dirty = computed(() => JSON.stringify(form) !== snapshot.value)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function fillForm(p: AdminCollaborationPartner) {
  Object.assign(form, {
    name: p.name,
    tagline: p.tagline ?? '',
    summary: p.summary,
    story: p.story ?? '',
    founder_name: p.founder_name ?? '',
    founder_role: p.founder_role ?? '',
    location: p.location ?? '',
    website_url: p.website_url ?? '',
    services: servicesToText(p.services),
    partner_since: p.partner_since ?? '',
    logo_url: p.logo_url ?? '',
    symbol_url: p.symbol_url ?? '',
    sort_order: p.sort_order,
  })
  snapshot.value = JSON.stringify(form)
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: AdminCollaborationPartner }>(endpoint.value)
    partner.value = res.data
    fillForm(res.data)
  }
  catch {
    error.value = 'Couldn’t load this partner.'
  }
  finally {
    loading.value = false
  }
}
onMounted(load)

const blank = (v: string) => (v.trim() === '' ? null : v.trim())

async function saveProfile() {
  const pathError = ['Use a site path like /logos/name.svg.']
  errors.value = {
    ...(isSitePath(form.logo_url.trim()) ? {} : { logo_url: pathError }),
    ...(isSitePath(form.symbol_url.trim()) ? {} : { symbol_url: pathError }),
  }
  if (Object.keys(errors.value).length) return
  saving.value = true
  try {
    const res = await apiFetch<{ data: AdminCollaborationPartner }>(endpoint.value, {
      method: 'PATCH',
      body: {
        name: form.name.trim(),
        tagline: blank(form.tagline),
        summary: form.summary.trim(),
        story: blank(form.story),
        founder_name: blank(form.founder_name),
        founder_role: blank(form.founder_role),
        location: blank(form.location),
        website_url: blank(form.website_url),
        services: servicesFromText(form.services),
        partner_since: blank(form.partner_since),
        logo_url: blank(form.logo_url),
        symbol_url: blank(form.symbol_url),
        sort_order: Number(form.sort_order) || 0,
      },
    })
    partner.value = res.data
    fillForm(res.data)
    toast.success('Profile saved', 'The public pages pick it up within about 5 minutes.')
  }
  catch (e) {
    errors.value = (e as ApiError)?.data?.errors ?? {}
    toast.error('Couldn’t save the profile', errMessage(e) ?? 'Please check the fields and try again.')
  }
  finally {
    saving.value = false
  }
}

// ── Live / Hidden ─────────────────────────────────────────────────────────────
const toggling = ref(false)

async function toggleLive() {
  const p = partner.value
  if (!p || toggling.value) return
  const goingLive = !p.active
  const ok = await confirm(goingLive
    ? {
        title: `Make ${p.name} live?`,
        message: `Shows /collaborations/${p.slug}, its card on /collaborations and the homepage, and every credited project's “Built with” chip.`,
        confirmLabel: 'Make live',
      }
    : {
        title: `Hide ${p.name}?`,
        message: `Hides /collaborations/${p.slug}, its card and every “Built with” credit. Nothing is deleted — you can make it live again.`,
        confirmLabel: 'Hide partner',
        variant: 'danger',
      })
  if (!ok) return
  toggling.value = true
  try {
    const res = await apiFetch<{ data: AdminCollaborationPartner }>(endpoint.value, { method: 'PATCH', body: { active: goingLive } })
    // Keep any unsaved profile edits — only the header and projects refresh.
    partner.value = res.data
    toast.success(goingLive ? 'Partner is live' : 'Partner hidden')
  }
  catch (e) {
    toast.error('Couldn’t change visibility', errMessage(e) ?? 'Please try again.')
  }
  finally {
    toggling.value = false
  }
}

// ── Shared projects ──────────────────────────────────────────────────────────
interface ProjectOption { id: number, name: string, slug: string, active: boolean }
const allProjects = ref<ProjectOption[]>([])
const linkOpen = ref(false)
const linking = ref(false)
const linkErrors = ref<Record<string, string[]>>({})
const linkForm = reactive({ project_id: '' as number | '', mode: 'co_build' as CollaborationMode, role_note: '', credit_public: true })
// "Show publicly" follows the mode (on only for co-builds) until it's touched.
const creditTouched = ref(false)
watch(() => linkForm.mode, (mode) => {
  if (!creditTouched.value) linkForm.credit_public = mode === 'co_build'
})

const projectItems = computed(() => {
  const linked = new Set(partner.value?.projects.map(p => p.id))
  return allProjects.value.map(p => ({
    value: p.id,
    label: linked.has(p.id) ? `${p.name} (already linked)` : p.active ? p.name : `${p.name} (off the site)`,
    disabled: linked.has(p.id),
  }))
})

async function openLink() {
  linkOpen.value = true
  linkErrors.value = {}
  Object.assign(linkForm, { project_id: '', mode: 'co_build', role_note: '', credit_public: true })
  creditTouched.value = false
  if (!allProjects.value.length) {
    try {
      const res = await apiFetch<{ data: ProjectOption[] }>('/api/v1/admin/projects')
      allProjects.value = res.data
    }
    catch {
      toast.error('Couldn’t load projects', 'Please try again.')
    }
  }
}

async function submitLink() {
  if (!partner.value || linkForm.project_id === '') {
    linkErrors.value = { project_id: ['Pick a project.'] }
    return
  }
  linking.value = true
  linkErrors.value = {}
  try {
    const res = await apiFetch<{ data: AdminCollaborationPartner }>(`${endpoint.value}/projects`, {
      method: 'POST',
      body: {
        project_id: linkForm.project_id,
        mode: linkForm.mode,
        role_note: blank(linkForm.role_note),
        credit_public: linkForm.credit_public,
      },
    })
    partner.value = res.data
    linkOpen.value = false
    toast.success('Project linked')
  }
  catch (e) {
    linkErrors.value = (e as ApiError)?.data?.errors ?? {}
    toast.error('Couldn’t link the project', errMessage(e) ?? 'Please try again.')
  }
  finally {
    linking.value = false
  }
}

const editingId = ref<number | null>(null)
const editForm = reactive({ mode: 'co_build' as CollaborationMode, role_note: '' })
const rowBusy = ref<number | null>(null)

function startEdit(p: AdminSharedProject) {
  editingId.value = p.id
  Object.assign(editForm, { mode: p.mode, role_note: p.role_note ?? '' })
}

async function patchLink(p: AdminSharedProject, body: Record<string, unknown>, done: string) {
  rowBusy.value = p.id
  try {
    const res = await apiFetch<{ data: AdminCollaborationPartner }>(`${endpoint.value}/projects/${p.id}`, { method: 'PATCH', body })
    partner.value = res.data
    editingId.value = null
    toast.success(done)
  }
  catch (e) {
    toast.error('Couldn’t update the link', errMessage(e) ?? 'Please try again.')
  }
  finally {
    rowBusy.value = null
  }
}

function saveEdit(p: AdminSharedProject) {
  patchLink(p, { mode: editForm.mode, role_note: blank(editForm.role_note) }, 'Link updated')
}

async function toggleCredit(p: AdminSharedProject) {
  const turningOn = !p.credit_public
  // Subcontracted / referred work is often confidential to the end client.
  if (turningOn && p.mode !== 'co_build') {
    const ok = await confirm({
      title: `Name ${partner.value?.name} on ${p.name}?`,
      message: `${collaborationModeLabels[p.mode]} work is often confidential to the end client, and the partner may have white-labelled it. Only switch this on with the client’s OK.`,
      confirmLabel: 'Show publicly',
      variant: 'warning',
    })
    if (!ok) return
  }
  patchLink(p, { credit_public: turningOn }, turningOn ? 'Shown publicly' : 'Credit hidden')
}

async function unlink(p: AdminSharedProject) {
  const ok = await confirm({
    title: `Unlink ${p.name}?`,
    message: 'Removes the shared-project link and its “Built with” credit. The project itself stays as it is.',
    confirmLabel: 'Unlink',
    variant: 'danger',
  })
  if (!ok) return
  rowBusy.value = p.id
  try {
    const res = await apiFetch<{ data: AdminCollaborationPartner }>(`${endpoint.value}/projects/${p.id}`, { method: 'DELETE' })
    partner.value = res.data
    toast.success('Project unlinked')
  }
  catch (e) {
    toast.error('Couldn’t unlink the project', errMessage(e) ?? 'Please try again.')
  }
  finally {
    rowBusy.value = null
  }
}

function visibilityLabel(p: AdminSharedProject): string {
  return p.public ? 'Public' : sharedProjectHiddenReasonLabels[p.hidden_reason ?? 'credit_off']
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-6 md:pt-10 pb-24 md:pb-32">
    <NuxtLink
      to="/admin/collaborations" class="inline-flex items-center gap-2 text-[13px] mb-8 max-md:mb-5 max-md:py-1.5 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> Collaborations
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <p v-else-if="error" style="color: var(--color-danger);">{{ error }}</p>

    <div v-else-if="partner" class="space-y-6 max-md:space-y-4">

      <!-- Header -->
      <div class="rounded-2xl border p-6 max-md:p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <div class="flex items-start justify-between gap-4 flex-wrap">
          <div class="flex items-center gap-4 min-w-0">
            <span class="collab-logo-plate rounded-xl shrink-0 inline-flex items-center justify-center overflow-hidden h-14 px-3 min-w-14">
              <img v-if="partner.logo_url || partner.symbol_url" :src="(partner.logo_url || partner.symbol_url)!" :alt="`${partner.name} logo`" class="h-8 max-w-36 object-contain">
              <UIcon v-else name="i-lucide-combine" class="size-5" style="color: var(--color-text-tertiary);" />
            </span>
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-[22px] md:text-[24px] font-bold tracking-tight" style="color: var(--color-text);">{{ partner.name }}</h1>
                <StatusPill :status="partner.active ? 'live' : 'hidden'" type="collab_partner" />
              </div>
              <p class="text-[12px] mt-1 inline-flex items-center gap-1.5" style="color: var(--color-text-tertiary);">
                <UIcon name="i-lucide-lock" class="size-3" aria-hidden="true" />
                /collaborations/{{ partner.slug }} · {{ partnerSinceLabel(partner.partner_since) }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2 flex-wrap max-md:w-full">
            <a
              v-if="partner.active" :href="`/collaborations/${partner.slug}`" target="_blank" rel="noopener"
              class="btn-pill btn-pill-ghost text-[13px] max-md:flex-1 justify-center">
              <UIcon name="i-lucide-external-link" class="size-3.5" /> View public page
            </a>
            <button
              type="button" class="btn-pill text-[13px] max-md:flex-1 justify-center"
              :class="partner.active ? 'btn-pill-ghost' : 'btn-pill-accent'"
              :disabled="toggling" @click="toggleLive">
              <UIcon :name="partner.active ? 'i-lucide-eye-off' : 'i-lucide-eye'" class="size-3.5" />
              {{ toggling ? 'Working…' : partner.active ? 'Hide partner' : 'Make live' }}
            </button>
          </div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-5 mt-5 border-t" style="border-color: var(--color-border);">
          <div>
            <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Public projects</p>
            <p class="text-[15px] font-semibold tabular-nums" style="color: var(--color-text);">{{ partner.projects_public }} <span class="text-[12px] font-normal" style="color: var(--color-text-tertiary);">/ {{ partner.projects_total }}</span></p>
          </div>
          <div>
            <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Open enquiries</p>
            <p class="text-[15px] font-semibold tabular-nums" style="color: var(--color-text);">{{ partner.enquiries_open }}</p>
          </div>
          <div>
            <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">New</p>
            <p class="text-[15px] font-semibold tabular-nums" :style="{ color: partner.enquiries_new ? 'var(--color-accent)' : 'var(--color-text)' }">{{ partner.enquiries_new }}</p>
          </div>
          <div>
            <p class="text-[11px] font-medium uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">Website</p>
            <a v-if="partner.website_url" :href="partner.website_url" target="_blank" rel="noopener" class="text-[13px] font-medium break-all" style="color: var(--color-accent);">{{ partner.website_url.replace(/^https?:\/\//, '').replace(/\/$/, '') }}</a>
            <p v-else class="text-[13px]" style="color: var(--color-text-tertiary);">—</p>
          </div>
        </div>
      </div>

      <!-- Profile -->
      <form class="rounded-2xl border p-6 max-md:p-5 space-y-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }" @submit.prevent="saveProfile">
        <div class="flex items-center justify-between gap-3 flex-wrap">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Public profile</p>
            <p class="text-[12px] mt-1" style="color: var(--color-text-secondary);">What /collaborations/{{ partner.slug }} shows. Section copy on the page itself stays in code.</p>
          </div>
          <button type="submit" class="btn-pill btn-pill-accent text-[13px] max-md:w-full justify-center" :disabled="saving || !dirty">
            {{ saving ? 'Saving…' : dirty ? 'Save profile' : 'Saved' }}
          </button>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <label class="block">
            <span class="collab-field-label">Name *</span>
            <input v-model="form.name" type="text" required maxlength="100" class="contact-input w-full">
            <span v-if="errors.name" class="collab-field-error">{{ errors.name[0] }}</span>
          </label>
          <label class="block">
            <span class="collab-field-label">Tagline</span>
            <input v-model="form.tagline" type="text" maxlength="150" class="contact-input w-full">
            <span v-if="errors.tagline" class="collab-field-error">{{ errors.tagline[0] }}</span>
          </label>
        </div>

        <label class="block">
          <span class="collab-field-label">Summary * <span class="font-normal tabular-nums" style="color: var(--color-text-tertiary);">({{ form.summary.length }}/200)</span></span>
          <textarea v-model="form.summary" required maxlength="200" rows="2" class="contact-input w-full" />
          <span v-if="errors.summary" class="collab-field-error">{{ errors.summary[0] }}</span>
        </label>

        <label class="block">
          <span class="collab-field-label">Story</span>
          <textarea v-model="form.story" rows="7" class="contact-input w-full" />
          <span class="collab-field-hint">Plain text. Leave a blank line between paragraphs.</span>
          <span v-if="errors.story" class="collab-field-error">{{ errors.story[0] }}</span>
        </label>

        <div class="grid sm:grid-cols-3 gap-4">
          <label class="block">
            <span class="collab-field-label">Founder</span>
            <input v-model="form.founder_name" type="text" maxlength="100" class="contact-input w-full">
          </label>
          <label class="block">
            <span class="collab-field-label">Founder role</span>
            <input v-model="form.founder_role" type="text" maxlength="100" class="contact-input w-full">
          </label>
          <label class="block">
            <span class="collab-field-label">Location</span>
            <input v-model="form.location" type="text" maxlength="100" class="contact-input w-full">
          </label>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <label class="block">
            <span class="collab-field-label">Website</span>
            <input v-model="form.website_url" type="url" maxlength="500" placeholder="https://" class="contact-input w-full">
            <span v-if="errors.website_url" class="collab-field-error">{{ errors.website_url[0] }}</span>
          </label>
          <label class="block">
            <span class="collab-field-label">Partner since</span>
            <input v-model="form.partner_since" type="date" class="contact-input w-full">
            <span class="collab-field-hint">The site shows the year only.</span>
            <span v-if="errors.partner_since" class="collab-field-error">{{ errors.partner_since[0] }}</span>
          </label>
        </div>

        <label class="block">
          <span class="collab-field-label">Services</span>
          <textarea v-model="form.services" rows="4" class="contact-input w-full" placeholder="Websites&#10;Mobile apps" />
          <span class="collab-field-hint">One per line.</span>
          <span v-if="errors.services" class="collab-field-error">{{ errors.services[0] }}</span>
        </label>

        <div class="grid sm:grid-cols-[1fr_1fr_120px] gap-4 items-start">
          <label class="block">
            <span class="collab-field-label">Logo (wordmark)</span>
            <input v-model="form.logo_url" type="text" maxlength="500" placeholder="/logos/name.svg" class="contact-input w-full">
            <span v-if="errors.logo_url" class="collab-field-error">{{ errors.logo_url[0] }}</span>
          </label>
          <label class="block">
            <span class="collab-field-label">Symbol (square mark)</span>
            <input v-model="form.symbol_url" type="text" maxlength="500" placeholder="/logos/name-mark.svg" class="contact-input w-full">
            <span v-if="errors.symbol_url" class="collab-field-error">{{ errors.symbol_url[0] }}</span>
          </label>
          <label class="block">
            <span class="collab-field-label">Sort order</span>
            <input v-model.number="form.sort_order" type="number" min="0" max="65535" class="contact-input w-full">
          </label>
        </div>
        <div v-if="form.logo_url || form.symbol_url" class="flex items-center gap-3 flex-wrap">
          <span v-if="form.logo_url" class="collab-logo-plate rounded-xl h-14 px-3 inline-flex items-center"><img :src="form.logo_url" alt="Logo preview" class="h-8 max-w-40 object-contain"></span>
          <span v-if="form.symbol_url" class="collab-logo-plate rounded-xl size-14 inline-flex items-center justify-center"><img :src="form.symbol_url" alt="Symbol preview" class="size-8 object-contain"></span>
          <span class="collab-field-hint">Files live in frontend/public/logos/ — they ship with a deploy.</span>
        </div>
      </form>

      <!-- Shared projects -->
      <div class="rounded-2xl border p-6 max-md:p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Shared projects</p>
            <p class="text-[12px] mt-1" style="color: var(--color-text-secondary);">Public only when the partner is live, “Show publicly” is on and the project itself is on the site.</p>
          </div>
          <button v-if="!linkOpen" type="button" class="btn-pill btn-pill-ghost text-[13px] max-md:w-full justify-center" @click="openLink">
            <UIcon name="i-lucide-link" class="size-3.5" /> Link a project
          </button>
        </div>

        <!-- Link form -->
        <div v-if="linkOpen" class="rounded-xl border p-4 mb-4 space-y-4" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)' }">
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <span class="collab-field-label">Project *</span>
              <AdminSelect v-model="linkForm.project_id" :items="projectItems" placeholder="Choose a project…" />
              <span v-if="linkErrors.project_id" class="collab-field-error">{{ linkErrors.project_id[0] }}</span>
            </div>
            <div>
              <span class="collab-field-label">How we worked *</span>
              <AdminSelect v-model="linkForm.mode" :items="modeItems" />
            </div>
          </div>
          <label class="block">
            <span class="collab-field-label">Role note</span>
            <input v-model="linkForm.role_note" type="text" maxlength="200" placeholder="TRLabs: UI + mobile app · Axel Nova: backend + admin" class="contact-input w-full">
            <span v-if="linkErrors.role_note" class="collab-field-error">{{ linkErrors.role_note[0] }}</span>
          </label>
          <label class="flex items-start gap-2.5 cursor-pointer">
            <input v-model="linkForm.credit_public" type="checkbox" class="mt-0.5 size-4 shrink-0" style="accent-color: var(--color-accent);" @change="creditTouched = true">
            <span class="text-[13px]" style="color: var(--color-text);">
              Show publicly
              <span class="block text-[11px]" style="color: var(--color-text-tertiary);">On by default for co-builds only — subcontracted and referred work is often confidential to the end client.</span>
            </span>
          </label>
          <div class="flex items-center justify-end gap-2">
            <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :disabled="linking" @click="linkOpen = false">Cancel</button>
            <button type="button" class="btn-pill btn-pill-accent text-[13px]" :disabled="linking" @click="submitLink">{{ linking ? 'Linking…' : 'Link project' }}</button>
          </div>
        </div>

        <p v-if="!partner.projects.length && !linkOpen" class="text-center py-10 text-[13px]" style="color: var(--color-text-secondary);">
          No shared projects yet.
        </p>

        <ul v-else class="divide-y" style="border-color: var(--color-border);">
          <li v-for="p in partner.projects" :key="p.id" class="py-4 first:pt-0 last:pb-0" :style="{ borderColor: 'var(--color-border)' }">
            <!-- Editing -->
            <div v-if="editingId === p.id" class="space-y-3">
              <p class="text-[13px] font-semibold" style="color: var(--color-text);">{{ p.name }}</p>
              <div class="grid sm:grid-cols-[200px_1fr] gap-3">
                <AdminSelect v-model="editForm.mode" :items="modeItems" />
                <input v-model="editForm.role_note" type="text" maxlength="200" placeholder="Role note" class="contact-input w-full">
              </div>
              <div class="flex items-center justify-end gap-2">
                <button type="button" class="btn-pill btn-pill-ghost text-[12px]" :disabled="rowBusy === p.id" @click="editingId = null">Cancel</button>
                <button type="button" class="btn-pill btn-pill-accent text-[12px]" :disabled="rowBusy === p.id" @click="saveEdit(p)">Save</button>
              </div>
            </div>

            <!-- Reading -->
            <div v-else class="flex items-start justify-between gap-4 flex-wrap">
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <NuxtLink :to="`/admin/projects/${p.id}`" class="text-[14px] font-semibold" style="color: var(--color-text);">{{ p.name }}</NuxtLink>
                  <span class="inline-flex items-center h-5 px-2 rounded-full text-[11px] font-medium" style="background: var(--color-bg-secondary); color: var(--color-text-secondary);">
                    {{ collaborationModeLabels[p.mode] }}
                  </span>
                  <span
                    class="inline-flex items-center gap-1 h-5 px-2 rounded-full text-[11px] font-medium"
                    :style="p.public
                      ? { background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
                      : { background: 'var(--color-bg-secondary)', color: 'var(--color-text-tertiary)' }">
                    <UIcon :name="p.public ? 'i-lucide-globe' : 'i-lucide-eye-off'" class="size-3" aria-hidden="true" />
                    {{ visibilityLabel(p) }}
                  </span>
                </div>
                <p class="text-[12px] mt-1" style="color: var(--color-text-secondary);">{{ p.role_note ?? 'No role note' }}</p>
              </div>
              <div class="flex items-center gap-1.5 flex-wrap max-md:w-full">
                <label class="btn-table-action cursor-pointer max-md:flex-1" :class="{ 'opacity-50': rowBusy === p.id }">
                  <input type="checkbox" class="size-3.5" style="accent-color: var(--color-accent);" :checked="p.credit_public" :disabled="rowBusy === p.id" @click.prevent="toggleCredit(p)">
                  Show publicly
                </label>
                <button type="button" class="btn-table-action max-md:flex-1" :disabled="rowBusy === p.id" @click="startEdit(p)">
                  <UIcon name="i-lucide-pencil" class="size-3.5" /> Edit
                </button>
                <button type="button" class="btn-table-action is-danger max-md:flex-1" :disabled="rowBusy === p.id" @click="unlink(p)">
                  <UIcon name="i-lucide-unlink" class="size-3.5" /> Unlink
                </button>
              </div>
            </div>
          </li>
        </ul>
      </div>

      <!-- Enquiries — clients who asked to hire this partner through us -->
      <div class="rounded-2xl border p-6 max-md:p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Enquiries</p>
        <p class="text-[12px] mt-1 mb-2" style="color: var(--color-text-secondary);">Clients who asked to hire {{ partner.name }} through us. Partners have no login — pass the lead on yourself.</p>
        <AdminCollabEnquiries origin="partner" :partner-id="partner.id" :empty-text="`No client has asked to hire ${partner.name} through us yet.`" />
      </div>
    </div>

    <AdminConfirmDialog :open="confirmOpen" :config="confirmConfig" @resolve="resolveConfirm" />
  </div>
</template>

<style scoped>
.collab-field-label {
  display: block;
  margin-bottom: 0.375rem;
  font-size: 12px;
  font-weight: 500;
  color: var(--color-text-secondary);
}

.collab-field-hint {
  display: block;
  margin-top: 0.25rem;
  font-size: 11px;
  color: var(--color-text-tertiary);
}

.collab-field-error {
  display: block;
  margin-top: 0.25rem;
  font-size: 11px;
  color: var(--color-danger);
}
</style>
