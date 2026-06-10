import { MOTION } from '~/utils/motion'

/**
 * Selector-based scroll reveal — the standard `.reveal` API used by pages.
 * Upgraded to the motion tokens (y: 52, top 85%, power3.out, 0.9s); same
 * signature as before so existing call sites keep working. Initial hidden
 * state is set by GSAP only — SSR/JS-off paints full content.
 */
export function useScrollReveal(selector: string, options: Record<string, unknown> = {}) {
  if (import.meta.server) return

  const { gsap, ScrollTrigger, reduced } = useMotion()
  const tweens: gsap.core.Tween[] = []

  const reveal = () => {
    if (reduced) return // content is already visible — no-op

    const elements = document.querySelectorAll<HTMLElement>(selector)

    elements.forEach((el, i) => {
      const tween = gsap.fromTo(el,
        { opacity: 0, y: MOTION.reveal.y },
        {
          opacity: 1,
          y: 0,
          duration: MOTION.dur.slow,
          // Adjacent elements entering the viewport together cascade.
          delay: i * 0.06,
          ease: MOTION.ease.out,
          scrollTrigger: {
            trigger: el,
            start: MOTION.reveal.start,
            once: true,
          },
          // Leftover transforms break sticky/fixed descendants and hover transforms.
          onComplete: () => gsap.set(el, { clearProps: 'opacity,transform' }),
          ...options,
        },
      )
      tweens.push(tween)
    })

    ScrollTrigger?.refresh()
  }

  onMounted(() => {
    nextTick(() => {
      requestAnimationFrame(() => {
        setTimeout(reveal, 60)
      })
    })
  })

  onUnmounted(() => {
    tweens.forEach((t) => {
      t.scrollTrigger?.kill()
      t.kill()
    })
  })
}
