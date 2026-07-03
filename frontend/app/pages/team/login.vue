<script setup lang="ts">
import BrandMark from '~/components/shared/BrandMark.vue'
import VideoBackground from '~/components/shared/VideoBackground.vue'

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
  <div class="team-login-screen min-h-screen flex items-center justify-center px-6 py-12">
    <!-- Full-bleed ambient video behind the glass card, shown unscrimmed. -->
    <VideoBackground src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260503_101827_abebfeec-f243-466b-b494-7f6814c0fbbf.mp4" />

    <div class="relative w-full max-w-md">
      <!-- Soft accent halo bleeding from behind the glass for depth -->
      <div class="team-login-glow" aria-hidden="true" />

      <!-- One liquid-glass panel holds the entire sign-in -->
      <div class="glass-card relative rounded-4xl px-8 pt-10 pb-7 sm:px-10 sm:pt-12 sm:pb-8">
        <div class="relative space-y-6">
          <div class="space-y-4">
            <div class="flex items-center justify-center gap-3">
              <BrandMark variant="mark-only" class="team-brand" />
              <h1 class="text-[26px] font-bold tracking-tight text-gradient">Team workspace</h1>
            </div>
            <p class="text-[13px] text-center" style="color: var(--color-text-secondary);">
              Sign in to triage inquiries and manage referrals.
            </p>
          </div>

          <form class="space-y-5" @submit.prevent="handleLogin">
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Email</label>
              <input v-model="email" type="email" required autocomplete="email" placeholder="Email"
                class="contact-input glass-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }" />
            </div>
            <div class="space-y-1.5">
              <div class="flex items-center justify-between gap-3">
                <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Password</label>
                <NuxtLink to="/team/forgot" class="text-[12px]" style="color: var(--color-accent);">Forgot password?</NuxtLink>
              </div>
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
.team-login-screen {
  position: relative;
  overflow: hidden;
}

/* Soft accent halo bleeding from behind the glass card (the card's backdrop
   blur samples it, tinting the glass). */
.team-login-glow {
  position: absolute;
  inset: -22% -28%;
  background: radial-gradient(50% 50% at 50% 32%, var(--color-accent-soft) 0%, transparent 70%);
  filter: blur(28px);
  pointer-events: none;
}

/* Liquid-glass panel — frosted surface + specular top edge + diagonal sheen,
   floating over the video background. Surface/border/text stay on design
   tokens; only the material highlights are local rgba, switched per mode. */
.glass-card {
  --glass-edge: rgba(255, 255, 255, 0.7);
  --glass-sheen: rgba(255, 255, 255, 0.4);
  --glass-input: rgba(255, 255, 255, 0.55);
  position: relative;
  overflow: hidden;
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
