<script setup lang="ts">
// The whole "choose your password / passcode" card behind a one-time access
// link (?token=…) — shared by /team/set-password, /partners/set-passcode and
// /admin/reset-password. Checks the link first (GET), then sets the secret
// once (POST). Expired, used and unknown links all land on the same message.
import BrandMark from '~/components/shared/BrandMark.vue'
import {
  accessLinkCopy,
  loginPath,
  secretNoun,
  validateSecret,
  type AccessLinkInfo,
  type AccessSurface,
} from '~/utils/accessLink'

const props = defineProps<{ surface: AccessSurface }>()

// The token sits in the URL — never hand it to another origin as a Referer.
useHead({ meta: [{ name: 'referrer', content: 'no-referrer' }] })

const route = useRoute()
const api = useApi()
const token = computed(() => String(route.query.token ?? ''))
const endpoint = computed(() => `/api/v1/auth/access-links/${encodeURIComponent(token.value)}`)

const info = ref<AccessLinkInfo | null>(null)
const state = ref<'loading' | 'invalid' | 'form' | 'done'>('loading')
const secret = ref('')
const confirmation = ref('')
const show = ref(false)
const saving = ref(false)
const error = ref('')
const year = new Date().getFullYear()

const surface = computed<AccessSurface>(() => info.value?.surface ?? props.surface)
const noun = computed(() => secretNoun(surface.value))
const copy = computed(() => (info.value ? accessLinkCopy(info.value) : null))
const signInPath = computed(() => loginPath(surface.value))
const forgotPath = computed(() => (surface.value === 'partner' ? '/partners/forgot' : surface.value === 'admin' ? '/admin/forgot' : '/team/forgot'))
const nounTitle = computed(() => noun.value.charAt(0).toUpperCase() + noun.value.slice(1))

onMounted(async () => {
  if (!token.value) {
    state.value = 'invalid'
    return
  }
  try {
    info.value = await api<AccessLinkInfo>(endpoint.value)
    state.value = 'form'
  }
  catch {
    state.value = 'invalid'
  }
})

async function submit() {
  if (!info.value || saving.value) return
  error.value = validateSecret(secret.value, confirmation.value, info.value.min_length, noun.value) ?? ''
  if (error.value) return

  saving.value = true
  try {
    await api(endpoint.value, {
      method: 'POST',
      body: { secret: secret.value, secret_confirmation: confirmation.value },
    })
    state.value = 'done'
  }
  catch (e) {
    const err = e as { response?: { status?: number }, data?: { errors?: { secret?: string[] } } }
    if (err.response?.status === 410) {
      state.value = 'invalid'
    }
    else {
      error.value = err.data?.errors?.secret?.[0] ?? 'Something went wrong. Please try again in a moment.'
    }
  }
  finally {
    saving.value = false
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
              <BrandMark variant="mark-only" class="access-brand" />
              <h1 class="text-[24px] font-bold tracking-tight" style="color: var(--color-text);">
                {{ copy?.title ?? (state === 'invalid' ? 'Link expired' : `Set your ${noun}`) }}
              </h1>
            </div>
            <p v-if="copy && state === 'form'" class="text-[13px] text-center" style="color: var(--color-text-secondary);">
              {{ copy.intro }}
            </p>
          </div>

          <p v-if="state === 'loading'" class="text-[13px] text-center" style="color: var(--color-text-tertiary);">Checking your link…</p>

          <!-- Expired / used / unknown — one message, a way forward -->
          <div v-else-if="state === 'invalid'" class="space-y-5">
            <div
              class="rounded-2xl border p-4 flex items-start gap-2.5"
              :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
              <UIcon name="i-lucide-link-2-off" class="size-5 shrink-0" style="color: var(--color-warning);" />
              <p class="text-[13px] leading-relaxed" style="color: var(--color-text-secondary);">
                This link has expired or has already been used. You can ask for a new one below.
              </p>
            </div>
            <NuxtLink :to="forgotPath" class="btn-pill btn-pill-accent w-full justify-center access-submit">
              Get a new link →
            </NuxtLink>
          </div>

          <!-- Done -->
          <div v-else-if="state === 'done'" class="space-y-5">
            <div
              class="rounded-2xl border p-4 flex items-start gap-2.5"
              :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
              <UIcon name="i-lucide-circle-check" class="size-5 shrink-0" style="color: var(--color-success);" />
              <p class="text-[13px] leading-relaxed" style="color: var(--color-text-secondary);">{{ copy?.done }}</p>
            </div>
            <NuxtLink :to="signInPath" class="btn-pill btn-pill-accent w-full justify-center access-submit">
              Go to sign in →
            </NuxtLink>
          </div>

          <!-- Choose it -->
          <form v-else class="space-y-5" @submit.prevent="submit">
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">New {{ noun }}</label>
              <div class="relative">
                <input
                  v-model="secret" :type="show ? 'text' : 'password'" required autocomplete="new-password"
                  :placeholder="`At least ${info?.min_length ?? 12} characters`"
                  class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', paddingRight: '2.75rem' }">
                <button
                  type="button" class="access-pw-toggle" :aria-label="show ? `Hide ${noun}` : `Show ${noun}`"
                  @click="show = !show">
                  <UIcon :name="show ? 'i-lucide-eye-off' : 'i-lucide-eye'" class="size-4.5" />
                </button>
              </div>
            </div>
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Confirm {{ noun }}</label>
              <input
                v-model="confirmation" :type="show ? 'text' : 'password'" required autocomplete="new-password"
                :placeholder="`Type the ${noun} again`"
                class="contact-input" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }">
            </div>
            <p v-if="error" class="text-[12px] flex items-center gap-1.5" style="color: var(--color-danger);">
              <UIcon name="i-lucide-alert-circle" class="size-4 shrink-0" />
              {{ error }}
            </p>
            <button type="submit" class="btn-pill btn-pill-accent w-full justify-center access-submit" :disabled="saving || !secret || !confirmation">
              {{ saving ? 'Saving…' : `${copy?.submit ?? `Set ${noun}`} →` }}
            </button>
            <p class="text-[11px] text-center" style="color: var(--color-text-tertiary);">
              {{ nounTitle }}s are private to you — nobody at Axel Nova ever sees them.
            </p>
          </form>

          <div class="space-y-2.5">
            <div class="access-divider" />
            <NuxtLink :to="signInPath" class="access-back">
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
.access-brand :deep(img) {
  width: 2.4rem;
  height: 2.4rem;
}

.access-submit {
  height: 48px;
}

.access-pw-toggle {
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
.access-pw-toggle:hover {
  color: var(--color-text);
}

.access-divider {
  height: 1px;
  background: var(--color-border);
}

.access-back {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 12px;
  color: var(--color-text-tertiary);
  transition: color 0.15s ease;
}
.access-back:hover {
  color: var(--color-text);
}
</style>
