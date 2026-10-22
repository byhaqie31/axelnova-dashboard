<script setup lang="ts">
import SectionHeader from '~/components/shared/SectionHeader.vue'
import CompanyHeroCard from '~/components/public/CompanyHeroCard.vue'
import { MOTION } from '~/utils/motion'

definePageMeta({ layout: 'public' })

// The studio's own page, told as a story: the mark arrives in its box in the
// hero, then the chapters alternate between the page surface and solid
// full-bleed bands (`.band-soft` grey, one `.band-ink` navy chapter —
// UI-STANDARDS "Story bands"). Copy follows docs/frontend/SITE-COPY.md § Company.
usePublicSeo({
  title: 'About Our Digital Studio in Kuala Lumpur | Axel Nova',
  description: 'Learn how Axel Nova Ventures approaches website design and custom business systems in Kuala Lumpur, from the first conversation to launch.',
  path: '/company',
  card: {
    label: 'THE STUDIO',
    headline: 'A digital studio built around how people use technology.',
  },
})

// The company card — registry facts, as a definition list on the first band.
const facts = [
  { label: 'Registered name', value: 'Axel Nova Ventures' },
  { label: 'Registration number', value: '202603119899 (CA0420977-U)', numeric: true },
  { label: 'Established', value: '2026' },
  { label: 'Based in', value: 'Kuala Lumpur, Malaysia' },
  { label: 'Focus', value: 'Websites and custom business systems', wide: true },
]

// Origin — read paragraph by paragraph; the one in the middle of the viewport
// is the one in focus (see the observer below).
const origin = [
  'Axel Nova Ventures began with a personal decision: Qie wanted to give his work in design and software a home of its own. In 2026, he registered the venture in Kuala Lumpur and began building it as a design-led digital studio.',
  'From the start, the focus has been practical: websites that help businesses present themselves clearly, and custom systems designed around the way their teams work. The studio takes each project from an early idea through design and development, choosing the approach and technology to fit the need.',
  'The company is still young. Client work is its foundation, while the longer ambition is to create original products and ventures under the Axel Nova name. Each project helps define what the company will become.',
]

const nameHalves = [
  {
    letter: 'A',
    word: 'Axel',
    lead: 'Axel draws from “axis”, the point around which things move.',
    body: 'It reflects the structure that keeps a system working as it grows.',
  },
  {
    letter: 'N',
    word: 'Nova',
    lead: 'Nova evokes a new beginning and the energy behind change.',
    body: 'It reflects the urge to keep learning and building.',
  },
]

// How we work — the three pillars as the steps of a project, in order.
const steps = [
  {
    title: 'Start with the user.',
    desc: 'Understand the task, the context and where people get stuck.',
    points: [
      'Who uses it, and in what moment of their day.',
      'Where the current way of working slows people down.',
      'What a good result looks like to them, not just to us.',
    ],
  },
  {
    title: 'Build for real use.',
    desc: 'Choose the technology and level of complexity the project needs.',
    points: [
      'The simplest stack that does the job well.',
      'Features people will actually use, nothing for show.',
      'Room to grow without starting over.',
    ],
  },
  {
    title: 'Make it maintainable.',
    desc: 'Leave clients with clear handover materials and a system they can keep using.',
    points: [
      'Clear handover notes and documentation.',
      'Admin tools your own team can run.',
      'A system that keeps working long after launch.',
    ],
  },
]

// Looking ahead — the long view as three stops on one line.
const horizon = [
  { stage: 'Now', title: 'Client work', desc: 'Websites and custom systems for businesses across Malaysia.', current: true },
  { stage: 'Next', title: 'Products', desc: 'Useful tools that grow out of the problems client work keeps surfacing.', current: false },
  { stage: 'Later', title: 'Ventures', desc: 'New ventures under the Axel Nova name, built on what the studio has learned.', current: false },
]

const motion = useMotion()

