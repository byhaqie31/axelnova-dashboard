<script setup lang="ts">
// Workspace › Users (Task 8) — the founder's provisioning screen. Founder-only
// (backend Gate `manage-users`, plus the nav item itself is `roles: ['founder']`).
// Create is marketer|engineer only — founder accounts aren't provisioned here,
// even though the backend role whitelist would technically allow it. Availability
// is self-service (owned by /team/profile) and renders read-only. Deactivation is
// a persistent lockout (`deactivated_at`, added alongside this page) — checked at
// login on both /v1/admin and /v1/team, not just a signed-out session.
import StatusPill from '~/components/shared/primitives/StatusPill.vue'
import { availabilityMeta } from '~/data/availabilityStatuses'
import { workspaceRoleOptions, roleMeta } from '~/data/workspaceRoles'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

// Typed extraction of the API error message (avoids `catch (e: any)`).
function errMessage(e: unknown): string | undefined {
  return (e as { data?: { message?: string } } | null)?.data?.message
}

interface UserRecord {
  id: number
  name: string
  email: string
  role: 'founder' | 'marketer' | 'engineer'
  tier: 'cockpit' | 'workspace'
  availability: 'available' | 'busy'
  monthly_allowance_myr: number | null
  deactivated_at: string | null
  created_at: string
}

const users = ref<UserRecord[]>([])
// Starts true — the fetch only kicks off in onMounted (never during SSR), so a
// false default would flash the empty state before loading (Task-2 convention).
const loading = ref(true)
const error = ref('')

// The signed-in founder — used to block self-deactivation in the UI (the
// backend already rejects it, this just avoids the round-trip).
const me = ref<{ id: number } | null>(null)

async function fetchUsers() {
  loading.value = true
  error.value = ''
  try {
    users.value = await apiFetch<UserRecord[]>('/api/v1/admin/users')
  }
  catch {
    error.value = 'Failed to load the team roster. Check your session.'
  }
  finally {
    loading.value = false
  }
}

async function fetchMe() {
  try {
    me.value = await apiFetch<{ id: number }>('/api/v1/admin/me')
  }
  catch {
    // Non-fatal — only used to gray out the "deactivate self" action.
  }
}

onMounted(() => {
  fetchUsers()
  fetchMe()
})

// ── Client-side search + filters. The roster is small (a founder's team, not
// a customer list) so one unpaginated GET + local filtering keeps this simple.
const filters = reactive({ q: '', role: '', status: '' })

const roleFilterOptions = [
  { value: '', label: 'All roles' },
  { value: 'founder', label: 'Founder' },
  { value: 'marketer', label: 'Marketer' },
  { value: 'engineer', label: 'Engineer' },
]
const statusFilterOptions = [
  { value: '', label: 'All' },
  { value: 'active', label: 'Active' },
  { value: 'deactivated', label: 'Deactivated' },
]

const filteredUsers = computed(() => {
  const q = filters.q.trim().toLowerCase()
  return users.value.filter((u) => {
    if (q && !u.name.toLowerCase().includes(q) && !u.email.toLowerCase().includes(q)) return false
    if (filters.role && u.role !== filters.role) return false
    if (filters.status === 'active' && u.deactivated_at) return false
    if (filters.status === 'deactivated' && !u.deactivated_at) return false
    return true
  })
})

// Self-deactivation is the only UI-blockable case (the backend rejects it
// unconditionally). No "last active founder" check is needed here: the viewer
// IS an active founder (founder-gated page), so a sole-active-founder row can
// only ever be the self row — already caught by this check.
function canDeactivate(u: UserRecord): boolean {
  return u.id !== me.value?.id
}

function fmtDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
function fmtMyr(amount: number | null) {
  if (amount == null) return '—'
  return `RM ${Number(amount).toLocaleString('en-MY')}`
}

// ── Create / edit slideover (§12.13). One panel, two modes — editingUser null
// means create. Role is a §12.6 pill group, restricted to marketer|engineer on
// create; editing a founder row shows role as a locked chip instead.
const slideoverOpen = ref(false)
const editingUser = ref<UserRecord | null>(null)
const saving = ref(false)

const form = reactive({
  name: '',
  email: '',
  password: '',
  role: 'marketer' as 'marketer' | 'engineer',
  // '' when blank; Vue auto-casts type="number" v-model input to number once digits are typed
  allowance: '' as string | number,
})

