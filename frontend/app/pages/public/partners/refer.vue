<script setup lang="ts">
definePageMeta({ layout: 'public' })

import SectionHeader from '~/components/shared/SectionHeader.vue'

const siteUrl = 'https://axelnovaventures.com'
const seoTitle = 'Refer a Business — Axel Nova Partner Program'
const seoDescription = 'Refer a business to Axel Nova and earn up to 15% when the project closes. Takes two minutes — no cost, no commitment.'

useSeoMeta({
  title: seoTitle,
  description: seoDescription,
  ogTitle: seoTitle,
  ogDescription: seoDescription,
  ogUrl: `${siteUrl}/partners/refer`,
  twitterTitle: seoTitle,
  twitterDescription: seoDescription,
  twitterCard: 'summary_large_image',
})

useHead({
  link: [{ rel: 'canonical', href: `${siteUrl}/partners/refer` }],
})

const form = reactive({
  // Your details (so we can credit you)
  fullName: '',
  email: '',
  phone: '',
  // The business you're referring
  businessName: '',
  businessEmail: '',
  businessPhone: '',
  need: '',
  relationship: 'Just passing their contact',
  notes: '',
  agreed: false,
})

const needs = ['Website', 'UI/UX design', 'Web app or SaaS', 'Not sure yet']
const relationships = [
  'Just passing their contact',
  'I’ll introduce you personally',
  'We’ve already discussed it',
]

const tiers = [
  { name: 'Cold lead', rate: '5%' },
  { name: 'Warm intro', rate: '10%' },
  { name: 'Closed referral', rate: 'Up to 15%' },
]

const submitted = ref(false)
const loading = ref(false)
const error = ref('')