// ── Hero entrance — page-specific (CLAUDE.md), the /collaborations/trlabs
// card choreography on one box: the mark arrives from the left inside a small
// box (the card's surface, clipped to the mark's footprint), holds a beat,
// then the box expands into the card — the surface unclips while the mark
// glides up into place — the studio name rises in under it, and the title
// beside it rises while the box is still expanding. The card layout never changes size, so nothing re-lays out
// mid-animation. The SSR HTML is the finished state; the start state is set
// here.
//
// Pre-paint hold (MOTION rule 12): a server-rendered head script marks <html>
// before first paint so the finished hero doesn't flash before collapsing to
// tiles. It is lifted the moment the entrance takes over (or on any bail-out),
// and a CSS failsafe reveals the hero after 4s regardless. Reduced-motion
// visitors are never held.
const INTRO_ATTR = 'data-company-intro'
if (import.meta.server) {
  useHead({
    script: [{
      key: 'company-intro-hold',
      tagPosition: 'head',
      innerHTML: `try{if(!matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.setAttribute('${INTRO_ATTR}','')}catch(e){}`,
    }],
  })
}

const heroEl = ref<HTMLElement | null>(null)
let heroTl: gsap.core.Timeline | null = null
const releaseHold = () => document.documentElement.removeAttribute(INTRO_ATTR)

onMounted(() => {
  if (import.meta.server) return
  if (!heroEl.value || motion.reduced) return releaseHold()
  const { gsap } = motion
  const q = (sel: string) => [...heroEl.value!.querySelectorAll<HTMLElement>(sel)]

  const [card] = q('[data-hero-card="card"]')
  const [chrome] = q('[data-hero-card="chrome"]')
  const [plate] = q('[data-hero-card="plate"]')
  const [name] = q('[data-hero-card="name"]')
  const copy = q('[data-hero]')
  if (!card || !chrome || !plate || !name) return releaseHold()

  // Finished geometry → the start pose: the mark sits at the card's centre
  // and the surface is clipped to hug its footprint there — that clipped
  // surface IS the small box the visitor sees first.
  const cardR = card.getBoundingClientRect()
  const plateR = plate.getBoundingClientRect()
  const dy = (cardR.top + cardR.height / 2) - (plateR.top + plateR.height / 2)
  const top = plateR.top - cardR.top + dy
  const left = plateR.left - cardR.left
  const bottom = cardR.height - top - plateR.height
  const right = cardR.width - left - plateR.width
  const plateRadius = parseFloat(getComputedStyle(plate).borderTopLeftRadius)
  const cardRadius = parseFloat(getComputedStyle(chrome).borderTopLeftRadius)
  // The end clip sits OUTSIDE the card (negative inset) so the card's shadow is
  // already whole when the clip is cleared — no shadow pop at the finish.
  const SHADOW_ROOM = 48
  const clipFrom = `inset(${top}px ${right}px ${bottom}px ${left}px round ${plateRadius}px)`
  const clipTo = `inset(${-SHADOW_ROOM}px ${-SHADOW_ROOM}px ${-SHADOW_ROOM}px ${-SHADOW_ROOM}px round ${cardRadius + SHADOW_ROOM}px)`

  // MOTION rule 10: no CSS transition on what GSAP animates — the hover
  // transitions are suspended until the entrance lets go.
  const box = [card, chrome, plate]
  gsap.set(box, { transition: 'none' })
  gsap.set(card, { opacity: 0, x: -56 })
  gsap.set(plate, { y: dy })
  gsap.set(chrome, { clipPath: clipFrom })
  gsap.set(name, { opacity: 0, y: 10 })
  gsap.set(copy, { opacity: 0, y: 20 })
  releaseHold()

  const grow = { duration: MOTION.dur.hero, ease: MOTION.ease.inout }
  heroTl = gsap.timeline({
    onComplete: () => {
      gsap.set([...box, name], { clearProps: 'transition,opacity,transform,clipPath' })
      gsap.set(copy, { clearProps: 'opacity,transform' })
    },
  })
    // 1. The mark slides in from the left inside its small box.
    .to(card, { opacity: 1, x: 0, duration: MOTION.dur.hero, ease: MOTION.ease.hero }, 0)
    // 2. After a beat the box expands into the card: surface unclipped, mark
    //    up into place. fromTo with explicit four-value strings — MOTION rule 11.
    .fromTo(chrome, { clipPath: clipFrom }, { clipPath: clipTo, immediateRender: false, ...grow }, 0.9)
    .to(plate, { y: 0, ...grow }, 0.9)
    // 3. The title rises beside the expanding box, then the studio name under the mark.
    .to(copy, { opacity: 1, y: 0, duration: MOTION.dur.slow, ease: MOTION.ease.out, stagger: MOTION.stagger.base }, 1.2)
    .to(name, { opacity: 1, y: 0, duration: MOTION.dur.base, ease: MOTION.ease.out }, 1.5)
})

