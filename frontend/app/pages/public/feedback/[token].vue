<script setup lang="ts">
import BrandMark from '~/components/shared/BrandMark.vue'
import FeedbackScale from '~/components/shared/FeedbackScale.vue'

// Token-gated client feedback form — /feedback/{token}. Fully standalone
// (no login, no site chrome), noindex, one submission per token.
definePageMeta({ layout: false })

useSeoMeta({ robots: 'noindex, nofollow' })
useHead({ title: 'Share your feedback — Axel Nova Ventures' })

const route = useRoute()
const token = computed(() => route.params.token as string)

interface Shell {
  name: string | null
  project_label: string | null
  already_submitted: boolean
}

const { data: shellRes, error: shellError } = await useFetch<{ data: Shell }>(
  () => `${useApiBase()}/api/v1/feedback/${token.value}`,
  { key: `feedback-shell-${token.value}` },
)

const shell = computed(() => shellRes.value?.data ?? null)
const notFound = computed(() => !!shellError.value)

const alreadySubmitted = ref(false)
watchEffect(() => {
  if (shell.value?.already_submitted) alreadySubmitted.value = true
})

const form = reactive({
  overall: null as number | null,
  rating_design: null as number | null,
  rating_communication: null as number | null,
  rating_delivery: null as number | null,
  rating_value: null as number | null,
  nps: null as number | null,
  praise: '',
  improve: '',
  publish_consent: false,
  attribution_name: '',
  attribution_role: '',
})

const dimensions = [
  { key: 'rating_design', label: 'Design', hint: 'Look, feel, and polish' },
  { key: 'rating_communication', label: 'Communication', hint: 'Updates, clarity, responsiveness' },
  { key: 'rating_delivery', label: 'Delivery', hint: 'Timeline and handover' },
  { key: 'rating_value', label: 'Value', hint: 'Worth of the investment' },
] as const

const submitting = ref(false)
const submitted = ref(false)
const errors = ref<Record<string, string[]>>({})
const message = ref('')

const canSubmit = computed(() =>
  form.overall !== null && (!form.publish_consent || form.attribution_name.trim().length > 0))