// A freshly-created account's one-time credentials — shown in place of the
// form until the founder acknowledges them (never retrievable again, since
// the backend only stores the hash).
const createdCredentials = ref<{ email: string, password: string } | null>(null)

// Crypto-random password — readable-ish (no ambiguous 0/O/1/l) but well past
// the backend's 12-char minimum.
function generatePassword(): string {
  const alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'
  const bytes = new Uint32Array(16)
  crypto.getRandomValues(bytes)
  return Array.from(bytes, n => alphabet[n % alphabet.length]).join('')
}

function openCreate() {
  editingUser.value = null
  createdCredentials.value = null
  form.name = ''
  form.email = ''
  form.password = generatePassword()
  form.role = 'marketer'
  form.allowance = ''
  slideoverOpen.value = true
}

function openEdit(user: UserRecord) {
  editingUser.value = user
  createdCredentials.value = null
  form.name = user.name
  form.email = user.email
  form.password = ''
  form.role = user.role === 'founder' ? 'marketer' : user.role
  form.allowance = user.monthly_allowance_myr != null ? String(user.monthly_allowance_myr) : ''
  slideoverOpen.value = true
}

function closeSlideover() {
  if (saving.value) return
  slideoverOpen.value = false
}

async function save() {
  if (!form.name.trim()) {
    toast.error('Name required', 'Give the teammate a name.')
    return
  }
  const allowance = form.allowance === '' ? null : Math.round(Number(form.allowance))

  if (editingUser.value === null) {
    if (!form.email.trim()) {
      toast.error('Email required', 'They sign in with this address.')
      return
    }
    if (form.password.length < 12) {
      toast.error('Password too short', 'Needs at least 12 characters — regenerate it.')
      return
    }
    saving.value = true
    try {
      await apiFetch('/api/v1/admin/users', {
        method: 'POST',
        body: {
          name: form.name.trim(),
          email: form.email.trim(),
          password: form.password,
          role: form.role,
          monthly_allowance_myr: allowance,
        },
      })
      createdCredentials.value = { email: form.email.trim(), password: form.password }
      fetchUsers()
    }
    catch (e) {
      toast.error('Couldn’t create the account', errMessage(e) ?? 'Please try again.')
    }
    finally {
      saving.value = false
    }
    return
  }

  saving.value = true
  try {
    const body: Record<string, unknown> = {
      name: form.name.trim(),
      monthly_allowance_myr: allowance,
    }
    // Founder rows keep their role — the pill group is locked in the template,
    // so never send a role change for one.
    if (editingUser.value.role !== 'founder') body.role = form.role

    await apiFetch(`/api/v1/admin/users/${editingUser.value.id}`, { method: 'PATCH', body })
    toast.success('Teammate updated')
    slideoverOpen.value = false
    fetchUsers()
  }
  catch (e) {
    toast.error('Couldn’t save changes', errMessage(e) ?? 'Please try again.')
  }
  finally {
    saving.value = false
  }
}

function doneAfterCreate() {
  slideoverOpen.value = false
  createdCredentials.value = null
}

// ── Deactivate / reactivate — confirm-before-act, one dialog for both.
type PendingAction = { user: UserRecord, kind: 'deactivate' | 'reactivate' }
const pendingAction = ref<PendingAction | null>(null)
const acting = ref(false)

const confirmCopy = computed(() => {
  if (!pendingAction.value) return { title: '', body: '', cta: '' }
  const { user, kind } = pendingAction.value
  return kind === 'deactivate'
    ? {
        title: `Deactivate ${user.name}?`,
        body: 'They’re signed out everywhere immediately and can’t log back in to /admin or /team until reactivated.',
        cta: 'Deactivate',
      }
    : {
        title: `Reactivate ${user.name}?`,
        body: 'They can sign in again with their existing email and password.',
        cta: 'Reactivate',
      }
})

async function confirmAction() {
  const pending = pendingAction.value
  if (!pending || acting.value) return
  acting.value = true
  try {
    await apiFetch(`/api/v1/admin/users/${pending.user.id}/${pending.kind}`, { method: 'POST' })
    toast.success(pending.kind === 'deactivate' ? 'Teammate deactivated' : 'Teammate reactivated')
    pendingAction.value = null
    fetchUsers()
  }
  catch (e) {
    toast.error('Action failed', errMessage(e) ?? 'Please try again.')
    pendingAction.value = null
  }
  finally {
    acting.value = false
  }
}