// ── Origin reading focus. The paragraph crossing the middle band of the
// viewport reads in full text colour; the others sit back in secondary. State
// only (a class), no motion — so it runs on top of Lenis without ScrollTrigger,
// and the SSR HTML already marks the first paragraph so JS-off keeps an
// emphasis. Reduced motion skips the observer and the CSS shows every
// paragraph in full colour instead.
const proseEl = ref<HTMLElement | null>(null)
let focusObserver: IntersectionObserver | null = null

onMounted(() => {
  if (import.meta.server || !proseEl.value || motion.reduced || !('IntersectionObserver' in window)) return
  const paras = [...proseEl.value.querySelectorAll<HTMLElement>('[data-focus]')]
  focusObserver = new IntersectionObserver((entries) => {
    entries.forEach(e => e.target.classList.toggle('is-focus', e.isIntersecting))
  }, { rootMargin: '-38% 0px -38% 0px', threshold: 0 })
  paras.forEach(p => focusObserver!.observe(p))
})

// ── How we work — a tablist that tells itself. One continuous line runs under
// the whole row: each step owns a third of it. Once the stepper scrolls into
// view the fill creeps through the active step's third over STEP_HOLD and the
// next step takes over where it stopped; hovering or focusing the box pauses
// it, and the first click or key press hands over for good — the fill then
// settles at the end of the chosen step's third as a plain position marker.
// GSAP owns the fill's transform (pause / resume / hand-over are exact); the
// CSS `--fill` default is what SSR, JS-off and reduced motion show.
const STEP_HOLD = 7
const stepperEl = ref<HTMLElement | null>(null)
const fillEl = ref<HTMLElement | null>(null)
const tabEls = ref<(HTMLButtonElement | null)[]>([])
const activeStep = ref(0)
const autoplay = ref(false)
const stepperPaused = ref(false)
let stepperObserver: IntersectionObserver | null = null
let fillTween: gsap.core.Tween | null = null

// Where the line stands once step `i` is done: the end of its third.
const segmentEnd = (i: number) => (i + 1) / steps.length

// Autoplay: fill the active step's third, then move on.
const runStep = (i: number) => {
  if (!fillEl.value) return
  fillTween?.kill()
  fillTween = motion.gsap.fromTo(fillEl.value,
    { scaleX: i / steps.length },
    {
      scaleX: segmentEnd(i),
      duration: STEP_HOLD,
      ease: 'none',
      onComplete: () => { if (autoplay.value) selectStep(activeStep.value + 1, false) },
    },
  )
  if (stepperPaused.value) fillTween.pause()
}
// Hand-over: the line settles at the end of the chosen step's third.
const settleFill = (i: number) => {
  if (!fillEl.value) return
  fillTween?.kill()
  fillTween = null
  if (motion.reduced) return motion.gsap.set(fillEl.value, { scaleX: segmentEnd(i) })
  fillTween = motion.gsap.to(fillEl.value, { scaleX: segmentEnd(i), duration: MOTION.dur.fast, ease: MOTION.ease.out })
}

const selectStep = (i: number, byUser = true) => {
  activeStep.value = (i + steps.length) % steps.length
  if (byUser) {
    autoplay.value = false
    settleFill(activeStep.value)
  }
  else {
    runStep(activeStep.value)
  }
}
watch(stepperPaused, (paused) => {
  if (!autoplay.value || !fillTween) return
  if (paused) fillTween.pause()
  else fillTween.resume()
})
const onTabKeydown = (e: KeyboardEvent) => {
  const map: Record<string, number> = {
    ArrowRight: activeStep.value + 1,
    ArrowLeft: activeStep.value - 1,
    Home: 0,
    End: steps.length - 1,
  }
  const next = map[e.key]
  if (next === undefined) return
  e.preventDefault()
  selectStep(next)
  tabEls.value[activeStep.value]?.focus()
}

