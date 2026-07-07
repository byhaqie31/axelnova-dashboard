<script setup lang="ts">
// Founder's user detail page — the full profile that replaced the cramped edit
// slideover for existing teammates (the slideover stays for quick-create on the
// listing). The founder edits the admin-owned fields (name, role, allowance) and
// manages account state; the personal / bank / address block is READ-ONLY here —
// teammates fill their own on /team/profile, and this page just surfaces it +
// the completeness state. Cross-links to payroll.
import StatusPill from '~/components/shared/primitives/StatusPill.vue'
import { availabilityMeta } from '~/data/availabilityStatuses'
import { workspaceRoleOptions, roleMeta } from '~/data/workspaceRoles'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

function errMessage(e: unknown): string | undefined {
  return (e as { data?: { message?: string } } | null)?.data?.message
}

interface Profile {
  id: number
  name: string
  email: string
  role: 'founder' | 'marketer' | 'engineer'
  tier: 'cockpit' | 'workspace'
  availability: 'available' | 'busy'
  monthly_allowance_myr: number | null
  deactivated_at: string | null
  created_at: string
  phone: string | null
  bank_name: string | null
  bank_account_number: string | null
  bank_account_holder: string | null
  address_line1: string | null
  address_line2: string | null
  city: string | null
  postcode: string | null
  state: string | null
  country: string | null
  profile_complete: boolean
  profile_missing: string[]
}

const profile = ref<Profile | null>(null)
const loading = ref(true)
const error = ref('')
const meId = ref<number | null>(null)

const form = reactive({
  name: '',
  role: 'marketer' as 'marketer' | 'engineer',
  allowance: '' as string | number,
})
const saving = ref(false)

useHead(() => ({ title: profile.value ? `${profile.value.name} — Users` : 'User — Users' }))

async function fetchProfile() {
  loading.value = true
  error.value = ''
  try {
    const p = await apiFetch<Profile>(`/api/v1/admin/users/${route.params.id}`)
    profile.value = p
    form.name = p.name
    form.role = p.role === 'founder' ? 'marketer' : p.role
    form.allowance = p.monthly_allowance_myr == null ? '' : String(p.monthly_allowance_myr)
  }
  catch (e: unknown) {
    const status = (e as { status?: number } | null)?.status
    if (status === 404) error.value = 'Teammate not found.'
    else if (status === 403) error.value = 'Founder only.'
    else error.value = 'Failed to load this profile. Check your session.'
  }
  finally {
    loading.value = false
  }
}
async function fetchMe() {
  try {
    meId.value = (await apiFetch<{ id: number }>('/api/v1/admin/me')).id
  }
  catch { /* non-fatal — self-deactivate guard also enforced server-side */ }
}
onMounted(() => {
  fetchProfile()
  fetchMe()
})

const dirty = computed(() => {
  const p = profile.value
  if (!p) return false
  const allowanceNow = p.monthly_allowance_myr == null ? '' : String(p.monthly_allowance_myr)
  const roleNow = p.role === 'founder' ? 'marketer' : p.role
  return form.name !== p.name
    || (p.role !== 'founder' && form.role !== roleNow)
    || String(form.allowance) !== allowanceNow
})

async function save() {
  const p = profile.value
  if (!p || saving.value) return
  if (!form.name.trim()) {
    toast.error('Name required', 'Give the teammate a name.')
    return
  }
  const allowance = form.allowance === '' ? null : Math.round(Number(form.allowance))
  saving.value = true
  try {
    const body: Record<string, unknown> = { name: form.name.trim(), monthly_allowance_myr: allowance }
    if (p.role !== 'founder') body.role = form.role
    // PATCH returns the lean roster present() — merge it over the fuller profile.
    const updated = await apiFetch<Partial<Profile>>(`/api/v1/admin/users/${p.id}`, { method: 'PATCH', body })
    profile.value = { ...p, ...updated }
    form.name = profile.value.name
    form.allowance = profile.value.monthly_allowance_myr == null ? '' : String(profile.value.monthly_allowance_myr)
    toast.success('Profile updated')
  }
  catch (e) {
    toast.error('Couldn’t save changes', errMessage(e) ?? 'Please try again.')
  }
  finally {
    saving.value = false
  }
}

// ── Deactivate / reactivate ────────────────────────────────────────────────
const pendingAction = ref<'deactivate' | 'reactivate' | null>(null)
const acting = ref(false)
const isSelf = computed(() => !!profile.value && profile.value.id === meId.value)

