import { MOTION } from '~/utils/motion'

type SplitTextInstance = InstanceType<typeof import('gsap/SplitText').SplitText>

interface SplitTextRevealOptions {
  /**
   * Restore the original (SSR) markup once the entrance completes.
   * Default true — keeps the DOM clean and resize-safe after the one-shot reveal.
   */
  revertOnComplete?: boolean
  /** Hook to adjust the split DOM before animating (e.g. gradient-text fixes). */
  onSplit?: (split: SplitTextInstance) => void
}

/**
 * Signature headline reveal — SplitText into masked words rising in.
 * Returns { build } rather than auto-running: call build() inside onMounted
 * to get the timeline so the caller can sequence it into a master timeline.
 * build() returns null under reduced motion / SSR (content stays visible).
 * Cleanup (revert + kill) is handled here on unmount.
 */
export function useSplitTextReveal(
  target: Ref<HTMLElement | null> | string,
  options: SplitTextRevealOptions = {},
) {
  const { gsap, SplitText, reduced } = useMotion()
  let split: SplitTextInstance | null = null
  let tl: gsap.core.Timeline | null = null

  const build = (): gsap.core.Timeline | null => {
    if (import.meta.server || reduced) return null

    const el = typeof target === 'string'
      ? document.querySelector<HTMLElement>(target)
      : target.value
    if (!el) return null

    split = new SplitText(el, { type: 'words', mask: 'words' })
    options.onSplit?.(split!)

    // Hidden state set imperatively (not via fromTo) so it is deterministic
    // regardless of where the timeline is nested.
    gsap.set(split!.words, { yPercent: 110 })

    tl = gsap.timeline({
      onComplete: () => {
        if (options.revertOnComplete !== false) {
          split?.revert()
          split = null
        }
      },
    })
    tl.to(split!.words, {
      yPercent: 0,
      duration: 1.1,
      ease: MOTION.ease.hero,
      stagger: MOTION.stagger.base,
    })
    return tl
  }

  onUnmounted(() => {
    tl?.kill()
    split?.revert()
    split = null
  })

  return { build }
}
