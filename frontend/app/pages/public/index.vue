<script setup lang="ts">
definePageMeta({ layout: 'public' })

import type { ComponentPublicInstance } from 'vue'
import type { Project } from '~/data/projects'
import FeaturedProjectsCarousel from '~/components/shared/FeaturedProjectsCarousel.vue'
import SectionHeader from '~/components/shared/SectionHeader.vue'
import { MOTION } from '~/utils/motion'

// Captured at setup — Nuxt context isn't available inside event callbacks.
const motion = useMotion()

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

const heroBadge    = ref<HTMLElement | null>(null)
const heroHeadline = ref<HTMLElement | null>(null)
const heroLine2    = ref<HTMLElement | null>(null)
const heroSub      = ref<HTMLElement | null>(null)
const heroCtas     = ref<HTMLElement | null>(null)
const heroCta      = ref<ComponentPublicInstance | HTMLElement | null>(null)
const bandCta      = ref<ComponentPublicInstance | HTMLElement | null>(null)
const statEls      = ref<(HTMLElement | null)[]>([])

// `background-clip: text` on an ancestor stops painting text inside transformed
// descendants (the SplitText words), so the gradient line gets per-word
// backgrounds sized/positioned to emulate one continuous gradient. The split
// reverts after the entrance, restoring the original parent gradient.
const mapGradientWords = (split: { words: Element[] }) => {
  const base = heroLine2.value
  if (!base) return
  const baseRect = base.getBoundingClientRect()
  split.words.forEach((node) => {
    const w = node as HTMLElement
    if (!base.contains(w)) return
    const r = w.getBoundingClientRect()
    w.style.backgroundImage = 'var(--grad-text-accent)'
    w.style.backgroundSize = `${baseRect.width}px ${baseRect.height}px`
    w.style.backgroundPosition = `${baseRect.left - r.left}px ${baseRect.top - r.top}px`
    // Vendor prefix still required for gradient text in Safari/Chrome; set via
    // setProperty so the standard backgroundClip property carries the lint.
    w.style.setProperty('-webkit-background-clip', 'text')
    w.style.backgroundClip = 'text'
    w.style.color = 'transparent'
  })
}

// Keep the split mounted after the reveal: reverting it re-runs layout and, on
// Safari, produces a second visible reflow (the "shrink"). The gradient is
// already mapped per word, so leaving the split in place looks identical.
const { build: buildHeadline } = useSplitTextReveal(heroHeadline, {
  onSplit: mapGradientWords,
  revertOnComplete: false,
})

useMagnetic(heroCta)
useMagnetic(bandCta)

stats.forEach((s, i) => useCountUp(() => statEls.value[i], s.value))
useReveal('.stat-cell', { stagger: MOTION.stagger.base })
useScrollReveal('.reveal')

let heroTl: gsap.core.Timeline | null = null
let safetyTimer: number | undefined
let fontGate: number | undefined

onMounted(() => {
  if (import.meta.server) return

  const { gsap, reduced } = motion
  const els = [heroBadge.value, heroSub.value, heroCtas.value].filter(Boolean) as HTMLElement[]
  if (reduced || !els.length) return

  // Hide immediately so there's no flash while we wait for the font.
  gsap.set(els, { opacity: 0, y: 24 })

  const start = () => {
    if (heroTl) return // already started (font-ready and safety-net can race)

    // SplitText measures word geometry now, so it must run AFTER the webfont
    // loads — splitting against the metrically-different fallback font and then
    // swapping Inter in is what made the headline reflow ("shrink") on Safari.
    const tl = gsap.timeline({
      defaults: { ease: MOTION.ease.out },
      onComplete: () => gsap.set(els, { clearProps: 'opacity,transform' }),
    })
    tl.to(heroBadge.value, { opacity: 1, y: 0, duration: MOTION.dur.base })
    const headline = buildHeadline()
    if (headline) tl.add(headline, '-=0.3')
    tl.to(heroSub.value, { opacity: 1, y: 0, duration: MOTION.dur.slow }, '-=0.55')
    tl.to(heroCtas.value, { opacity: 1, y: 0, duration: MOTION.dur.slow }, '-=0.7')
    heroTl = tl
  }

  // Gate on fonts; fall back after a short timeout if fonts.ready stalls.
  fontGate = window.setTimeout(start, 600)
  document.fonts?.ready.then(() => {
    clearTimeout(fontGate)
    start()
  }) ?? start()

  // Throttled/background tabs may never run rAF — force-finish whatever has
  // started so nothing is stranded hidden. If the gate never fired, reveal flat.
  safetyTimer = window.setTimeout(() => {
    if (!heroTl) {
      gsap.set(els, { clearProps: 'opacity,transform' })
      return
    }
    if (heroTl.progress() < 1) heroTl.progress(1)
  }, 3500)
})

