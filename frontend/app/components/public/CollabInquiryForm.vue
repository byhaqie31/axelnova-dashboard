<script setup lang="ts">
// Both /collaborations forms post to the shared inquiries pipeline
// (POST /v1/inquiries → /admin/inquiries, plus the auto-reply and admin emails):
//   variant 'partner'       — a client hiring a partner studio THROUGH Axel Nova
//                             (origin 'partner', tagged with the partner slug)
//   variant 'collaboration' — a studio applying to partner with Axel Nova
//                             (origin 'collaboration'; the portfolio link rides
//                             into the message server-side)
const props = defineProps<{
  variant: 'partner' | 'collaboration'
  /** Required for the 'partner' variant. */
  partnerSlug?: string
  partnerName?: string
}>()

const isPartner = computed(() => props.variant === 'partner')

const projectTypes = ['Website', 'Mobile app', 'Business system', 'AI product', 'Not sure yet']
const studioTypes = ['Design studio', 'Development studio', 'Agency', 'Freelancer', 'Other']

const blank = () => ({
  name: '',
  email: '',
  phone: '',
  company: '',
  portfolioUrl: '',
  pill: isPartner.value ? projectTypes[0]! : studioTypes[0]!,
  message: '',
})
const form = reactive(blank())

const submitted = ref(false)
const loading = ref(false)
const error = ref('')
const honeypot = ref('')

const runtimeConfig = useRuntimeConfig()
const { withRef } = useReferralAttribution()

const reset = () => {
  Object.assign(form, blank())
  submitted.value = false
  error.value = ''
}

