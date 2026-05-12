<script setup lang="ts">
definePageMeta({ layout: 'public' })

import SectionHeader from '~/components/shared/SectionHeader.vue'
import { useApiBase } from '~/composables/useApiBase'

interface ApiPackage {
  id: number
  slug: string
  name: string
  tagline: string
  price_min_myr: string | number
  price_max_myr: string | number | null
  unit: string
  duration_text: string
  features: string[]
  featured: boolean
}

interface ApiCategory {
  id: number
  slug: string
  name: string
  description: string
  icon: string
  packages: ApiPackage[]
}

interface FAQ { q: string, a: string }
interface Deliverable { icon: string, title: string, desc: string }
interface Enrichment {
  seoTitle?: string
  seoDescription?: string
  heroTitle?: string
  heroSubtitle?: string
  deliverablesTitle?: string
  deliverables?: Deliverable[]
  stackTitle?: string
  stack?: string[]
  faqs?: FAQ[]
  serviceType?: string
  audience?: string
}

// Universal FAQs shown on every category page (combined with category-specific
// FAQs from the enrichment map below when those exist).
const generalFaqs: FAQ[] = [
  {
    q: 'How do payments work?',
    a: '50% deposit to start, 50% on delivery. For projects over RM10,000 I split into milestone-based payments. Invoices via Stripe, FPX, or bank transfer — your call.',
  },
  {
    q: 'Do you work with international clients?',
    a: 'Yes. I work in MYR for Malaysian clients and USD / SGD / GBP for everyone else. Project management runs in your timezone — I\'m flexible on meeting hours within reason.',
  },
  {
    q: 'What\'s your typical turnaround time?',
    a: 'Most projects start within 1–2 weeks of contract signing. Total timeline depends on scope — see the duration on each package, or get an estimate via the quote tool.',
  },
  {
    q: 'Can you sign an NDA?',
    a: 'Yes. I\'ll sign your NDA before any sensitive material is shared. If you don\'t have one, I can provide a mutual NDA template.',
  },
  {
    q: 'What happens if I need changes after delivery?',
    a: 'Each package includes a set number of revision rounds — listed on every package card. Additional changes are quoted hourly at RM150/hr. For ongoing work, a Retainer plan is more cost-effective.',
  },
  {
    q: 'Do you offer post-launch support?',
    a: 'Yes — every project includes 14 days of bug-fix support after handover at no extra cost. After that, you can either go on retainer or pay per-issue.',
  },
  {
    q: 'Who owns the code and design files?',
    a: 'You do, fully. I deliver source code, Figma files, and any third-party account access on project completion. No license keys, no rent.',
  },
  {
    q: 'How do we start?',
    a: 'Send a quote request via the form, or message me directly via WhatsApp / email. I usually reply within a working day with next steps.',
  },
]

