/**
 * Scrubbed parallax drift for media inside an overflow-hidden mask.
 * The mask must be sized 114% height with top: -7% so the ±5% drift never
 * exposes edges (see docs/frontend/MOTION.md). Desktop pointer:fine only;
 * skipped on touch and under reduced motion.
 */
export function useParallaxImage(target: Ref<HTMLElement | null> | string) {
  if (import.meta.server) return

  const { gsap, reduced } = useMotion()
  let tween: gsap.core.Tween | null = null

  onMounted(() => {
    if (reduced || !window.matchMedia('(pointer: fine)').matches) return

    const el = typeof target === 'string'
      ? document.querySelector<HTMLElement>(target)
      : target.value
    if (!el) return

    tween = gsap.fromTo(el,
      { yPercent: -5 },
      {
        yPercent: 5,
        ease: 'none',
        scrollTrigger: {
          trigger: el.parentElement ?? el,
          start: 'top bottom',
          end: 'bottom top',
          scrub: true,
        },
      },
    )
  })

  onUnmounted(() => {
    tween?.scrollTrigger?.kill()
    tween?.kill()
  })
}
