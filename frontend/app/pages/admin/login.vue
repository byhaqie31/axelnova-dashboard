<script setup lang="ts">
import BrandMark from '~/components/shared/BrandMark.vue'

definePageMeta({ layout: false })
useHead({ title: 'Admin Login — Axel Nova' })
useSeoMeta({ robots: 'noindex, nofollow' })

const route = useRoute()
const { setToken, apiFetch } = useAdminAuth()
const email = ref('')
const password = ref('')
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')

// Send the user back to the admin page they were trying to reach (set by the
// admin-auth middleware), falling back to the dashboard. Only allow internal
// /admin paths to avoid open-redirects.
const redirectTo = computed(() => {
  const r = route.query.redirect
  return typeof r === 'string' && r.startsWith('/admin') && !r.startsWith('//') ? r : '/admin'
})

async function handleLogin() {
  if (!email.value || !password.value) return
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ token: string }>('/api/v1/admin/login', {
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
  <div class="admin-login-screen min-h-screen flex items-center justify-center px-6 py-12"
    style="background: var(--color-bg);">
    <div class="admin-login-glow" aria-hidden="true" />

    <div class="relative w-full max-w-md">
      <!-- Brand sits above the card -->
      <div class="flex justify-center mb-7">
        <BrandMark class="admin-brand" />
      </div>

      <div class="rounded-3xl border p-10 sm:p-12 space-y-8"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
        <!-- Heading -->
        <div class="space-y-1.5 text-center">
          <h1 class="text-[30px] font-bold tracking-tight" style="color: var(--color-text);">Admin portal</h1>
          <p class="text-[13px]" style="color: var(--color-text-secondary);">
            Sign in to manage projects, quotations and orders.
          </p>
        </div>

        <form class="space-y-5" @submit.prevent="handleLogin">
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Email</label>
            <input v-model="email" type="email" required autocomplete="email" placeholder="baihaqie@axelnova.tech"
              class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Password</label>
            <div class="relative">
              <input v-model="password" :type="showPassword ? 'text' : 'password'" required autocomplete="current-password" placeholder="••••••••"
                class="contact-input"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)', paddingRight: '2.75rem' }" />
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
          <button type="submit" class="btn-pill btn-pill-accent w-full justify-center admin-login-submit" :disabled="loading">
            {{ loading ? 'Signing in…' : 'Sign in →' }}
          </button>
        </form>
      </div>

      <NuxtLink to="/" class="admin-login-back">
        <UIcon name="i-lucide-arrow-left" class="size-3.5" />
        Back to axelnovaventures.com
      </NuxtLink>
    </div>
  </div>
</template>

<style scoped>
.admin-login-screen {
  position: relative;
  overflow: hidden;
}

.admin-login-glow {
  position: absolute;
  top: -22%;
  left: 50%;
  width: 640px;
  height: 640px;
  transform: translateX(-50%);
  background: radial-gradient(circle, var(--color-accent-soft) 0%, transparent 70%);
  pointer-events: none;
}

/* Make the brand mark bigger than the nav default for the login card */
.admin-brand :deep(img) {
  width: 2.5rem;
  height: 2.5rem;
}
.admin-brand :deep(span) {
  font-size: 22px;
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

.admin-login-submit {
  height: 48px;
}

.admin-login-back {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 20px;
  font-size: 12px;
  color: var(--color-text-tertiary);
  transition: color 0.15s ease;
}
.admin-login-back:hover {
  color: var(--color-text);
}
</style>
