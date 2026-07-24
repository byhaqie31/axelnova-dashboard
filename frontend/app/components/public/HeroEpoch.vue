<script setup lang="ts">
import type { ComponentPublicInstance } from 'vue'
import { MOTION } from '~/utils/motion'

// Captured at setup — Nuxt context isn't available inside timer / event callbacks.
const motion = useMotion()

const videoEl     = ref<HTMLVideoElement | null>(null)
const heroBadge   = ref<HTMLElement | null>(null)
const heroHeadline = ref<HTMLElement | null>(null)
const heroSub     = ref<HTMLElement | null>(null)
const heroCtaWrap = ref<HTMLElement | null>(null)
const heroCta     = ref<ComponentPublicInstance | HTMLElement | null>(null)
const heroNav     = ref<HTMLElement | null>(null)

// Headline is solid `--color-text` (no gradient), so no per-word background
// mapping is needed — a plain SplitText reveal with the default revert is fine.
const { build: buildHeadline } = useSplitTextReveal(heroHeadline)

useMagnetic(heroCta)

// Stack logos hotlinked from svgl.app (+ Simple Icons for GSAP). Brand-coloured
// marks read on both light and dark cards via a single `src`. Monochrome marks
// (GitHub, Three.js, React) would vanish on one card colour, so they ship a
// per-mode `light`/`dark` pair — the right one is shown via the `.dark` class
// (svgl convention: `*-light` = dark mark for light backgrounds, `*-dark` =
// light mark for dark backgrounds). TODO: self-host to /public/logos/ before
// shipping, same as the hero video below.
type StackLogo =
  | { alt: string; src: string }
  | { alt: string; light: string; dark: string }

const logos: StackLogo[] = [
  { src: 'https://svgl.app/library/vue.svg',          alt: 'Vue' },
  { src: 'https://svgl.app/library/nuxt.svg',         alt: 'Nuxt' },
  { light: 'https://svgl.app/library/react_light.svg',  dark: 'https://svgl.app/library/react_dark.svg',  alt: 'React' },
  { light: 'https://svgl.app/library/threejs-light.svg', dark: 'https://svgl.app/library/threejs-dark.svg', alt: 'Three.js' },
  { src: 'https://cdn.simpleicons.org/gsap/0AE448',   alt: 'GSAP' },
  { src: 'https://svgl.app/library/laravel.svg',      alt: 'Laravel' },
  { src: 'https://svgl.app/library/tailwindcss.svg',  alt: 'Tailwind CSS' },
  { src: 'https://svgl.app/library/typescript.svg',   alt: 'TypeScript' },
  { src: 'https://svgl.app/library/stripe.svg',       alt: 'Stripe' },
  { src: 'https://svgl.app/library/cloudflare.svg',   alt: 'Cloudflare' },
  { src: 'https://svgl.app/library/docker.svg',       alt: 'Docker' },
  { light: 'https://svgl.app/library/github_light.svg', dark: 'https://svgl.app/library/github_dark.svg', alt: 'GitHub' },
  { src: 'https://svgl.app/library/gitlab.svg',       alt: 'GitLab' },
]

// Normalise to a uniform shape (always light + dark) so the template never
// branches on optional fields; `themed` marks render both variants and let CSS
// pick. Rendered twice so the -50% keyframe loops seamlessly.
const marqueeLogos = [...logos, ...logos].map(l =>
  'src' in l
    ? { alt: l.alt, themed: false, light: l.src, dark: l.src }
    : { alt: l.alt, themed: true, light: l.light, dark: l.dark },
)

let heroTl: gsap.core.Timeline | null = null
let safetyTimer: number | undefined
let fontGate: number | undefined

