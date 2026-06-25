<script setup lang="ts">
import BrandMark from '~/components/shared/BrandMark.vue'
import FluidBackground from '~/components/shared/FluidBackground.vue'

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
const year = new Date().getFullYear()

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
  <div class="admin-login-screen min-h-screen flex items-center justify-center px-6 py-12">
    <!-- Fluid generative-art background (OCEAN preset). `interactive` lets it warp
         toward the cursor (like the live preview): the screen is click-through so
         empty areas reach the fluid, while the glass card re-enables pointer
         events for the form. /public/fluid-bg.jpg is the offline fallback. -->
    <FluidBackground fallback-image="/fluid-bg.jpg" :scrim="0.35" interactive />

    <div class="relative w-full max-w-md">
      <!-- Soft accent halo bleeding from behind the glass for depth -->
      <div class="admin-login-glow" aria-hidden="true" />

      <!-- One liquid-glass panel holds the entire sign-in -->
      <div class="glass-card relative rounded-4xl px-8 pt-10 pb-7 sm:px-10 sm:pt-12 sm:pb-8">
        <div class="relative space-y-6">
          <!-- Logo beside the title -->
          <div class="space-y-4">
            <div class="flex items-center justify-center gap-3">
              <BrandMark variant="mark-only" class="admin-brand" />
              <h1 class="text-[26px] font-bold tracking-tight text-gradient">Admin portal</h1>
            </div>
            <p class="text-[13px] text-center" style="color: var(--color-text-secondary);">
              Sign in to manage projects, quotations and orders.
            </p>
          </div>

          <form class="space-y-5" @submit.prevent="handleLogin">
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Email</label>
              <input v-model="email" type="email" required autocomplete="email" placeholder="Email"
                class="contact-input glass-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }" />
            </div>
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Password</label>
              <div class="relative">
                <input v-model="password" :type="showPassword ? 'text' : 'password'" required autocomplete="current-password" placeholder="Password"
                  class="contact-input glass-input"
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
            <button type="submit" class="btn-pill btn-pill-accent w-full justify-center admin-login-submit" :disabled="loading">
              {{ loading ? 'Signing in…' : 'Sign in →' }}
            </button>
          </form>

          <div class="space-y-5">
            <div class="space-y-2.5">
              <div class="glass-divider" />
              <NuxtLink to="/" class="admin-login-back">
                <UIcon name="i-lucide-arrow-left" class="size-3.5" />
                Back to axelnovaventures.com
              </NuxtLink>
            </div>
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
.admin-login-screen {
  position: relative;
  overflow: hidden;
  /* Click-through so the interactive Fluid layer behind receives the cursor in
     the empty areas around the card. The card re-enables pointer events. */
  pointer-events: none;
}

/* Soft accent halo bleeding from behind the glass card (the card's backdrop
   blur samples it, tinting the glass). */
.admin-login-glow {
  position: absolute;
  inset: -22% -28%;
  background: radial-gradient(50% 50% at 50% 32%, var(--color-accent-soft) 0%, transparent 70%);
  filter: blur(28px);
  pointer-events: none;
}

/* Liquid-glass panel — frosted surface + specular top edge + diagonal sheen,
   floating over the live Fluid background. Surface/border/text stay on design
   tokens; only the material highlights are local rgba, switched per mode. */
.glass-card {
  --glass-edge: rgba(255, 255, 255, 0.7);
  --glass-sheen: rgba(255, 255, 255, 0.4);
  --glass-input: rgba(255, 255, 255, 0.55);
  position: relative;
  overflow: hidden;
  /* Re-enable hit-testing inside the otherwise click-through screen. */
  pointer-events: auto;
  background: var(--nav-bg-scrolled);
  border: 1px solid var(--color-border);
  backdrop-filter: blur(40px) saturate(180%);
  -webkit-backdrop-filter: blur(40px) saturate(180%);
  box-shadow:
    var(--shadow-lg),
    inset 0 1px 0 0 var(--glass-edge);
}
.dark .glass-card {
  --glass-edge: rgba(255, 255, 255, 0.14);
  --glass-sheen: rgba(255, 255, 255, 0.07);
  --glass-input: rgba(255, 255, 255, 0.05);
}
.glass-card::before {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: linear-gradient(135deg, var(--glass-sheen) 0%, transparent 40%);
  pointer-events: none;
}

/* Inputs read as nested panes within the glass (still fully legible to type in). */
.glass-input {
  background: var(--glass-input);
}

.glass-divider {
  height: 1px;
  background: var(--color-border);
}

/* Logo mark sits beside the "Admin portal" title — bigger than the nav default. */
.admin-brand :deep(img) {
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

.admin-login-submit {
  height: 48px;
}

.admin-login-back {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 12px;
  color: var(--color-text-tertiary);
  transition: color 0.15s ease;
}
.admin-login-back:hover {
  color: var(--color-text);
}
</style>
