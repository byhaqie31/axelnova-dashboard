<script setup lang="ts">
import type { ComponentPublicInstance } from 'vue'
import HeroLoader from '~/components/public/HeroLoader.vue'
import BrandMark from '~/components/shared/BrandMark.vue'
import { MOTION } from '~/utils/motion'

// Captured at setup — Nuxt context isn't available inside timer / event callbacks.
const motion = useMotion()

const heroShell   = ref<HTMLElement | null>(null)
const heroCard    = ref<HTMLElement | null>(null)
const heroContent = ref<HTMLElement | null>(null)
const videoEl     = ref<HTMLVideoElement | null>(null)
const heroBadge   = ref<HTMLElement | null>(null)
const heroHeadline = ref<HTMLElement | null>(null)
const heroSub     = ref<HTMLElement | null>(null)
const heroCtaWrap = ref<HTMLElement | null>(null)
const heroCta     = ref<ComponentPublicInstance | HTMLElement | null>(null)
const heroNav       = ref<HTMLElement | null>(null)
const heroDock      = ref<HTMLElement | null>(null)
const pillCollapsed = ref<HTMLElement | null>(null)
const pillExpanded  = ref<HTMLElement | null>(null)

// Mirror of the layout header's links — the expanded pill IS the header until
// it docks. Keep in sync with layouts/public.vue.
const navLinks = [
  { label: 'Home',     to: '/' },
  { label: 'About',    to: '/about' },
  { label: 'Company',  to: '/company' },
  { label: 'Projects', to: '/projects' },
  { label: 'Services', to: '/services' },
  { label: 'Partners', to: '/partners' },
  { label: 'Contact',  to: '/contact' },
]

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
let zoomTl: gsap.core.Timeline | null = null
let dockSt: import('gsap/ScrollTrigger').ScrollTrigger | null = null
let startEntrance: (() => void) | null = null

// Fixed design numbers for the dock math, kept in sync with the template
// classes: h-150 card, h-12 pill, bottom-6 sm:bottom-10 pill offset,
// pt-6 md:pt-10 stage padding; the layout's header pill floats at pt-3.
const CARD_END_PX = 600
const PILL_H_PX = 48
const DOCK_TOP_PX = 12
const padTopPx = () => (window.innerWidth >= 768 ? 40 : 24)
const dockBottomPx = () => (window.innerWidth >= 640 ? 40 : 24)

// Scroll cue / "Axel Nova" button on the collapsed pill: ride through the
// whole zoom-out in one smooth move.
const scrollPastIntro = () => {
  const end = zoomTl?.scrollTrigger?.end ?? window.innerHeight
  if (motion.lenis) motion.lenis.scrollTo(end, { duration: 1.4 })
  else window.scrollTo({ top: end, behavior: 'smooth' })
}
let safetyTimer: number | undefined
let fontGate: number | undefined
let loaderCap: number | undefined
let loaderMin: number | undefined

// Intro loader: fresh page loads only — client-side navs back home skip it.
const INTRO_FLAG = 'axn-hero-intro-seen'
// Branding moment: the loader stays up at least this long even when assets
// beat it; asset waiting itself is still capped at LOADER_CAP_MS.
const LOADER_MIN_MS = 4000
const LOADER_CAP_MS = 2500
// Starts true so the overlay ships in the SSR HTML and covers the page from the
// first paint. Repeat visits and reduced motion are hidden pre-paint by CSS
// (`html[data-intro-seen] .hero-loader`, stamped by the inline script in
// nuxt.config), then unmounted below — so they never see a frame of it.
const showLoader = ref(true)
const loaderDone = ref(false)

// The layout header stays hidden for the whole hero experience — it only
// appears when the expanded pill docks at the top (see dockSt below), because
// the pill IS the header until then. Shared with layouts/public.vue.
const heroImmersive = useState('hero-immersive', () => false)