// Escape closes the topmost layer: confirm dialog first, then the slideover.
onKeyStroke('Escape', () => {
  if (pendingAction.value) { pendingAction.value = null; return }
  if (slideoverOpen.value) closeSlideover()
})
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Users</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
          Provision teammates, set their monthly allowance, and deactivate access when someone leaves.
        </p>
      </div>
      <button type="button" class="btn-pill btn-pill-primary text-[13px]" @click="openCreate">
        <UIcon name="i-lucide-user-plus" class="size-4" />
        New teammate
      </button>
    </div>

    <!-- Filter row (§12.11) -->
    <div class="flex flex-wrap items-center gap-3 mb-6">
      <AdminExpandingSearch v-model="filters.q" placeholder="Search by name or email…" />
      <div class="flex flex-wrap gap-1.5">
        <button
          v-for="r in roleFilterOptions" :key="r.value" type="button" class="standard-pill"
          :style="filters.role === r.value ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' } : {}"
          @click="filters.role = r.value">
          {{ r.label }}
        </button>
      </div>
      <AdminStatusFilter v-model="filters.status" :options="statusFilterOptions" :total="filteredUsers.length" class="ml-auto" />
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading team roster…</div>

    <div
      v-else-if="!filteredUsers.length" class="rounded-2xl border p-12 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <UIcon name="i-lucide-user-cog" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">
        {{ users.length ? 'No teammates match these filters' : 'No teammates yet' }}
      </p>
      <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
        {{ users.length ? 'Try clearing the search or filters.' : 'Create the first one with the button above.' }}
      </p>
    </div>

    <!-- Desktop: table -->
    <div v-else class="hidden md:block admin-table-card">
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr>
              <th
                v-for="h in ['Name', 'Role', 'Availability', 'Allowance', 'Status', 'Created', 'Actions']" :key="h"
                class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
                {{ h }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in filteredUsers" :key="u.id" class="admin-table-row" @click="openEdit(u)">
              <td class="px-4 py-3.5 max-w-64">
                <p class="text-[13px] font-medium truncate" style="color: var(--color-text);">{{ u.name }}</p>
                <p class="text-[11px] truncate" style="color: var(--color-text-tertiary);">{{ u.email }}</p>
              </td>
              <td class="px-4 py-3.5">
                <span
                  class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full text-[11px] font-medium"
                  :style="{ color: roleMeta(u.role).color, background: roleMeta(u.role).bg }">
                  <UIcon v-if="u.role === 'founder'" name="i-lucide-crown" class="size-3" aria-hidden="true" />
                  {{ roleMeta(u.role).label }}
                </span>
              </td>
              <td class="px-4 py-3.5">
                <span class="inline-flex items-center gap-1.5 text-[12px]" style="color: var(--color-text-secondary);">
                  <span class="size-1.5 rounded-full" :style="{ background: availabilityMeta(u.availability)?.color }" aria-hidden="true" />
                  {{ availabilityMeta(u.availability)?.label ?? '—' }}
                </span>
              </td>
              <td class="px-4 py-3.5 text-[13px] tabular-nums" style="color: var(--color-text);">{{ fmtMyr(u.monthly_allowance_myr) }}</td>
              <td class="px-4 py-3.5">
                <StatusPill :status="u.deactivated_at ? 'deactivated' : 'active'" type="user" />
              </td>
              <td class="px-4 py-3.5 text-[12px]" style="color: var(--color-text-secondary);">{{ fmtDate(u.created_at) }}</td>
              <td class="px-4 py-3.5">
                <div class="flex items-center gap-1.5">
                  <button type="button" class="btn-pill btn-pill-ghost text-[12px]" @click.stop="openEdit(u)">Edit</button>
                  <button
                    v-if="u.deactivated_at" type="button" class="btn-pill btn-pill-accent text-[12px]"
                    @click.stop="pendingAction = { user: u, kind: 'reactivate' }">
                    Reactivate
                  </button>
                  <button
                    v-else type="button" class="btn-pill btn-pill-danger text-[12px]"
                    :disabled="!canDeactivate(u)" :class="{ 'opacity-40 cursor-not-allowed': !canDeactivate(u) }"
                    :title="!canDeactivate(u) ? 'You can’t deactivate your own account' : undefined"
                    @click.stop="canDeactivate(u) && (pendingAction = { user: u, kind: 'deactivate' })">
                    Deactivate
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Mobile: cards -->
    <div v-if="!loading && filteredUsers.length" class="md:hidden space-y-2.5">
      <div
        v-for="u in filteredUsers" :key="u.id" class="rounded-xl border p-4 cursor-pointer"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
        @click="openEdit(u)">
        <div class="flex items-start justify-between gap-3 mb-1.5">
          <div class="min-w-0">
            <p class="text-[13px] font-semibold leading-tight truncate" :style="{ color: 'var(--color-text)' }">{{ u.name }}</p>
            <p class="text-[11px] truncate" :style="{ color: 'var(--color-text-tertiary)' }">{{ u.email }}</p>
          </div>
          <StatusPill :status="u.deactivated_at ? 'deactivated' : 'active'" type="user" />
        </div>
        <p class="text-[11px] mb-3 flex items-center gap-1.5 flex-wrap" :style="{ color: 'var(--color-text-tertiary)' }">
          <span
            class="inline-flex items-center gap-1 h-5 px-2 rounded-full font-medium"
            :style="{ color: roleMeta(u.role).color, background: roleMeta(u.role).bg }">
            <UIcon v-if="u.role === 'founder'" name="i-lucide-crown" class="size-2.5" aria-hidden="true" />
            {{ roleMeta(u.role).label }}
          </span>
          · {{ availabilityMeta(u.availability)?.label ?? '—' }} · {{ fmtMyr(u.monthly_allowance_myr) }}
        </p>
        <div class="pt-2 border-t flex items-center justify-between gap-3" :style="{ borderColor: 'var(--color-border)' }">
          <span class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Since {{ fmtDate(u.created_at) }}</span>
          <div class="flex items-center gap-1.5">
            <button
              v-if="u.deactivated_at" type="button" class="btn-pill btn-pill-accent text-[12px]"
              @click.stop="pendingAction = { user: u, kind: 'reactivate' }">
              Reactivate
            </button>
            <button
              v-else type="button" class="btn-pill btn-pill-danger text-[12px]"
              :disabled="!canDeactivate(u)" :class="{ 'opacity-40 cursor-not-allowed': !canDeactivate(u) }"
              @click.stop="canDeactivate(u) && (pendingAction = { user: u, kind: 'deactivate' })">
              Deactivate
            </button>
          </div>
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
                  {{ createdCredentials ? 'Account created' : editingUser === null ? 'New teammate' : 'Edit teammate' }}
                </p>
                <p class="text-[12px] mt-0.5" style="color: var(--color-text-secondary);">
                  {{ createdCredentials
                    ? 'Share these sign-in details — the password won’t be shown again.'
                    : editingUser === null ? 'Marketer or engineer — founder accounts aren’t created here.' : 'Email stays fixed — it’s their identity.' }}
                </p>
              </div>
              <button type="button" class="slideover-close" aria-label="Close" @click="closeSlideover">
                <UIcon name="i-lucide-x" class="size-4" />
              </button>
            </div>

            <!-- One-time credential reveal, replacing the form after a successful create. -->
            <div v-if="createdCredentials" class="slideover-body space-y-4">
              <div class="rounded-xl border p-4 space-y-3" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
                <div>
                  <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Email</span>
                  <div class="flex items-center gap-2 mt-1">
                    <code class="text-[13px] flex-1 truncate" style="color: var(--color-text);">{{ createdCredentials.email }}</code>
                    <AdminCopyButton :value="createdCredentials.email" size="md" />
                  </div>
                </div>
                <div>
                  <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Temporary password</span>
                  <div class="flex items-center gap-2 mt-1">
                    <code class="text-[13px] flex-1 truncate" style="color: var(--color-text);">{{ createdCredentials.password }}</code>
                    <AdminCopyButton :value="createdCredentials.password" size="md" />
                  </div>
                </div>
              </div>
              <p class="text-[11px]" style="color: var(--color-text-tertiary);">
                They can sign in at /team/login and change nothing here — there's no self-service password reset, so keep a copy until it's handed off.
              </p>
              <button type="button" class="btn-pill btn-pill-primary w-full justify-center text-[13px]" @click="doneAfterCreate">
                Done
              </button>
            </div>

            <div v-else class="slideover-body space-y-5">
              <label class="block">
                <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Name</span>
                <input v-model="form.name" type="text" maxlength="150" placeholder="Full name" class="contact-input mt-1 w-full">
              </label>

              <label class="block">
                <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Email</span>
                <input
                  v-model="form.email" type="email" maxlength="200" placeholder="name@example.com"
                  class="contact-input mt-1 w-full" :class="{ 'opacity-60': editingUser !== null }"
                  :disabled="editingUser !== null">
                <p v-if="editingUser !== null" class="mt-1.5 text-[11px]" style="color: var(--color-text-tertiary);">Email is their identity — it can't be changed here.</p>
              </label>

              <label v-if="editingUser === null" class="block">
                <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Temporary password</span>
                <div class="flex items-center gap-2 mt-1">
                  <input v-model="form.password" type="text" minlength="12" class="contact-input flex-1">
                  <button type="button" class="btn-pill btn-pill-ghost text-[12px]" @click="form.password = generatePassword()">
                    <UIcon name="i-lucide-refresh-cw" class="size-3.5" />
                    Regenerate
                  </button>
                </div>
                <p class="mt-1.5 text-[11px]" style="color: var(--color-text-tertiary);">Min 12 characters. You'll share this with them once — it can't be recovered afterwards.</p>
              </label>

              <div>
                <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Role</span>
                <div v-if="editingUser?.role === 'founder'" class="mt-1.5">
                  <span
                    class="inline-flex items-center gap-1.5 h-7 px-3 rounded-full text-[12px] font-medium"
                    :style="{ color: roleMeta('founder').color, background: roleMeta('founder').bg }">
                    <UIcon name="i-lucide-crown" class="size-3.5" aria-hidden="true" />
                    Founder
                  </span>
                  <p class="mt-1.5 text-[11px]" style="color: var(--color-text-tertiary);">Founder accounts can't be re-roled from this screen.</p>
                </div>
                <div v-else class="flex flex-wrap gap-1.5 mt-1.5">
                  <button
                    v-for="r in workspaceRoleOptions" :key="r.value" type="button" class="standard-pill"
                    :style="form.role === r.value ? { borderColor: r.color, background: r.bg, color: r.color } : {}"
                    @click="form.role = r.value">
                    {{ r.label }}
                  </button>
                </div>
              </div>

              <label class="block">
                <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Monthly allowance (RM, optional)</span>
                <input v-model="form.allowance" type="number" min="0" step="1" placeholder="None on file" class="contact-input mt-1 w-full">
                <p class="mt-1.5 text-[11px]" style="color: var(--color-text-tertiary);">Snapshotted onto each payslip at generation — later changes never rewrite past payslips.</p>
              </label>

              <button
                type="button" class="btn-pill btn-pill-primary w-full justify-center text-[13px]"
                :class="{ 'opacity-50': saving }" :disabled="saving" @click="save">
                {{ saving ? 'Saving…' : (editingUser === null ? 'Create account' : 'Save changes') }}
              </button>
            </div>
          </aside>
        </div>
      </Transition>
    </Teleport>

    <!-- Deactivate / reactivate confirmation (layered above the slideover) -->
    <Teleport to="body">
      <Transition name="confirm-fade">
        <div v-if="pendingAction" class="confirm-overlay" @click.self="pendingAction = null">
          <div class="confirm-card" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
            <h2 class="text-[17px] font-bold tracking-tight mb-2" style="color: var(--color-text);">{{ confirmCopy.title }}</h2>
            <p class="text-[13px] leading-relaxed mb-6" style="color: var(--color-text-secondary);">{{ confirmCopy.body }}</p>
            <div class="flex items-center justify-end gap-2">
              <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :disabled="acting" @click="pendingAction = null">Cancel</button>
              <button
                type="button"
                :class="pendingAction.kind === 'deactivate' ? 'btn-pill-danger' : 'btn-pill-accent'"
                class="btn-pill text-[13px]" :disabled="acting" @click="confirmAction">
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
/* Slideover panel (§12.13) — same class names + motion as /admin/tasks and
   /admin/referrals (not yet promoted to main.css). */
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
