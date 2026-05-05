export function useScrollReveal(selector: string, options: Record<string, unknown> = {}) {
  if (import.meta.server) return

  const { $gsap, $ScrollTrigger } = useNuxtApp() as unknown as {
    $gsap: typeof import('gsap').default
    $ScrollTrigger: typeof import('gsap/ScrollTrigger').ScrollTrigger
  }

  const tweens: gsap.core.Tween[] = []

  const reveal = () => {
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    const elements = document.querySelectorAll<HTMLElement>(selector)

    if (prefersReduced) {
      elements.forEach((el) => { el.style.opacity = '1'; el.style.transform = 'none' })
      return
    }

    elements.forEach((el, i) => {
      const tween = $gsap.fromTo(el,
        { opacity: 0, y: 24 },
        {
          opacity: 1,
          y: 0,
          duration: 0.7,
          delay: i * 0.06,
          ease: 'power2.out',
          scrollTrigger: {
            trigger: el,
            start: 'top 95%',
            once: true,
          },
          ...options,
        },
      )
      tweens.push(tween)
    })

    $ScrollTrigger?.refresh()
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