onMounted(() => {
  const { gsap, reduced } = motion

  if (videoEl.value) {
    // Vue can drop the `muted` prop on hydration, which breaks autoplay — force it.
    videoEl.value.muted = true
    // Background footage is ambient motion — pause it under reduced motion, like
    // the aurora mesh and the marquee.
    if (reduced) videoEl.value.pause()
  }

  // Entrance targets fade opacity ONLY — their transforms belong to the zoom
  // scrub (big corner pose → designed size), so the two never fight. The
  // description and CTA are absent here entirely: they're scrub-owned and
  // only appear once scrolling.
  const els = [heroBadge.value, heroNav.value].filter(Boolean) as HTMLElement[]
  // Reduced motion is a full no-op — no entrances, no loader, no pinned zoom;
  // the scoped fullscreen CSS is also gated on no-preference, so SSR painted
  // the plain card and nothing here needs to run. The SSR'd overlay is already
  // display:none here (the pre-paint script stamps reduced motion too); drop it
  // from the tree so it can't trap focus or clicks.
  if (reduced || !els.length) {
    showLoader.value = false
    return
  }

  heroImmersive.value = true

  // Pinned zoom-out: the section holds for one viewport of scroll while the
  // card scrubs from fullscreen (painted by the scoped CSS below) to its
  // designed box. Function-based values + invalidateOnRefresh keep the end
  // state exact across resizes/breakpoints.
  if (heroShell.value && heroCard.value) {
    // The natural (CSS) layout is the designed card — the `.hero-boot` class
    // only exists so SSR paints the fullscreen state before hydration. Drop it
    // now, then let `.from()` tweens recreate that fullscreen state as inline
    // styles in the same tick (no paint happens in between). This is what
    // keeps the pin spacer honest: ScrollTrigger measures the reverted
    // (natural = end) state, so there's no dead gap after the marquee, and the
    // dock trigger below measures true post-zoom positions.
    heroShell.value.classList.remove('hero-boot')

    zoomTl = gsap.timeline({
      defaults: { ease: 'none' },
      scrollTrigger: {
        trigger: heroShell.value,
        start: 'top top',
        end: '+=100%',
        pin: true,
        scrub: 0.6,
        invalidateOnRefresh: true,
      },
    })
    zoomTl.from(heroShell.value, { paddingLeft: 0, paddingRight: 0, paddingTop: 0 }, 0)
    zoomTl.from(
      heroCard.value,
      {
        height: () => window.innerHeight,
        maxWidth: () => `${window.innerWidth}px`,
        borderRadius: 0,
      },
      0,
    )
    // Pill morph: compact "Axel Nova" brand chip grows to the full-width nav
    // bar, crossfading its two content layers along the way.
    if (heroNav.value) {
      zoomTl.from(heroNav.value, { width: '12rem' }, 0)
    }
    if (pillCollapsed.value) {
      zoomTl.from(pillCollapsed.value, { autoAlpha: 1, duration: 0.15 }, 0)
    }
    if (pillExpanded.value) {
      zoomTl.from(pillExpanded.value, { autoAlpha: 0, duration: 0.25 }, 0.2)
    }

    // Copy morph: the eyebrow + headline hold their top-left corner but start
    // BIG (scaled from the corner, so they stay anchored — the centered pose
    // read as weird) and shrink to the designed size as the zoom plays.
    // Transform-only, so layout never moves. The opening scale is computed
    // from the text's real width so the headline FILLS the open area: ~62% of
    // the viewport from md up, edge-to-edge on phones. offsetWidth ignores
    // transforms, so re-measuring mid-scrub is safe.
    const headlineScale = () => {
      const el = heroHeadline.value
      if (!el || !el.offsetWidth) return 1.5
      const padX = window.innerWidth >= 768 ? 64 : 24 // content px-6 / md:px-16
      const avail = window.innerWidth - padX * 2
      const fill = window.innerWidth >= 768 ? 0.62 : 1
      return Math.min(3, Math.max(1.1, (avail * fill) / el.offsetWidth))
    }
    // The opening pose also sits the whole copy block LOWER — near the optical
    // centre of the fullscreen card instead of hard against its top padding,
    // which left a dead band under the headline (only the eyebrow + headline
    // exist in this pose; the description and CTA are still faded out). It
    // rides back to the designed top-left slot as the zoom plays.
    //
    // Transform only, never layout: `.hero-shell` is the pin trigger, so a
    // height change here would corrupt the pin spacer and the marquee wrap's
    // negative margin. Function value so invalidateOnRefresh recomputes it on
    // resize and across the breakpoint. Mirrored by `.hero-boot .hero-content`
    // in the scoped CSS for the pre-hydration paint — keep the two in sync.
    if (heroContent.value) {
      zoomTl.from(
        heroContent.value,
        { y: () => window.innerHeight * (window.innerWidth >= 768 ? 0.22 : 0.12) },
        0,
      )
    }
    if (heroBadge.value) {
      zoomTl.from(heroBadge.value, { scale: 1.15, transformOrigin: '0% 0%' }, 0)
    }
    if (heroHeadline.value) {
      zoomTl.from(heroHeadline.value, { scale: headlineScale, transformOrigin: '0% 0%' }, 0)
    }
    // Description and CTA don't exist in the opening pose — they fade in
    // mid-zoom, already sitting in their final spots.
    if (heroSub.value) zoomTl.from(heroSub.value, { autoAlpha: 0, duration: 0.2 }, 0.22)
    if (heroCtaWrap.value) zoomTl.from(heroCtaWrap.value, { autoAlpha: 0, duration: 0.2 }, 0.28)

    // Dock handoff: past the zoom, the expanded pill rides up with the page;
    // when its top reaches the header's floating position the pill fades out
    // and the real header (identical glass pill) takes over — reversed on the
    // way back up. The start is pure math from the settled geometry (verified
    // against measured positions): element-based measurement is unreliable
    // here because the scrub's inline styles are applied whenever a refresh
    // runs, so the pill would be measured in the wrong state.
    const ScrollTriggerRef = motion.ScrollTrigger
    dockSt = ScrollTriggerRef.create({
      start: () => {
        const pinEnd = zoomTl?.scrollTrigger?.end ?? window.innerHeight
        const pillTopAtRest = padTopPx() + CARD_END_PX - dockBottomPx() - PILL_H_PX
        return pinEnd + pillTopAtRest - DOCK_TOP_PX
      },
      end: () => ScrollTriggerRef.maxScroll(window),
      onEnter: () => {
        heroImmersive.value = false
        if (heroNav.value) gsap.to(heroNav.value, { autoAlpha: 0, duration: 0.2, overwrite: 'auto' })
      },
      onLeaveBack: () => {
        heroImmersive.value = true
        if (heroNav.value) gsap.to(heroNav.value, { autoAlpha: 1, duration: 0.2, overwrite: 'auto' })
      },
    })
  }

  // Hide immediately so there's no flash while we wait for the font.
  gsap.set(els, { opacity: 0 })

  const start = () => {
    if (heroTl) return // font-ready and the safety net can race

    // Both gates wait on the webfont, and Outfit changes the headline's
    // measured width — re-evaluate the scrub's function-based values (fill
    // scale, dock position) against the final metrics before revealing.
    motion.ScrollTrigger.refresh()

    // SplitText measures word geometry now, so it must run AFTER the webfont
    // (Outfit) loads — splitting against the fallback then swapping reflows.
    const tl = gsap.timeline({
      defaults: { ease: MOTION.ease.out },
      // Clear ONLY opacity — transforms on the badge/sub are owned by the
      // zoom scrub and must survive the entrance.
      onComplete: () => gsap.set(els, { clearProps: 'opacity' }),
    })
    tl.to(heroBadge.value, { opacity: 1, duration: MOTION.dur.base })
    const headline = buildHeadline()
    if (headline) tl.add(headline, '-=0.3')
    // The navbar arrives last, on its own beat — "not loaded yet" on a fresh
    // view, it settles in after the wording.
    tl.to(heroNav.value, { opacity: 1, duration: MOTION.dur.slow }, '+=0.35')
    heroTl = tl
  }
  startEntrance = start

  let introSeen = true
  try { introSeen = !!sessionStorage.getItem(INTRO_FLAG) } catch { /* private mode — skip the loader */ }

  if (!introSeen) {
    // Fresh load: show the loader, hold scroll, and wait on fonts + the
    // video's first frame — hard-capped so a slow CDN never blocks the page.
    // The entrance starts from the loader's `reveal` event.
    try { sessionStorage.setItem(INTRO_FLAG, '1') } catch { /* ignore */ }
    // NB: the matching `data-intro-seen` attribute is stamped in
    // `onLoaderHidden`, NOT here — the CSS rule keyed off it hides the loader,
    // so setting it now would kill the intro a frame after hydration.
    motion.lenis?.stop()

    const videoReady = new Promise<void>((resolve) => {
      const v = videoEl.value
      if (!v || v.readyState >= 2 || v.error) return resolve()
      v.addEventListener('loadeddata', () => resolve(), { once: true })
      v.addEventListener('error', () => resolve(), { once: true })
    })
    const fontsReady = document.fonts?.ready ?? Promise.resolve()
    const cap = new Promise<void>((resolve) => { loaderCap = window.setTimeout(resolve, LOADER_CAP_MS) })
    const minTime = new Promise<void>((resolve) => { loaderMin = window.setTimeout(resolve, LOADER_MIN_MS) })
    Promise.all([Promise.race([Promise.all([videoReady, fontsReady]), cap]), minTime]).then(() => {
      clearTimeout(loaderCap)
      loaderDone.value = true
    })
  }
  else {
    // Repeat visit this session — no loader. CSS already hid the SSR'd overlay
    // before paint, so dropping it here is invisible. Must run before the
    // safety timer below, which sizes its window off `showLoader`.
    showLoader.value = false
    // Keep <html> in sync with sessionStorage: the pre-paint script only sees
    // storage as it was at page load, so a later client-side nav back to `/`
    // would otherwise remount the SSR-default overlay unhidden for a frame.
    document.documentElement.setAttribute('data-intro-seen', '')
    // Gate on fonts as before, with a fallback if fonts.ready stalls.
    fontGate = window.setTimeout(start, 600)
    document.fonts?.ready.then(() => {
      clearTimeout(fontGate)
      start()
    }) ?? start()
  }

  // Background/throttled tabs never run rAF — force-finish whatever has started
  // so nothing is stranded hidden (or scroll-locked). If the gate never fired,
  // reveal flat. Loader path gets a longer window (cap + fade + entrance).
  safetyTimer = window.setTimeout(() => {
    motion.lenis?.start()
    if (!heroTl) {
      gsap.set(els, { clearProps: 'opacity' })
      return
    }
    if (heroTl.progress() < 1) heroTl.progress(1)
  }, showLoader.value ? LOADER_MIN_MS + 4000 : 3500)
})

