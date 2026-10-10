import { MOTION } from '~/utils/motion'

/**
 * Selector-based scroll reveal — the standard `.reveal` API used by pages.
 * Motion tokens (y: 52, top 85%, power3.out, 0.9s); same signature as before
 * so existing call sites keep working. Initial hidden state is set by GSAP
 * only — SSR/JS-off paints full content.
 *
 * Elements are grouped with `ScrollTrigger.batch`: only the ones entering the
 * viewport within the same ~100ms window cascade (0.06s apart), and each
 * batch starts the moment it enters. The previous per-element
 * `delay: i * 0.06` used the element's index across the WHOLE page, so the
 * 30th section sat invisible for ~2s after it had scrolled into view —
 * measured 2.5s to visible on /partners and 1.9s on /projects, which read as
 * the page being stuck.
 */
export function useScrollReveal(selector: string, options: Record<string, unknown> = {}) {
  if (import.meta.server) return

  const { gsap, ScrollTrigger, reduced } = useMotion()
  let triggers: import('gsap/ScrollTrigger').ScrollTrigger[] = []
  let elements: HTMLElement[] = []

  // Hand an element back to CSS: drop the reveal's inline opacity/transform
  // (leftover transforms break sticky/fixed descendants and hover transforms)
  // and the transition suppression. The cleared values equal the tween's end
  // values, so restoring the transition in the same task starts nothing.
  const savedTransition = new Map<Element, string>()
  const release = (els: Element[]) => {
    gsap.set(els, { clearProps: 'opacity,transform' })
    els.forEach((el) => {
      (el as HTMLElement).style.setProperty('transition', savedTransition.get(el) ?? '')
      savedTransition.delete(el)
    })
  }

  const reveal = () => {
    if (reduced) return // content is already visible — no-op

    elements = Array.from(document.querySelectorAll<HTMLElement>(selector))
    if (!elements.length) return

    // GSAP owns these elements until their reveal completes. A CSS transition
    // on opacity/transform (a card's `transition-all` hover rule, say) would
    // re-ease every per-frame value GSAP writes — measured: it stretched the
    // project-card fade from 0.7s to 1.05s and turned the initial hide into a
    // visible fade-out at mount. Restored per element on complete.
    elements.forEach((el) => {
      savedTransition.set(el, el.style.getPropertyValue('transition'))
      el.style.setProperty('transition', 'none')
    })
    gsap.set(elements, { opacity: 0, y: MOTION.reveal.y })

    triggers = ScrollTrigger.batch(elements, {
      start: MOTION.reveal.start,
      once: true,
      interval: 0.1,
      onEnter: (batch) => {
        // A fast flick (or an instant jump) enters many elements in one
        // window, including ones the viewport has already scrolled past.
        // Those just become visible — nobody would see them animate, and
        // keeping them in the stagger is what delayed the on-screen ones.
        const passed = batch.filter(el => el.getBoundingClientRect().bottom <= 0)
        const onScreen = batch.filter(el => !passed.includes(el))
        if (passed.length) release(passed)
        if (!onScreen.length) return

        // Adjacent elements entering the viewport together cascade 0.06s
        // apart, but a whole grid of cards on screen at once must not queue
        // up: the cascade is capped at 0.3s in total, so the last card of a
        // 13-card grid starts 0.3s in instead of 0.78s.
        const cascade = Math.min(0.06, 0.3 / Math.max(1, onScreen.length - 1))

        gsap.to(onScreen, {
          opacity: 1,
          y: 0,
          duration: MOTION.dur.slow,
          ease: MOTION.ease.out,
          stagger: cascade,
          overwrite: true,
          onComplete: () => release(onScreen),
          ...options,
        })
      },
    })

    ScrollTrigger.refresh()
  }

  onMounted(() => {
    nextTick(() => {
      requestAnimationFrame(() => {
        setTimeout(reveal, 60)
      })
    })
  })

  onUnmounted(() => {
    triggers.forEach(t => t.kill())
    triggers = []
    if (elements.length) gsap.killTweensOf(elements)
  })
}
