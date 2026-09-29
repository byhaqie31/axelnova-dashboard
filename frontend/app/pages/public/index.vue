<script setup lang="ts">
import type { ComponentPublicInstance } from 'vue'
import type { Project } from '~/data/projects'
import HeroEpoch from '~/components/public/HeroEpoch.vue'
import FeaturedMockups from '~/components/public/FeaturedMockups.vue'
import ReferralBand from '~/components/public/ReferralBand.vue'
import TestimonialWall from '~/components/public/TestimonialWall.vue'
import BlogLatest from '~/components/public/BlogLatest.vue'
import FeaturedProjectsCarousel from '~/components/shared/FeaturedProjectsCarousel.vue'
import SectionHeader from '~/components/shared/SectionHeader.vue'
import { MOTION } from '~/utils/motion'

definePageMeta({ layout: 'public' })

const siteUrl = 'https://axelnovaventures.com'
const ogImage = `${siteUrl}/og-image.jpg`
// Front-loads what the studio does and where ("web development", "Kuala
// Lumpur", "Malaysia") — the searches this page should rank for.
const seoTitle = 'Web Design & Development in Kuala Lumpur | Axel Nova'
const seoDescription = 'Axel Nova Ventures designs and builds websites, booking portals and custom business systems in Kuala Lumpur for businesses across Malaysia.'

useSeoMeta({
  title: seoTitle,
  description: seoDescription,
  ogTitle: seoTitle,
  ogDescription: seoDescription,
  ogImage,
  ogImageWidth: 1200,
  ogImageHeight: 630,
  ogImageAlt: 'Axel Nova Ventures — Crafted by design. Built to last.',
  ogUrl: siteUrl,
  twitterTitle: seoTitle,
  twitterDescription: seoDescription,
  twitterImage: ogImage,
  twitterCard: 'summary_large_image',
})

useHead({
  link: [{ rel: 'canonical', href: siteUrl }],
  script: [
    {
      type: 'application/ld+json',
      // ProfessionalService (a LocalBusiness subtype) rather than a bare
      // Organization, so search engines read a local web studio serving
      // Malaysia. Keep name / locality / phone identical to the Google
      // Business Profile — mismatches weaken the local signal.
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@graph': [
          {
            '@type': 'ProfessionalService',
            '@id': `${siteUrl}/#business`,
            name: 'Axel Nova Ventures',
            url: siteUrl,
            logo: `${siteUrl}/axel_nova_logo.png`,
            image: ogImage,
            description: seoDescription,
            telephone: '+60183173103',
            email: 'baihaqie@axelnova.tech',
            foundingDate: '2026',
            founder: {
              '@type': 'Person',
              name: 'Ahmad Baihaqie',
              jobTitle: 'Founder & Software Engineer',
              url: `${siteUrl}/about`,
            },
            address: {
              '@type': 'PostalAddress',
              addressLocality: 'Kuala Lumpur',
              addressCountry: 'MY',
            },
            areaServed: { '@type': 'Country', name: 'Malaysia' },
            knowsAbout: ['Web development', 'Website design', 'UI/UX design', 'Custom business systems', 'Booking systems', 'E-commerce'],
            sameAs: [
              'https://github.com/byhaqie31',
              'https://linkedin.com/in/byhaqieyusri',
            ],
          },
          {
            '@type': 'WebSite',
            '@id': `${siteUrl}/#website`,
            name: 'Axel Nova Ventures',
            url: siteUrl,
            inLanguage: 'en-MY',
            publisher: { '@id': `${siteUrl}/#business` },
          },
        ],
      }),
    },
  ],
})

interface ApiProject {
  id: number
  slug: string
  name: string
  description: string
  long_description: string
  status: 'live' | 'soon' | 'wip' | 'planning'
  url: string | null
  repo: string | null
  cover_image_url: string | null
  tags: string[]
  stack: string[]
  featured: boolean
  likes_count: number
}

const { data: apiResponse } = await useFetch<{ data: ApiProject[] }>(
  `${useApiBase()}/api/v1/projects`,
  { key: 'public-projects-home' },
)

const projects = computed<Project[]>(() => {
  return (apiResponse.value?.data ?? []).map(p => ({
    id: p.slug,
    dbId: p.id,
    likes: p.likes_count ?? 0,
    name: p.name,
    description: p.description,
    longDescription: p.long_description,
    status: p.status,
    url: p.url ?? undefined,
    repo: p.repo ?? undefined,
    coverImage: p.cover_image_url ?? undefined,
    tags: p.tags ?? [],
    stack: p.stack ?? [],
    featured: p.featured,
  }))
})

const featuredProjects = computed(() => projects.value.filter(p => p.featured))

