import gsap from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'
import { SplitText } from 'gsap/SplitText'
import Lenis from 'lenis'

export default defineNuxtPlugin((nuxtApp) => {
  gsap.registerPlugin(ScrollTrigger, SplitText)

  // Mobile browsers resize the viewport as the URL bar hides/shows. Left alone,
  // every one of those resizes refreshes ScrollTrigger mid-scroll, which
  // re-measures the pinned hero and shows up as a stutter or a jump. Height-only
  // resizes are exactly the case to ignore; real orientation changes still fire.
  ScrollTrigger.config({ ignoreMobileResize: true })

  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  let lenis: Lenis | null = null

  if (!reduced) {
    lenis = new Lenis({ lerp: 0.1 })
    lenis.on('scroll', ScrollTrigger.update)
    gsap.ticker.add((t) => lenis!.raf(t * 1000))
    gsap.ticker.lagSmoothing(0) // without this Lenis stutters against GSAP's ticker

    // Native anchor jumps bypass Lenis and desync its internal scroll position —
    // route same-page hash links through lenis.scrollTo instead.
    document.addEventListener('click', (e) => {
      const link = (e.target as HTMLElement).closest?.('a[href^="#"]') as HTMLAnchorElement | null
      if (!link || !lenis) return
      const hash = link.getAttribute('href')
      if (!hash || hash === '#') return
      // querySelector throws on malformed selectors (e.g. "#1foo") — treat as no target.
      const target = (() => {
        try {
          return document.querySelector<HTMLElement>(hash)
        }
        catch {
          return null
        }
      })()
      if (!target) return
      e.preventDefault()
      lenis.scrollTo(target, { duration: 1.1, offset: -64 })
    })
  }

  // Auth surfaces (admin/portal/team/partners/investor) are dense work UIs:
  // smooth-scroll interpolation there just adds per-frame repaints under the
  // sticky blurred headers. lerp 1 applies scroll deltas immediately (native
  // feel) while keeping the same Lenis instance alive for the public pages —
  // destroying/recreating it would strand the refs components captured.
  const AUTH_SURFACE = /^\/(admin|portal|team|partners|investor)(\/|$)/

  // After each navigation, reset scroll (through Lenis so its internal position
  // stays in sync) and recalculate ScrollTrigger positions against the new layout.
  nuxtApp.hook('page:finish', () => {
    if (lenis) {
      lenis.options.lerp = AUTH_SURFACE.test(window.location.pathname) ? 1 : 0.1
      lenis.scrollTo(0, { immediate: true })
    }
    else {
      window.scrollTo({ top: 0 })
    }
    // Re-measuring forces a full-document reflow — skip it on pages that
    // registered no ScrollTriggers (all of the admin portal).
    if (ScrollTrigger.getAll().length) ScrollTrigger.refresh()
  })

  return {
    provide: { gsap, ScrollTrigger, SplitText, lenis },
  }
})