const handleSubmit = async () => {
  if (!form.businessEmail.trim() && !form.businessPhone.trim()) {
    error.value = 'Please provide at least one way to reach the business — an email or a phone number.'
    return
  }
  if (!form.agreed) {
    error.value = 'Please agree to the Partner Program Terms & Conditions to continue.'
    return
  }
  loading.value = true
  error.value = ''
  try {
    const res = await fetch('https://api.web3forms.com/submit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        access_key: 'a9100b0c-2c2b-4c5c-a381-543301ef9b17',
        subject: 'New Partner Referral — axelnovaventures.com',
        from_name: form.fullName,
        // Reply-to goes to the person making the referral.
        email: form.email,
        referred_by_name: form.fullName,
        referred_by_email: form.email,
        referred_by_phone: form.phone,
        business_name: form.businessName,
        business_email: form.businessEmail || '—',
        business_phone: form.businessPhone || '—',
        what_they_need: form.need || '—',
        relationship_to_business: form.relationship,
        notes: form.notes || '—',
        agreed_to_terms: 'Yes',
      }),
    })
    const result = await res.json()
    if (result.success) {
      submitted.value = true
    } else {
      error.value = 'Something went wrong. Please try again, or email baihaqie@axelnova.tech directly.'
    }
  } catch {
    error.value = 'Network error. Please check your connection and try again.'
  } finally {
    loading.value = false
  }
}

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-24 pb-32">
    <SectionHeader
      eyebrow="Partner Program"
      title="Refer a business."
      subtitle="Tell us who you’re referring and how to reach them. We’ll take it from there — and credit you if it becomes a project."
    />

    <div class="grid lg:grid-cols-[1.4fr_1fr] gap-10 lg:gap-16 reveal">

      <!-- Form -->
      <div>
        <!-- Submitted state -->
        <Transition name="page">
          <div
            v-if="submitted"
            class="rounded-2xl border p-12 text-center h-full flex flex-col items-center justify-center gap-5"
            :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
          >
            <div
              class="w-14 h-14 rounded-2xl flex items-center justify-center"
              style="background: var(--color-success-soft);"
            >
              <UIcon
                name="i-fluent-checkmark-circle-24-regular"
                class="size-7"
                style="color: var(--color-success);"
              />
            </div>
            <div>
              <p class="text-[20px] font-semibold tracking-tight mb-2" style="color: var(--color-text);">
                Referral received.
              </p>
              <p class="text-[14px] leading-relaxed max-w-sm" style="color: var(--color-text-secondary);">
                Thanks for the referral. We’ll reach out to the business — usually within 3 business
                days — and keep you posted. If it becomes a signed, paid project, your commission
                follows per the Partner Program terms.
              </p>
            </div>
            <NuxtLink to="/partners" class="btn-pill btn-pill-ghost mt-2">
              Back to Partner Program
            </NuxtLink>
          </div>
        </Transition>

        <!-- Form fields -->
        <form
          v-if="!submitted"
          class="space-y-8"
          @submit.prevent="handleSubmit"
        >
          <!-- Section: Your details -->
          <div class="space-y-5">
            <p class="text-[11px] font-medium uppercase tracking-widest" style="color: var(--color-text-tertiary);">
              Your details
            </p>

            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">
                Full name <span style="color: var(--color-danger);">*</span>
              </label>
              <input
                v-model="form.fullName"
                type="text"
                placeholder="John Doe"
                required
                class="contact-input"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }"
              />
            </div>

            <div class="grid sm:grid-cols-2 gap-5">
              <div class="space-y-1.5">
                <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">
                  Email <span style="color: var(--color-danger);">*</span>
                </label>
                <input
                  v-model="form.email"
                  type="email"
                  placeholder="you@example.com"
                  required
                  class="contact-input"
                  :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }"
                />
              </div>
              <div class="space-y-1.5">
                <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">
                  Phone number <span style="color: var(--color-danger);">*</span>
                </label>
                <input
                  v-model="form.phone"
                  type="tel"
                  placeholder="+60 12-345 6789"
                  required
                  class="contact-input"
                  :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }"
                />
              </div>
            </div>
          </div>

          <!-- Divider -->
          <div class="border-t" :style="{ borderColor: 'var(--color-border)' }" />

          <!-- Section: The business you're referring -->
          <div class="space-y-5">
            <p class="text-[11px] font-medium uppercase tracking-widest" style="color: var(--color-text-tertiary);">
              The business you’re referring
            </p>

            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">
                Business / contact name <span style="color: var(--color-danger);">*</span>
              </label>
              <input
                v-model="form.businessName"
                type="text"
                placeholder="The company or person"
                required
                class="contact-input"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }"
              />
            </div>

            <div class="space-y-1.5">
              <div class="grid sm:grid-cols-2 gap-5">
                <div class="space-y-1.5">
                  <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">
                    Their email
                  </label>
                  <input
                    v-model="form.businessEmail"
                    type="email"
                    placeholder="contact@company.com"
                    class="contact-input"
                    :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }"
                  />
                </div>
                <div class="space-y-1.5">
                  <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">
                    Their phone
                  </label>
                  <input
                    v-model="form.businessPhone"
                    type="tel"
                    placeholder="+60 12-345 6789"
                    class="contact-input"
                    :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }"
                  />
                </div>
              </div>
              <p class="text-[12px] leading-relaxed pt-1" style="color: var(--color-text-tertiary);">
                Provide at least one — whichever you have for them.
              </p>
            </div>

            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">
                What do they need? <span style="color: var(--color-text-tertiary);">(optional)</span>
              </label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="n in needs"
                  :key="n"
                  type="button"
                  class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
                  :style="{
                    borderColor: form.need === n ? 'var(--color-accent)' : 'var(--color-border)',
                    background: form.need === n ? 'var(--color-accent-soft)' : 'transparent',
                    color: form.need === n ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                    fontWeight: form.need === n ? 500 : 400,
                  }"
                  @click="form.need = form.need === n ? '' : n"
                >
                  {{ n }}
                </button>
              </div>
            </div>

            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">
                How well do you know them?
              </label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="r in relationships"
                  :key="r"
                  type="button"
                  class="text-[12px] px-3.5 py-1.5 rounded-full border transition-all"
                  :style="{
                    borderColor: form.relationship === r ? 'var(--color-accent)' : 'var(--color-border)',
                    background: form.relationship === r ? 'var(--color-accent-soft)' : 'transparent',
                    color: form.relationship === r ? 'var(--color-accent)' : 'var(--color-text-secondary)',
                    fontWeight: form.relationship === r ? 500 : 400,
                  }"
                  @click="form.relationship = r"
                >
                  {{ r }}
                </button>
              </div>
              <p class="text-[12px] leading-relaxed pt-1" style="color: var(--color-text-tertiary);">
                This helps us set expectations — your commission tier is confirmed once we’ve spoken to them.
              </p>
            </div>

            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">
                Anything else we should know? <span style="color: var(--color-text-tertiary);">(optional)</span>
              </label>
              <textarea
                v-model="form.notes"
                rows="4"
                placeholder="Context on the business, what they’re after, timing, or how to mention you…"
                class="contact-input resize-none"
                :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }"
              />
            </div>
          </div>

          <label class="flex items-start gap-3 cursor-pointer select-none">
            <input
              v-model="form.agreed"
              type="checkbox"
              required
              class="mt-0.5 size-4 shrink-0 rounded"
              style="accent-color: var(--color-accent);"
            />
            <span class="text-[13px] leading-relaxed" style="color: var(--color-text-secondary);">
              I confirm I have a genuine connection to this business and agree to the Axel Nova
              Partner Program Terms &amp; Conditions. Full terms are shared when a referral progresses
              to a project.
            </span>
          </label>

          <button
            type="submit"
            class="btn-pill btn-pill-accent w-full justify-center"
            :disabled="loading"
            :style="{ opacity: loading ? '0.7' : '1', cursor: loading ? 'not-allowed' : 'pointer' }"
          >
            {{ loading ? 'Submitting…' : 'Submit referral →' }}
          </button>

          <p v-if="error" class="text-[12px] text-center" style="color: var(--color-danger);">
            {{ error }}
          </p>

          <p class="text-[12px] text-center" style="color: var(--color-text-tertiary);">
            Bank details for payouts are collected separately, only once a referral becomes a paid project.
          </p>
        </form>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6 lg:pt-1">

        <!-- What you'll earn -->
        <div
          class="rounded-2xl border p-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <p class="text-[11px] font-medium uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">
            What you’ll earn
          </p>
          <div class="space-y-3">
            <div
              v-for="t in tiers"
              :key="t.name"
              class="flex items-center justify-between"
            >
              <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ t.name }}</span>
              <span class="text-[14px] font-semibold tabular-nums" style="color: var(--color-text);">
                {{ t.rate }}
              </span>
            </div>
          </div>
          <p class="text-[12px] leading-relaxed mt-4 pt-4 border-t" :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-tertiary)' }">
            Commission is paid on the final project value, within 14 working days of cleared payment.
          </p>
        </div>

        <!-- What happens next -->
        <div
          class="rounded-2xl border p-5"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        >
          <p class="text-[11px] font-medium uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">
            What happens next
          </p>
          <ol class="space-y-2.5">
            <li class="text-[13px] leading-relaxed flex gap-2.5" style="color: var(--color-text-secondary);">
              <span class="font-semibold tabular-nums" style="color: var(--color-text);">1.</span>
              We reach out to the business you referred, usually within 3 business days.
            </li>
            <li class="text-[13px] leading-relaxed flex gap-2.5" style="color: var(--color-text-secondary);">
              <span class="font-semibold tabular-nums" style="color: var(--color-text);">2.</span>
              We scope and deliver the project — you stay in the loop throughout.
            </li>
            <li class="text-[13px] leading-relaxed flex gap-2.5" style="color: var(--color-text-secondary);">
              <span class="font-semibold tabular-nums" style="color: var(--color-text);">3.</span>
              When it’s signed and paid, your commission is paid within 14 working days.
            </li>
          </ol>
        </div>

        <!-- Note -->
        <div
          class="rounded-xl border px-4 py-3.5 flex items-start gap-3"
          :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }"
        >
          <UIcon
            name="i-fluent-info-24-regular"
            class="size-4 mt-0.5 shrink-0"
            style="color: var(--color-text-tertiary);"
          />
          <p class="text-[12px] leading-relaxed" style="color: var(--color-text-secondary);">
            Want the full picture first? Read the
            <NuxtLink to="/partners" style="color: var(--color-accent);">Partner Program overview</NuxtLink>
            or email
            <a href="mailto:baihaqie@axelnova.tech" style="color: var(--color-accent);">baihaqie@axelnova.tech</a>.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