onMounted(() => {
  const { gsap, reduced } = motion

  if (videoEl.value) {
    // Vue can drop the `muted` prop on hydration, which breaks autoplay — force it.
    videoEl.value.muted = true
    // Background footage is ambient motion — pause it under reduced motion, like
    // the aurora mesh and the marquee.
    if (reduced) videoEl.value.pause()
  }

  const els = [heroBadge.value, heroSub.value, heroCtaWrap.value, heroNav.value].filter(Boolean) as HTMLElement[]
  // Reduced motion is a full no-op for entrances — SSR already painted everything.
  if (reduced || !els.length) return

  // Hide immediately so there's no flash while we wait for the font.
  gsap.set(els, { opacity: 0, y: 24 })

  const start = () => {
    if (heroTl) return // font-ready and the safety net can race

    // SplitText measures word geometry now, so it must run AFTER the webfont
    // (Outfit) loads — splitting against the fallback then swapping reflows.
    const tl = gsap.timeline({
      defaults: { ease: MOTION.ease.out },
      onComplete: () => gsap.set(els, { clearProps: 'opacity,transform' }),
    })
    tl.to(heroBadge.value, { opacity: 1, y: 0, duration: MOTION.dur.base })
    const headline = buildHeadline()
    if (headline) tl.add(headline, '-=0.3')
    tl.to(heroSub.value, { opacity: 1, y: 0, duration: MOTION.dur.slow }, '-=0.55')
    tl.to(heroCtaWrap.value, { opacity: 1, y: 0, duration: MOTION.dur.slow }, '-=0.7')
    tl.to(heroNav.value, { opacity: 1, y: 0, duration: MOTION.dur.slow }, '-=0.6')
    heroTl = tl
  }

  // Gate on fonts; fall back after a short timeout if fonts.ready stalls.
  fontGate = window.setTimeout(start, 600)
  document.fonts?.ready.then(() => {
    clearTimeout(fontGate)
    start()
  }) ?? start()

  // Background/throttled tabs never run rAF — force-finish whatever has started
  // so nothing is stranded hidden. If the gate never fired, reveal flat.
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
  <section class="px-4 sm:px-6 pt-6 md:pt-10 pb-10 md:pb-14">
    <!-- HERO CARD + VIDEO BACKGROUND -->
    <div
      class="relative w-full max-w-350 mx-auto rounded-[48px] overflow-hidden h-150 flex flex-col"
      :style="{ background: 'var(--color-bg-elevated)', border: '1px solid var(--color-border)', boxShadow: 'var(--shadow-lg)' }"
    >
      <!-- Background video layer -->
      <div class="absolute inset-0 pointer-events-none z-0 overflow-hidden select-none" aria-hidden="true">
        <video
          ref="videoEl"
          class="w-full h-full object-cover scale-105 transition-transform duration-1000"
          autoplay
          loop
          muted
          playsinline
          preload="auto"
        >
          <!-- Placeholder source — self-host to R2 and swap before shipping. -->
          <source
            src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260505_101331_74f9b798-3f00-4e86-8a01-377aa16ffeaa.mp4"
            type="video/mp4"
          >
        </video>

        <!-- Optional legibility scrim — uncomment if bright footage hurts
             light-mode contrast (token-based, no hardcoded hex):
        <div
          class="absolute inset-0"
          style="background: linear-gradient(180deg, color-mix(in srgb, var(--color-bg) 50%, transparent) 0%, transparent 45%);"
        />
        -->
      </div>

      <!-- Text content -->
      <div class="relative z-20 flex-1 px-6 sm:px-10 md:px-16 pt-12 md:pt-16 flex flex-col items-start">
        <div
          ref="heroBadge"
          class="inline-flex items-center gap-2 rounded-full border px-3 py-1 backdrop-blur-md"
          :style="{ borderColor: 'var(--color-border)', background: 'var(--nav-bg-scrolled)' }"
        >
          <span class="size-1.5 rounded-full" style="background: var(--grad-iridescent);" />
          <span class="eyebrow">Design &amp; engineering studio</span>
        </div>

        <!-- Base 34px keeps the two lines inside the card at 375px; the spec's
             42 / 56 sizes hold from sm upward. Weight/tracking/leading are set in
             the scoped .epoch-headline rule, not via font-medium/tracking-tight
             utilities — main.css's unlayered `h1 {}` base rule beats Tailwind's
             layered utilities, so those props must be set unlayered to win. -->
        <h1
          ref="heroHeadline"
          class="epoch-headline font-display mt-7 text-[34px] sm:text-[42px] md:text-[56px]"
          style="color: var(--hero-fg);"
        >
          Crafted by design.<br>Built to last.
        </h1>

        <p
          ref="heroSub"
          class="mt-5 max-w-[52ch] text-[14px] md:text-[15px] leading-relaxed"
          style="color: var(--hero-fg-muted);"
        >
          Axel Nova Ventures is a design-led software studio building fintech, SaaS, and bespoke
          web products — engineered end to end with Vue, Nuxt, Laravel, and Docker.
        </p>

        <div ref="heroCtaWrap" class="mt-7">
          <NuxtLink ref="heroCta" to="/quote" class="btn-pill btn-pill-primary">
            <span class="magnetic-label">Start a Project</span>
          </NuxtLink>
        </div>
      </div>

      <!-- Floating bottom navbar (centering on the wrapper so GSAP can own the
           inner element's transform without losing the -translate-x). On mobile
           the bar spans the card width and spreads its items; from sm it
           collapses back to the compact centered pill. -->
      <div class="absolute bottom-6 sm:bottom-10 left-1/2 -translate-x-1/2 z-30 w-[92%] sm:w-auto">
        <nav
          ref="heroNav"
          aria-label="Hero quick links"
          class="glass-nav flex sm:inline-flex w-full sm:w-auto items-center justify-between sm:justify-center gap-1 p-1.5 pl-3"
        >
          <NuxtLink to="/projects" class="epoch-nav-link">Projects</NuxtLink>
          <!-- Same-page jump to the mockups marquee; the GSAP plugin routes
               a[href^="#"] clicks through lenis.scrollTo so Lenis stays in sync. -->
          <a href="#mockups" class="epoch-nav-link">Mockups</a>
          <NuxtLink to="/services" class="epoch-nav-link">Services</NuxtLink>
          <NuxtLink
            to="/contact"
            class="pill-chip gap-1 h-8 pl-3.5 pr-2.5 text-[12px] font-semibold whitespace-nowrap shrink-0"
            style="color: var(--color-text);"
          >
            Let's talk
            <UIcon name="i-lucide-chevron-right" class="size-3.5" />
          </NuxtLink>
        </nav>
      </div>
    </div>

    <!-- SEAMLESS MARQUEE -->
    <div class="epoch-marquee mt-10 max-w-350 mx-auto" aria-hidden="true">
      <div class="epoch-marquee-track">
        <div
          v-for="(logo, i) in marqueeLogos"
          :key="i"
          class="epoch-logo-card pill-chip h-16.75 w-28 shrink-0 overflow-hidden mr-3"
        >
          <span class="epoch-logo-wash" />
          <img
            v-if="!logo.themed"
            :src="logo.light"
            :alt="logo.alt"
            loading="lazy"
            class="relative h-5.5 w-auto max-w-[64%] object-contain"
          >
          <template v-else>
            <img
              :src="logo.light"
              :alt="logo.alt"
              loading="lazy"
              class="epoch-mark epoch-mark-light relative h-5.5 w-auto max-w-[64%] object-contain"
            >
            <img
              :src="logo.dark"
              :alt="logo.alt"
              loading="lazy"
              class="epoch-mark epoch-mark-dark relative h-5.5 w-auto max-w-[64%] object-contain"
            >
          </template>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
/* Spec: font-medium + tracking-tight + leading-[1.05]. Set here (unlayered, so
   it beats the unlayered `h1 {}` base in main.css — Tailwind utilities are
   layered and would lose to it). */
.epoch-headline {
  font-weight: 500;
  letter-spacing: -0.025em;
  line-height: 1.05;
}

.epoch-nav-link {
  font-size: 12px;
  font-weight: 600;
  padding: 0 0.625rem;
  white-space: nowrap;
  color: var(--color-text-secondary);
  transition: color 0.2s ease;
}
.epoch-nav-link:hover {
  color: var(--color-text);
}
/* Three links plus the CTA chip overflow the mobile pill at stock padding. */
@media (max-width: 400px) {
  .epoch-nav-link {
    padding: 0 0.375rem;
  }
}

/* Seamless marquee — pure CSS, pauses on hover, fades at both edges. */
.epoch-marquee {
  overflow: hidden;
  -webkit-mask-image: linear-gradient(to right, transparent 0, #000 8%, #000 92%, transparent 100%);
  mask-image: linear-gradient(to right, transparent 0, #000 8%, #000 92%, transparent 100%);
}
.epoch-marquee-track {
  display: flex;
  width: max-content;
  animation: epoch-marquee 40s linear infinite;
}
.epoch-marquee:hover .epoch-marquee-track {
  animation-play-state: paused;
}
@keyframes epoch-marquee {
  from { transform: translateX(0); }
  to   { transform: translateX(-50%); }
}

/* Faint iridescent wash revealed under each logo on hover. Natural-colour logos
   stay put on top — never brightness-0 invert (vanishes on dark cards). */
.epoch-logo-wash {
  position: absolute;
  inset: 0;
  background: linear-gradient(120deg, var(--grad-aurora-violet), var(--grad-aurora-blue), var(--grad-aurora-cyan));
  opacity: 0;
  transform: scale(1.5);
  transition: opacity 0.5s ease, transform 0.5s ease;
  pointer-events: none;
}
.epoch-logo-card:hover .epoch-logo-wash {
  opacity: 0.14;
  transform: scale(1);
}

/* Monochrome marks (GitHub, Three.js, React) ship a variant per card colour;
   show the one that reads against the active theme. FOUC-safe — the .dark class
   is applied before paint (see the FOUC rule), so there's no flash. */
.epoch-mark-dark { display: none; }
.dark .epoch-mark-light { display: none; }
.dark .epoch-mark-dark { display: block; }

@media (prefers-reduced-motion: reduce) {
  .epoch-marquee-track { animation: none; }
}
</style>
