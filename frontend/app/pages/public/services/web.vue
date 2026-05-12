<script setup lang="ts">
definePageMeta({ layout: 'public' })

import SectionHeader from '~/components/shared/SectionHeader.vue'

const siteUrl = 'https://axelnovaventures.com'
const pageUrl = `${siteUrl}/services/web`
const ogImage = `${siteUrl}/og-image.png`

const seoTitle = 'Web Development Services — Vue, Nuxt & Laravel | Axel Nova Ventures'
const seoDescription = 'Custom web development for startups and SMEs in Malaysia. Vue, Nuxt, Laravel, and Tailwind. Production-grade builds with clean code, SSR-ready performance, and ongoing support.'

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
    q: 'What stack do you build with?',
    a: 'Vue 3 + Nuxt 4 on the frontend, Laravel 11 on the backend, MySQL or PostgreSQL for data, deployed on a VPS or AWS. Tailwind CSS for styling. Everything containerised with Docker.',
  },
  {
    q: 'How long does a typical web project take?',
    a: 'A landing site takes 1–2 weeks. A marketing website with CMS takes 3–4 weeks. A custom web app with auth, dashboard, and integrations takes 6–10 weeks. Timeline is agreed in writing before work begins.',
  },
  {
    q: 'Do you handle hosting and deployment?',
    a: 'Yes. I set up the production server (VPS, AWS, or Cloudflare), wire up CI/CD via GitHub Actions, configure SSL, and hand over the deploy keys. You own the infrastructure.',
  },
  {
    q: 'Can you work with existing teams?',
    a: 'Yes. I have experience embedding into fintech and SaaS teams. PR-based workflow, code review, documentation — same standards as your in-house engineers.',
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
        serviceType: 'Web Development',
        name: 'Web Development Services',
        description: seoDescription,
        url: pageUrl,
        provider: {
          '@type': 'Organization',
          name: 'Axel Nova Ventures',
          url: siteUrl,
        },
        areaServed: { '@type': 'Country', name: 'Malaysia' },
        audience: { '@type': 'Audience', audienceType: 'Startups and SMEs' },
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
          { '@type': 'ListItem', position: 3, name: 'Web Presence', item: pageUrl },
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
  { icon: 'i-lucide-layout', title: 'Marketing sites', desc: 'High-performance landing pages and multi-page sites with CMS integration.' },
  { icon: 'i-lucide-code', title: 'Custom web apps', desc: 'Auth, dashboards, billing, integrations — full-stack builds end to end.' },
  { icon: 'i-lucide-cloud', title: 'API + backend', desc: 'REST or GraphQL APIs in Laravel, with queues, jobs, and a clean admin layer.' },
  { icon: 'i-lucide-rocket', title: 'Deploy + handover', desc: 'CI/CD, SSL, monitoring, documentation. You own everything when we finish.' },
]

const stack = [
  'Vue 3', 'Nuxt 4', 'TypeScript', 'Tailwind CSS', 'Laravel 11', 'MySQL', 'Docker', 'GitHub Actions', 'AWS', 'Cloudflare',
]

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-5xl mx-auto px-6 pt-24 pb-32">
    <SectionHeader
      eyebrow="Web Development"
      title="Web apps and sites that work — and keep working."
      subtitle="Production-grade builds for startups and SMEs in Malaysia. Vue, Nuxt, and Laravel. Clean code, real performance, and full ownership on day one."
    />

    <section class="reveal mb-24">
      <h3 class="text-2xl font-semibold tracking-tight mb-6">What you get</h3>
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
      <h3 class="text-2xl font-semibold tracking-tight mb-6">Stack</h3>
      <div class="flex flex-wrap gap-2">
        <span
          v-for="s in stack"
          :key="s"
          class="text-[13px] px-3 py-1.5 rounded-full border"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
        >{{ s }}</span>
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
        <h3 class="text-3xl font-semibold tracking-tight mb-3">Ready to start?</h3>
        <p class="text-[15px] mb-6 max-w-xl mx-auto" :style="{ color: 'var(--color-text-secondary)' }">
          Get a transparent quote based on your scope — no sales calls required.
        </p>
        <div class="flex items-center justify-center gap-3">
          <NuxtLink to="/quote" class="btn-pill btn-pill-accent">Get a quote</NuxtLink>
          <NuxtLink to="/services" class="btn-pill btn-pill-primary">See all services</NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>
