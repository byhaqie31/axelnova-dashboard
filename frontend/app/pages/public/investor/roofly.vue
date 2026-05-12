<script setup lang="ts">
definePageMeta({ layout: 'public' })
useSeoMeta({ robots: 'noindex, nofollow' })
useHead({
  title: 'Roofly — Investor materials | Axel Nova Ventures',
  meta: [
    { name: 'description', content: 'Investor materials for Roofly — a rent-management SaaS for Malaysian landlords. Pitch deck, investment package, and financial summary.' },
    { name: 'robots', content: 'noindex' },
  ],
})

useScrollReveal('.reveal')

interface Material {
  label: string
  title: string
  description: string
  icon: string
  href: string
}

const materials: Material[] = [
  {
    label: 'Pitch deck',
    title: 'The full investor pitch',
    description: 'Vision, market opportunity, product walkthrough, traction, team, and the ask. Start here for the complete narrative.',
    icon: 'i-lucide-presentation',
    href: '/investor/roofly/deck.html',
  },
  {
    label: 'Investment package',
    title: 'Terms and structure',
    description: 'Investment instrument, valuation, use of funds, milestones, and what an investor commitment looks like.',
    icon: 'i-lucide-wallet',
    href: '/investor/roofly/investor-package.html',
  },
  {
    label: 'Financial summary',
    title: 'Pricing and projections',
    description: 'Subscription pricing model, unit economics, revenue projections, and the assumptions behind them.',
    icon: 'i-lucide-line-chart',
    href: '/investor/roofly/pricing-financial-summary.html',
  },
]
</script>

<template>
  <main class="min-h-screen" style="background: var(--color-bg);">
    <!-- Hero -->
    <section class="px-6 pt-32 pb-16 md:pt-40 md:pb-24">
      <div class="max-w-5xl mx-auto">
        <p class="reveal eyebrow mb-5">Investor materials</p>
        <h1
          class="reveal text-4xl md:text-6xl lg:text-7xl font-semibold tracking-tight"
          style="color: var(--color-text); letter-spacing: -0.045em; line-height: 1.05;"
        >
          Roofly
          <span class="text-gradient">— investor brief</span>
        </h1>
        <p
          class="reveal mt-6 text-[17px] md:text-[19px] leading-relaxed max-w-2xl"
          style="color: var(--color-text-secondary);"
        >
          A rent-management SaaS for Malaysian landlords. Currently in active development under
          <NuxtLink to="/" class="underline" :style="{ color: 'var(--color-accent)' }">Axel Nova Ventures</NuxtLink>.
          The materials below are the full investor brief — review at your own pace.
        </p>

        <div class="reveal mt-8 flex flex-wrap items-center gap-3">
          <a
            href="/investor/roofly/deck.html"
            target="_blank"
            rel="noopener"
            class="btn-pill btn-pill-accent text-[14px] inline-flex items-center gap-2"
          >
            Open pitch deck
            <UIcon name="i-lucide-arrow-up-right" class="size-4" />
          </a>
          <a
            href="mailto:baihaqie@axelnova.tech?subject=Roofly%20%E2%80%94%20investor%20conversation"
            class="btn-pill btn-pill-ghost text-[14px] inline-flex items-center gap-2"
          >
            <UIcon name="i-lucide-mail" class="size-4" />
            Request a walkthrough
          </a>
        </div>
      </div>
    </section>

    <!-- Materials -->
    <section class="px-6 pb-24 md:pb-32">
      <div class="max-w-5xl mx-auto">
        <div class="reveal mb-10">
          <p class="eyebrow mb-3">Documents</p>
          <h2
            class="text-2xl md:text-3xl font-semibold tracking-tight"
            style="color: var(--color-text);"
          >
            Three documents, one brief
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">
          <a
            v-for="m in materials"
            :key="m.href"
            :href="m.href"
            target="_blank"
            rel="noopener"
            class="reveal group flex flex-col rounded-2xl border p-6 transition-all hover:-translate-y-1"
            :style="{
              borderColor: 'var(--color-border)',
              background: 'var(--color-bg-elevated)',
              boxShadow: 'var(--shadow-sm)',
            }"
          >
            <div
              class="inline-flex items-center justify-center size-10 rounded-xl mb-5"
              :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
            >
              <UIcon :name="m.icon" class="size-5" />
            </div>
            <p class="eyebrow mb-2">{{ m.label }}</p>
            <h3
              class="text-[18px] font-semibold tracking-tight mb-2"
              style="color: var(--color-text);"
            >
              {{ m.title }}
            </h3>
            <p
              class="text-[14px] leading-relaxed flex-1"
              style="color: var(--color-text-secondary);"
            >
              {{ m.description }}
            </p>
            <div
              class="mt-5 inline-flex items-center gap-1.5 text-[13px] font-medium transition-all group-hover:gap-2.5"
              :style="{ color: 'var(--color-accent)' }"
            >
              Open
              <UIcon name="i-lucide-arrow-up-right" class="size-3.5" />
            </div>
          </a>
        </div>
      </div>
    </section>

    <!-- Closing note -->
    <section class="px-6 pb-32">
      <div
        class="reveal max-w-5xl mx-auto rounded-2xl border p-8 md:p-10"
        :style="{
          borderColor: 'var(--color-border)',
          background: 'var(--color-bg-secondary)',
        }"
      >
        <p class="eyebrow mb-3">Private inquiries</p>
        <h2
          class="text-2xl md:text-3xl font-semibold tracking-tight mb-3"
          style="color: var(--color-text); letter-spacing: -0.022em;"
        >
          Prefer a conversation?
        </h2>
        <p
          class="text-[15px] leading-relaxed max-w-2xl mb-6"
          style="color: var(--color-text-secondary);"
        >
          For a 1:1 walkthrough, deeper diligence, or specific questions not covered in the materials — reach out directly. Replies within one business day.
        </p>
        <a
          href="mailto:baihaqie@axelnova.tech?subject=Roofly%20%E2%80%94%20investor%20conversation"
          class="btn-pill btn-pill-primary text-[14px] inline-flex items-center gap-2"
        >
          <UIcon name="i-lucide-mail" class="size-4" />
          baihaqie@axelnova.tech
        </a>
      </div>
    </section>
  </main>
</template>