// Per-slug SEO enrichment. Add an entry to give a category a richer landing page.
// Categories without an entry still render — they just use the API name/description
// and the universal generalFaqs above.
const enrichmentBySlug: Record<string, Enrichment> = {
  'web-presence': {
    seoTitle: 'Web Development Services — Vue, Nuxt & Laravel | Axel Nova Ventures',
    seoDescription: 'Custom web development for startups and SMEs in Malaysia. Vue, Nuxt, Laravel, and Tailwind. Production-grade builds with clean code, SSR-ready performance, and ongoing support.',
    heroTitle: 'Web apps and sites that work — and keep working.',
    heroSubtitle: 'Production-grade builds for startups and SMEs in Malaysia. Vue, Nuxt, and Laravel. Clean code, real performance, and full ownership on day one.',
    deliverablesTitle: 'What you get',
    deliverables: [
      { icon: 'i-lucide-layout', title: 'Marketing sites', desc: 'High-performance landing pages and multi-page sites with CMS integration.' },
      { icon: 'i-lucide-code', title: 'Custom web apps', desc: 'Auth, dashboards, billing, integrations — full-stack builds end to end.' },
      { icon: 'i-lucide-cloud', title: 'API + backend', desc: 'REST or GraphQL APIs in Laravel, with queues, jobs, and a clean admin layer.' },
      { icon: 'i-lucide-rocket', title: 'Deploy + handover', desc: 'CI/CD, SSL, monitoring, documentation. You own everything when we finish.' },
    ],
    stackTitle: 'Stack',
    stack: ['Vue 3', 'Nuxt 4', 'TypeScript', 'Tailwind CSS', 'Laravel 11', 'MySQL', 'Docker', 'GitHub Actions', 'AWS', 'Cloudflare'],
    faqs: [
      { q: 'What stack do you build with?', a: 'Vue 3 + Nuxt 4 on the frontend, Laravel 11 on the backend, MySQL or PostgreSQL for data, deployed on a VPS or AWS. Tailwind CSS for styling. Everything containerised with Docker.' },
      { q: 'How long does a typical web project take?', a: 'A landing site takes 1–2 weeks. A marketing website with CMS takes 3–4 weeks. A custom web app with auth, dashboard, and integrations takes 6–10 weeks. Timeline is agreed in writing before work begins.' },
      { q: 'Do you handle hosting and deployment?', a: 'Yes. I set up the production server (VPS, AWS, or Cloudflare), wire up CI/CD via GitHub Actions, configure SSL, and hand over the deploy keys. You own the infrastructure.' },
      { q: 'Can you work with existing teams?', a: 'Yes. I have experience embedding into fintech and SaaS teams. PR-based workflow, code review, documentation — same standards as your in-house engineers.' },
    ],
    serviceType: 'Web Development',
    audience: 'Startups and SMEs',
  },
  'admin-portal': {
    seoTitle: 'Admin Portal & Dashboard Development | Axel Nova Ventures',
    seoDescription: 'Custom admin panels, SaaS dashboards, and internal tools. Role-based access, real-time data, and clean UX. Built in Vue + Nuxt with Laravel APIs.',
    heroTitle: 'Admin dashboards your team will actually use.',
    heroSubtitle: 'Custom admin panels, internal tools, and SaaS dashboards. Built for operators who measure productivity in clicks saved.',
    deliverablesTitle: 'Core capabilities',
    deliverables: [
      { icon: 'i-lucide-users', title: 'Role-based access', desc: 'Granular permissions per user, team, or resource. Built on Laravel Sanctum with audit logging.' },
      { icon: 'i-lucide-database', title: 'Data tables that scale', desc: 'Server-side pagination, filters, sort, bulk actions, and CSV exports — built to handle thousands of rows.' },
      { icon: 'i-lucide-line-chart', title: 'Real-time dashboards', desc: 'Live KPIs, charts, and alerts. WebSocket or polling — whichever fits your data volume.' },
      { icon: 'i-lucide-workflow', title: 'Workflow automation', desc: 'Approvals, notifications, queued background jobs. Wire your business logic into a clean operator UI.' },
    ],
    stackTitle: 'Stack',
    stack: ['Vue 3', 'Nuxt 4', '@nuxt/ui', 'Tailwind CSS', 'Laravel 11', 'Sanctum', 'MySQL', 'Redis', 'WebSockets', 'Chart.js'],
    faqs: [
      { q: 'What kind of admin dashboards do you build?', a: 'SaaS admin panels, internal ops tools, fintech back-office consoles, and customer portals. Anything that needs role-based access, structured data tables, charts, and CRUD workflows.' },
      { q: 'How do you handle authentication and roles?', a: 'Laravel Sanctum for token-based auth, with role and permission management via spatie/laravel-permission or a custom layer. SSO and 2FA available on request.' },
      { q: 'Can you integrate with our existing API?', a: 'Yes. I can build the frontend portal against your existing REST or GraphQL API, or extend your current backend. Auth handoff, error handling, and rate limiting are all part of the scope.' },
      { q: 'Do you provide analytics and reporting?', a: 'Yes — dashboards with charts (line, bar, donut, sparklines) via Chart.js or ECharts, plus CSV/PDF export, scheduled reports, and audit logs.' },
    ],
    serviceType: 'Admin Portal Development',
    audience: 'SaaS, Fintech, and Internal Tools teams',
  },
  'ui-ux-frontend': {
    seoTitle: 'UI/UX Design Services — Product & Interface Design | Axel Nova Ventures',
    seoDescription: 'UI/UX design for SaaS, fintech, and web products. User research, wireframes, interactive Figma prototypes, and design systems — by a designer who also ships the code.',
    heroTitle: 'Design that ships — because the designer codes too.',
    heroSubtitle: 'Product, interface, and interaction design for SaaS and fintech. From research to design system to dev handover, by one person who\'s owned both sides.',
    deliverablesTitle: 'What\'s included',
    deliverables: [
      { icon: 'i-lucide-search', title: 'UX research', desc: 'Stakeholder interviews, competitor audits, usability testing — grounded decisions instead of guesses.' },
      { icon: 'i-lucide-layers', title: 'Design system', desc: 'A scalable Figma component library with real tokens — colors, type, spacing, motion, and states.' },
      { icon: 'i-lucide-pen-tool', title: 'High-fidelity UI', desc: 'Pixel-precise screens for web and mobile, with full dark/light support and accessibility baked in.' },
      { icon: 'i-lucide-rocket', title: 'Dev handover', desc: 'Annotated handover, component-to-code mapping, and async support while engineering builds.' },
    ],
    stackTitle: 'Tools',
    stack: ['Figma', 'Tailwind CSS', 'Framer Motion', 'GSAP', 'Iconify', 'Heroicons', 'Material Symbols'],
    faqs: [
      { q: 'What deliverables come with a UI/UX engagement?', a: 'A Figma file with the full design system (colors, type, components, states), interactive prototypes for every key flow, an accessibility checklist, and a written handover doc for developers.' },
      { q: 'Do you do user research too?', a: 'Yes. Stakeholder interviews, competitor audits, lightweight user interviews, and usability testing on prototypes. Scope depends on the project — I scope research separately from visual design.' },
      { q: 'Can you redesign an existing product?', a: 'Yes. Most redesign projects start with a UX audit of the current product — heuristic review, analytics dive if you have data, and a prioritised list of fixes before redesigning visuals.' },
      { q: 'Will the developers be able to build what you design?', a: 'Yes — because I also code. My Figma files use real Tailwind tokens, real component composition, and realistic states. Developer handover meetings are part of every project.' },
    ],
    serviceType: 'UI/UX Design',
    audience: 'SaaS and fintech product teams',
  },
  'digital-marketing': {
    seoTitle: 'Digital Marketing & Creative Design — Posters, Campaigns & Brand Kits | Axel Nova Ventures',
    seoDescription: 'Premium social campaigns, e-flyers, event collateral, and brand refresh kits for Malaysian SMEs. EN + BM copywriting. Festive season ready. Print + digital delivered.',
    heroTitle: 'Creative that looks premium on every channel.',
    heroSubtitle: 'From single social posts to full festive campaigns and brand refresh kits. Designed for Malaysian SMEs that want to look serious — without paying agency retainer prices.',
    deliverablesTitle: 'What you get',
    deliverables: [
      { icon: 'i-lucide-megaphone', title: 'Social campaigns', desc: 'Hero creative + adapted assets for FB, IG, WhatsApp, and email. EN + BM copywriting included.' },
      { icon: 'i-lucide-image', title: 'Event collateral', desc: 'E-backdrops, buntings, standees, badges, and teasers — print-ready files with vendor specs.' },
      { icon: 'i-lucide-palette', title: 'Brand refresh kits', desc: 'Logo polish, colour and type system, business cards, email signatures, and reusable social templates.' },
      { icon: 'i-lucide-calendar-days', title: 'Monthly content retainer', desc: 'Consistent design output every month — social posts, e-flyers, and campaign concepts with planning.' },
    ],
    stackTitle: 'Tools',
    stack: ['Figma', 'Adobe Illustrator', 'Adobe Photoshop', 'Canva Pro', 'CapCut', 'Print-ready exports'],
    faqs: [
      { q: 'Do you write in Bahasa Malaysia and English?', a: 'Yes. Every campaign includes EN + BM copywriting by default. For trilingual campaigns (BM / EN / 中文), I bring in a native Mandarin copywriter — costed separately and transparently.' },
      { q: 'Can you handle festive campaigns (Raya, CNY, Deepavali)?', a: 'Yes — festive launches are my busiest period. Book 3–4 weeks ahead. I deliver a hero design plus 5+ adapted assets per festive campaign as standard.' },
      { q: 'Do I get print-ready files?', a: 'Yes. Every design comes with print-ready PDF / CMYK files plus digital exports (RGB JPG / PNG). Vendor specs included so your printer or e-backdrop supplier can run it directly.' },
      { q: 'What if I need ongoing content every month?', a: 'That\'s the Monthly Content Retainer — 8–12 social posts, 2 e-flyers, and 1 campaign concept per month, with content calendar planning. Cheaper than in-house, more reliable than freelancers.' },
    ],
    serviceType: 'Digital Marketing & Creative Design',
    audience: 'SMEs, hotels, clinics, salons, and growing Malaysian brands',
  },
  'booking-portal': {
    seoTitle: 'Online Booking System — Hotels, Salons & Homestays Malaysia | Axel Nova Ventures',
    seoDescription: 'Custom online booking and customer portals for Malaysian hotels, homestays, clinics, salons, and tuition centres. Calendar engine, Billplz / ToyyibPay payment, WhatsApp confirmations.',
    heroTitle: 'Stop managing bookings in WhatsApp.',
    heroSubtitle: 'Booking systems built for Malaysian homestays, salons, clinics, and tuition centres. Customers book 24/7, pay online, and get auto-confirmations — while you sleep.',
    deliverablesTitle: 'Core capabilities',
    deliverables: [
      { icon: 'i-lucide-calendar-check', title: 'Smart booking engine', desc: 'Calendar with availability, lead time, blackout dates, and double-booking prevention. Mobile-first by default.' },
      { icon: 'i-lucide-credit-card', title: 'Malaysian payment gateways', desc: 'Billplz, ToyyibPay, or Stripe for FPX, e-wallets (GrabPay, TNG, Boost), and cards. Refund flow included.' },
      { icon: 'i-lucide-message-circle', title: 'Auto-notifications', desc: 'Booking confirmations via email + WhatsApp + SMS. Reminders before appointment. Admin gets a copy of everything.' },
      { icon: 'i-lucide-users', title: 'Customer self-serve', desc: 'Customers manage their own bookings — reschedule, cancel, view history. Less admin work, less WhatsApp triage.' },
    ],
    stackTitle: 'Stack',
    stack: ['Vue 3', 'Nuxt 4', 'Laravel 11', 'Billplz', 'ToyyibPay', 'Stripe', 'WhatsApp Business API', 'MySQL', 'Tailwind CSS'],
    faqs: [
      { q: 'Which payment gateways do you integrate?', a: 'Billplz, ToyyibPay, and Stripe for Malaysian businesses. That covers FPX (online banking), e-wallets (GrabPay, TNG, Boost, ShopeePay), and credit/debit cards. International cards too if you serve foreign customers.' },
      { q: 'Can it handle multiple rooms / staff / services?', a: 'Yes. Booking Pro and Custom plans support multi-resource scheduling — multiple rooms, staff, treatment rooms, classrooms, or service types in parallel. Each with its own availability and pricing rules.' },
      { q: 'Will I get WhatsApp notifications for new bookings?', a: 'Yes. Customer and admin both receive WhatsApp confirmations on every booking, plus reminders before the appointment. SMS and email backup channels included.' },
      { q: 'Can it integrate with my existing PMS or POS?', a: 'Yes. I\'ve integrated with hotel PMS, salon POS, and clinic management systems via REST API or webhook. The Custom Booking System plan covers this — scope agreed on call.' },
    ],
    serviceType: 'Booking & Customer Portal Development',
    audience: 'Malaysian hotels, homestays, clinics, salons, and tuition centres',
  },
  'ecommerce': {
    seoTitle: 'E-commerce Development — DTC Storefronts for Malaysian Brands | Axel Nova Ventures',
    seoDescription: 'Direct-to-consumer storefronts with FPX, e-wallets (GrabPay, TNG, Boost), and card checkout via Billplz / ToyyibPay / Stripe. Own your customer, ditch the marketplace margins.',
    heroTitle: 'Own your storefront. Own your customer.',
    heroSubtitle: 'Direct-to-consumer e-commerce for Malaysian brands tired of marketplace fees and platform restrictions. Your domain, your checkout, your customer data — start to finish.',
    deliverablesTitle: 'What you get',
    deliverables: [
      { icon: 'i-lucide-shopping-bag', title: 'Branded storefront', desc: 'Mobile-first product catalogue with variants, bundles, and category navigation. Fast, SEO-friendly, and brand-aligned.' },
      { icon: 'i-lucide-banknote', title: 'Malaysian checkout', desc: 'FPX, GrabPay, TNG, Boost, ShopeePay, and card payments via Billplz, ToyyibPay, or Stripe. One-click checkout for returning customers.' },
      { icon: 'i-lucide-package', title: 'Inventory & fulfilment', desc: 'Stock tracking, low-stock alerts, order management, and shipping label generation. Sync with EasyParcel / Pos Laju if needed.' },
      { icon: 'i-lucide-trending-up', title: 'Sales analytics', desc: 'Revenue dashboard, abandoned cart recovery, discount codes, and customer LTV tracking. Decide on real data, not gut feel.' },
    ],
    stackTitle: 'Stack',
    stack: ['Vue 3', 'Nuxt 4', 'Laravel 11', 'Billplz', 'ToyyibPay', 'Stripe', 'EasyParcel', 'MySQL', 'Redis', 'Tailwind CSS'],
    faqs: [
      { q: 'Can I accept FPX and Malaysian e-wallets?', a: 'Yes — FPX, GrabPay, TNG, Boost, and ShopeePay via Billplz or ToyyibPay. International cards via Stripe. Every storefront supports the full Malaysian payment stack from day one.' },
      { q: 'Is this better than selling on Shopee / Lazada?', a: 'Different, not better-or-worse. Marketplaces give you traffic but take 5–15% per transaction and own the customer relationship. Your own storefront builds brand, owns customer data, and has no marketplace fees. Most successful brands run both in parallel.' },
      { q: 'Can you integrate with my existing inventory or accounting system?', a: 'Yes. I\'ve integrated with Xero, AutoCount, SQL Account, and custom ERPs. Storefront Pro and Custom plans cover real-time stock sync, automatic invoicing, and accounting export.' },
      { q: 'What about subscriptions or B2B pricing?', a: 'The Custom Commerce plan covers subscriptions, recurring billing, B2B tiers, bulk pricing, and net-30 terms. Scope agreed on call — these are common for skincare, supplement, and wholesale brands.' },
    ],
    serviceType: 'E-commerce & Payment Flow Development',
    audience: 'Malaysian DTC brands, retail, and B2B sellers',
  },
}

