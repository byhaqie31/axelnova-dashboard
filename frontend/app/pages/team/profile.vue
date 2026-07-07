<script setup lang="ts">
// Self-service profile (Task 4 of the portal restructure) — the workspace's
// replacement for the old shortcut-card entries. Name is editable, role is
// read-only (role changes are a founder-only /admin/users action), and
// availability is a two-value pill picker (§12.6 — no native <select>).
import { availabilityOptions } from '~/data/availabilityStatuses'

definePageMeta({ layout: 'team', middleware: 'team-auth' })
useHead({ title: 'Profile — Team' })

const { apiFetch } = useTeamAuth()
const toast = useAdminToast()

// Shared /v1/team/me state (composables/useTeamMe.ts) — saving here updates
// the same ref the team layout reads, so the header dot/pill reflect the new
// availability immediately, with no reload.
const { me, refresh: fetchMe } = useTeamMe()
const loading = ref(true)
const saving = ref(false)
const error = ref('')

// The teammate's own profile fields — contact / bank / address. They own these
// (the founder only reads them on /admin/users), and filling them all flips
// `profile_complete`, which clears the onboarding nudge on the home.
const PROFILE_FIELDS = ['phone', 'bank_name', 'bank_account_number', 'bank_account_holder', 'address_line1', 'address_line2', 'city', 'postcode', 'state', 'country'] as const

const form = reactive({
  name: '',
  availability: 'available' as 'available' | 'busy',
  phone: '',
  bank_name: '',
  bank_account_number: '',
  bank_account_holder: '',
  address_line1: '',
  address_line2: '',
  city: '',
  postcode: '',
  state: '',
  country: '',
})

function hydrate() {
  const m = me.value
  if (!m) return
  form.name = m.name
  form.availability = m.availability ?? 'available'
  for (const k of PROFILE_FIELDS) form[k] = m[k] ?? ''
}

// Save is only meaningful once something actually changed.
const dirty = computed(() => {
  const m = me.value
  if (!m) return false
  if (form.name !== m.name || form.availability !== (m.availability ?? 'available')) return true
  return PROFILE_FIELDS.some(k => form[k] !== (m[k] ?? ''))
})

onMounted(async () => {
  loading.value = true
  error.value = ''
  const result = await fetchMe()
  if (!result) error.value = 'Failed to load your profile. Check your session.'
  else hydrate()
  loading.value = false
})

async function save() {
  if (!dirty.value || saving.value) return
  saving.value = true
  try {
    const body: Record<string, unknown> = { name: form.name, availability: form.availability }
    for (const k of PROFILE_FIELDS) body[k] = form[k].trim() === '' ? null : form[k].trim()
    me.value = await apiFetch<NonNullable<typeof me.value>>('/api/v1/team/me', { method: 'PATCH', body })
    hydrate()
    toast.success('Profile updated')
  }
  catch (e: any) {
    toast.error('Couldn’t save profile', e?.data?.message ?? 'Please try again.')
  }
  finally {
    saving.value = false
  }
}

const roleLabel = computed(() => {
  const r = me.value?.role
  return r ? r.charAt(0).toUpperCase() + r.slice(1) : '—'
})

