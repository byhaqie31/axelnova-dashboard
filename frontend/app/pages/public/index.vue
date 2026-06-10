<script setup lang="ts">
definePageMeta({ layout: 'public' })

import type { ComponentPublicInstance } from 'vue'
import type { Project } from '~/data/projects'
import ProjectCard from '~/components/shared/ProjectCard.vue'
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
}

const { data: apiResponse } = await useFetch<{ data: ApiProject[] }>(
  `${useApiBase()}/api/v1/projects`,
  { key: 'public-projects-home' },
)

const projects = computed<Project[]>(() => {
  return (apiResponse.value?.data ?? []).map(p => ({
    id: p.slug,
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

const homeFilters = ['All', 'Laravel', 'Nuxt', 'Fintech', 'Live']
const activeFilter = ref('All')

const visibleProjects = computed(() => {
  const f = activeFilter.value
  if (f === 'All') return featuredProjects.value
  if (f === 'Live') return featuredProjects.value.filter(p => p.status === 'live')
  return featuredProjects.value.filter(p =>
    p.stack.includes(f) || p.tags.includes(f),
  )
})

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

const { build: buildHeadline } = useSplitTextReveal(heroHeadline, { onSplit: mapGradientWords })

useMagnetic(heroCta)
useMagnetic(bandCta)

stats.forEach((s, i) => useCountUp(() => statEls.value[i], s.value))
useReveal('.stat-cell', { stagger: MOTION.stagger.base })
useScrollReveal('.reveal')

let heroTl: gsap.core.Timeline | null = null
let safetyTimer: number | undefined

onMounted(() => {
  if (import.meta.server) return

  const { gsap, reduced } = motion
  const els = [heroBadge.value, heroSub.value, heroCtas.value].filter(Boolean) as HTMLElement[]
  if (reduced || !els.length) return

  gsap.set(els, { opacity: 0, y: 24 })

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

  // Throttled/background tabs may never run rAF — force-finish the entrance
  // so nothing is stranded hidden.
  safetyTimer = window.setTimeout(() => {
    if (tl.progress() < 1) tl.progress(1)
  }, 3500)
})

onUnmounted(() => {
  clearTimeout(safetyTimer)
  heroTl?.kill()
})

// --- Filter tabs: sliding pill indicator + GSAP grid swap -------------------

const filterPill = ref<HTMLElement | null>(null)
const filterBtns: Record<string, HTMLElement | null> = {}
const projectGrid = ref<HTMLElement | null>(null)
const pillReady = ref(false)

const setFilterBtn = (f: string) => (el: unknown) => {
  filterBtns[f] = el as HTMLElement | null
}

const movePill = (f: string, animate = true) => {
  const pill = filterPill.value
  const btn = filterBtns[f]
  if (!pill || !btn) return
  const { gsap, reduced } = motion
  const vars = { x: btn.offsetLeft, y: btn.offsetTop, width: btn.offsetWidth, height: btn.offsetHeight }
  if (!animate || reduced) gsap.set(pill, vars)
  else gsap.to(pill, { ...vars, duration: 0.45, ease: MOTION.ease.out, overwrite: 'auto' })
  pillReady.value = true
}

let swapTl: gsap.core.Timeline | null = null

const setFilter = (f: string) => {
  if (f === activeFilter.value) return
  movePill(f)

  const { gsap, reduced } = motion
  if (reduced) {
    activeFilter.value = f
    return
  }

  // Rapid clicks: kill the in-flight timeline and start over — an
  // early-return guard would drop inputs and can strand cards invisible.
  swapTl?.kill()

  const outCards = Array.from(projectGrid.value?.children ?? []) as HTMLElement[]
  const tl = gsap.timeline()
  swapTl = tl
  if (outCards.length) {
    tl.to(outCards, {
      opacity: 0, y: 10, scale: 0.98,
      duration: 0.22, stagger: 0.03, ease: 'power2.in',
    })
  }
  tl.call(async () => {
    activeFilter.value = f
    await nextTick()
    const inCards = Array.from(projectGrid.value?.children ?? []) as HTMLElement[]
    if (!inCards.length) return
    // immediateRender: false — otherwise the from-state (opacity 0) is applied
    // at build time and visible (DOM-reused) cards flash out.
    swapTl = gsap.timeline().fromTo(inCards,
      { opacity: 0, y: 16, scale: 0.98 },
      {
        opacity: 1, y: 0, scale: 1,
        duration: 0.5, stagger: 0.06, ease: MOTION.ease.out,
        immediateRender: false, clearProps: 'opacity,transform',
      },
    )
  })
}

const syncPill = () => movePill(activeFilter.value, false)

onMounted(() => {
  if (import.meta.server) return
  nextTick(() => {
    syncPill()
    // Font swap changes button widths after first layout.
    document.fonts?.ready?.then(syncPill)
    window.addEventListener('resize', syncPill, { passive: true })
  })
})

onUnmounted(() => {
  window.removeEventListener('resize', syncPill)
  swapTl?.kill()
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
        title="Recent projects."
        :action="{ label: 'View all', to: '/projects' }"
      />

      <div class="relative hidden md:flex flex-wrap gap-2 mb-10">
        <span
          ref="filterPill"
          aria-hidden
          class="filter-pill"
          :style="{ opacity: pillReady ? 1 : 0 }"
        />
        <button
          v-for="f in homeFilters" :key="f"
          :ref="setFilterBtn(f)"
          class="relative text-[13px] px-4 py-1.5 rounded-full border transition-colors duration-200"
          :style="{
            borderColor: activeFilter === f ? 'transparent' : 'var(--color-border-strong)',
            background: activeFilter === f && !pillReady ? 'var(--color-text)' : 'transparent',
            color: activeFilter === f ? 'var(--color-bg)' : 'var(--color-text-secondary)',
            fontWeight: activeFilter === f ? 500 : 400,
          }"
          @click="setFilter(f)"
        >
          {{ f }}
        </button>
      </div>

      <div ref="projectGrid" class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        <ProjectCard
          v-for="p in visibleProjects" :key="p.id"
          :project="p"
          class="reveal"
        />
        <div
          v-if="visibleProjects.length === 0"
          class="col-span-full text-center py-12 text-sm"
          style="color: var(--color-text-secondary);"
        >
          No projects match this filter yet.
        </div>
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
      <div class="max-w-7xl mx-auto px-6 py-20 flex flex-col md:flex-row items-center justify-between gap-6 text-center md:text-left">
        <div>
          <p class="text-3xl md:text-5xl font-semibold tracking-tight">
            Have a project in mind?
          </p>
          <p class="mt-3 text-[17px] max-w-lg" style="color: var(--color-text-secondary);">
            Let's design something premium together. Fintech, SaaS, or a product that needs senior craft.
          </p>
        </div>
        <NuxtLink ref="bandCta" to="/services" class="btn-pill btn-pill-accent shrink-0">
          <span class="magnetic-label">Let's talk</span>
        </NuxtLink>
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

/* Sliding indicator behind the filter tabs; x/width tweened by GSAP. */
.filter-pill {
  position: absolute;
  left: 0;
  top: 0;
  width: 0;
  height: 0;
  border-radius: 9999px;
  background: var(--color-text);
  box-shadow: var(--shadow-sm);
  transition: opacity 0.2s ease;
}

@media (prefers-reduced-motion: reduce) {
  .hero-status-dot { animation: none; }
}
</style>