onMounted(() => {
  if (import.meta.server || !stepperEl.value || motion.reduced || !('IntersectionObserver' in window)) return
  stepperObserver = new IntersectionObserver((entries) => {
    if (!entries.some(e => e.isIntersecting)) return
    autoplay.value = true
    runStep(activeStep.value)
    stepperObserver?.disconnect()
    stepperObserver = null
  }, { threshold: 0.4 })
  stepperObserver.observe(stepperEl.value)
})

onUnmounted(() => {
  heroTl?.kill()
  releaseHold()
  focusObserver?.disconnect()
  stepperObserver?.disconnect()
  fillTween?.kill()
})

useScrollReveal('.reveal')
</script>

<template>
  <!-- overflow-x-clip on the full-width root: it keeps the entrance's off-screen
       cards and the full-bleed hero glow from adding a horizontal scrollbar.
       `clip` (not `hidden`) is no scroll container, so the sticky chapter
       marker below still sticks. -->
  <div class="overflow-x-clip">
    <!-- Hero — the box with the mark on the left, the title on the right
         (stacked and centred below lg). -->
    <section
      ref="heroEl"
      class="relative max-w-7xl mx-auto px-6 pt-20 pb-24 min-h-[68vh] grid lg:grid-cols-[auto_minmax(0,1fr)] gap-12 lg:gap-20 items-center"
    >
      <span aria-hidden="true" class="company-hero-glow">
        <span class="company-hero-glow-blob company-hero-glow-left" />
        <span class="company-hero-glow-blob company-hero-glow-right" />
      </span>

      <CompanyHeroCard class="relative company-hero-hold mx-auto lg:mx-0" />

      <div class="relative text-center lg:text-left">
        <p data-hero class="company-hero-hold eyebrow mb-5">
          The studio · Kuala Lumpur
        </p>
        <h1
          data-hero
          class="company-hero-hold text-[clamp(34px,4.6vw,60px)] font-semibold tracking-tighter leading-[1.06] max-w-3xl mx-auto lg:mx-0 text-balance"
          style="color: var(--color-text);"
        >
          A digital studio built around <span class="text-gradient">how people use technology.</span>
        </h1>
        <p
          data-hero
          class="company-hero-hold mt-7 max-w-lg mx-auto lg:mx-0 text-[17px] leading-relaxed"
          style="color: var(--color-text-secondary);"
        >
          Axel Nova Ventures is based in Kuala Lumpur and works with businesses across Malaysia. The
          studio designs and builds websites and custom systems with clear interfaces, practical
          features and room to grow.
        </p>
      </div>
    </section>

    <!-- The company card — registry facts on the first solid band. -->
    <section class="band-soft reveal">
      <div class="max-w-7xl mx-auto px-6 py-10">
        <dl class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4">
          <div
            v-for="f in facts"
            :key="f.label"
            class="band-tile rounded-2xl px-5 py-4"
            :class="f.wide && 'sm:col-span-2 lg:col-span-1'"
          >
            <dt class="text-[11px] font-medium uppercase tracking-wide mb-1.5" style="color: var(--color-text-tertiary);">
              {{ f.label }}
            </dt>
            <dd
              class="text-[15px] font-medium leading-snug"
              :class="f.numeric && 'tabular-nums'"
              style="color: var(--color-text);"
            >
              {{ f.value }}
            </dd>
          </div>
        </dl>
      </div>
    </section>

    <!-- Origin — the chapter marker stays put while the story scrolls past it. -->
    <section class="max-w-7xl mx-auto px-6 py-32 reveal">
      <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)] gap-10 lg:gap-20 items-start">
        <div class="lg:sticky lg:top-28">
          <SectionHeader eyebrow="Origin" title="How Axel Nova Ventures began" />
        </div>

        <div ref="proseEl" class="story-prose space-y-6 max-w-[62ch] lg:pt-3">
          <p
            v-for="(para, i) in origin"
            :key="i"
            data-focus
            :class="i === 0 && 'is-focus'"
            class="text-[18px] leading-[1.7]"
          >
            {{ para }}
          </p>
          <p class="story-lead text-[21px] font-semibold tracking-tight leading-snug pt-2" style="color: var(--color-text);">
            A project begins by understanding the people, process and constraints involved. The
            design and technology choices follow from there.
          </p>
        </div>
      </div>
    </section>

    <!-- The name — the two ideas behind it. -->
    <section class="band-soft reveal">
      <div class="max-w-7xl mx-auto px-6 py-32">
        <SectionHeader eyebrow="The name" title="The story behind the name." />

        <div class="grid md:grid-cols-2 gap-5">
          <article
            v-for="half in nameHalves"
            :key="half.word"
            class="name-box band-tile rounded-3xl p-8 md:p-10 relative overflow-hidden"
          >
            <span aria-hidden="true" class="name-box-letter">{{ half.letter }}</span>
            <h3 class="eyebrow mb-5">{{ half.word }}</h3>
            <p class="relative text-[24px] font-semibold tracking-tight leading-snug mb-4 max-w-md" style="color: var(--color-text);">
              {{ half.lead }}
            </p>
            <p class="relative text-[15px] leading-relaxed max-w-md" style="color: var(--color-text-secondary);">
              {{ half.body }}
            </p>
          </article>
        </div>

        <!-- Together: the mark, with the two ideas resolved into one line. -->
        <div class="band-tile rounded-2xl px-6 py-5 mt-5 flex items-center gap-4">
          <span class="size-10 rounded-xl flex items-center justify-center shrink-0" style="background: var(--color-accent-soft);">
            <img src="/favicon/web-app-manifest-512x512.png" alt="" aria-hidden="true" class="size-8 object-contain">
          </span>
          <p class="text-[15px] sm:text-[16px] leading-relaxed" style="color: var(--color-text);">
            <strong>Axel Nova</strong> brings those ideas together: strong foundations and the freedom to
            move forward.
          </p>
        </div>
      </div>
    </section>

    <!-- How we work — the three pillars as the steps of a project. -->
    <section class="max-w-7xl mx-auto px-6 py-32 reveal">
      <SectionHeader
        eyebrow="How we work"
        title="Three habits behind every project."
        subtitle="The common thread is simple: build things that solve real problems and feel clear to use."
      />

      <div
        ref="stepperEl"
        class="stepper rounded-3xl border overflow-hidden"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
        @mouseenter="stepperPaused = true"
        @mouseleave="stepperPaused = false"
        @focusin="stepperPaused = true"
        @focusout="stepperPaused = false"
      >
        <div role="tablist" aria-label="How we work" class="relative grid grid-cols-3 p-2">
          <!-- The active-step box: one element that slides from 01 to 02 to 03
               (the three tabs are equal thirds, so its position is `--i` of
               its own width — no measuring, resize-proof). -->
          <span aria-hidden="true" class="step-highlight" :style="{ '--i': activeStep }" />
          <button
            v-for="(s, i) in steps"
            :id="`how-tab-${i}`"
            :key="s.title"
            :ref="el => { tabEls[i] = el as HTMLButtonElement | null }"
            type="button"
            role="tab"
            :aria-selected="i === activeStep"
            :aria-controls="`how-panel-${i}`"
            :tabindex="i === activeStep ? 0 : -1"
            class="step-tab relative text-left px-4 sm:px-6 py-4 sm:py-5 rounded-2xl cursor-pointer"
            :class="{ 'is-active': i === activeStep }"
            @click="selectStep(i)"
            @keydown="onTabKeydown"
          >
            <span class="block text-[12px] font-medium tabular-nums mb-1.5" style="color: var(--color-text-tertiary);">
              0{{ i + 1 }}
            </span>
            <span class="step-tab-title block text-[13px] sm:text-[16px] font-semibold tracking-tight leading-snug">
              {{ s.title }}
            </span>
          </button>
        </div>

        <!-- The one line under the row: the track is the divider, the fill
             shows how far through the three steps the story is. -->
        <div class="stepper-track" aria-hidden="true">
          <span ref="fillEl" class="stepper-fill" :style="{ '--fill': segmentEnd(activeStep) }" />
        </div>

        <!-- Every panel is rendered in the same grid cell, so the box keeps the
             height of the tallest step and nothing jumps on a swap. -->
        <div class="grid">
          <div
            v-for="(s, i) in steps"
            :id="`how-panel-${i}`"
            :key="s.title"
            role="tabpanel"
            :aria-labelledby="`how-tab-${i}`"
            :aria-hidden="i !== activeStep"
            class="step-panel [grid-area:1/1] p-7 sm:p-10 lg:p-12 grid lg:grid-cols-2 gap-8 lg:gap-14"
            :class="{ 'is-active': i === activeStep }"
          >
            <div>
              <h3 class="text-[26px] sm:text-[30px] font-semibold tracking-tight leading-tight mb-4" style="color: var(--color-text);">
                {{ s.title }}
              </h3>
              <p class="text-[17px] leading-relaxed max-w-md" style="color: var(--color-text-secondary);">
                {{ s.desc }}
              </p>
            </div>
            <div class="lg:border-l lg:pl-14" :style="{ borderColor: 'var(--color-border)' }">
              <p class="text-[11px] font-medium uppercase tracking-widest mb-5" style="color: var(--color-text-tertiary);">
                What that looks like
              </p>
              <ul class="space-y-4">
                <li v-for="point in s.points" :key="point" class="flex items-start gap-3">
                  <UIcon name="i-lucide-check" class="size-4 mt-1 shrink-0" style="color: var(--color-accent);" />
                  <span class="text-[15px] leading-relaxed" style="color: var(--color-text);">{{ point }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Philosophy — the one inverted chapter. -->
    <section class="band-ink relative overflow-hidden reveal">
      <span aria-hidden="true" class="band-ink-glow" />
      <div class="relative max-w-7xl mx-auto px-6 py-32">
        <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] gap-12 lg:gap-24 items-center">
          <div>
            <SectionHeader eyebrow="Philosophy" title="Shaped by the world." />
            <div class="space-y-5 -mt-4 text-[17px] leading-[1.7] max-w-[58ch]" style="color: var(--color-text-secondary);">
              <p>
                Travel has made Qie pay attention to how people move through unfamiliar places. A clear
                sign, a timely message or a simple check-in can change the whole experience.
              </p>
              <p>
                The same idea applies to software: the useful details are often the ones people barely
                notice when they work well.
              </p>
            </div>
          </div>

          <blockquote class="lg:pl-6">
            <span aria-hidden="true" class="block w-[38px] h-[1.5px] mb-8" style="background: var(--color-accent);" />
            <p
              class="text-[clamp(26px,3.2vw,40px)] font-semibold tracking-tight leading-[1.15] text-balance"
              style="color: var(--color-text);"
            >
              &ldquo;The best interface helps someone get on with what they came to do.&rdquo;
            </p>
            <footer class="mt-6 text-[14px]" style="color: var(--color-text-secondary);">
              Qie, founder of Axel Nova Ventures
            </footer>
          </blockquote>
        </div>
      </div>
    </section>

    <!-- Looking ahead — the long view as three stops on one line, then the close. -->
    <section class="max-w-7xl mx-auto px-6 pt-32 pb-28 reveal">
      <SectionHeader
        eyebrow="Looking ahead"
        title="Where the studio is heading."
        subtitle="Axel Nova Ventures began with client work in design and software. Over time, I want it to become a home for useful products and new ventures."
      />

      <ol class="horizon relative grid md:grid-cols-3 gap-10 md:gap-8">
        <span aria-hidden="true" class="horizon-line" />
        <li v-for="h in horizon" :key="h.stage" class="relative pl-9 md:pl-0 md:pt-10">
          <span aria-hidden="true" class="horizon-dot" :class="{ 'is-current': h.current }" />
          <p
            class="text-[11px] font-medium uppercase tracking-wide mb-2"
            :style="{ color: h.current ? 'var(--color-accent)' : 'var(--color-text-tertiary)' }"
          >
            {{ h.stage }}
          </p>
          <h3 class="text-[20px] font-semibold tracking-tight mb-2" style="color: var(--color-text);">{{ h.title }}</h3>
          <p class="text-[15px] leading-relaxed max-w-sm" style="color: var(--color-text-secondary);">{{ h.desc }}</p>
        </li>
      </ol>

      <div class="mt-28 text-center">
        <p
          class="text-[clamp(26px,3.6vw,44px)] font-semibold tracking-tighter leading-tight max-w-3xl mx-auto text-balance"
          style="color: var(--color-text);"
        >
          This is a young studio with a long view.
        </p>
        <p class="mt-6 text-[16px] leading-relaxed max-w-md mx-auto" style="color: var(--color-text-secondary);">
          Client work today shapes the products and team Axel Nova Ventures hopes to build next.
        </p>
        <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
          <NuxtLink to="/services" class="btn-pill btn-pill-accent">
            Explore the services →
          </NuxtLink>
          <NuxtLink to="/about" class="btn-pill btn-pill-ghost">
            Meet Qie
          </NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* ── Hero ──────────────────────────────────────────────────────────────── */

