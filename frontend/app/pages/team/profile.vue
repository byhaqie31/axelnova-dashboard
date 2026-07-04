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

const form = reactive({
  name: '',
  availability: 'available' as 'available' | 'busy',
})

// Save is only meaningful once something actually changed.
const dirty = computed(() =>
  !!me.value && (form.name !== me.value.name || form.availability !== me.value.availability),
)

onMounted(async () => {
  loading.value = true
  error.value = ''
  const result = await fetchMe()
  if (!result) error.value = 'Failed to load your profile. Check your session.'
  else {
    form.name = result.name
    form.availability = result.availability ?? 'available'
  }
  loading.value = false
})

async function save() {
  if (!dirty.value || saving.value) return
  saving.value = true
  try {
    me.value = await apiFetch<NonNullable<typeof me.value>>('/api/v1/team/me', {
      method: 'PATCH',
      body: { name: form.name, availability: form.availability },
    })
    form.name = me.value.name
    form.availability = me.value.availability ?? 'available'
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
</script>

<template>
  <div class="max-w-2xl mx-auto px-4 sm:px-6 pt-10 pb-32">
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

      <div class="flex justify-end pt-1">
        <button class="btn-pill btn-pill-accent" :disabled="!dirty || saving" @click="save">
          {{ saving ? 'Saving…' : 'Save changes' }}
        </button>
      </div>
    </div>
  </div>
</template>