const route = useRoute()
const slug = computed(() => String(route.params.slug))

const siteUrl = 'https://axelnovaventures.com'
const ogImage = `${siteUrl}/og-image.png`

const { data: apiResponse } = await useFetch<{ data: ApiCategory[] }>(
  `${useApiBase()}/api/v1/services`,
  { key: 'public-services-detail' },
)

const category = computed(() =>
  apiResponse.value?.data?.find(c => c.slug === slug.value) ?? null,
)

if (!category.value) {
  throw createError({ statusCode: 404, statusMessage: 'Service not found', fatal: true })
}

const enrichment = computed<Enrichment>(() => enrichmentBySlug[slug.value] ?? {})

const pageUrl = computed(() => `${siteUrl}/services/${slug.value}`)

const seoTitle = computed(
  () => enrichment.value.seoTitle ?? `${category.value!.name} | Axel Nova Ventures`,
)
const seoDescription = computed(
  () => enrichment.value.seoDescription ?? category.value!.description,
)

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

// Category-specific FAQs first (more relevant), then the universal set.
const allFaqs = computed<FAQ[]>(() => [
  ...(enrichment.value.faqs ?? []),
  ...generalFaqs,
])

const jsonLdScripts = computed(() => {
  const base = [
    {
      type: 'application/ld+json',
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'Service',
        serviceType: enrichment.value.serviceType ?? category.value!.name,
        name: seoTitle.value,
        description: seoDescription.value,
        url: pageUrl.value,
        provider: { '@type': 'Organization', name: 'Axel Nova Ventures', url: siteUrl },
        areaServed: { '@type': 'Country', name: 'Malaysia' },
        ...(enrichment.value.audience && {
          audience: { '@type': 'Audience', audienceType: enrichment.value.audience },
        }),
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
          { '@type': 'ListItem', position: 3, name: category.value!.name, item: pageUrl.value },
        ],
      }),
    },
  ]
  if (allFaqs.value.length) {
    base.push({
      type: 'application/ld+json',
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: allFaqs.value.map(f => ({
          '@type': 'Question',
          name: f.q,
          acceptedAnswer: { '@type': 'Answer', text: f.a },
        })),
      }),
    })
  }
  return base
})

