<script setup lang="ts">
import SectionHeader from '~/components/shared/SectionHeader.vue'
import ProjectCard from '~/components/shared/ProjectCard.vue'
import CollabLockup from '~/components/public/CollabLockup.vue'
import CollabModes from '~/components/public/CollabModes.vue'
import CollabInquiryForm from '~/components/public/CollabInquiryForm.vue'
import { collaborationModeLabels, type CollaborationPartner } from '~/data/collaborations'
import type { Project } from '~/data/projects'
import { MOTION } from '~/utils/motion'

definePageMeta({ layout: 'public' })

// Axel Nova × TRLabs — the first studio collaboration, hand-built rather than
// templated. The page sits in the .collab-trlabs scope (main.css), which swaps
// the accent tokens for the co-brand. Facts, story, services and shared
// projects come from the seeded partner row (docs/global/COLLABORATIONS.md);
// the layout and section copy live here.
usePublicSeo({
  title: 'Axel Nova × TRLabs | Studio Collaboration in Malaysia',
  description: 'Axel Nova Ventures and TRLabs are two founder-led studios in Malaysia working as one build team on websites, business systems, mobile apps and AI products.',
  path: '/collaborations/trlabs',
  card: {
    label: 'COLLABORATION',
    headline: 'Axel Nova × TRLabs',
    subline: 'Two founder-led studios. One build team.',
  },
})

const { data, error } = await useFetch<{ data: CollaborationPartner }>(
  `${useApiBase()}/api/v1/collaborations/trlabs`,
  { key: 'collaboration-trlabs' },
)
const partner = computed(() => data.value?.data)

if (import.meta.server && !partner.value) {
  setResponseStatus(useRequestEvent()!, error.value?.statusCode ?? 404, 'Collaboration not found')
}