async function submit() {
  if (!canSubmit.value || submitting.value) return
  submitting.value = true
  errors.value = {}
  message.value = ''
  try {
    await $fetch(`${useApiBase()}/api/v1/feedback/${token.value}`, {
      method: 'POST',
      body: {
        ...form,
        praise: form.praise || null,
        improve: form.improve || null,
        attribution_name: form.attribution_name || null,
        attribution_role: form.attribution_role || null,
      },
    })
    submitted.value = true
  }
  catch (e: any) {
    if (e?.status === 409) {
      alreadySubmitted.value = true
    }
    else if (e?.data?.errors) {
      errors.value = e.data.errors
      message.value = e.data.message ?? 'Please check the highlighted fields.'
    }
    else {
      message.value = 'Something went wrong sending your feedback. Please try again.'
    }
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex flex-col" style="background: var(--color-bg); color: var(--color-text);">
    <div class="aurora-line shrink-0" aria-hidden="true" />

    <header class="border-b" :style="{ borderColor: 'var(--color-border)' }">
      <div class="max-w-xl mx-auto px-4 sm:px-6 h-12 flex items-center justify-between">
        <BrandMark variant="compact" />
        <span
          class="font-mono text-[11px] font-medium px-2.5 py-1 rounded-full border"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
        >Feedback</span>
      </div>
    </header>

    <main class="flex-1 w-full max-w-xl mx-auto px-4 sm:px-6 py-12 sm:py-16">
      <!-- Unknown / expired token -->
      <div v-if="notFound" class="text-center py-20">
        <p class="text-4xl font-semibold tracking-tight mb-4">Link not found.</p>
        <p class="text-[15px] mb-8" :style="{ color: 'var(--color-text-secondary)' }">
          This feedback link may have expired or been mistyped.
        </p>
        <NuxtLink to="/" class="text-[14px]" :style="{ color: 'var(--color-accent)' }">
          ← Back to axelnovaventures.com
        </NuxtLink>
      </div>

      <!-- Thank-you (fresh submit) -->
      <div v-else-if="submitted" class="text-center py-20">
        <span
          class="inline-flex items-center justify-center size-14 rounded-full mb-6"
          :style="{ background: 'var(--color-success-soft)', color: 'var(--color-success)' }"
        >
          <UIcon name="i-lucide-check" class="size-7" />
        </span>
        <p class="text-4xl font-semibold tracking-tight mb-4">Thank you.</p>
        <p class="text-[15px] max-w-sm mx-auto" :style="{ color: 'var(--color-text-secondary)' }">
          Your feedback landed safely — I read every one personally.
        </p>
      </div>

      <!-- Already submitted earlier -->
      <div v-else-if="alreadySubmitted" class="text-center py-20">
        <span
          class="inline-flex items-center justify-center size-14 rounded-full mb-6"
          :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
        >
          <UIcon name="i-lucide-mail-check" class="size-7" />
        </span>
        <p class="text-4xl font-semibold tracking-tight mb-4">Already received.</p>
        <p class="text-[15px] max-w-sm mx-auto" :style="{ color: 'var(--color-text-secondary)' }">
          This feedback form has already been submitted — thank you again. If you'd like to add
          anything, just reply to the original email.
        </p>
      </div>

      <!-- The form -->
      <form v-else @submit.prevent="submit">
        <div class="mb-10">
          <p v-if="shell?.project_label" class="font-mono text-[12px] font-medium mb-3" :style="{ color: 'var(--color-accent)' }">
            {{ shell.project_label }}
          </p>
          <h1 class="text-4xl sm:text-5xl font-semibold tracking-tight mb-3">
            How did we do{{ shell?.name ? `, ${shell.name.split(' ')[0]}` : '' }}?
          </h1>
          <p class="text-[15px]" :style="{ color: 'var(--color-text-secondary)' }">
            Two minutes, honest answers. Only the overall rating is required.
          </p>
        </div>

        <div
          class="rounded-2xl border p-5 sm:p-6 space-y-7"
          :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }"
        >
          <!-- Overall -->
          <div>
            <label class="text-[13px] font-medium block mb-2.5">
              Overall experience <span :style="{ color: 'var(--color-accent)' }">*</span>
            </label>
            <FeedbackScale v-model="form.overall" :max="5" :labels="['Rough', 'Excellent']" />
            <p v-if="errors.overall?.length" class="mt-1.5 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.overall[0] }}</p>
          </div>

          <!-- Dimensions -->
          <div class="grid sm:grid-cols-2 gap-x-6 gap-y-5">
            <div v-for="d in dimensions" :key="d.key">
              <label class="text-[13px] font-medium block">{{ d.label }}</label>
              <p class="text-[11px] mb-2" :style="{ color: 'var(--color-text-tertiary)' }">{{ d.hint }}</p>
              <FeedbackScale v-model="form[d.key]" :max="5" />
            </div>
          </div>

          <!-- NPS -->
          <div>
            <label class="text-[13px] font-medium block mb-2.5">
              How likely are you to recommend Axel Nova to someone else?
            </label>
            <FeedbackScale v-model="form.nps" :min="0" :max="10" :labels="['Not likely', 'Extremely likely']" />
          </div>

          <!-- Open text -->
          <div class="space-y-5">
            <div>
              <label class="text-[13px] font-medium block mb-1.5">What did we get right?</label>
              <textarea
                v-model="form.praise" rows="3" maxlength="2000" class="contact-input w-full"
                placeholder="The part of the project that worked best for you…"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
              />
            </div>
            <div>
              <label class="text-[13px] font-medium block mb-1.5">Where should we improve?</label>
              <textarea
                v-model="form.improve" rows="3" maxlength="2000" class="contact-input w-full"
                placeholder="Anything that felt slow, unclear, or missing…"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
              />
            </div>
          </div>

          <!-- Publish consent — §12.2 toggle row-card -->
          <div class="space-y-3">
            <button
              type="button" class="w-full flex items-center gap-3 rounded-lg border px-4 py-3 transition-all text-left"
              :style="form.publish_consent
                ? { borderColor: 'var(--color-accent)', background: 'var(--color-bg-elevated)' }
                : { borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
              @click="form.publish_consent = !form.publish_consent"
            >
              <span
                class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors"
                :style="form.publish_consent
                  ? { background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
                  : { background: 'var(--color-bg-elevated)', color: 'var(--color-text-tertiary)' }"
              >
                <UIcon name="i-lucide-quote" class="size-4" />
              </span>
              <span class="flex-1 min-w-0">
                <span class="block text-[13px] font-medium" :style="{ color: form.publish_consent ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">
                  You may publish my words
                </span>
                <span class="block text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">
                  Your praise + name may appear on the Axel Nova site. Off = private, admin-only.
                </span>
              </span>
              <span
                class="relative inline-block rounded-full transition-colors shrink-0"
                :style="{
                  background: form.publish_consent ? 'var(--color-accent)' : 'var(--color-switch-off-track, #d1d5db)',
                  height: '1.25rem',
                  width: '2.25rem',
                }"
              >
                <span
                  class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all"
                  :style="{ left: form.publish_consent ? '1.125rem' : '0.125rem' }"
                />
              </span>
            </button>

            <div v-if="form.publish_consent" class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">
                  Publish under this name <span :style="{ color: 'var(--color-accent)' }">*</span>
                </label>
                <input
                  v-model="form.attribution_name" type="text" placeholder="e.g. Aina R."
                  class="contact-input w-full"
                  :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
                >
                <p v-if="errors.attribution_name?.length" class="mt-1 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.attribution_name[0] }}</p>
              </div>
              <div>
                <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">
                  Role / company <span class="font-normal">(optional)</span>
                </label>
                <input
                  v-model="form.attribution_role" type="text" placeholder="e.g. Founder, Roofly"
                  class="contact-input w-full"
                  :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
                >
              </div>
            </div>
          </div>

          <p v-if="message" class="text-[13px]" :style="{ color: 'var(--color-danger)' }">{{ message }}</p>

          <div class="pt-1">
            <button
              type="submit"
              class="btn-pill btn-pill-accent w-full sm:w-auto text-[14px]"
              :disabled="!canSubmit || submitting"
              :style="!canSubmit ? { opacity: 0.5, cursor: 'not-allowed' } : {}"
            >
              {{ submitting ? 'Sending…' : 'Send feedback' }}
            </button>
            <p class="mt-3 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">
              Goes straight to Ahmad Baihaqie. Nothing is published without your permission above.
            </p>
          </div>
        </div>
      </form>
    </main>

    <footer class="border-t py-6" :style="{ borderColor: 'var(--color-border)' }">
      <p class="text-center font-mono text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">
        Axel Nova Ventures · axelnovaventures.com
      </p>
    </footer>
  </div>
</template>