async function confirmAction() {
  const p = profile.value
  const kind = pendingAction.value
  if (!p || !kind || acting.value) return
  acting.value = true
  try {
    const updated = await apiFetch<Partial<Profile>>(`/api/v1/admin/users/${p.id}/${kind}`, { method: 'POST' })
    profile.value = { ...p, ...updated }
    pendingAction.value = null
    toast.success(kind === 'deactivate' ? 'Teammate deactivated' : 'Teammate reactivated')
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

function orNone(v: string | null): string {
  return v && v.trim() !== '' ? v : 'Not provided'
}
function fmtDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
const addressBlock = computed(() => {
  const p = profile.value
  if (!p) return ''
  return [p.address_line1, p.address_line2, [p.postcode, p.city].filter(Boolean).join(' '), p.state, p.country]
    .filter(v => v && v.trim() !== '')
    .join('\n')
})
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <NuxtLink to="/admin/users" class="inline-flex items-center gap-1.5 text-[13px] mb-6 transition-colors hover:opacity-80" style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All teammates
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading profile…</div>

    <div v-else-if="error || !profile" class="rounded-2xl border p-12 text-center" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <UIcon name="i-lucide-user-x" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
      <p class="text-[14px] font-medium" :style="{ color: 'var(--color-text)' }">{{ error || 'Not found' }}</p>
    </div>

    <template v-else>
      <!-- Header -->
      <div class="flex items-start justify-between gap-4 flex-wrap mb-8">
        <div class="flex items-center gap-3">
          <span class="size-12 rounded-2xl inline-flex items-center justify-center text-[18px] font-bold shrink-0" :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }">
            {{ profile.name.charAt(0).toUpperCase() }}
          </span>
          <div>
            <h1 class="text-[24px] font-bold tracking-tight" style="color: var(--color-text);">{{ profile.name }}</h1>
            <div class="flex items-center gap-2 mt-1">
              <span class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full text-[11px] font-medium" :style="{ color: roleMeta(profile.role).color, background: roleMeta(profile.role).bg }">
                <UIcon v-if="profile.role === 'founder'" name="i-lucide-crown" class="size-3" aria-hidden="true" />
                {{ roleMeta(profile.role).label }}
              </span>
              <StatusPill :status="profile.deactivated_at ? 'deactivated' : 'active'" type="user" />
            </div>
          </div>
        </div>
        <NuxtLink :to="`/admin/payroll/${profile.id}`" class="btn-pill btn-pill-ghost text-[13px]">
          <UIcon name="i-lucide-banknote" class="size-4" /> View payroll
        </NuxtLink>
      </div>

      <div class="grid lg:grid-cols-2 gap-6 items-start">
        <!-- LEFT: admin-owned -->
        <div class="space-y-6">
          <!-- Identity + compensation (editable) -->
          <section class="rounded-2xl border p-6 space-y-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <h2 class="text-[13px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Identity & pay</h2>

            <label class="block">
              <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Name</span>
              <input v-model="form.name" type="text" maxlength="150" class="contact-input mt-1 w-full">
            </label>

            <label class="block">
              <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Email</span>
              <input :value="profile.email" type="email" disabled class="contact-input mt-1 w-full opacity-60">
              <p class="mt-1.5 text-[11px]" style="color: var(--color-text-tertiary);">Email is their identity — it can't be changed here.</p>
            </label>

            <div>
              <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Role</span>
              <div v-if="profile.role === 'founder'" class="mt-1.5">
                <span class="inline-flex items-center gap-1.5 h-7 px-3 rounded-full text-[12px] font-medium" :style="{ color: roleMeta('founder').color, background: roleMeta('founder').bg }">
                  <UIcon name="i-lucide-crown" class="size-3.5" aria-hidden="true" /> Founder
                </span>
                <p class="mt-1.5 text-[11px]" style="color: var(--color-text-tertiary);">Founder accounts can't be re-roled here.</p>
              </div>
              <div v-else class="flex flex-wrap gap-1.5 mt-1.5">
                <button
                  v-for="r in workspaceRoleOptions" :key="r.value" type="button" class="standard-pill"
                  :style="form.role === r.value ? { borderColor: r.color, background: r.bg, color: r.color } : {}"
                  @click="form.role = r.value">{{ r.label }}</button>
              </div>
            </div>

            <label class="block">
              <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Monthly allowance (RM, optional)</span>
              <input v-model="form.allowance" type="number" min="0" step="1" placeholder="None on file" class="contact-input mt-1 w-full">
              <p class="mt-1.5 text-[11px]" style="color: var(--color-text-tertiary);">Snapshotted onto each payslip at generation — later changes never rewrite past payslips.</p>
            </label>

            <button type="button" class="btn-pill btn-pill-primary w-full justify-center text-[13px]" :class="{ 'opacity-50': saving || !dirty }" :disabled="saving || !dirty" @click="save">
              {{ saving ? 'Saving…' : 'Save changes' }}
            </button>
          </section>

          <!-- Account -->
          <section class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <h2 class="text-[13px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Account</h2>
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="text-[13px]" style="color: var(--color-text);">Joined {{ fmtDate(profile.created_at) }}</p>
                <p class="text-[12px] mt-0.5" style="color: var(--color-text-tertiary);">
                  Availability: {{ availabilityMeta(profile.availability)?.label ?? '—' }}
                </p>
              </div>
              <button
                v-if="!profile.deactivated_at" type="button" class="btn-table-action is-danger"
                :disabled="isSelf" :title="isSelf ? 'You can’t deactivate your own account' : undefined"
                @click="pendingAction = 'deactivate'">
                <UIcon name="i-lucide-user-x" class="size-3.5" /> Deactivate
              </button>
              <button v-else type="button" class="btn-table-action is-accent" @click="pendingAction = 'reactivate'">
                <UIcon name="i-lucide-user-check" class="size-3.5" /> Reactivate
              </button>
            </div>
          </section>
        </div>

        <!-- RIGHT: teammate-owned (read-only) -->
        <section class="rounded-2xl border p-6" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <div class="flex items-center justify-between gap-3 mb-1">
            <h2 class="text-[13px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Personal details</h2>
            <span
              class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full text-[11px] font-medium"
              :style="profile.profile_complete
                ? { color: 'var(--color-success)', background: 'var(--status-succeeded-bg)' }
                : { color: 'var(--color-warning)', background: 'var(--status-refunded-bg)' }">
              <span class="size-1.5 rounded-full" :style="{ background: profile.profile_complete ? 'var(--color-success)' : 'var(--color-warning)' }" />
              {{ profile.profile_complete ? 'Complete' : `${profile.profile_missing.length} missing` }}
            </span>
          </div>
          <p class="text-[12px] mb-5" style="color: var(--color-text-tertiary);">Filled by {{ profile.name.split(' ')[0] }} on their team profile — read-only here.</p>

          <dl class="space-y-4">
            <div>
              <dt class="text-[11px] font-medium uppercase tracking-wider mb-0.5" style="color: var(--color-text-tertiary);">Phone</dt>
              <dd class="text-[13px]" :style="{ color: profile.phone ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">{{ orNone(profile.phone) }}</dd>
            </div>
            <div class="pt-4 border-t" :style="{ borderColor: 'var(--color-border)' }">
              <dt class="text-[11px] font-medium uppercase tracking-wider mb-1.5" style="color: var(--color-text-tertiary);">Bank</dt>
              <dd class="space-y-1">
                <p class="text-[13px]" :style="{ color: profile.bank_name ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">{{ orNone(profile.bank_name) }}</p>
                <p class="text-[13px] tabular-nums" :style="{ color: profile.bank_account_number ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">{{ orNone(profile.bank_account_number) }}</p>
                <p class="text-[12px]" style="color: var(--color-text-secondary);">{{ profile.bank_account_holder ? `Holder: ${profile.bank_account_holder}` : '' }}</p>
              </dd>
            </div>
            <div class="pt-4 border-t" :style="{ borderColor: 'var(--color-border)' }">
              <dt class="text-[11px] font-medium uppercase tracking-wider mb-1.5" style="color: var(--color-text-tertiary);">Address</dt>
              <dd v-if="addressBlock" class="text-[13px] whitespace-pre-line leading-relaxed" style="color: var(--color-text);">{{ addressBlock }}</dd>
              <dd v-else class="text-[13px]" style="color: var(--color-text-tertiary);">Not provided</dd>
            </div>
          </dl>
        </section>
      </div>
    </template>

    <!-- Deactivate / reactivate confirm -->
    <Teleport to="body">
      <Transition name="confirm-fade">
        <div v-if="pendingAction && profile" class="confirm-overlay" @click.self="pendingAction = null">
          <div class="confirm-card" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
            <h2 class="text-[17px] font-bold tracking-tight mb-2" style="color: var(--color-text);">
              {{ pendingAction === 'deactivate' ? 'Deactivate' : 'Reactivate' }} {{ profile.name }}?
            </h2>
            <p class="text-[13px] leading-relaxed mb-6" style="color: var(--color-text-secondary);">
              <template v-if="pendingAction === 'deactivate'">Locks them out of the workspace and revokes their active sessions. Their payslip history is kept.</template>
              <template v-else>Restores their workspace access. They sign in fresh to get a new session.</template>
            </p>
            <div class="flex items-center justify-end gap-2">
              <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :disabled="acting" @click="pendingAction = null">Cancel</button>
              <button
                type="button" class="btn-pill text-[13px]"
                :class="pendingAction === 'deactivate' ? 'btn-pill-danger' : 'btn-pill-accent'"
                :disabled="acting" @click="confirmAction">
                {{ acting ? 'Working…' : (pendingAction === 'deactivate' ? 'Deactivate' : 'Reactivate') }}
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