/* Pre-paint hold (see the hero entrance in <script>): hidden until the entrance
   takes over; the delayed failsafe reveals it anyway if JS never does. Plain
   descendant selector on purpose (MOTION rule 12). */
html[data-company-intro] .company-hero-hold {
  opacity: 0;
  animation: company-hero-failsafe 0.5s ease 4s forwards;
}
@keyframes company-hero-failsafe {
  to { opacity: 1; }
}

/* The glow behind the hero — its own absolutely positioned child (hard rule
   8), breaking out of the container to the full screen width so wide screens
   have no bare sides; the page root clips the overflow. */
.company-hero-glow {
  position: absolute;
  top: -8%;
  left: 50%;
  width: 100vw;
  height: 92%;
  transform: translateX(-50%);
  pointer-events: none;
}
/* The two ends of the brand gradient — blue behind the box on the left,
   violet behind the title on the right — drift toward the middle and back,
   swelling and brightening as they meet. Transform + opacity only, blur on each
   blob (not the wrapper) so the compositor moves it without re-blurring. */
.company-hero-glow-blob {
  position: absolute;
  top: 0;
  width: 62%;
  height: 100%;
  opacity: 0.7;
  filter: blur(24px);
  animation: company-glow-meet 9s ease-in-out infinite alternate;
}
.company-hero-glow-left {
  left: -4%;
  --glow-drift: 6vw;
  background: radial-gradient(50% 50% at 50% 50%, color-mix(in srgb, var(--grad-aurora-blue) 22%, transparent) 0%, transparent 100%);
}
.company-hero-glow-right {
  right: -4%;
  --glow-drift: -6vw;
  background: radial-gradient(50% 50% at 50% 50%, color-mix(in srgb, var(--grad-aurora-violet) 18%, transparent) 0%, transparent 100%);
}
@keyframes company-glow-meet {
  to {
    transform: translateX(var(--glow-drift)) scale(1.1);
    opacity: 1;
  }
}

