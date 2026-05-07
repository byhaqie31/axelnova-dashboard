<script setup lang="ts">
definePageMeta({ layout: 'default' })

useHead({ title: 'Quote Sent — Axel Nova Ventures' })

const route = useRoute()
const ref_ = route.query.ref as string
const until = route.query.until as string

const copied = ref(false)

async function copyRef() {
  await navigator.clipboard.writeText(ref_)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}
</script>

<template>
  <div class="max-w-2xl mx-auto px-6 pt-24 pb-32 text-center">

    <!-- Success icon -->
    <div class="flex justify-center mb-8">
      <div class="w-20 h-20 rounded-3xl flex items-center justify-center"
        style="background: rgba(48,209,88,0.12);">
        <UIcon name="i-fluent-checkmark-circle-24-regular" class="size-10" style="color: var(--color-success);" />
      </div>
    </div>

    <h1 class="text-[32px] font-bold tracking-tight mb-3" style="color: var(--color-text);">
      Your quote is on the way.
    </h1>
    <p class="text-[16px] leading-relaxed mb-8" style="color: var(--color-text-secondary);">
      Check your inbox in 2–5 minutes for your personalised PDF quote.
      If it doesn't arrive, check your spam folder.
    </p>

    <!-- Reference code -->
    <div v-if="ref_" class="rounded-2xl border p-6 mb-8 text-left"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Reference code</p>
          <p class="font-mono text-[22px] font-bold" style="color: var(--color-text);">{{ ref_ }}</p>
          <p v-if="until" class="text-[12px] mt-1" style="color: var(--color-text-secondary);">
            Quote valid until <span class="font-medium" style="color: var(--color-text);">{{ until }}</span>
          </p>
        </div>
        <button class="btn-pill btn-pill-ghost flex items-center gap-2" @click="copyRef">
          <UIcon :name="copied ? 'i-fluent-checkmark-24-regular' : 'i-fluent-copy-24-regular'" class="size-4" />
          {{ copied ? 'Copied' : 'Copy' }}
        </button>
      </div>
    </div>

    <!-- What happens next -->
    <div class="rounded-2xl border p-6 mb-8 text-left"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
      <p class="text-[12px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">What happens next</p>
      <div class="space-y-3">
        <div v-for="(step, i) in [
          'You receive a PDF quote with full scope, timeline, and pricing.',
          'Review it at your own pace — no pressure, valid for 30 days.',
          'If you\'re happy, we schedule a 30-minute discovery call to finalise scope.',
          'On signed agreement + 50% upfront, we start building.',
        ]" :key="i" class="flex items-start gap-3">
          <span class="text-[11px] font-bold shrink-0 mt-0.5 w-5 h-5 rounded flex items-center justify-center"
            style="background: var(--color-accent-soft); color: var(--color-accent);">
            {{ i + 1 }}
          </span>
          <p class="text-[13px] leading-relaxed" style="color: var(--color-text-secondary);">{{ step }}</p>
        </div>
      </div>
    </div>

    <!-- CTAs -->
    <div class="flex flex-col sm:flex-row gap-3 justify-center">
      <a
        href="https://calendly.com/baihaqie"
        target="_blank" rel="noopener"
        class="btn-pill btn-pill-accent">
        Book a discovery call →
      </a>
      <NuxtLink to="/" class="btn-pill btn-pill-ghost">Back to home</NuxtLink>
    </div>

  </div>
</template>