// Commercial facts only — keep each one current and supportable.
const stats = [
  { value: 7,  suffix: '+', label: 'Years building' },
  { value: 3,  suffix: '',  label: 'Years in industry' },
  { value: 10, suffix: '+', label: 'Projects shipped' },
]

const bandCta = ref<ComponentPublicInstance | HTMLElement | null>(null)
const statEls = ref<(HTMLElement | null)[]>([])

// The hero (entrance timeline, SplitText, magnetic CTA) lives in <HeroEpoch>.
useMagnetic(bandCta)

stats.forEach((s, i) => useCountUp(() => statEls.value[i], s.value))
useReveal('.stat-cell', { stagger: MOTION.stagger.base })
useScrollReveal('.reveal')
</script>

<template>
  <div>
    <!-- HERO -->
    <HeroEpoch />

    <!-- STATS — vivid "hero blue" band; white numerals + light dividers. -->
    <section :style="{ background: 'var(--stat-band-bg)' }">
      <div class="max-w-7xl mx-auto grid grid-cols-3">
        <div
          v-for="(s, i) in stats"
          :key="s.label"
          class="stat-cell px-3 sm:px-6 py-14 text-center"
          :style="{ borderRight: i < stats.length - 1 ? '1px solid var(--stat-band-divider)' : 'none' }"
        >
          <div class="text-4xl md:text-5xl font-semibold tracking-tight tabular-nums" :style="{ color: 'var(--stat-band-fg)' }">
            <span :ref="el => { statEls[i] = el as HTMLElement | null }">{{ s.value }}</span>{{ s.suffix }}
          </div>
          <div class="text-[13px] mt-2" :style="{ color: 'var(--stat-band-fg-muted)' }">
            {{ s.label }}
          </div>
        </div>
      </div>
    </section>

    <!-- SELECTED PROJECTS -->
    <section class="max-w-7xl mx-auto px-6 py-32 reveal">
      <SectionHeader
        eyebrow="Selected work"
        title="Featured projects."
        :action="{ label: 'View all', to: '/projects' }"
      >
        <!-- Hover doesn't exist on touch — tell mobile users to swipe instead. -->
        <template #subtitle>
          <span class="hidden sm:inline">Explore selected projects and visit the live sites to see how they work.</span>
          <span class="sm:hidden">Swipe to explore projects, then tap a card to visit the live site.</span>
        </template>
      </SectionHeader>

      <FeaturedProjectsCarousel
        v-if="featuredProjects.length"
        :projects="featuredProjects"
        class="reveal"
      />
      <div
        v-else
        class="text-center py-12 text-sm"
        style="color: var(--color-text-secondary);"
      >
        Featured projects coming soon.
      </div>
    </section>

    <!-- FEATURED MOCKUPS — #mockups is the hero nav's in-page jump target. -->
    <section id="mockups" class="max-w-7xl mx-auto px-6 pb-32 scroll-mt-24 reveal">
      <SectionHeader
        eyebrow="Client previews"
        title="Featured mockups."
        subtitle="Explore working prototypes and open a preview without leaving this page."
        :action="{ label: 'View all', to: 'https://axelnova.my/', target: '_blank' }"
      />

      <FeaturedMockups class="reveal" />
    </section>

    <!-- LATEST BLOG POSTS — renders nothing until a post is published. -->
    <BlogLatest />

    <!-- PARTNER REFERRAL SHORTCUT — the inverse pitch of the closing band. -->
    <ReferralBand />

    <!-- CLIENT TESTIMONIALS — renders nothing until reviews are published. -->
    <TestimonialWall />

    <!-- CTA — closing band on the page background; the vivid blue is reserved
         for the stats band so it stays a single accent moment per page. -->
    <section class="reveal">
      <div class="max-w-7xl mx-auto px-6 py-24 flex flex-col items-center gap-7 text-center">
        <div>
          <p class="text-3xl md:text-5xl font-semibold tracking-tight" :style="{ color: 'var(--color-text)' }">
            Have a project in mind?
          </p>
          <p class="mt-3 text-[17px] max-w-lg mx-auto" :style="{ color: 'var(--color-text-secondary)' }">
            Tell me what you're planning, whether it's a new website, a booking flow or a system your team needs every day. I'll help you work out the next step.
          </p>
        </div>
        <div class="flex flex-wrap items-center justify-center gap-3">
          <NuxtLink ref="bandCta" to="/quote" class="btn-pill btn-pill-primary">
            <span class="magnetic-label">Request a quote</span>
          </NuxtLink>
          <NuxtLink to="/contact" class="btn-pill btn-pill-ghost">
            Discuss a project
          </NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>
