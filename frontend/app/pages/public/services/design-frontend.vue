<script setup lang="ts">
definePageMeta({ layout: 'public' })

import SectionHeader from '~/components/shared/SectionHeader.vue'

const siteUrl = 'https://axelnovaventures.com'
const pageUrl = `${siteUrl}/services/design-frontend`
const ogImage = `${siteUrl}/og-image.png`

const seoTitle = 'UI/UX Design Services — Product & Interface Design | Axel Nova Ventures'
const seoDescription = 'UI/UX design for SaaS, fintech, and web products. User research, wireframes, interactive Figma prototypes, and design systems — by a designer who also ships the code.'

useSeoMeta({
  title: seoTitle,
  description: seoDescription,
  ogTitle: seoTitle,
  ogDescription: seoDescription,
  ogImage,
  ogUrl: pageUrl,
  twitterTitle: seoTitle,
  twitterDescription: seoDescription,
  twitterImage: ogImage,
  twitterCard: 'summary_large_image',
})

const faqs = [
  {
    q: 'What deliverables come with a UI/UX engagement?',
    a: 'A Figma file with the full design system (colors, type, components, states), interactive prototypes for every key flow, an accessibility checklist, and a written handover doc for developers.',
  },
  {
    q: 'Do you do user research too?',
    a: 'Yes. Stakeholder interviews, competitor audits, lightweight user interviews, and usability testing on prototypes. Scope depends on the project — I scope research separately from visual design.',
  },
  {
    q: 'Can you redesign an existing product?',
    a: 'Yes. Most redesign projects start with a UX audit of the current product — heuristic review, analytics dive if you have data, and a prioritised list of fixes before redesigning visuals.',
  },
  {
    q: 'Will the developers be able to build what you design?',
    a: 'Yes — because I also code. My Figma files use real Tailwind tokens, real component composition, and realistic states. Developer handover meetings are part of every project.',
  },
]

useHead({
  link: [{ rel: 'canonical', href: pageUrl }],
  script: [
    {
      type: 'application/ld+json',
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'Service',
        serviceType: 'UI/UX Design',
        name: 'UI/UX Design Services',
        description: seoDescription,
        url: pageUrl,
        provider: {
          '@type': 'Organization',
          name: 'Axel Nova Ventures',
          url: siteUrl,
        },
        areaServed: { '@type': 'Country', name: 'Malaysia' },
        audience: { '@type': 'Audience', audienceType: 'SaaS and fintech product teams' },
      }),
    },
    {
      type: 'application/ld+json',
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
          { '@type': 'ListItem', position: 1, name: 'Home', item: siteUrl },
          { '@type': 'ListItem', position: 2, name: 'Services', item: `${siteUrl}/services` },
          { '@type': 'ListItem', position: 3, name: 'Design & Frontend', item: pageUrl },
        ],
      }),
    },
    {
      type: 'application/ld+json',
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: faqs.map(f => ({
          '@type': 'Question',
          name: f.q,
          acceptedAnswer: { '@type': 'Answer', text: f.a },
        })),
      }),
    },
  ],
})

const deliverables = [
  { icon: 'i-lucide-search', title: 'UX research', desc: 'Stakeholder interviews, competitor audits, usability testing — grounded decisions instead of guesses.' },
  { icon: 'i-lucide-layers', title: 'Design system', desc: 'A scalable Figma component library with real tokens — colors, type, spacing, motion, and states.' },
  { icon: 'i-lucide-pen-tool', title: 'High-fidelity UI', desc: 'Pixel-precise screens for web and mobile, with full dark/light support and accessibility baked in.' },
  { icon: 'i-lucide-rocket', title: 'Dev handover', desc: 'Annotated handover, component-to-code mapping, and async support while engineering builds.' },
]

const tools = [
  'Figma', 'Tailwind CSS', 'Framer Motion', 'GSAP', 'Iconify', 'Heroicons', 'Material Symbols',
]

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-5xl mx-auto px-6 pt-24 pb-32">
    <SectionHeader
      eyebrow="UI/UX Design"
      title="Design that ships — because the designer codes too."
      subtitle="Product, interface, and interaction design for SaaS and fintech. From research to design system to dev handover, by one person who's owned both sides."
    />

    <section class="reveal mb-24">
      <h3 class="text-2xl font-semibold tracking-tight mb-6">What's included</h3>
      <div class="grid sm:grid-cols-2 gap-5">
        <div
          v-for="d in deliverables"
          :key="d.title"
          class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }"
        >
          <div
            class="size-10 rounded-xl flex items-center justify-center mb-4"
            :style="{ background: 'var(--color-accent-soft)' }"
          >
            <UIcon :name="d.icon" class="size-5" :style="{ color: 'var(--color-accent)' }" />
          </div>
          <h4 class="text-lg font-semibold tracking-tight mb-2">{{ d.title }}</h4>
          <p class="text-[14px] leading-relaxed" :style="{ color: 'var(--color-text-secondary)' }">
            {{ d.desc }}
          </p>
        </div>
      </div>
    </section>

    <section class="reveal mb-24">
      <h3 class="text-2xl font-semibold tracking-tight mb-6">Tools</h3>
      <div class="flex flex-wrap gap-2">
        <span
          v-for="t in tools"
          :key="t"
          class="text-[13px] px-3 py-1.5 rounded-full border"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
        >{{ t }}</span>
      </div>
    </section>

    <section class="reveal mb-24">
      <h3 class="text-2xl font-semibold tracking-tight mb-6">FAQ</h3>
      <div class="space-y-4">
        <details
          v-for="f in faqs"
          :key="f.q"
          class="rounded-2xl border p-6 group"
          :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }"
        >
          <summary class="text-[15px] font-semibold cursor-pointer flex items-center justify-between">
            {{ f.q }}
            <UIcon name="i-lucide-chevron-down" class="size-4 transition-transform group-open:rotate-180" />
          </summary>
          <p class="mt-3 text-[14px] leading-relaxed" :style="{ color: 'var(--color-text-secondary)' }">
            {{ f.a }}
          </p>
        </details>
      </div>
    </section>

    <section class="reveal">
      <div
        class="rounded-2xl border p-10 text-center"
        :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }"
      >
        <h3 class="text-3xl font-semibold tracking-tight mb-3">Have a design problem?</h3>
        <p class="text-[15px] mb-6 max-w-xl mx-auto" :style="{ color: 'var(--color-text-secondary)' }">
          Tell me where it hurts and I'll come back with an honest assessment and quote.
        </p>
        <div class="flex items-center justify-center gap-3">
          <NuxtLink to="/quote" class="btn-pill btn-pill-accent">Get a quote</NuxtLink>
          <NuxtLink to="/services" class="btn-pill btn-pill-primary">See all services</NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>