onUnmounted(() => {
  clearTimeout(safetyTimer)
  clearTimeout(fontGate)
  heroTl?.kill()
})
</script>

<template>
  <div>
    <!-- HERO -->
    <section
      class="bg-aurora flex flex-col items-center justify-center text-center px-6 relative overflow-hidden"
      style="min-height: calc(100vh - 49px);"
    >
      <div
        ref="heroBadge"
        class="inline-flex items-center gap-2 px-3 py-1 rounded-full border backdrop-blur-md"
        :style="{ borderColor: 'var(--color-border-strong)', background: 'var(--color-accent-soft)' }"
      >
        <span class="hero-status-dot size-1.5 rounded-full" style="background: var(--color-success);" />
        <span class="text-[12px] font-medium" style="color: var(--color-text);">
          Open to freelance
        </span>
      </div>

      <h1
        ref="heroHeadline"
        class="mt-7 leading-[1.02] tracking-tighter font-semibold max-w-5xl"
        style="font-size: clamp(52px, 9vw, 112px);"
      >
        <span class="block">I craft interfaces</span>
        <span ref="heroLine2" class="block text-gradient">people actually enjoy.</span>
      </h1>

      <p
        ref="heroSub"
        class="mt-7 max-w-xl text-[19px] leading-normal"
        style="color: var(--color-text-secondary);"
      >
        UI/UX engineer with 7 years across fintech, SaaS, and products that need real craft.
        Vue · Nuxt · Laravel · Docker.
      </p>

      <div ref="heroCtas" class="mt-9 flex flex-wrap items-center justify-center gap-3">
        <NuxtLink ref="heroCta" to="/quote" class="btn-pill btn-pill-accent">
          <span class="magnetic-label">Get a quotation</span>
        </NuxtLink>
        <NuxtLink to="/services" class="btn-pill btn-pill-ghost">
          See my services
        </NuxtLink>
      </div>
    </section>

    <!-- STATS -->
    <section class="border-y" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
      <div class="max-w-7xl mx-auto grid grid-cols-2 md:grid-cols-4">
        <div
          v-for="(s, i) in stats"
          :key="s.label"
          class="stat-cell px-6 py-14 text-center"
          :style="{
            borderRight: i < stats.length - 1 ? '1px solid var(--color-border)' : 'none',
            borderBottom: i < 2 ? '1px solid var(--color-border)' : 'none'
          }"
          :class="{ 'md:border-b-0!': true }"
        >
          <div class="text-4xl md:text-5xl font-semibold tracking-tight tabular-nums">
            <span :ref="el => { statEls[i] = el as HTMLElement | null }">{{ s.value }}</span>{{ s.suffix }}
          </div>
          <div class="text-[13px] mt-2" style="color: var(--color-text-secondary);">
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

    <!-- CTA BANNER -->
    <section class="relative overflow-hidden reveal" :style="{ borderColor: 'var(--color-border)' }">
      <!-- gradient backdrop -->
      <div
        aria-hidden
        class="absolute inset-0 -z-10"
        style="
          background:
            radial-gradient(60% 80% at 15% 50%, rgba(168,85,247,0.16) 0%, transparent 60%),
            radial-gradient(50% 80% at 85% 50%, rgba(0,113,227,0.18) 0%, transparent 60%),
            var(--color-bg-secondary);
        "
      />
      <div class="max-w-7xl mx-auto px-6 py-20 flex flex-col items-center gap-7 text-center">
        <div>
          <p class="text-3xl md:text-5xl font-semibold tracking-tight">
            Have a project in mind?
          </p>
          <p class="mt-3 text-[17px] max-w-lg mx-auto" style="color: var(--color-text-secondary);">
            Let's design something premium together. Fintech, SaaS, or a product that needs senior craft.
          </p>
        </div>
        <div class="flex flex-wrap items-center justify-center gap-3">
          <NuxtLink ref="bandCta" to="/quote" class="btn-pill btn-pill-accent">
            <span class="magnetic-label">Get Inquiry</span>
          </NuxtLink>
          <NuxtLink to="/contact" class="btn-pill btn-pill-ghost">
            Let's talk
          </NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* Gentle pulse on the availability dot only — felt, not noticed. */
.hero-status-dot {
  box-shadow: 0 0 0 4px rgba(48, 209, 88, 0.18);
  animation: hero-dot-pulse 2.6s ease-in-out infinite;
}
@keyframes hero-dot-pulse {
  0%, 100% { box-shadow: 0 0 0 4px rgba(48, 209, 88, 0.18); }
  50%      { box-shadow: 0 0 0 8px rgba(48, 209, 88, 0.05); }
}

@media (prefers-reduced-motion: reduce) {
  .hero-status-dot { animation: none; }
}
</style>
