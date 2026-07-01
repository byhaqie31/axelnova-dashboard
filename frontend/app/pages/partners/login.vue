<script setup lang="ts">
import BrandMark from '~/components/shared/BrandMark.vue'

definePageMeta({ layout: false })
useHead({ title: 'Partner Portal Sign-in — Axel Nova' })
useSeoMeta({ robots: 'noindex, nofollow' })

const route = useRoute()
const { setToken, apiFetch } = usePartnerAuth()
const email = ref('')
const passcode = ref('')
const loading = ref(false)
const error = ref('')
const year = new Date().getFullYear()

// Send the partner back to where they were headed (set by partner-auth middleware),
// defaulting to the dashboard. Only allow internal /partners paths (no open-redirect).
const redirectTo = computed(() => {
  const r = route.query.redirect
  return typeof r === 'string' && r.startsWith('/partners') && !r.startsWith('//') ? r : '/partners/portal'
})

async function handleLogin() {
  if (!email.value || !passcode.value) return
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ token: string }>('/api/v1/partner/login', {
      method: 'POST',
      body: { email: email.value, passcode: passcode.value },
    })
    setToken(res.token)
    await navigateTo(redirectTo.value)
  }
  catch {
    error.value = 'Invalid credentials. Please check your email and passcode.'
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
              <BrandMark variant="mark-only" class="partner-brand" />
              <h1 class="text-[26px] font-bold tracking-tight" style="color: var(--color-text);">Partner Portal</h1>
            </div>
            <p class="text-[13px] text-center" style="color: var(--color-text-secondary);">
              Sign in to track your referrals and earnings.
            </p>
          </div>

          <form class="space-y-5" @submit.prevent="handleLogin">
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Email</label>
              <input v-model="email" type="email" required autocomplete="email" placeholder="Email"
                class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }" />
            </div>
            <div class="space-y-2">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">8-digit passcode</label>
              <PasscodeInput v-model="passcode" :length="8" />
            </div>
            <p v-if="error" class="text-[12px] flex items-center gap-1.5" style="color: var(--color-danger);">
              <UIcon name="i-lucide-alert-circle" class="size-4 shrink-0" />
              {{ error }}
            </p>
            <button type="submit" class="btn-pill btn-pill-accent w-full justify-center partner-login-submit" :disabled="loading || !email || passcode.length !== 8">
              {{ loading ? 'Signing in…' : 'Sign in →' }}
            </button>
          </form>

          <div class="space-y-2.5">
            <div class="partner-login-divider" />
            <p class="text-[12px] text-center">
              <NuxtLink to="/partners/forgot" style="color: var(--color-accent);">Forgot passcode?</NuxtLink>
            </p>
            <p class="text-[12px] text-center" style="color: var(--color-text-secondary);">
              Not a partner yet?
              <NuxtLink to="/partners/refer" style="color: var(--color-accent);">Refer a business</NuxtLink>
            </p>
            <NuxtLink to="/" class="partner-login-back">
              <UIcon name="i-lucide-arrow-left" class="size-3.5" />
              Back to axelnovaventures.com
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
.partner-brand :deep(img) {
  width: 2.7rem;
  height: 2.7rem;
}

.partner-login-submit {
  height: 48px;
}

.partner-login-divider {
  height: 1px;
  background: var(--color-border);
}

.partner-login-back {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 12px;
  color: var(--color-text-tertiary);
  transition: color 0.15s ease;
}
.partner-login-back:hover {
  color: var(--color-text);
}
</style>
