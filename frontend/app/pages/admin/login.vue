<script setup lang="ts">
definePageMeta({ layout: false })
useHead({ title: 'Admin Login — Axel Nova' })

const { setToken, apiFetch } = useAdminAuth()
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

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
    await navigateTo('/admin')
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
  <div class="min-h-screen flex items-center justify-center px-6"
    style="background: var(--color-bg);">
    <div class="w-full max-w-sm">
      <div class="rounded-2xl border p-8 space-y-6"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-2" style="color: var(--color-text-tertiary);">
            Axel Nova Platform
          </p>
          <h1 class="text-[22px] font-bold tracking-tight" style="color: var(--color-text);">Admin access</h1>
        </div>

        <form class="space-y-4" @submit.prevent="handleLogin">
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Email</label>
            <input v-model="email" type="email" required placeholder="baihaqie@axelnova.tech"
              class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Password</label>
            <input v-model="password" type="password" required placeholder="••••••••"
              class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          </div>
          <p v-if="error" class="text-[12px]" style="color: var(--color-danger);">{{ error }}</p>
          <button type="submit" class="btn-pill btn-pill-accent w-full justify-center" :disabled="loading">
            {{ loading ? 'Signing in…' : 'Sign in →' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