const sinceYear = computed(() => partner.value?.partner_since?.slice(0, 4) ?? null)
const story = computed(() => (partner.value?.story ?? '').split(/\n{2,}/).filter(Boolean))
const websiteHost = computed(() => partner.value?.website_url?.replace(/^https?:\/\//, '').replace(/\/$/, '') ?? null)

const facts = computed(() => {
  const p = partner.value
  if (!p) return []
  return [
    { label: 'Founder', value: p.founder_name, hint: p.founder_role },
    { label: 'Based in', value: p.location, hint: null },
    { label: 'Builds', value: p.services.join(', '), hint: null },
    { label: 'Partners since', value: sinceYear.value, hint: null },
  ].filter(f => f.value)
})

const founders = computed(() => [
  {
    name: 'Ahmad Baihaqie',
    role: 'Founder, Axel Nova Ventures',
    mark: '/favicon/web-app-manifest-512x512.png',
    markClass: 'size-full',
    bio: 'Designs and builds websites and custom business systems, drawing on years across UI/UX, frontend engineering and fintech.',
  },
  {
    name: partner.value?.founder_name ?? 'TRLabs',
    role: [partner.value?.founder_role, 'TRLabs'].filter(Boolean).join(', '),
    mark: partner.value?.symbol_url ?? '',
    markClass: 'w-[74%]',
    bio: 'Leads TRLabs projects directly, from the first conversation through design, build and launch.',
  },
])

// What each studio leads on in a shared build. Editorial — confirm with TRLabs
// before changing; it is the clearest promise on the page.
const strengths = computed(() => [
  {
    name: 'Axel Nova Ventures',
    mark: '/favicon/web-app-manifest-512x512.png',
    markClass: 'size-full',
    items: ['Websites and booking portals', 'Custom business systems and admin dashboards', 'Backend, integrations and hosting'],
  },
  {
    name: 'TRLabs',
    mark: partner.value?.symbol_url ?? '',
    markClass: 'w-[74%]',
    items: ['Brand-led websites and ecommerce', 'Mobile apps', 'AI products'],
  },
])

const steps = [
  { title: 'Send one brief', desc: 'Tell us what you want to build. It comes to Axel Nova and both studios read it.' },
  { title: 'We plan it together', desc: 'We agree who leads each part, so nothing falls between the two teams.' },
  { title: 'One proposal, one contact', desc: 'You get a single proposal, a single timeline and one person to talk to.' },
]

const sharedProjects = computed(() => (partner.value?.projects ?? []).map(p => ({
  card: {
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
  } satisfies Project,
  mode: collaborationModeLabels[p.collaboration.mode],
  roleNote: p.collaboration.role_note,
})))

// Hero entrance — page-specific (CLAUDE.md). The logos arrive as bare tiles
// and meet at the ×; each tile then grows into its card — the card's surface
// is clipped out of the tile (clip-path) while the logo glides up into place —
// and the studio names rise in; then the copy. The card layout itself never
// changes size, so nothing re-lays out mid-animation. The SSR HTML is the
// finished state; the start state is set here.
//
// Pre-paint hold: a server-rendered head script marks <html> before first
// paint so the finished hero doesn't flash before collapsing to tiles. It is
// lifted the moment the entrance takes over (or on any bail-out below), and a
// CSS failsafe reveals the hero after 4s regardless, so a JS failure never
// leaves the heading hidden. Reduced-motion visitors are never held. A
// client-side navigation needs no hold: onMounted runs before the next paint.
const INTRO_ATTR = 'data-collab-intro'
if (import.meta.server) {
  useHead({
    script: [{
      key: 'collab-intro-hold',
      tagPosition: 'head',
      innerHTML: `try{if(!matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.setAttribute('${INTRO_ATTR}','')}catch(e){}`,
    }],
  })
}

const heroEl = ref<HTMLElement | null>(null)
const motion = useMotion()
let heroTl: gsap.core.Timeline | null = null
const releaseHold = () => document.documentElement.removeAttribute(INTRO_ATTR)

onMounted(() => {
  if (import.meta.server) return
  if (!heroEl.value || motion.reduced) return releaseHold()
  const { gsap } = motion
  const q = (sel: string) => [...heroEl.value!.querySelectorAll<HTMLElement>(sel)]

  const cards = q('[data-lockup="left"], [data-lockup="right"]')
  const chromes = q('[data-lockup-chrome]')
  const plates = q('[data-lockup-plate]')
  const names = q('[data-lockup-name]')
  const cross = q('[data-lockup="x"]')
  const copy = q('[data-hero]')
  if (cards.length !== 2 || chromes.length !== 2 || plates.length !== 2) return releaseHold()

  // Finished geometry → the start pose: each logo sits at its card's centre
  // (level with the ×), and the surface is clipped to hug the tile there.
  const card = cards[0]!.getBoundingClientRect()
  const plate = plates[0]!.getBoundingClientRect()
  const dy = (card.top + card.height / 2) - (plate.top + plate.height / 2)
  const top = plate.top - card.top + dy
  const left = plate.left - card.left
  const bottom = card.height - top - plate.height
  const right = card.width - left - plate.width
  const plateRadius = parseFloat(getComputedStyle(plates[0]!).borderTopLeftRadius)
  const cardRadius = parseFloat(getComputedStyle(chromes[0]!).borderTopLeftRadius)
  // The end clip sits OUTSIDE the card (negative inset) so the card's shadow is
  // already whole when the clip is cleared — no shadow pop at the finish.
  const SHADOW_ROOM = 48
  const clipFrom = `inset(${top}px ${right}px ${bottom}px ${left}px round ${plateRadius}px)`
  const clipTo = `inset(${-SHADOW_ROOM}px ${-SHADOW_ROOM}px ${-SHADOW_ROOM}px ${-SHADOW_ROOM}px round ${cardRadius + SHADOW_ROOM}px)`

  // MOTION rule 10: no CSS transition on what GSAP animates — the hover
  // transitions are suspended until the entrance lets go.
  const lockup = [...cards, ...chromes, ...plates, ...cross]
  gsap.set(lockup, { transition: 'none' })
  gsap.set(cards, { opacity: 0 })
  gsap.set(cards[0]!, { x: -56 })
  gsap.set(cards[1]!, { x: 56 })
  gsap.set(plates, { y: dy })
  gsap.set(chromes, { clipPath: clipFrom, opacity: 0 })
  gsap.set(names, { opacity: 0, y: 10 })
  gsap.set(cross, { opacity: 0, scale: 0.4, rotate: -90 })
  gsap.set(copy, { opacity: 0, y: 20 })
  releaseHold()

  const grow = { duration: MOTION.dur.hero, ease: MOTION.ease.inout }
  heroTl = gsap.timeline({
    onComplete: () => {
      gsap.set([...lockup, ...names], { clearProps: 'transition,opacity,transform,clipPath' })
      gsap.set(copy, { clearProps: 'opacity,transform' })
    },
  })
    // 1. Tiles slide in and meet at the ×.
    .to(cards, { opacity: 1, x: 0, duration: MOTION.dur.hero, ease: MOTION.ease.hero }, 0)
    .to(cross, { opacity: 1, scale: 1, rotate: 0, duration: MOTION.dur.slow, ease: MOTION.ease.hero }, 0.35)
    // 2. Each tile grows into its card: surface unclipped, logo up into place.
    .to(chromes, { opacity: 1, duration: 0.2, ease: 'none' }, 0.85)
    // fromTo, not to: the browser normalises the computed start to the short
    // `inset(33px 26px round 24px)` form, and GSAP would then pair the numbers
    // wrongly (the radius collapses mid-morph). Both explicit strings carry all
    // four insets + the radius, so they interpolate value for value.
    .fromTo(chromes, { clipPath: clipFrom }, { clipPath: clipTo, immediateRender: false, ...grow }, 0.85)
    .to(plates, { y: 0, ...grow }, 0.85)
    // 3. Names rise in once the cards have room for them, then the copy.
    .to(names, { opacity: 1, y: 0, duration: MOTION.dur.base, ease: MOTION.ease.out }, 1.45)
    .to(copy, { opacity: 1, y: 0, duration: MOTION.dur.slow, ease: MOTION.ease.out, stagger: MOTION.stagger.base }, 1.6)
})

onUnmounted(() => {
  heroTl?.kill()
  releaseHold()
})

useScrollReveal('.reveal')
</script>

<template>
  <!-- overflow-x-clip lives on the full-width root, not the hero: it keeps the
       entrance's off-screen cards and the full-bleed glow from adding a
       horizontal scrollbar, while letting the glow reach the screen edges.
       `clip` (not `hidden`) is no scroll container, so the sticky panel below
       still sticks. -->
  <div class="collab-trlabs overflow-x-clip">
    <div v-if="partner" class="max-w-7xl mx-auto px-6 pt-24 pb-32">
      <!-- Hero -->
      <section
        ref="heroEl"
        class="relative min-h-[64vh] flex flex-col items-center justify-center text-center mb-32"
      >
        <span aria-hidden="true" class="collab-hero-glow">
          <span class="collab-hero-glow-blob collab-hero-glow-left" />
          <span class="collab-hero-glow-blob collab-hero-glow-right" />
        </span>
        <!-- The two named cards ARE the page heading (h1 text: "Axel Nova
             Ventures × TRLabs") — no second headline repeating the pair. -->
        <CollabLockup
          as="h1"
          named
          size="lg"
          class="relative collab-hero-hold"
          :partner-name="partner.name"
          :partner-symbol="partner.symbol_url"
        />

        <p data-hero class="relative collab-hero-hold eyebrow mt-12 mb-5">
          Collaboration<template v-if="sinceYear"> · Since {{ sinceYear }}</template>
        </p>
        <p data-hero class="relative collab-hero-hold max-w-xl text-balance md:max-w-none md:whitespace-nowrap text-[18px] leading-relaxed" style="color: var(--color-text-secondary);">
          Two founder-led studios in Malaysia. One build team for projects that need both.
        </p>
        <a data-hero href="#start" class="relative collab-hero-hold btn-pill btn-pill-accent mt-9">
          Start a project together
        </a>
      </section>

      <!-- About the partner -->
      <section class="mb-32 reveal">
        <div class="grid lg:grid-cols-[1fr_320px] gap-10 lg:gap-16 items-start">
          <div class="space-y-5">
            <p class="eyebrow mb-2">The partner</p>
            <h2 class="text-3xl font-semibold tracking-tight mb-8" style="color: var(--color-text);">
              Meet {{ partner.name }}
            </h2>
            <div v-if="partner.logo_url" class="collab-logo-plate inline-flex rounded-2xl px-6 py-4 mb-3">
              <img :src="partner.logo_url" :alt="`${partner.name} logo`" class="h-9 sm:h-11 w-auto">
            </div>
            <p
              v-for="(para, i) in story"
              :key="i"
              class="text-[17px] leading-[1.65]"
              :style="{ color: i === 0 ? 'var(--color-text)' : 'var(--color-text-secondary)' }"
            >
              {{ para }}
            </p>
            <p v-if="partner.tagline" class="text-[20px] font-semibold tracking-tight pt-2 text-gradient inline-block">
              {{ partner.tagline }}
            </p>
          </div>

          <!-- At a glance -->
          <div
            class="rounded-3xl border p-7 space-y-5 lg:sticky lg:top-28"
            :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
          >
            <p class="text-[11px] font-medium uppercase tracking-widest" style="color: var(--color-text-tertiary);">
              At a glance
            </p>
            <div class="divide-y" :style="{ borderColor: 'var(--color-border)' }">
              <div
                v-for="f in facts"
                :key="f.label"
                class="py-4 first:pt-0"
                :style="{ borderColor: 'var(--color-border)' }"
              >
                <p class="text-[11px] font-medium uppercase tracking-wide mb-1" style="color: var(--color-text-tertiary);">
                  {{ f.label }}
                </p>
                <p class="text-[15px] font-medium" style="color: var(--color-text);">{{ f.value }}</p>
                <p v-if="f.hint" class="text-[13px] mt-0.5" style="color: var(--color-text-secondary);">{{ f.hint }}</p>
              </div>
              <div v-if="partner.website_url" class="pt-4" :style="{ borderColor: 'var(--color-border)' }">
                <p class="text-[11px] font-medium uppercase tracking-wide mb-1" style="color: var(--color-text-tertiary);">
                  Website
                </p>
                <a
                  :href="partner.website_url"
                  target="_blank"
                  rel="noopener"
                  class="text-[15px] font-medium inline-flex items-center gap-1 link-underline"
                  style="color: var(--color-accent);"
                >
                  {{ websiteHost }}
                  <UIcon name="i-lucide-arrow-up-right" class="size-3.5" />
                </a>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Founders -->
      <section class="mb-32 reveal">
        <SectionHeader
          eyebrow="The founders"
          title="Two founders, one team."
          subtitle="Each studio is led by the person who does the work. On a shared project, you hear from both of us."
        />
        <div class="grid md:grid-cols-2 gap-5">
          <div
            v-for="f in founders"
            :key="f.name"
            class="reveal rounded-3xl border p-7 flex items-start gap-5"
            :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
          >
            <span class="collab-logo-plate size-16 rounded-2xl flex items-center justify-center shrink-0">
              <img v-if="f.mark" :src="f.mark" alt="" :class="[f.markClass, 'object-contain']">
            </span>
            <div class="min-w-0">
              <p class="text-[18px] font-semibold tracking-tight" style="color: var(--color-text);">{{ f.name }}</p>
              <p class="text-[13px] mb-3" style="color: var(--color-accent);">{{ f.role }}</p>
              <p class="text-[14px] leading-relaxed" style="color: var(--color-text-secondary);">{{ f.bio }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- How we work together -->
      <section class="mb-32 reveal">
        <SectionHeader
          eyebrow="How we work together"
          title="Three ways we take on a project."
          subtitle="Some projects need both studios from day one. Others need one studio, with the other on hand for a specific part."
        />
        <CollabModes />
      </section>

      <!-- What each studio brings -->
      <section class="mb-32 reveal">
        <SectionHeader
          eyebrow="Strengths"
          title="What each studio brings."
          subtitle="On a shared build, each part is led by the studio that does it every day."
        />
        <div class="grid md:grid-cols-2 gap-5">
          <div
            v-for="s in strengths"
            :key="s.name"
            class="reveal rounded-3xl border p-7"
            :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
          >
            <div class="flex items-center gap-3 mb-6">
              <span class="collab-logo-plate size-10 rounded-xl flex items-center justify-center shrink-0">
                <img v-if="s.mark" :src="s.mark" alt="" :class="[s.markClass, 'object-contain']">
              </span>
              <p class="text-[17px] font-semibold tracking-tight" style="color: var(--color-text);">{{ s.name }}</p>
            </div>
            <ul class="space-y-3">
              <li v-for="item in s.items" :key="item" class="flex items-start gap-3">
                <UIcon name="i-lucide-check" class="size-4 mt-0.5 shrink-0" style="color: var(--color-accent);" />
                <span class="text-[15px] leading-relaxed" style="color: var(--color-text-secondary);">{{ item }}</span>
              </li>
            </ul>
          </div>
        </div>
      </section>

      <!-- Shared work -->
      <section class="mb-32 reveal">
        <SectionHeader eyebrow="Shared work" title="Built together." />
        <div v-if="sharedProjects.length" class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div v-for="p in sharedProjects" :key="p.card.id" class="reveal flex flex-col gap-3">
            <ProjectCard :project="p.card" />
            <p class="text-[13px] leading-relaxed px-1" style="color: var(--color-text-secondary);">
              <span class="font-medium" style="color: var(--color-accent);">{{ p.mode }}</span>
              <template v-if="p.roleNote"> · {{ p.roleNote }}</template>
            </p>
          </div>
        </div>
        <div
          v-else
          class="rounded-3xl border border-dashed p-10 sm:p-14 text-center"
          :style="{ borderColor: 'var(--color-border-strong)' }"
        >
          <div
            class="size-12 rounded-2xl flex items-center justify-center mx-auto mb-5"
            :style="{ background: 'var(--color-accent-soft)' }"
          >
            <UIcon name="i-lucide-hammer" class="size-5" style="color: var(--color-accent);" />
          </div>
          <p class="text-[18px] font-semibold tracking-tight mb-2" style="color: var(--color-text);">
            Our first shared builds are under way.
          </p>
          <p class="text-[15px] leading-relaxed max-w-md mx-auto" style="color: var(--color-text-secondary);">
            They'll appear here as they launch. Have a project that needs both studios? It could be one of them.
          </p>
        </div>
      </section>

      <!-- Start a project -->
      <section id="start" class="scroll-mt-28 reveal">
        <SectionHeader
          :eyebrow="`Axel Nova × ${partner.name}`"
          title="Start a project together."
          subtitle="Send one brief to both studios. It comes to Axel Nova first, and we plan the work with TRLabs from there."
        />
        <div class="grid lg:grid-cols-[1.4fr_1fr] gap-10 lg:gap-16">
          <CollabInquiryForm variant="partner" :partner-slug="partner.slug" :partner-name="partner.name" />

          <div class="space-y-3 lg:pt-1">
            <p class="text-[11px] font-medium uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">
              What happens next
            </p>
            <div
              v-for="(s, i) in steps"
              :key="s.title"
              class="rounded-2xl border p-5 flex items-start gap-4"
              :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
            >
              <span
                class="size-8 rounded-full flex items-center justify-center shrink-0 text-[13px] font-semibold tabular-nums"
                :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
              >{{ i + 1 }}</span>
              <div>
                <p class="text-[15px] font-semibold tracking-tight mb-1" style="color: var(--color-text);">{{ s.title }}</p>
                <p class="text-[13px] leading-relaxed" style="color: var(--color-text-secondary);">{{ s.desc }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- The partner row is missing or the API is down -->
    <div v-else class="max-w-3xl mx-auto px-6 pt-32 pb-40 text-center">
      <h1 class="text-4xl font-semibold tracking-tight mb-4" style="color: var(--color-text);">
        This collaboration isn't available right now.
      </h1>
      <p class="text-[16px] mb-8" style="color: var(--color-text-secondary);">
        Please try again shortly, or see the studios we work with.
      </p>
      <NuxtLink to="/collaborations" class="btn-pill btn-pill-ghost">All collaborations</NuxtLink>
    </div>
  </div>
</template>

<style scoped>
/* Pre-paint hold (see the hero entrance in <script>): hidden until the entrance
   takes over; the delayed failsafe reveals it anyway if JS never does. Plain
   descendant selector on purpose: scoping tags only the last compound
   (.collab-hero-hold[data-v-…]), whereas `:global(html…) .x` would compile
   to the bare `html[…]` and hide the whole page. */
html[data-collab-intro] .collab-hero-hold {
  opacity: 0;
  animation: collab-hero-failsafe 0.5s ease 4s forwards;
}
@keyframes collab-hero-failsafe {
  to { opacity: 1; }
}

/* Co-brand glow behind the lockup — its own absolutely positioned child
   (UI-STANDARDS hard rule 8). It breaks out of the max-w-7xl container to the
   full screen width, so wide screens have no bare sides; the page root clips
   the overflow. Colours come from the scope's tokens. Vertical extent is a
   share of the hero (not px) so it stays centred on the lockup at any
   viewport height. */
.collab-hero-glow {
  position: absolute;
  top: -8%;
  left: 50%;
  width: 100vw;
  height: 92%;
  transform: translateX(-50%);
  pointer-events: none;
}

/* Axel Nova blue from the left, TRLabs orange from the right. They drift
   toward the middle and back, swelling and brightening as they meet — the two
   studios coming together. Transform + opacity only, and the blur sits on each
   blob (not the wrapper) so the compositor moves it without re-blurring. */
.collab-hero-glow-blob {
  position: absolute;
  top: 0;
  width: 62%;
  height: 100%;
  opacity: 0.7;
  filter: blur(24px);
  animation: collab-glow-meet 9s ease-in-out infinite alternate;
}
.collab-hero-glow-left {
  left: -4%;
  --glow-drift: 6vw;
  background: radial-gradient(50% 50% at 50% 50%, color-mix(in srgb, var(--grad-aurora-blue) 24%, transparent) 0%, transparent 100%);
}
.collab-hero-glow-right {
  right: -4%;
  --glow-drift: -6vw;
  background: radial-gradient(50% 50% at 50% 50%, color-mix(in srgb, var(--collab-trlabs-dot) 24%, transparent) 0%, transparent 100%);
}
@keyframes collab-glow-meet {
  to {
    transform: translateX(var(--glow-drift)) scale(1.1);
    opacity: 1;
  }
}
@media (prefers-reduced-motion: reduce) {
  .collab-hero-glow-blob { animation: none; }
}
</style>
