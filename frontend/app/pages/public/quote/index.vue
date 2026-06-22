<script setup lang="ts">
definePageMeta({ layout: 'public' })

const siteUrl = 'https://axelnovaventures.com'
const seoTitle = 'Request a Quote — Axel Nova Ventures'
const seoDescription = 'Tell me about your project and I\'ll put together a tailored quote. Share your goals, budget, and timeline — no commitment required.'

useSeoMeta({
  title: seoTitle,
  description: seoDescription,
  ogTitle: seoTitle,
  ogDescription: seoDescription,
  ogUrl: `${siteUrl}/quote`,
  twitterTitle: seoTitle,
  twitterDescription: seoDescription,
  twitterCard: 'summary_large_image',
})
useHead({ link: [{ rel: 'canonical', href: `${siteUrl}/quote` }] })

const runtimeConfig = useRuntimeConfig()
const route = useRoute()

const form = reactive({
  name: '',
  company: '',
  email: '',
  phone: '',
  projectType: '',
  budgetHint: '',
  timelineHint: '',
  message: '',
})

const projectTypes = ['Website', 'Dashboard / portal', 'Design & frontend', 'Web app / SaaS', 'Other / not sure']
const budgets = ['< RM 5k', 'RM 5k – 15k', 'RM 15k – 40k', 'RM 40k+', 'Flexible']
const timelines = ['ASAP', '1–2 months', '3–6 months', 'Flexible']

// Pre-fill the project type from /services deep links (?category=…, ?service=…).
const categoryToType: Record<string, string> = {
  web: 'Website',
  dashboard: 'Dashboard / portal',
  'design-frontend': 'Design & frontend',
  saas: 'Web app / SaaS',
}
onMounted(() => {
  const cat = typeof route.query.category === 'string' ? route.query.category : ''
  const service = typeof route.query.service === 'string' ? route.query.service : ''
  if (categoryToType[cat]) form.projectType = categoryToType[cat]
  else if (service) form.projectType = service.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
})

const loading = ref(false)
const error = ref('')

const canSubmit = computed(() =>
  form.name.trim().length >= 2
  && form.email.includes('@')
  && form.message.trim().length >= 10,
)