const handleSubmit = async () => {
  if (form.message.trim().length < 10) {
    error.value = 'Please add a little more detail, at least 10 characters.'
    return
  }
  loading.value = true
  error.value = ''
  try {
    await $fetch(withRef(`${runtimeConfig.public.apiBase}/api/v1/inquiries`), {
      method: 'POST',
      headers: { Accept: 'application/json' },
      body: isPartner.value
        ? {
            origin: 'partner',
            partner: props.partnerSlug,
            name: form.name,
            email: form.email,
            phone: form.phone || null,
            company: form.company || null,
            project_type: form.pill,
            message: form.message,
            website_url: honeypot.value || null,
          }
        : {
            origin: 'collaboration',
            name: form.name,
            email: form.email,
            company: form.company || null,
            subject: form.pill,
            portfolio_url: form.portfolioUrl || null,
            message: form.message,
            website_url: honeypot.value || null,
          },
    })
    submitted.value = true
  }
  catch (e) {
    error.value = formErrorMessage(e)
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <Transition name="page" mode="out-in">
      <div
        v-if="submitted"
        class="rounded-2xl border p-12 text-center flex flex-col items-center justify-center gap-5"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
      >
        <div
          class="w-14 h-14 rounded-2xl flex items-center justify-center"
          :style="{ background: 'var(--color-success-soft)' }"
        >
          <UIcon name="i-fluent-checkmark-circle-24-regular" class="size-7" style="color: var(--color-success);" />
        </div>
        <div>
          <p class="text-[20px] font-semibold tracking-tight mb-2" style="color: var(--color-text);">
            {{ isPartner ? 'Enquiry sent.' : 'Application sent.' }}
          </p>
          <p class="text-[14px] leading-relaxed max-w-sm mx-auto" style="color: var(--color-text-secondary);">
            <template v-if="isPartner">
              A confirmation is on its way to your inbox. We'll look at your project together and reply within 1–2 business days.
            </template>
            <template v-else>
              A confirmation is on its way to your inbox. I'll look through your work and reply personally.
            </template>
          </p>
        </div>
        <button type="button" class="btn-pill btn-pill-ghost mt-2" @click="reset">
          Send another
        </button>
      </div>

      <form v-else class="space-y-5" @submit.prevent="handleSubmit">
        <!-- Honeypot — bots fill it, the API rejects any value -->
        <input v-model="honeypot" type="text" name="website_url" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">

        <div class="grid sm:grid-cols-2 gap-5">
          <div class="space-y-1.5">
            <label :for="`${variant}-name`" class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Name</label>
            <input
              :id="`${variant}-name`" v-model="form.name" type="text" required minlength="2" maxlength="150"
              autocomplete="name" placeholder="Your name"
              class="contact-input"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }"
            >
          </div>
          <div class="space-y-1.5">
            <label :for="`${variant}-email`" class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Email</label>
            <input
              :id="`${variant}-email`" v-model="form.email" type="email" required maxlength="200"
              autocomplete="email" placeholder="you@example.com"
              class="contact-input"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }"
            >
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-5">
          <div v-if="isPartner" class="space-y-1.5">
            <label :for="`${variant}-phone`" class="text-[12px] font-medium" style="color: var(--color-text-secondary);">
              Phone <span style="color: var(--color-text-tertiary);">(optional)</span>
            </label>
            <input
              :id="`${variant}-phone`" v-model="form.phone" type="tel" maxlength="30"
              autocomplete="tel" placeholder="+60 12-345 6789"
              class="contact-input"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }"
            >
          </div>
          <div class="space-y-1.5">
            <label :for="`${variant}-company`" class="text-[12px] font-medium" style="color: var(--color-text-secondary);">
              {{ isPartner ? 'Company' : 'Studio name' }} <span style="color: var(--color-text-tertiary);">(optional)</span>
            </label>
            <input
              :id="`${variant}-company`" v-model="form.company" type="text" maxlength="200"
              autocomplete="organization" :placeholder="isPartner ? 'Your business' : 'Your studio'"
              class="contact-input"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }"
            >
          </div>
          <div v-if="!isPartner" class="space-y-1.5">
            <label :for="`${variant}-portfolio`" class="text-[12px] font-medium" style="color: var(--color-text-secondary);">
              Portfolio or website <span style="color: var(--color-text-tertiary);">(optional)</span>
            </label>
            <input
              :id="`${variant}-portfolio`" v-model="form.portfolioUrl" type="url" maxlength="300"
              autocomplete="url" placeholder="https://"
              class="contact-input"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }"
            >
          </div>
        </div>

        <fieldset class="space-y-1.5">
          <legend class="text-[12px] font-medium mb-1.5" style="color: var(--color-text-secondary);">
            {{ isPartner ? 'What do you need?' : 'What best describes you?' }}
          </legend>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="p in (isPartner ? projectTypes : studioTypes)"
              :key="p"
              type="button"
              class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
              :aria-pressed="form.pill === p"
              :style="{
                borderColor: form.pill === p ? 'var(--color-accent)' : 'var(--color-border)',
                background: form.pill === p ? 'var(--color-accent-soft)' : 'transparent',
                color: form.pill === p ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                fontWeight: form.pill === p ? 500 : 400,
              }"
              @click="form.pill = p"
            >
              {{ p }}
            </button>
          </div>
        </fieldset>

        <div class="space-y-1.5">
          <label :for="`${variant}-message`" class="text-[12px] font-medium" style="color: var(--color-text-secondary);">
            {{ isPartner ? 'Tell us about the project' : 'What you do and how you’d like to work together' }}
          </label>
          <textarea
            :id="`${variant}-message`" v-model="form.message" rows="6" required minlength="10" maxlength="5000"
            :placeholder="isPartner
              ? 'What you want to build, who it is for, and any timing we should know about.'
              : 'The kind of work you take on, the projects you enjoy, and where a partner would help.'"
            class="contact-input resize-none"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }"
          />
        </div>

        <button
          type="submit"
          class="btn-pill btn-pill-accent w-full justify-center"
          :disabled="loading"
          :style="{ opacity: loading ? '0.7' : '1', cursor: loading ? 'not-allowed' : 'pointer' }"
        >
          {{ loading ? 'Sending…' : (isPartner ? `Send to Axel Nova × ${partnerName} →` : 'Send application →') }}
        </button>

        <p v-if="error" class="text-[12px] text-center" role="alert" style="color: var(--color-danger);">
          {{ error }}
        </p>
      </form>
    </Transition>
  </div>
</template>
