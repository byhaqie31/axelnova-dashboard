<script setup lang="ts">
definePageMeta({ layout: 'public' })

import SectionHeader from '~/components/shared/SectionHeader.vue'
import PriceTag from '~/components/shared/primitives/PriceTag.vue'
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
  /** The page H1 — a plain service name ("Website development for Malaysian businesses."). Falls back to the admin category name. */
  h1?: string
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
    seoTitle: 'Website Development in Malaysia | Axel Nova Ventures',
    seoDescription: 'Custom website and web app development for Malaysian startups and SMEs. Fast, mobile-first builds you fully own, with SEO basics and ongoing support.',
    h1: 'Website development for Malaysian businesses.',
    heroSubtitle: 'From a landing page to a larger website or web app, I build around what your customers need to find and do. The scope, integrations and handover are agreed before development starts.',
    deliverablesTitle: 'What you get',
    deliverables: [
      { icon: 'i-lucide-layout', title: 'Marketing sites', desc: 'Single-page and multi-page sites designed around your brand, mobile-first and fast-loading.' },
      { icon: 'i-lucide-puzzle', title: 'Add-ons when you need them', desc: 'A CMS, booking flow, extra pages and extra languages are optional, quoted with your package.' },
      { icon: 'i-lucide-search', title: 'SEO foundations', desc: 'Meta tags, Open Graph, sitemap and optimised images on every site.' },
      { icon: 'i-lucide-rocket', title: 'Deploy + handover', desc: 'Deployment with SSL and your domain connected, plus a handover walkthrough. You own everything when we finish.' },
    ],
    stackTitle: 'Stack',
    stack: ['Vue 3', 'Nuxt 4', 'TypeScript', 'Tailwind CSS', 'Laravel 11', 'MySQL', 'Docker', 'GitHub Actions', 'AWS', 'Cloudflare'],
    faqs: [
      { q: 'What stack do you build with?', a: 'Vue 3 + Nuxt 4 on the frontend, Laravel 11 on the backend, MySQL or PostgreSQL for data, deployed on a VPS or AWS. Tailwind CSS for styling. Everything containerised with Docker.' },
      { q: 'How long does a typical web project take?', a: 'A Starter Site takes 1–2 weeks, a Business Site 3–4 weeks and a Premium Site 5–6 weeks. Add-ons such as a CMS or booking flow can extend that. Timeline is agreed in writing before work begins.' },
      { q: 'Do you handle hosting and deployment?', a: 'Yes. I set up the production server (VPS, AWS, or Cloudflare), wire up CI/CD via GitHub Actions, configure SSL, and hand over the deploy keys. You own the infrastructure.' },
      { q: 'Can you work with existing teams?', a: 'Yes. I have experience embedding into fintech and SaaS teams. PR-based workflow, code review, documentation — same standards as your in-house engineers.' },
    ],
    serviceType: 'Web Development',
    audience: 'Startups and SMEs',
  },
  'admin-portal': {
    seoTitle: 'Custom Admin Portals & Dashboards | Axel Nova',
    seoDescription: 'Custom admin panels, SaaS dashboards, and internal tools. Role-based access, real-time data, and clean UX. Built in Vue + Nuxt with Laravel APIs.',
    h1: 'Custom admin portals for the way your team works.',
    heroSubtitle: 'Bring the tasks your team repeats into one clear workspace, with the access levels, data views and integrations your process needs.',
    deliverablesTitle: 'Core capabilities',
    deliverables: [
      { icon: 'i-lucide-users', title: 'Role-based access', desc: 'Granular permissions per user, team, or resource. Built on Laravel Sanctum with audit logging.' },
      { icon: 'i-lucide-database', title: 'Data tables that scale', desc: 'Server-side pagination, filters, sort, bulk actions, and CSV exports — built to handle thousands of rows.' },
      { icon: 'i-lucide-line-chart', title: 'Dashboards and reports', desc: 'Key numbers, charts and exports on every portal. Business Admin adds scheduled reports; the Enterprise Portal adds real-time (WebSocket) updates.' },
      { icon: 'i-lucide-workflow', title: 'Workflow automation', desc: 'Approvals, notifications, queued background jobs. Wire your business logic into a clean operator UI.' },
    ],
    stackTitle: 'Stack',
    stack: ['Vue 3', 'Nuxt 4', '@nuxt/ui', 'Tailwind CSS', 'Laravel 11', 'Sanctum', 'MySQL', 'Redis', 'WebSockets', 'Chart.js'],
    faqs: [
      { q: 'What kind of admin dashboards do you build?', a: 'SaaS admin panels, internal ops tools, fintech back-office consoles, and customer portals. Anything that needs role-based access, structured data tables, charts, and CRUD workflows.' },
      { q: 'How do you handle authentication and roles?', a: 'Laravel Sanctum for token-based auth, with role and permission management via spatie/laravel-permission or a custom layer. SSO and 2FA available on request.' },
      { q: 'Can you integrate with our existing API?', a: 'Yes. I can build the frontend portal against your existing REST or GraphQL API, or extend your current backend. Auth handoff, error handling, and rate limiting are all part of the scope.' },
      { q: 'Do you provide analytics and reporting?', a: 'Yes. Every portal has a dashboard with charts, CSV export and an activity log. Business Admin adds advanced charts, scheduled and on-demand PDF / CSV reports, and a full audit log.' },
    ],
    serviceType: 'Admin Portal Development',
    audience: 'SaaS, Fintech, and Internal Tools teams',
  },
  'ui-ux-frontend': {
    seoTitle: 'UI/UX Design & Frontend Development | Axel Nova',
    seoDescription: 'UI/UX design for SaaS, fintech, and web products. User research, wireframes, interactive Figma prototypes, and design systems — by a designer who also ships the code.',
    h1: 'UI/UX design that can be built and used.',
    heroSubtitle: 'I design interfaces and build frontend experiences for web products. You get clear user flows, reusable components and a practical path from design to working software.',
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
      { q: 'What deliverables come with a UI/UX engagement?', a: 'It depends on the package. A UX Audit delivers a prioritised fix list with an annotated walkthrough. A Design + Build Sprint delivers Figma screens, a clickable prototype and the production frontend. A Design System Build delivers tokens, a component library in Figma and code, and documentation.' },
      { q: 'Do you do user research too?', a: 'Yes. Stakeholder interviews, competitor audits, lightweight user interviews, and usability testing on prototypes. Scope depends on the project — I scope research separately from visual design.' },
      { q: 'Can you redesign an existing product?', a: 'Yes. Most redesign projects start with a UX audit of the current product — heuristic review, analytics dive if you have data, and a prioritised list of fixes before redesigning visuals.' },
      { q: 'Will the developers be able to build what you design?', a: 'Yes — because I also code. My Figma files use real Tailwind tokens, real component composition, and realistic states. Developer handover meetings are part of every project.' },
    ],
    serviceType: 'UI/UX Design',
    audience: 'SaaS and fintech product teams',
  },
  'digital-marketing': {
    seoTitle: 'Digital Campaign & Creative Design in Malaysia | Axel Nova',
    seoDescription: 'Premium social campaigns, e-flyers, event collateral, and brand refresh kits for Malaysian SMEs. EN + BM copy on campaigns, events and retainers. Festive season ready.',
    h1: 'Creative design for Malaysian businesses.',
    heroSubtitle: 'From a social campaign to event materials or a brand refresh, I create assets that work across the channels and formats you actually use.',
    deliverablesTitle: 'What you get',
    deliverables: [
      { icon: 'i-lucide-megaphone', title: 'Social campaigns', desc: 'One hero visual adapted for FB, IG, WhatsApp and e-flyers, with EN + BM copy.' },
      { icon: 'i-lucide-image', title: 'Event collateral', desc: 'E-backdrops, buntings, standees, badges, and teasers — print-ready files with vendor specs.' },
      { icon: 'i-lucide-palette', title: 'Brand refresh kits', desc: 'Logo polish, colour and type system, business cards, email signatures, and reusable social templates.' },
      { icon: 'i-lucide-calendar-days', title: 'Monthly content retainer', desc: 'Consistent design output every month — social posts, e-flyers, and campaign concepts with planning.' },
    ],
    stackTitle: 'Tools',
    stack: ['Figma', 'Adobe Illustrator', 'Adobe Photoshop', 'Canva Pro', 'CapCut', 'Print-ready exports'],
    faqs: [
      { q: 'Do you write in Bahasa Malaysia and English?', a: 'Yes. The Campaign Pack, Event Activation and Monthly Content Retainer include EN + BM copy. A Single Asset includes copy in one language (EN or BM); a second language can be added. For trilingual campaigns (BM / EN / 中文), I bring in a native Mandarin copywriter, costed separately and transparently.' },
      { q: 'Can you handle festive campaigns (Raya, CNY, Deepavali)?', a: 'Yes — festive launches are my busiest period. Book 3–4 weeks ahead. A Campaign Pack delivers a hero key visual plus 5 adapted assets.' },
      { q: 'Do I get print-ready files?', a: 'Yes. The Campaign Pack, Event Activation and Brand Refresh Kit come with print-ready files plus digital exports (RGB JPG / PNG). A Single Asset ships web-ready, with a print-ready PDF on request. Event work includes vendor specs so your printer or e-backdrop supplier can run it directly.' },
      { q: 'What if I need ongoing content every month?', a: 'That\'s the Monthly Content Retainer — 8–12 social posts, 2 e-flyers, and 1 campaign concept per month, with content calendar planning.' },
    ],
    serviceType: 'Digital Marketing & Creative Design',
    audience: 'SMEs, hotels, clinics, salons, and growing Malaysian brands',
  },
  'booking-portal': {
    seoTitle: 'Online Booking Systems in Malaysia | Axel Nova',
    seoDescription: 'Custom online booking and customer portals for Malaysian hotels, homestays, clinics, salons, and tuition centres. Calendar engine, Billplz / ToyyibPay payment, WhatsApp confirmations.',
    h1: 'Online booking systems for Malaysian businesses.',
    heroSubtitle: 'Let customers check availability and request or make a booking online. We define the right payment, reminder and management features for your business before the build.',
    deliverablesTitle: 'Core capabilities',
    deliverables: [
      { icon: 'i-lucide-calendar-check', title: 'Smart booking engine', desc: 'Calendar with availability, lead time, blackout dates, and double-booking prevention. Mobile-first by default.' },
      { icon: 'i-lucide-credit-card', title: 'Malaysian payment gateways', desc: 'Booking Pro takes deposits or full payment via Billplz / ToyyibPay, with cancellation and refund rules. On Booking Lite, online payment (Stripe or FPX) is an add-on.' },
      { icon: 'i-lucide-message-circle', title: 'Auto-notifications', desc: 'Booking confirmations and reminders by email and WhatsApp, plus SMS on Booking Pro. Admin gets a copy of everything.' },
      { icon: 'i-lucide-users', title: 'Customer self-serve', desc: 'Customers reschedule or cancel on their own; Booking Pro adds customer accounts with booking history. Less admin work, less WhatsApp triage.' },
    ],
    stackTitle: 'Stack',
    stack: ['Vue 3', 'Nuxt 4', 'Laravel 11', 'Billplz', 'ToyyibPay', 'Stripe', 'WhatsApp Business API', 'MySQL', 'Tailwind CSS'],
    faqs: [
      { q: 'Which payment gateways do you integrate?', a: 'Billplz, ToyyibPay, and Stripe for Malaysian businesses. That covers FPX (online banking), e-wallets (GrabPay, TNG, Boost, ShopeePay), and credit/debit cards. Online payment is included in Booking Pro and an add-on for Booking Lite.' },
      { q: 'Can it handle multiple rooms / staff / services?', a: 'Yes. Booking Pro and Custom plans support multi-resource scheduling — multiple rooms, staff, treatment rooms, classrooms, or service types in parallel. Each with its own availability and pricing rules.' },
      { q: 'Will I get WhatsApp notifications for new bookings?', a: 'Yes. Customer and admin both receive WhatsApp and email confirmations on every booking, plus reminders before the appointment. Booking Pro adds SMS. WhatsApp API and SMS usage fees are billed separately.' },
      { q: 'Can it integrate with my existing PMS or POS?', a: 'Yes. I\'ve integrated with hotel PMS, salon POS, and clinic management systems via REST API or webhook. The Custom Booking System plan covers this — scope agreed on call.' },
    ],
    serviceType: 'Booking & Customer Portal Development',
    audience: 'Malaysian hotels, homestays, clinics, salons, and tuition centres',
  },
  'ecommerce': {
    seoTitle: 'E-commerce Websites for Malaysian Brands | Axel Nova',
    seoDescription: 'Direct-to-consumer storefronts with FPX, e-wallets (GrabPay, TNG, Boost), and card checkout via Billplz / ToyyibPay / Stripe. Own your customer, ditch the marketplace margins.',
    h1: 'E-commerce websites built around your business.',
    heroSubtitle: 'Sell through a storefront that reflects your brand and gives customers a clear path from product to checkout. Payments, stock and delivery integrations depend on your agreed scope.',
    deliverablesTitle: 'What you get',
    deliverables: [
      { icon: 'i-lucide-shopping-bag', title: 'Branded storefront', desc: 'Mobile-first product catalogue with categories and product pages; Storefront Pro adds variants and unlimited SKUs. Fast, SEO-friendly, and brand-aligned.' },
      { icon: 'i-lucide-banknote', title: 'Malaysian checkout', desc: 'Billplz / ToyyibPay checkout on Storefront Starter; card and e-wallet checkout (GrabPay, TNG, Boost) on Storefront Pro. Stripe and FPX are add-ons where a package lists them.' },
      { icon: 'i-lucide-package', title: 'Inventory & fulfilment', desc: 'Order management, stock status and shipping rules; Storefront Pro adds inventory with low-stock alerts. EasyParcel / Pos Laju sync is scoped if needed.' },
      { icon: 'i-lucide-trending-up', title: 'Sales analytics', desc: 'On Storefront Pro: a sales analytics dashboard, abandoned cart recovery and discount codes. Decide on real data, not gut feel.' },
    ],
    stackTitle: 'Stack',
    stack: ['Vue 3', 'Nuxt 4', 'Laravel 11', 'Billplz', 'ToyyibPay', 'Stripe', 'EasyParcel', 'MySQL', 'Redis', 'Tailwind CSS'],
    faqs: [
      { q: 'Can I accept FPX and Malaysian e-wallets?', a: 'Yes. Storefront Starter checks out through Billplz or ToyyibPay, and Storefront Pro adds card and e-wallet checkout (GrabPay, TNG, Boost). Stripe card payments and FPX online banking are add-ons where a package lists them.' },
      { q: 'Is this better than selling on Shopee / Lazada?', a: 'Different, not better-or-worse. Marketplaces give you traffic but charge fees and own the customer relationship. Your own storefront builds brand, owns customer data, and has no marketplace fees. Most successful brands run both in parallel.' },
      { q: 'Can you integrate with my existing inventory or accounting system?', a: 'Yes. I\'ve integrated with Xero, AutoCount, SQL Account, and custom ERPs. Storefront Pro includes order exports for your accounting; real-time stock sync and ERP / accounting integration are part of Custom Commerce.' },
      { q: 'What about subscriptions or B2B pricing?', a: 'The Custom Commerce plan covers subscriptions, recurring billing, B2B tiers, bulk pricing, and net-30 terms. Scope agreed on call — these are common for skincare, supplement, and wholesale brands.' },
    ],
    serviceType: 'E-commerce & Payment Flow Development',
    audience: 'Malaysian DTC brands, retail, and B2B sellers',
  },
}

