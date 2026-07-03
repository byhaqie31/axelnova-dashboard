<script setup lang="ts">
import BrandMark from '~/components/shared/BrandMark.vue'

definePageMeta({ layout: false })
useHead({ title: 'Forgot password — Axel Nova Team' })
useSeoMeta({ robots: 'noindex, nofollow' })

const { apiFetch } = useTeamAuth()
const email = ref('')
const loading = ref(false)
const done = ref(false)
const error = ref('')
const year = new Date().getFullYear()

async function handleSubmit() {
  if (!email.value || loading.value) return
  loading.value = true
  error.value = ''
  try {
    // Always a generic response — the server never reveals whether the email exists.
    await apiFetch('/api/v1/team/forgot-password', {
      method: 'POST',
      body: { email: email.value },
    })
    done.value = true
  }
  catch {
    error.value = 'Something went wrong. Please try again in a moment.'
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-6 py-12" style="background: var(--color-bg-secondary);">
    <div class="w-full max-w-md">
      <div
        class="rounded-3xl border px-8 pt-10 pb-7 sm:px-10 sm:pt-12 sm:pb-8"
        :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }"
      >
        <div class="space-y-6">
          <div class="space-y-4">
            <div class="flex items-center justify-center gap-3">
              <BrandMark variant="mark-only" class="team-brand" />
              <h1 class="text-[24px] font-bold tracking-tight" style="color: var(--color-text);">Forgot password</h1>
            </div>
            <p class="text-[13px] text-center" style="color: var(--color-text-secondary);">
              Team passwords are reset by the admin. Enter your work email and Baihaqie will be notified to reset yours.
            </p>
          </div>

          <!-- Success (generic — no account disclosure) -->
          <div v-if="done" class="space-y-5">
            <div class="rounded-2xl border p-4 flex items-start gap-2.5"
              :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
              <UIcon name="i-lucide-mail-check" class="size-5 shrink-0" style="color: var(--color-success);" />
              <p class="text-[13px] leading-relaxed" style="color: var(--color-text-secondary);">
                If that email matches a team account, the admin has been notified and will reset your password and send you the new credentials shortly.
              </p>
            </div>
            <NuxtLink to="/team/login" class="btn-pill btn-pill-accent w-full justify-center team-forgot-submit">
              Back to sign in →
            </NuxtLink>
          </div>

          <!-- Request form -->
          <form v-else class="space-y-5" @submit.prevent="handleSubmit">
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Email</label>
              <input v-model="email" type="email" required autocomplete="email" placeholder="Your work email"
                class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }" />
            </div>
            <p v-if="error" class="text-[12px] flex items-center gap-1.5" style="color: var(--color-danger);">
              <UIcon name="i-lucide-alert-circle" class="size-4 shrink-0" />
              {{ error }}
            </p>
            <button type="submit" class="btn-pill btn-pill-accent w-full justify-center team-forgot-submit" :disabled="loading || !email">
              {{ loading ? 'Notifying…' : 'Notify admin →' }}
            </button>
          </form>

          <div class="space-y-2.5">
            <div class="team-forgot-divider" />
            <NuxtLink to="/team/login" class="team-forgot-back">
              <UIcon name="i-lucide-arrow-left" class="size-3.5" />
              Back to sign in
            </NuxtLink>
            <p class="text-[11px] text-center" style="color: var(--color-text-tertiary);">
              © {{ year }} Axel Nova Ventures. All rights reserved.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.team-brand :deep(img) {
  width: 2.4rem;
  height: 2.4rem;
}

.team-forgot-submit {
  height: 48px;
}

.team-forgot-divider {
  height: 1px;
  background: var(--color-border);
}

.team-forgot-back {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 12px;
  color: var(--color-text-tertiary);
  transition: color 0.15s ease;
}
.team-forgot-back:hover {
  color: var(--color-text);
}
</style>