// The loader's fade-out has begun — release scroll and play the entrance.
const onLoaderReveal = () => {
  motion.lenis?.start()
  startEntrance?.()
}

// The overlay is fully gone. Only now is it safe to mark the intro as seen:
// `html[data-intro-seen] .hero-loader` is what hides the loader, so stamping it
// any earlier cuts the intro short. This is what keeps a later client-side nav
// back to `/` from flashing the SSR-default overlay.
const onLoaderHidden = () => {
  document.documentElement.setAttribute('data-intro-seen', '')
  showLoader.value = false
}

onUnmounted(() => {
  clearTimeout(safetyTimer)
  clearTimeout(fontGate)
  clearTimeout(loaderCap)
  clearTimeout(loaderMin)
  heroImmersive.value = false // never leave the layout header hidden
  motion.lenis?.start()
  dockSt?.kill()
  zoomTl?.scrollTrigger?.kill()
  zoomTl?.kill()
  heroTl?.kill()
})
</script>

<template>
  <!-- Pinned stage. Its height is a CONSTANT 100svh (when motion is on):
       ScrollTrigger freezes a pinned element's box at pin time, so anything
       whose layout height changes mid-pin leaves dead space behind. Only the
       card inside resizes; the stage never does. -->
  <section ref="heroShell" class="hero-shell hero-boot px-4 sm:px-6 pt-6 md:pt-10">
    <!-- Intro loader (fresh loads only) — teleported so the pinned section's
         positioning can never affect the fixed overlay. -->
    <Teleport to="body">
      <HeroLoader v-if="showLoader" :done="loaderDone" @reveal="onLoaderReveal" @hidden="onLoaderHidden" />
    </Teleport>

    <!-- HERO CARD + VIDEO BACKGROUND — starts fullscreen (scoped CSS below),
         scrubs down to this designed card via the pinned ScrollTrigger. -->
    <div
      ref="heroCard"
      class="hero-card relative w-full max-w-350 mx-auto rounded-[48px] overflow-hidden h-150 flex flex-col"
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
      <div
        ref="heroContent"
        class="hero-content relative z-20 flex-1 px-6 sm:px-10 md:px-16 pt-12 md:pt-16 flex flex-col items-start"
      >
        <div
          ref="heroBadge"
          class="hero-badge inline-flex items-center gap-2 rounded-full border px-3 py-1 backdrop-blur-md"
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
          class="hero-sub mt-5 max-w-[52ch] text-[14px] md:text-[15px] leading-relaxed"
          style="color: var(--hero-fg-muted);"
        >
          Axel Nova Ventures is a design-led digital studio creating immersive websites, SaaS
          platforms, and bespoke digital products.
          <!-- Second sentence is desktop-only — on phones the description ends
               at "products." to keep the hero copy tight. -->
          <span class="hidden md:inline">
            Every solution is custom-designed and engineered around your needs, using the
            right combination of Vue, Nuxt, Laravel, GSAP, and Three.js.
          </span>
        </p>

        <div ref="heroCtaWrap" class="hero-cta mt-7">
          <NuxtLink ref="heroCta" to="/quote" class="btn-pill btn-pill-primary">
            <span class="magnetic-label">Start a Project</span>
          </NuxtLink>
        </div>
      </div>

      <!-- Morphing floating navbar. Fullscreen state: a compact "Axel Nova"
           brand chip with a scroll cue (clicking it rides through the zoom).
           The pinned zoom scrubs it out to the full-width nav — the header's
           content in the header's glass-nav surface — and once it scrolls up
           to the top of the viewport it docks: this pill fades out and the
           layout's (identical) floating header takes over. Under reduced
           motion the expanded layer renders statically (see scoped CSS). -->
      <div
        ref="heroDock"
        class="absolute bottom-6 sm:bottom-10 left-1/2 -translate-x-1/2 z-30 w-[92%] max-w-7xl flex justify-center"
      >
        <nav
          ref="heroNav"
          aria-label="Hero navigation"
          class="glass-nav hero-pill relative h-12 overflow-hidden"
        >
          <!-- Collapsed layer: brand + scroll-down cue -->
          <button
            ref="pillCollapsed"
            type="button"
            class="hero-pill-collapsed absolute inset-0 flex items-center justify-center gap-2.5"
            aria-label="Scroll to explore"
            @click="scrollPastIntro"
          >
            <img src="/favicon/apple-touch-icon.png" alt="" aria-hidden="true" class="size-6 object-contain">
            <span class="text-gradient text-[14px] font-semibold tracking-tight whitespace-nowrap">Axel Nova</span>
            <UIcon name="i-lucide-chevron-down" class="hero-pill-cue size-4" style="color: var(--color-text-secondary);" />
          </button>

          <!-- Expanded layer: the header nav, brand → links → CTA -->
          <div
            ref="pillExpanded"
            class="hero-pill-expanded absolute inset-0 flex items-center justify-between gap-3 pl-4 pr-1.5"
          >
            <BrandMark variant="compact" wordmark="Axel Nova" class="shrink-0" />
            <ul class="hidden md:flex items-center">
              <li v-for="l in navLinks" :key="l.to">
                <NuxtLink :to="l.to" class="epoch-nav-link">{{ l.label }}</NuxtLink>
              </li>
            </ul>
            <NuxtLink
              to="/contact"
              class="pill-chip gap-1 h-8 pl-3.5 pr-2.5 text-[12px] font-semibold whitespace-nowrap shrink-0"
              style="color: var(--color-text);"
            >
              Let's talk
              <UIcon name="i-lucide-chevron-right" class="size-3.5" />
            </NuxtLink>
          </div>
        </nav>
      </div>
    </div>
  </section>

  <!-- SEAMLESS MARQUEE — outside the pinned stage. The stage stays 100svh
       tall even after the card shrinks to 600px, so this wrapper's negative
       top margin (scoped CSS) pulls the marquee up over the stage's empty
       lower band, landing exactly mt-10 below the settled card. During the
       pin's final stretch it rises into view under the shrinking card. -->
  <div class="hero-marquee-wrap px-4 sm:px-6 pt-10 pb-10 md:pb-14">
    <div class="epoch-marquee max-w-350 mx-auto" aria-hidden="true">
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
  </div>
