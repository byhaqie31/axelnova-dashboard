<script setup lang="ts">
import BrandMark from '~/components/shared/BrandMark.vue'

definePageMeta({ layout: false })
useHead({ title: 'Team Sign-in — Axel Nova' })
useSeoMeta({ robots: 'noindex, nofollow' })

const route = useRoute()
const { setToken, apiFetch } = useTeamAuth()
const email = ref('')
const password = ref('')
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')
const year = new Date().getFullYear()

// Send the user back to the workspace page they were trying to reach (set by the
// team-auth middleware), falling back to the dashboard. Only allow internal /team
// paths to avoid open-redirects.
const redirectTo = computed(() => {
  const r = route.query.redirect
  return typeof r === 'string' && r.startsWith('/team') && !r.startsWith('//') ? r : '/team'
})

async function handleLogin() {
  if (!email.value || !password.value) return
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ token: string }>('/api/v1/team/login', {
      method: 'POST',
      body: { email: email.value, password: password.value },
    })
    setToken(res.token)
    await navigateTo(redirectTo.value)
  }
  catch {
    error.value = 'Invalid credentials. Please try again.'
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
              <h1 class="text-[26px] font-bold tracking-tight" style="color: var(--color-text);">Team workspace</h1>
            </div>
            <p class="text-[13px] text-center" style="color: var(--color-text-secondary);">
              Sign in to triage inquiries and manage referrals.
            </p>
          </div>

          <form class="space-y-5" @submit.prevent="handleLogin">
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Email</label>
              <input v-model="email" type="email" required autocomplete="email" placeholder="Email"
                class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }" />
            </div>
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Password</label>
              <div class="relative">
                <input v-model="password" :type="showPassword ? 'text' : 'password'" required autocomplete="current-password" placeholder="Password"
                  class="contact-input"
                  :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', paddingRight: '2.75rem' }" />
                <button type="button" class="pw-toggle" :aria-label="showPassword ? 'Hide password' : 'Show password'"
                  @click="showPassword = !showPassword">
                  <UIcon :name="showPassword ? 'i-lucide-eye-off' : 'i-lucide-eye'" class="size-4.5" />
                </button>
              </div>
            </div>
            <p v-if="error" class="text-[12px] flex items-center gap-1.5" style="color: var(--color-danger);">
              <UIcon name="i-lucide-alert-circle" class="size-4 shrink-0" />
              {{ error }}
            </p>
            <button type="submit" class="btn-pill btn-pill-accent w-full justify-center team-login-submit" :disabled="loading">
              {{ loading ? 'Signing in…' : 'Sign in →' }}
            </button>
          </form>

          <div class="space-y-2.5">
            <div class="team-login-divider" />
            <NuxtLink to="/" class="team-login-back">
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
.team-brand :deep(img) {
  width: 2.7rem;
  height: 2.7rem;
}

.pw-toggle {
  position: absolute;
  top: 50%;
  right: 12px;
  transform: translateY(-50%);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--color-text-tertiary);
  transition: color 0.15s ease;
}
.pw-toggle:hover {
  color: var(--color-text);
}

.team-login-submit {
  height: 48px;
}

.team-login-divider {
  height: 1px;
  background: var(--color-border);
}

.team-login-back {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 12px;
  color: var(--color-text-tertiary);
  transition: color 0.15s ease;
}
.team-login-back:hover {
  color: var(--color-text);
}
</style>
