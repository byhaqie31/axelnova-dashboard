import { MOTION } from '~/utils/motion'

interface CounterOptions {
  /** Marketing default 1.8s; dashboards shorten to ~0.9s. */
  duration?: number
}

/**
 * Counts an element's textContent from 0 to `end` on first view, once.
 * Animates a JS proxy (never layout). Put `tabular-nums` on the element
 * so digits don't jitter. Under reduced motion the final value is set instantly.
 */
export function useCountUp(
  target: Ref<HTMLElement | null> | (() => HTMLElement | null | undefined),
  end: number,
  options: CounterOptions = {},
) {
  if (import.meta.server) return

  const { gsap, reduced } = useMotion()
  let tween: gsap.core.Tween | null = null

  onMounted(() => {
    const el = typeof target === 'function' ? target() : target.value
    if (!el) return

    if (reduced) {
      el.textContent = String(end)
      return
    }

    // SSR renders the real value (JS-off keeps it); zero it now, post-hydration,
    // so the count runs 0 → end on first view.
    el.textContent = '0'

    const proxy = { v: 0 }
    tween = gsap.to(proxy, {
      v: end,
      duration: options.duration ?? 1.8,
      ease: MOTION.ease.settle,
      snap: { v: 1 },
      scrollTrigger: { trigger: el, start: 'top 88%', once: true },
      onUpdate: () => { el.textContent = String(Math.round(proxy.v)) },
    })
  })

  onUnmounted(() => {
    tween?.scrollTrigger?.kill()
    tween?.kill()
  })
}
