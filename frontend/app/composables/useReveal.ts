import { MOTION } from '~/utils/motion'

type RevealTarget = string | Ref<HTMLElement | null>

interface RevealOptions {
  y?: number
  duration?: number
  ease?: string
  /** Stagger for groups (selector matching multiple elements). */
  stagger?: number
  delay?: number
  start?: string
}

/**
 * Fade-up scroll reveal — the standard entrance for sections and groups.
 * Initial hidden state is set by GSAP only (never CSS), so SSR/JS-off paints
 * full content. Reveals run once; never scrub-reversed.
 */
export function useReveal(target: RevealTarget, options: RevealOptions = {}) {
  if (import.meta.server) return

  const { gsap, reduced } = useMotion()
  let tween: gsap.core.Tween | null = null

  onMounted(() => {
    if (reduced) return // content is already visible — no-op

    const els = typeof target === 'string'
      ? Array.from(document.querySelectorAll<HTMLElement>(target))
      : ([target.value].filter(Boolean) as HTMLElement[])
    if (!els.length) return

    tween = gsap.fromTo(els,
      { opacity: 0, y: options.y ?? MOTION.reveal.y },
      {
        opacity: 1,
        y: 0,
        duration: options.duration ?? MOTION.dur.slow,
        ease: options.ease ?? MOTION.ease.out,
        stagger: options.stagger ?? 0,
        delay: options.delay ?? 0,
        scrollTrigger: {
          trigger: els[0],
          start: options.start ?? MOTION.reveal.start,
          once: true,
        },
        // Leftover transforms break sticky/fixed descendants and hover transforms.
        onComplete: () => gsap.set(els, { clearProps: 'opacity,transform' }),
      },
    )
  })

  onUnmounted(() => {
    tween?.scrollTrigger?.kill()
    tween?.kill()
  })
}