/* ── Origin reading focus ──────────────────────────────────────────────── */

.story-prose [data-focus] {
  color: var(--color-text-secondary);
  transition: color 0.4s ease;
}
.story-prose [data-focus].is-focus {
  color: var(--color-text);
}

/* ── The name ──────────────────────────────────────────────────────────── */

.name-box-letter {
  position: absolute;
  right: -0.5rem;
  top: -0.75rem;
  font-size: 140px;
  font-weight: 700;
  line-height: 1;
  letter-spacing: -0.05em;
  color: var(--color-text);
  opacity: 0.04;
  user-select: none;
  pointer-events: none;
  transition: opacity 0.6s cubic-bezier(0.215, 0.61, 0.355, 1), transform 0.6s cubic-bezier(0.215, 0.61, 0.355, 1);
}
@media (hover: hover) {
  .name-box:hover .name-box-letter {
    opacity: 0.08;
    transform: translateY(-4px);
  }
}

/* ── How we work (the stepper) ─────────────────────────────────────────── */

/* The active-step box. A raised white segment on the elevated box in light
   mode; in dark mode the elevated box is the darker tone, so the segment
   takes the lighter secondary surface instead. Radius = the stepper's 24px
   minus the 8px inset, so the corners nest. Slides with the filter tabs'
   0.45s / power3.out (MOTION.md). */
