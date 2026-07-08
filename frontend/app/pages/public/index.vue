<script setup lang="ts">
definePageMeta({ layout: 'public' })

import type { ComponentPublicInstance } from 'vue'
import type { Project } from '~/data/projects'
import HeroEpoch from '~/components/public/HeroEpoch.vue'
import FeaturedMockups from '~/components/public/FeaturedMockups.vue'
import FeaturedProjectsCarousel from '~/components/shared/FeaturedProjectsCarousel.vue'
import SectionHeader from '~/components/shared/SectionHeader.vue'
import { MOTION } from '~/utils/motion'

const siteUrl = 'https://axelnovaventures.com'
const ogImage = `${siteUrl}/og-image.png`
const seoTitle = 'Axel Nova Ventures — Design & Engineering Studio'
const seoDescription = 'Design-led software studio building fintech, SaaS, and bespoke web products. Vue · Nuxt · Laravel · Docker · AWS.'

useSeoMeta({
  title: seoTitle,
  description: seoDescription,
  ogTitle: seoTitle,
  ogDescription: seoDescription,
  ogImage,
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
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'Organization',
        name: 'Axel Nova Ventures',
        url: siteUrl,
        logo: `${siteUrl}/axel_nova_logo.png`,
        description: seoDescription,
        foundingDate: '2026',
        founder: {
          '@type': 'Person',
          name: 'Ahmad Baihaqie',
          jobTitle: 'Founder & Software Engineer',
        },
        address: {
          '@type': 'PostalAddress',
          addressLocality: 'Kuala Lumpur',
          addressCountry: 'MY',
        },
        sameAs: [
          'https://github.com/byhaqie31',
          'https://linkedin.com/in/byhaqieyusri',
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
    tags: p.tags ?? [],
    stack: p.stack ?? [],
    featured: p.featured,
  }))
})

const featuredProjects = computed(() => projects.value.filter(p => p.featured))

const stats = [
  { value: 7,  suffix: '+', label: 'Years building' },
  { value: 3,  suffix: '',  label: 'Years in industry' },
  { value: 10, suffix: '+', label: 'Projects shipped' },
  { value: 2,  suffix: '',  label: 'Degrees pursuing' },
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
      <div class="max-w-7xl mx-auto grid grid-cols-2 md:grid-cols-4">
        <div
          v-for="(s, i) in stats"
          :key="s.label"
          class="stat-cell px-6 py-14 text-center"
          :style="{
            borderRight: i < stats.length - 1 ? '1px solid var(--stat-band-divider)' : 'none',
            borderBottom: i < 2 ? '1px solid var(--stat-band-divider)' : 'none'
          }"
          :class="{ 'md:border-b-0!': true }"
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
        subtitle="A few live builds — hover a card to visit the real site."
        :action="{ label: 'View all', to: '/projects' }"
      />

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

    <!-- FEATURED MOCKUPS -->
    <section class="max-w-7xl mx-auto px-6 pb-32 reveal">
      <SectionHeader
        eyebrow="Client previews"
        title="Featured mockups."
        subtitle="Live prototypes built for clients — click a card for an instant in-page preview."
        :action="{ label: 'View all', to: 'https://axelnova.my/', target: '_blank' }"
      />

      <FeaturedMockups class="reveal" />
    </section>

    <!-- CTA — full-width vivid blue band, matching the stats band. -->
    <section class="reveal" :style="{ background: 'var(--stat-band-bg)' }">
      <div class="max-w-7xl mx-auto px-6 py-24 flex flex-col items-center gap-7 text-center">
        <div>
          <p class="text-3xl md:text-5xl font-semibold tracking-tight" :style="{ color: 'var(--stat-band-fg)' }">
            Have a project in mind?
          </p>
          <p class="mt-3 text-[17px] max-w-lg mx-auto" :style="{ color: 'var(--stat-band-fg-muted)' }">
            Let's design something premium together. Fintech, SaaS, or a product that needs senior craft.
          </p>
        </div>
        <div class="flex flex-wrap items-center justify-center gap-3">
          <NuxtLink ref="bandCta" to="/quote" class="btn-pill" :style="{ background: 'var(--band-cta-bg)', color: 'var(--band-cta-fg)', boxShadow: 'var(--shadow-sm)' }">
            <span class="magnetic-label">Get Inquiry</span>
          </NuxtLink>
          <NuxtLink to="/contact" class="btn-pill" :style="{ background: 'transparent', border: '1px solid var(--stat-band-fg)', color: 'var(--stat-band-fg)' }">
            Let's talk
          </NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>