async function handleSubmit() {
  if (!canSubmit.value) return
  loading.value = true
  error.value = ''
  try {
    await $fetch(`${runtimeConfig.public.apiBase}/api/v1/inquiries`, {
      method: 'POST',
      headers: { Accept: 'application/json' },
      body: {
        name: form.name,
        email: form.email,
        phone: form.phone || null,
        company: form.company || null,
        project_type: form.projectType || null,
        budget_hint: form.budgetHint || null,
        timeline_hint: form.timelineHint || null,
        message: form.message,
      },
    })
    await navigateTo('/quote/success')
  }
  catch (e: any) {
    const errs = e?.data?.errors ? Object.values(e.data.errors).flat().join(' ') : ''
    error.value = errs || e?.data?.message || 'Something went wrong. Please try again, or email baihaqie@axelnova.tech directly.'
  }
  finally {
    loading.value = false
  }
}

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-24 pb-32">

    <!-- Header -->
    <div class="mb-12 reveal">
      <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-accent);">Start a project</p>
      <h1 class="text-[36px] lg:text-[48px] font-bold tracking-tight leading-tight mb-4" style="color: var(--color-text);">
        Tell me about your project.
      </h1>
      <p class="text-[16px] leading-relaxed max-w-xl" style="color: var(--color-text-secondary);">
        Share a few details and I'll put together a tailored quote — scope, timeline, and pricing.
        No commitment required.
      </p>
    </div>

    <div class="grid lg:grid-cols-[1.4fr_1fr] gap-10 lg:gap-16 reveal">

      <!-- Form -->
      <form class="space-y-8" @submit.prevent="handleSubmit">

        <!-- About you -->
        <div class="space-y-5">
          <p class="text-[11px] font-medium uppercase tracking-widest" style="color: var(--color-text-tertiary);">About you</p>
          <div class="grid sm:grid-cols-2 gap-5">
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Full name <span style="color: var(--color-danger);">*</span></label>
              <input v-model="form.name" type="text" placeholder="John Doe" required class="contact-input"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
            </div>
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Company / project</label>
              <input v-model="form.company" type="text" placeholder="Acme Sdn Bhd" class="contact-input"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
            </div>
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Email <span style="color: var(--color-danger);">*</span></label>
              <input v-model="form.email" type="email" placeholder="you@company.com" required class="contact-input"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
            </div>
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Phone / WhatsApp</label>
              <input v-model="form.phone" type="tel" placeholder="+60 12-345 6789" class="contact-input"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
            </div>
          </div>
        </div>

        <div class="border-t" :style="{ borderColor: 'var(--color-border)' }" />

        <!-- Project -->
        <div class="space-y-5">
          <p class="text-[11px] font-medium uppercase tracking-widest" style="color: var(--color-text-tertiary);">Your project</p>

          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">What are you building?</label>
            <div class="flex flex-wrap gap-2">
              <button v-for="t in projectTypes" :key="t" type="button"
                class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
                :style="{
                  borderColor: form.projectType === t ? 'var(--color-accent)' : 'var(--color-border)',
                  background: form.projectType === t ? 'var(--color-accent-soft)' : 'transparent',
                  color: form.projectType === t ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                }"
                @click="form.projectType = form.projectType === t ? '' : t">
                {{ t }}
              </button>
            </div>
          </div>

          <div class="grid sm:grid-cols-2 gap-5">
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Budget range</label>
              <div class="flex flex-wrap gap-2">
                <button v-for="b in budgets" :key="b" type="button"
                  class="text-[12px] px-3 py-1.5 rounded-full border transition-all"
                  :style="{
                    borderColor: form.budgetHint === b ? 'var(--color-accent)' : 'var(--color-border)',
                    background: form.budgetHint === b ? 'var(--color-accent-soft)' : 'transparent',
                    color: form.budgetHint === b ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                  }"
                  @click="form.budgetHint = form.budgetHint === b ? '' : b">{{ b }}</button>
              </div>
            </div>
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Timeline</label>
              <div class="flex flex-wrap gap-2">
                <button v-for="tl in timelines" :key="tl" type="button"
                  class="text-[12px] px-3 py-1.5 rounded-full border transition-all"
                  :style="{
                    borderColor: form.timelineHint === tl ? 'var(--color-accent)' : 'var(--color-border)',
                    background: form.timelineHint === tl ? 'var(--color-accent-soft)' : 'transparent',
                    color: form.timelineHint === tl ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                  }"
                  @click="form.timelineHint = form.timelineHint === tl ? '' : tl">{{ tl }}</button>
              </div>
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Project details <span style="color: var(--color-danger);">*</span></label>
            <textarea v-model="form.message" rows="6" required
              placeholder="Goals, key features, any references or constraints, and anything else that'll help me scope it…"
              class="contact-input resize-none w-full"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
          </div>
        </div>

        <!-- Honeypot -->
        <input type="text" name="website_url" class="hidden" tabindex="-1" autocomplete="off" />

        <button type="submit" class="btn-pill btn-pill-accent w-full justify-center"
          :disabled="!canSubmit || loading"
          :style="{ opacity: (!canSubmit || loading) ? '0.6' : '1', cursor: (!canSubmit || loading) ? 'not-allowed' : 'pointer' }">
          {{ loading ? 'Sending…' : 'Send inquiry →' }}
        </button>

        <p v-if="error" class="text-[12px] text-center" style="color: var(--color-danger);">{{ error }}</p>
        <p class="text-[11px] text-center" style="color: var(--color-text-tertiary);">
          I'll review your details and reply with a tailored quote, usually within 1–2 business days.
        </p>
      </form>

      <!-- Sidebar -->
      <div class="space-y-6 lg:pt-1">
        <div class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-medium uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">What happens next</p>
          <ol class="space-y-2.5">
            <li class="text-[13px] leading-relaxed flex gap-2.5" style="color: var(--color-text-secondary);">
              <span class="font-semibold tabular-nums" style="color: var(--color-text);">1.</span>
              I review your details and put together a tailored quote — scope, timeline, and pricing.
            </li>
            <li class="text-[13px] leading-relaxed flex gap-2.5" style="color: var(--color-text-secondary);">
              <span class="font-semibold tabular-nums" style="color: var(--color-text);">2.</span>
              You receive it by email to review at your own pace. No pressure.
            </li>
            <li class="text-[13px] leading-relaxed flex gap-2.5" style="color: var(--color-text-secondary);">
              <span class="font-semibold tabular-nums" style="color: var(--color-text);">3.</span>
              If it's a fit, we book a short call to finalise scope and get started.
            </li>
          </ol>
        </div>

        <div class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
          <p class="text-[11px] font-medium uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Not sure what you need?</p>
          <p class="text-[13px] leading-relaxed mb-4" style="color: var(--color-text-secondary);">
            Browse the <NuxtLink to="/services" style="color: var(--color-accent);">services</NuxtLink> to see what's possible, or just describe your idea above — I'll help you shape it.
          </p>
          <a href="https://calendly.com/baihaqie" target="_blank" rel="noopener" class="btn-pill btn-pill-ghost w-full justify-center text-[13px]">
            Book a discovery call →
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