.step-highlight {
  position: absolute;
  top: 0.5rem;
  bottom: 0.5rem;
  left: 0.5rem;
  width: calc((100% - 1rem) / 3);
  border-radius: 1rem;
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  box-shadow: var(--shadow-sm);
  transform: translateX(calc(var(--i, 0) * 100%));
  transition: transform 0.45s cubic-bezier(0.215, 0.61, 0.355, 1);
  pointer-events: none;
}
.dark .step-highlight {
  background: var(--color-bg-secondary);
}
.step-tab {
  transition: background-color 0.2s ease;
}
.step-tab:not(.is-active):hover {
  background: var(--color-bg-secondary);
}
.step-tab:focus-visible {
  outline: none;
  box-shadow: inset var(--shadow-glow);
}
.step-tab-title {
  color: var(--color-text-secondary);
  transition: color 0.2s ease;
}
.step-tab.is-active .step-tab-title {
  color: var(--color-text);
}
/* One continuous line under the row — the track doubles as the divider, the
   accent fill is the clock. GSAP owns the fill's transform once the page is
   live (see the script); `--fill` is the SSR / JS-off / reduced-motion state,
   the end of the active step's third. */
.stepper-track {
  position: relative;
  height: 2px;
  background: var(--color-border);
}
.stepper-fill {
  position: absolute;
  inset: 0;
  background: var(--color-accent);
  transform: scaleX(var(--fill, 0.3333));
  transform-origin: left center;
}
/* Panels crossfade in place; the leaving one goes faster than the arriving one. */
.step-panel {
  opacity: 0;
  visibility: hidden;
  transform: translateY(6px);
  transition: opacity 0.2s ease-in, transform 0.2s ease-in, visibility 0s linear 0.2s;
}
.step-panel.is-active {
  opacity: 1;
  visibility: visible;
  transform: none;
  transition: opacity 0.35s ease-out 0.1s, transform 0.35s ease-out 0.1s, visibility 0s;
}

