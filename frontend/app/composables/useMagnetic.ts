import type { ComponentPublicInstance } from 'vue'
import { MOTION } from '~/utils/motion'

type MagneticTarget = Ref<HTMLElement | ComponentPublicInstance | null>

/**
 * Magnetic pull on primary CTAs — desktop pointer:fine only.
 * The button translates 0.3× the cursor offset from its center; an inner
 * `.magnetic-label` span translates 0.12× for depth. Release springs back
 * with the single elastic ease allowed in the system.
 * Wrap the button's contents in `<span class="magnetic-label">` (the class
 * sets pointer-events: none in main.css).
 */
export function useMagnetic(target: MagneticTarget) {
  if (import.meta.server) return

  const { gsap, reduced } = useMotion()
  let el: HTMLElement | null = null
  let label: HTMLElement | null = null
  let onMove: ((e: PointerEvent) => void) | null = null
  let onLeave: (() => void) | null = null

  onMounted(() => {
    if (reduced || !window.matchMedia('(pointer: fine)').matches) return

    const raw = target.value
    el = raw && '$el' in (raw as ComponentPublicInstance)
      ? (raw as ComponentPublicInstance).$el as HTMLElement
      : raw as HTMLElement | null
    if (!el) return

    // Drops the CSS transform transition so quickTo updates aren't smoothed twice.
    el.classList.add('is-magnetic')
    label = el.querySelector<HTMLElement>('.magnetic-label')

    const xTo = gsap.quickTo(el, 'x', { duration: 0.4, ease: MOTION.ease.out })
    const yTo = gsap.quickTo(el, 'y', { duration: 0.4, ease: MOTION.ease.out })
    const labelXTo = label ? gsap.quickTo(label, 'x', { duration: 0.4, ease: MOTION.ease.out }) : null
    const labelYTo = label ? gsap.quickTo(label, 'y', { duration: 0.4, ease: MOTION.ease.out }) : null

    onMove = (e: PointerEvent) => {
      const rect = el!.getBoundingClientRect()
      const dx = e.clientX - (rect.left + rect.width / 2)
      const dy = e.clientY - (rect.top + rect.height / 2)
      xTo(dx * 0.3)
      yTo(dy * 0.3)
      labelXTo?.(dx * 0.12)
      labelYTo?.(dy * 0.12)
    }

    onLeave = () => {
      const targets = label ? [el!, label] : [el!]
      gsap.to(targets, { x: 0, y: 0, duration: 0.8, ease: MOTION.ease.spring })
    }

    el.addEventListener('pointermove', onMove)
    el.addEventListener('pointerleave', onLeave)
  })

  onUnmounted(() => {
    if (!el) return
    if (onMove) el.removeEventListener('pointermove', onMove)
    if (onLeave) el.removeEventListener('pointerleave', onLeave)
    gsap.killTweensOf(label ? [el, label] : [el])
  })
}
