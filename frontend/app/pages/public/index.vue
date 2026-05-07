<script setup lang="ts">
definePageMeta({ layout: 'public' })

import type { Project } from '~/data/projects'
import ProjectCard from '~/components/shared/ProjectCard.vue'
import SectionHeader from '~/components/shared/SectionHeader.vue'

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

const runtimeConfig = useRuntimeConfig()
const { data: apiResponse } = await useFetch<{ data: ApiProject[] }>(
  `${runtimeConfig.public.apiBase}/api/v1/projects`,
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

const heroBadge = ref<HTMLElement | null>(null)
const heroLine1 = ref<HTMLElement | null>(null)
const heroLine2 = ref<HTMLElement | null>(null)
const heroSub   = ref<HTMLElement | null>(null)
const heroCtas  = ref<HTMLElement | null>(null)
const statValues = ref<HTMLElement[]>([])

onMounted(async () => {
  if (import.meta.server) return

  const targets = [heroBadge.value, heroLine1.value, heroLine2.value, heroSub.value, heroCtas.value].filter(Boolean) as HTMLElement[]
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches

  if (prefersReduced) {
    targets.forEach((el) => { el.style.opacity = '1'; el.style.transform = 'none' })
    statValues.value.forEach((el) => { if (el) el.textContent = el.dataset.value || '0' })
    return
  }

  const { $gsap } = useNuxtApp() as unknown as { $gsap: typeof import('gsap').default }

  await nextTick()
  $gsap.set(targets, { opacity: 0, y: 20 })

  requestAnimationFrame(() => {
    $gsap.to(targets,
      { opacity: 1, y: 0, duration: 0.8, ease: 'power3.out', stagger: 0.09 },
    )
  })

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return
      const el = entry.target as HTMLElement
      const target = parseInt(el.dataset.value || '0', 10)
      $gsap.fromTo({ n: 0 }, { n: 0 },
        {
          n: target,
          duration: 1.4,
          ease: 'power2.out',
          onUpdate() {
            el.textContent = Math.floor(this.targets()[0].n).toString()
          },
        },
      )
      observer.unobserve(el)
    })
  }, { threshold: 0.4 })

  statValues.value.forEach(el => el && observer.observe(el))
})

useScrollReveal('.reveal')
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
        <span class="size-1.5 rounded-full" style="background: var(--color-success); box-shadow: 0 0 0 4px rgba(48,209,88,0.18);" />
        <span class="text-[12px] font-medium" style="color: var(--color-text);">
          Open to freelance
        </span>
      </div>

      <h1
        class="mt-7 leading-[1.02] tracking-tighter font-semibold max-w-5xl"
        style="font-size: clamp(52px, 9vw, 112px);"
      >
        <span ref="heroLine1" class="block">I craft interfaces</span>
        <span ref="heroLine2" class="block text-gradient">people actually enjoy.</span>
      </h1>

      <p
        ref="heroSub"
        class="mt-7 max-w-xl text-[19px] leading-normal"
        style="color: var(--color-text-secondary);"
      >
        UI/UX engineer with 7 years of building — fintech, SaaS, and products that need real craft.
        Vue · Nuxt · Laravel · Docker.
      </p>

      <div ref="heroCtas" class="mt-9 flex flex-wrap items-center justify-center gap-3">
        <NuxtLink to="/projects" class="btn-pill btn-pill-accent">
          See my work
        </NuxtLink>
        <a
          href="https://baihaqie.com"
          target="_blank"
          rel="noopener"
          class="btn-pill btn-pill-ghost"
        >
          Full portfolio →
        </a>
      </div>
    </section>

    <!-- STATS -->
    <section class="border-y" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
      <div class="max-w-7xl mx-auto grid grid-cols-2 md:grid-cols-4">
        <div
          v-for="(s, i) in stats"
          :key="s.label"
          class="px-6 py-14 text-center"
          :style="{
            borderRight: i < stats.length - 1 ? '1px solid var(--color-border)' : 'none',
            borderBottom: i < 2 ? '1px solid var(--color-border)' : 'none'
          }"
          :class="{ 'md:border-b-0!': true }"
        >
          <div class="text-4xl md:text-5xl font-semibold tracking-tight tabular-nums">
            <span ref="statValues" :data-value="s.value">0</span>{{ s.suffix }}
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

      <div class="flex flex-wrap gap-2 mb-10">
        <button
          v-for="f in homeFilters" :key="f"
          class="text-[13px] px-4 py-1.5 rounded-full border transition-all duration-200"
          :style="{
            borderColor: activeFilter === f ? 'transparent' : 'var(--color-border-strong)',
            background: activeFilter === f ? 'var(--color-text)' : 'transparent',
            color: activeFilter === f ? 'var(--color-bg)' : 'var(--color-text-secondary)',
            fontWeight: activeFilter === f ? 500 : 400,
            boxShadow: activeFilter === f ? 'var(--shadow-sm)' : 'none'
          }"
          @click="activeFilter = f"
        >
          {{ f }}
        </button>
      </div>

      <Transition name="page" mode="out-in">
        <div :key="activeFilter" class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
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
      </Transition>
    </section>

    <!-- CTA BANNER -->
    <section class="relative overflow-hidden border-y" :style="{ borderColor: 'var(--color-border)' }">
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
            Let's design something premium together — fintech, SaaS, or a product that needs senior craft.
          </p>
        </div>
        <NuxtLink to="/services" class="btn-pill btn-pill-accent shrink-0">
          Let's talk
        </NuxtLink>
      </div>
    </section>
  </div>
</template>
