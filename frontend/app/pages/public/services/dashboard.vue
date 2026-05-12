<script setup lang="ts">
definePageMeta({ layout: 'public' })

import SectionHeader from '~/components/shared/SectionHeader.vue'

const siteUrl = 'https://axelnovaventures.com'
const pageUrl = `${siteUrl}/services/dashboard`
const ogImage = `${siteUrl}/og-image.png`

const seoTitle = 'Admin Portal & Dashboard Development | Axel Nova Ventures'
const seoDescription = 'Custom admin panels, SaaS dashboards, and internal tools. Role-based access, real-time data, and clean UX. Built in Vue + Nuxt with Laravel APIs.'

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
    q: 'What kind of admin dashboards do you build?',
    a: 'SaaS admin panels, internal ops tools, fintech back-office consoles, and customer portals. Anything that needs role-based access, structured data tables, charts, and CRUD workflows.',
  },
  {
    q: 'How do you handle authentication and roles?',
    a: 'Laravel Sanctum for token-based auth, with role and permission management via spatie/laravel-permission or a custom layer. SSO and 2FA available on request.',
  },
  {
    q: 'Can you integrate with our existing API?',
    a: 'Yes. I can build the frontend portal against your existing REST or GraphQL API, or extend your current backend. Auth handoff, error handling, and rate limiting are all part of the scope.',
  },
  {
    q: 'Do you provide analytics and reporting?',
    a: 'Yes — dashboards with charts (line, bar, donut, sparklines) via Chart.js or ECharts, plus CSV/PDF export, scheduled reports, and audit logs.',
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
        serviceType: 'Admin Portal Development',
        name: 'Admin Portal & Dashboard Development',
        description: seoDescription,
        url: pageUrl,
        provider: {
          '@type': 'Organization',
          name: 'Axel Nova Ventures',
          url: siteUrl,
        },
        areaServed: { '@type': 'Country', name: 'Malaysia' },
        audience: { '@type': 'Audience', audienceType: 'SaaS, Fintech, and Internal Tools teams' },
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
          { '@type': 'ListItem', position: 3, name: 'Dashboard & Portal', item: pageUrl },
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

const features = [
  { icon: 'i-lucide-users', title: 'Role-based access', desc: 'Granular permissions per user, team, or resource. Built on Laravel Sanctum with audit logging.' },
  { icon: 'i-lucide-database', title: 'Data tables that scale', desc: 'Server-side pagination, filters, sort, bulk actions, and CSV exports — built to handle thousands of rows.' },
  { icon: 'i-lucide-line-chart', title: 'Real-time dashboards', desc: 'Live KPIs, charts, and alerts. WebSocket or polling — whichever fits your data volume.' },
  { icon: 'i-lucide-workflow', title: 'Workflow automation', desc: 'Approvals, notifications, queued background jobs. Wire your business logic into a clean operator UI.' },
]

const stack = [
  'Vue 3', 'Nuxt 4', '@nuxt/ui', 'Tailwind CSS', 'Laravel 11', 'Sanctum', 'MySQL', 'Redis', 'WebSockets', 'Chart.js',
]

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-5xl mx-auto px-6 pt-24 pb-32">
    <SectionHeader
      eyebrow="Admin Portal"
      title="Admin dashboards your team will actually use."
      subtitle="Custom admin panels, internal tools, and SaaS dashboards. Built for operators who measure productivity in clicks saved."
    />

    <section class="reveal mb-24">
      <h3 class="text-2xl font-semibold tracking-tight mb-6">Core capabilities</h3>
      <div class="grid sm:grid-cols-2 gap-5">
        <div
          v-for="f in features"
          :key="f.title"
          class="rounded-2xl border p-6"
          :style="{ background: 'var(--color-bg-secondary)', borderColor: 'var(--color-border)' }"
        >
          <div
            class="size-10 rounded-xl flex items-center justify-center mb-4"
            :style="{ background: 'var(--color-accent-soft)' }"
          >
            <UIcon :name="f.icon" class="size-5" :style="{ color: 'var(--color-accent)' }" />
          </div>
          <h4 class="text-lg font-semibold tracking-tight mb-2">{{ f.title }}</h4>
          <p class="text-[14px] leading-relaxed" :style="{ color: 'var(--color-text-secondary)' }">
            {{ f.desc }}
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
        <h3 class="text-3xl font-semibold tracking-tight mb-3">Have a portal in mind?</h3>
        <p class="text-[15px] mb-6 max-w-xl mx-auto" :style="{ color: 'var(--color-text-secondary)' }">
          Share your scope and I'll come back with a transparent quote — usually within a working day.
        </p>
        <div class="flex items-center justify-center gap-3">
          <NuxtLink to="/quote" class="btn-pill btn-pill-accent">Get a quote</NuxtLink>
          <NuxtLink to="/services" class="btn-pill btn-pill-primary">See all services</NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>