const route = useRoute()
const slug = computed(() => String(route.params.slug))

const siteUrl = 'https://axelnovaventures.com'

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
// Admin-written category descriptions can run past the ~155 characters search
// results show, so the fallback is clipped at a word boundary.
const seoDescription = computed(
  () => enrichment.value.seoDescription ?? clipText(category.value!.description, 155),
)

useSeoMeta({
  title: seoTitle,
  description: seoDescription,
  ogTitle: seoTitle,
  ogDescription: seoDescription,
  ogUrl: pageUrl,
  twitterTitle: seoTitle,
  twitterDescription: seoDescription,
  twitterCard: 'summary_large_image',
})

// Generated link-preview card (docs/frontend/OG-IMAGES.md).
defineOgImage('SiteCard', {
  label: 'SERVICES',
  headline: category.value.name,
  subline: clipText(category.value.description, 90),
  footer: ogFooter(`/services/${slug.value}`),
}, { alt: category.value.name })

// Category-specific FAQs first (more relevant), then the universal set.
const allFaqs = computed<FAQ[]>(() => [
  ...(enrichment.value.faqs ?? []),
  ...generalFaqs,
])

const jsonLdScripts = computed(() => {
  const base = [
    {
      type: 'application/ld+json' as const,
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'Service',
        serviceType: enrichment.value.serviceType ?? category.value!.name,
        name: seoTitle.value,
        description: seoDescription.value,
        url: pageUrl.value,
        // Same @id as the homepage's ProfessionalService node, so both describe one business.
        provider: { '@type': 'ProfessionalService', '@id': `${siteUrl}/#business`, name: 'Axel Nova Ventures', url: siteUrl },
        areaServed: { '@type': 'Country', name: 'Malaysia' },
        ...(enrichment.value.audience && {
          audience: { '@type': 'Audience', audienceType: enrichment.value.audience },
        }),
      }),
    },
    {
      type: 'application/ld+json' as const,
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
      type: 'application/ld+json' as const,
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

    <!-- The page's one H1: the plain service name from `h1`, else the admin category name. -->
    <SectionHeader
      as="h1"
      :eyebrow="category.name"
      :title="enrichment.h1 ?? `${category.name}.`"
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
          <PriceTag
            class="text-[17px] font-semibold tracking-tight mb-1"
            :min="pkg.price_min_myr"
            :max="pkg.price_max_myr"
            :unit="pkg.unit"
          />
          <p class="text-[12px] mb-4 inline-flex items-center gap-1.5" :style="{ color: 'var(--color-text-secondary)' }">
            <UIcon name="i-lucide-clock" class="size-3.5 shrink-0" />
            {{ pkg.duration_text }}
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