</template>

<style scoped>
/* Base pill state — the natural layout everywhere is the designed END state
   (card + full-width nav). It's what ScrollTrigger measures, what reduced
   motion renders, and what the zoom scrubs back to. */
.hero-pill {
  width: 100%;
}
.hero-pill-collapsed {
  opacity: 0;
  visibility: hidden;
}

/* Fullscreen boot state, painted by SSR before GSAP hydrates (unlayered, so it
   beats the Tailwind utility classes on the same elements). onMounted removes
   .hero-boot and the `.from()` tweens recreate this exact state inline in the
   same tick — no flash. Under reduced motion the class stays on the element
   but this media query keeps it inert, so the plain card renders. */
@media (prefers-reduced-motion: no-preference) {
  /* Constant stage height — pinned elements must never change layout height
     (ScrollTrigger freezes the box at pin time). Boot and settled state alike. */
  .hero-shell {
    height: 100svh;
  }
  /* The settled card (padT + 600px) ends well above the 100svh stage bottom;
     pull the marquee wrapper up over that empty band so its pt-10 gap lands
     right under the card. 39rem = 1.5rem padT + 37.5rem card (40rem with the
     md:pt-10 padding — see the md block below). The wrapper's own padding-top
     (not a child margin) supplies the gap, so no margin collapsing can shift
     this. min() keeps short viewports sane. */
  .hero-marquee-wrap {
    margin-top: min(0px, calc(39rem - 100svh));
  }
  .hero-boot.hero-shell {
    padding-left: 0;
    padding-right: 0;
    padding-top: 0;
  }
  .hero-boot .hero-card {
    height: 100svh;
    max-width: none;
    border-radius: 0;
  }
  .hero-boot .hero-pill {
    width: 12rem;
  }
  .hero-boot .hero-pill-collapsed {
    opacity: 1;
    visibility: visible;
  }
  .hero-boot .hero-pill-expanded {
    opacity: 0;
    visibility: hidden;
  }
  /* Boot copy pose: eyebrow + headline big, sat low toward the optical centre
     of the fullscreen card, no description, no CTA, no navbar — the
     pre-hydration mirror of the scrub's from() values. Corner-origin scaling
     keeps the text anchored in place; the block's drop is a separate transform
     on the wrapper. svh to match the card's own height unit. */
  .hero-boot .hero-content {
    transform: translateY(12svh);
  }
  .hero-boot .hero-badge {
    transform-origin: 0 0;
    transform: scale(1.15);
  }
  .hero-boot .epoch-headline {
    transform-origin: 0 0;
    transform: scale(1.15);
  }
  .hero-boot .hero-sub,
  .hero-boot .hero-cta {
    opacity: 0;
    visibility: hidden;
  }
  .hero-boot .hero-pill {
    opacity: 0;
    visibility: hidden;
  }
  /* Hover invitation on the compact "Axel Nova" chip. Width is scrub-owned
     (inline), so the grow is a transform — it never fights GSAP. */
  .hero-pill {
    transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1);
  }
  .hero-pill:has(.hero-pill-collapsed:hover) {
    transform: scale(1.07);
  }
  .hero-pill-cue {
    animation: hero-cue-bob 1.6s ease-in-out infinite;
  }
}

@media (prefers-reduced-motion: no-preference) and (min-width: 768px) {
  .hero-marquee-wrap {
    margin-top: min(0px, calc(40rem - 100svh));
  }
  /* Static approximation of the scrub's width-filling headlineScale() for the
     pre-hydration paint (phones stay at the base 1.15 so the lines fit). */
  .hero-boot .epoch-headline {
    transform: scale(1.9);
  }
  .hero-boot .hero-content {
    transform: translateY(22svh);
  }
}

@keyframes hero-cue-bob {
  0%, 100% { transform: translateY(-1px); }
  50%      { transform: translateY(3px); }
}

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