/* ── Philosophy (ink band) ─────────────────────────────────────────────── */

.band-ink-glow {
  position: absolute;
  top: -30%;
  right: -10%;
  width: 60vw;
  height: 120%;
  pointer-events: none;
  background: radial-gradient(50% 50% at 50% 50%, var(--band-ink-glow) 0%, transparent 100%);
  filter: blur(40px);
  opacity: 0.6;
}

/* ── Looking ahead (the horizon) ───────────────────────────────────────── */

/* One line through the three stops, fading out past the last — the view keeps
   going. Vertical on phones (down the left edge), horizontal from md. */
.horizon-line {
  position: absolute;
  top: 0;
  bottom: 0;
  left: calc(0.375rem - 0.5px);
  width: 1px;
  background: linear-gradient(180deg, var(--color-border-strong) 0%, var(--color-border-strong) 70%, transparent 100%);
}
.horizon-dot {
  position: absolute;
  left: 0;
  top: 0.25rem;
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 9999px;
  background: var(--color-bg);
  border: 1px solid var(--color-border-strong);
}
.horizon-dot.is-current {
  background: var(--color-accent);
  border-color: var(--color-accent);
  box-shadow: 0 0 0 4px var(--color-accent-soft);
  animation: horizon-pulse 2.5s ease-in-out infinite;
}
@media (min-width: 768px) {
  .horizon-line {
    top: calc(0.375rem - 0.5px);
    bottom: auto;
    left: 0;
    right: 0;
    width: auto;
    height: 1px;
    background: linear-gradient(90deg, var(--color-border-strong) 0%, var(--color-border-strong) 72%, transparent 100%);
  }
  .horizon-dot {
    top: 0;
  }
}
@keyframes horizon-pulse {
  0%, 100% { box-shadow: 0 0 0 4px var(--color-accent-soft); }
  50% { box-shadow: 0 0 0 9px transparent; }
}

/* ── Reduced motion ────────────────────────────────────────────────────── */

@media (prefers-reduced-motion: reduce) {
  .company-hero-glow-blob,
  .horizon-dot.is-current { animation: none; }
  /* No reading focus: every paragraph in full colour. */
  .story-prose [data-focus] { color: var(--color-text); }
  .name-box:hover .name-box-letter { transform: none; }
}
</style>