// Matches this page's existing inputs — .contact-input carries no base colours.
const inputStyle = { borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <h1 class="text-[24px] font-bold tracking-tight mb-1" style="color: var(--color-text);">Profile</h1>
    <p class="text-[14px] mb-8" style="color: var(--color-text-secondary);">Your account details and availability status.</p>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">
      Loading profile…
    </div>

    <div
      v-else
      class="rounded-2xl border p-5 sm:p-6 space-y-6"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
    >
      <!-- Identity block -->
      <div class="flex items-center gap-4">
        <span
          class="size-14 rounded-full inline-flex items-center justify-center shrink-0"
          :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
        >
          <UIcon name="i-lucide-user" class="size-6" />
        </span>
        <div class="min-w-0">
          <p class="text-[15px] font-semibold tracking-tight truncate" :style="{ color: 'var(--color-text)' }">{{ me?.name }}</p>
          <p class="text-[13px] truncate" :style="{ color: 'var(--color-text-tertiary)' }">{{ me?.email }}</p>
        </div>
      </div>

      <hr class="border-0 border-t" :style="{ borderColor: 'var(--color-border)' }">

      <!-- Read-only role -->
      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Role</label>
        <div
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-semibold"
          :style="{ background: 'var(--color-bg-secondary)', color: 'var(--color-text-secondary)' }"
        >
          <UIcon name="i-lucide-badge-check" class="size-3.5" />
          {{ roleLabel }}
        </div>
        <p class="mt-1.5 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Roles are managed by the founder.</p>
      </div>

      <!-- Editable display name -->
      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Display name</label>
        <input
          v-model="form.name"
          type="text"
          maxlength="150"
          class="contact-input"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
        >
      </div>

      <!-- Availability pill picker (§12.6 — not a native select) -->
      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Availability</label>
        <div class="flex flex-wrap gap-1.5">
          <button
            v-for="opt in availabilityOptions"
            :key="opt.value"
            type="button"
            class="standard-pill"
            :style="form.availability === opt.value
              ? { borderColor: opt.color, background: opt.bg, color: opt.color }
              : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
            @click="form.availability = opt.value"
          >
            <span class="size-1.5 rounded-full" :style="{ background: opt.color }" />
            {{ opt.label }}
          </button>
        </div>
        <p class="mt-1.5 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Shown to the team next to your name.</p>
      </div>

      <hr class="border-0 border-t" :style="{ borderColor: 'var(--color-border)' }">

      <!-- Personal details (self-serve) — used for payroll/records; only you and the founder see these. -->
      <div>
        <div class="flex items-center justify-between gap-3 mb-1">
          <h2 class="text-[13px] font-semibold uppercase tracking-widest" :style="{ color: 'var(--color-text-tertiary)' }">Personal details</h2>
          <span
            v-if="me"
            class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full text-[11px] font-medium"
            :style="me.profile_complete
              ? { color: 'var(--color-success)', background: 'var(--status-succeeded-bg)' }
              : { color: 'var(--color-warning)', background: 'var(--status-refunded-bg)' }">
            <span class="size-1.5 rounded-full" :style="{ background: me.profile_complete ? 'var(--color-success)' : 'var(--color-warning)' }" />
            {{ me.profile_complete ? 'Complete' : `${me.profile_missing?.length ?? 0} to fill` }}
          </span>
        </div>
        <p class="text-[12px] mb-4" :style="{ color: 'var(--color-text-tertiary)' }">Used for payroll and records. Only you and the founder can see these.</p>

        <div class="space-y-4">
          <div>
            <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Phone number</label>
            <input v-model="form.phone" type="tel" maxlength="40" placeholder="e.g. 012-345 6789" class="contact-input" :style="inputStyle">
          </div>

          <div class="grid sm:grid-cols-2 gap-3">
            <div>
              <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Bank name</label>
              <input v-model="form.bank_name" type="text" maxlength="120" placeholder="e.g. Maybank" class="contact-input" :style="inputStyle">
            </div>
            <div>
              <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Account number</label>
              <input v-model="form.bank_account_number" type="text" inputmode="numeric" maxlength="60" placeholder="Account no." class="contact-input" :style="inputStyle">
            </div>
            <div class="sm:col-span-2">
              <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Account holder name</label>
              <input v-model="form.bank_account_holder" type="text" maxlength="150" placeholder="As printed on the account" class="contact-input" :style="inputStyle">
            </div>
          </div>

          <div class="grid sm:grid-cols-2 gap-3">
            <div class="sm:col-span-2">
              <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Address line 1</label>
              <input v-model="form.address_line1" type="text" maxlength="200" placeholder="Street / unit" class="contact-input" :style="inputStyle">
            </div>
            <div class="sm:col-span-2">
              <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Address line 2 <span :style="{ color: 'var(--color-text-tertiary)' }">(optional)</span></label>
              <input v-model="form.address_line2" type="text" maxlength="200" class="contact-input" :style="inputStyle">
            </div>
            <div>
              <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">City</label>
              <input v-model="form.city" type="text" maxlength="100" class="contact-input" :style="inputStyle">
            </div>
            <div>
              <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Postcode</label>
              <input v-model="form.postcode" type="text" inputmode="numeric" maxlength="20" class="contact-input" :style="inputStyle">
            </div>
            <div>
              <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">State</label>
              <input v-model="form.state" type="text" maxlength="100" placeholder="e.g. Selangor" class="contact-input" :style="inputStyle">
            </div>
            <div>
              <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Country</label>
              <input v-model="form.country" type="text" maxlength="100" placeholder="Malaysia" class="contact-input" :style="inputStyle">
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end pt-1">
        <button class="btn-pill btn-pill-accent" :disabled="!dirty || saving" @click="save">
          {{ saving ? 'Saving…' : 'Save changes' }}
        </button>
      </div>
    </div>
  </div>
</template>