useHead({
  link: [{ rel: 'canonical', href: pageUrl }],
  script: jsonLdScripts,
})

useScrollReveal('.reveal')
</script>

<template>
  <div v-if="category" class="max-w-5xl mx-auto px-6 pt-20 pb-32">
    <NuxtLink
      to="/services"
      class="text-[13px] inline-flex items-center gap-1.5 mb-10 transition-colors hover:opacity-80"
      style="color: var(--color-text-secondary);"
    >
      <span aria-hidden>←</span> Back to services
    </NuxtLink>

    <SectionHeader
      :eyebrow="category.name"
      :title="enrichment.heroTitle ?? `${category.name}.`"
      :subtitle="enrichment.heroSubtitle ?? category.description"
    />

    <section v-if="enrichment.deliverables?.length" class="reveal mb-24">
      <h3 class="text-2xl font-semibold tracking-tight mb-6">
        {{ enrichment.deliverablesTitle ?? 'What you get' }}
      </h3>
      <div class="grid sm:grid-cols-2 gap-5">
        <div
          v-for="d in enrichment.deliverables"
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
          <h4 class="text-lg font-semibold tracking-tight mb-2">
            {{ d.title }}
          </h4>
          <p class="text-[14px] leading-relaxed" :style="{ color: 'var(--color-text-secondary)' }">
            {{ d.desc }}
          </p>
        </div>
      </div>
    </section>

    <section v-if="enrichment.stack?.length" class="reveal mb-24">
      <h3 class="text-2xl font-semibold tracking-tight mb-6">
        {{ enrichment.stackTitle ?? 'Stack' }}
      </h3>
      <div class="flex flex-wrap gap-2">
        <span
          v-for="s in enrichment.stack"
          :key="s"
          class="text-[13px] px-3 py-1.5 rounded-full border"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
        >{{ s }}</span>
      </div>
    </section>

    <section v-if="category.packages.length" class="reveal mb-24">
      <h3 class="text-2xl font-semibold tracking-tight mb-8">Packages</h3>
      <div class="grid md:grid-cols-3 gap-5">
        <div
          v-for="pkg in category.packages"
          :key="pkg.id"
          class="relative rounded-2xl border p-6 flex flex-col"
          :style="{
            background: 'var(--color-bg-secondary)',
            borderColor: pkg.featured ? 'var(--color-accent)' : 'var(--color-border)',
            borderWidth: pkg.featured ? '2px' : '1px',
          }"
        >
          <span
            v-if="pkg.featured"
            class="absolute -top-3 left-1/2 -translate-x-1/2 text-[11px] font-medium px-3 py-1 rounded-full"
            style="background: var(--color-accent); color: white;"
          >
            Most popular
          </span>

          <h4 class="text-lg font-semibold tracking-tight mb-1">
            {{ pkg.name }}
          </h4>
          <p class="text-[13px] mb-4" :style="{ color: 'var(--color-text-secondary)' }">
            {{ pkg.tagline }}
          </p>
          <p class="text-[12px] mb-4" :style="{ color: 'var(--color-text-secondary)' }">
            From RM {{ Number(pkg.price_min_myr).toLocaleString() }}
            <span v-if="pkg.price_max_myr"> – RM {{ Number(pkg.price_max_myr).toLocaleString() }}</span>
            <span v-else>+</span> · {{ pkg.duration_text }}
          </p>
          <ul class="space-y-1.5 text-[13px] flex-1" :style="{ color: 'var(--color-text)' }">
            <li v-for="f in pkg.features.slice(0, 4)" :key="f" class="flex items-start gap-2">
              <UIcon name="i-fluent-checkmark-24-regular" class="size-3.5 mt-1 shrink-0" :style="{ color: 'var(--color-accent)' }" />
              <span>{{ f }}</span>
            </li>
          </ul>
        </div>
      </div>
    </section>

    <section v-if="allFaqs.length" class="reveal mb-24">
      <h3 class="text-2xl font-semibold tracking-tight mb-6">FAQ</h3>
      <div class="space-y-4">
        <details
          v-for="f in allFaqs"
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
        <h3 class="text-3xl font-semibold tracking-tight mb-3">
          Ready to start?
        </h3>
        <p class="text-[15px] mb-6 max-w-xl mx-auto" :style="{ color: 'var(--color-text-secondary)' }">
          Get a transparent quote based on your scope — no sales calls required.
        </p>
        <div class="flex items-center justify-center gap-3">
          <NuxtLink :to="`/quote?service=${slug}`" class="btn-pill btn-pill-accent">
            Get a quote
          </NuxtLink>
          <NuxtLink to="/services" class="btn-pill btn-pill-primary">
            See all services
          </NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>
