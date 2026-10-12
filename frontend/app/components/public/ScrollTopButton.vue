<script setup lang="ts">
// Floating Back to top (public layout). Appears only while the visitor scrolls
// UP past the first screen — the moment they're heading back — and hides as
// they scroll down to read or reach the top, like the header's reveal. Its
// ring is a reading-progress gauge: the aurora stroke fills as the page
// scrolls down and drains on the way up.
const motion = useMotion()

const visible = ref(false)
const progress = ref(0) // 0 → 1 over the scrollable height

// Ring geometry (viewBox 44): r 20 leaves room for the 2.5px stroke.
const RADIUS = 20
const CIRCUMFERENCE = 2 * Math.PI * RADIUS
const dashOffset = computed(() => CIRCUMFERENCE * (1 - progress.value))

// Rides Lenis when it owns scrolling (a native smooth scroll would fight it),
// then hands keyboard focus to the header's brand link so a keyboard user
// lands where the eye does.
function toTop() {
  if (motion.lenis) motion.lenis.scrollTo(0, { duration: motion.reduced ? 0 : 1.4 })
  else window.scrollTo({ top: 0, behavior: motion.reduced ? 'auto' : 'smooth' })
  document.querySelector<HTMLElement>('header a')?.focus({ preventScroll: true })
}

onMounted(() => {
  let lastY = window.scrollY
  let upAccum = 0
  let downAccum = 0
  let frame = 0

  const update = () => {
    frame = 0
    const y = window.scrollY
    const max = document.documentElement.scrollHeight - window.innerHeight
    progress.value = max > 0 ? Math.min(1, Math.max(0, y / max)) : 0

    // Past ~60% of the first screen; 6–8px accumulators so jitter or a
    // trackpad's rebound doesn't flicker it.
    const dy = y - lastY
    if (y <= window.innerHeight * 0.6) {
      visible.value = false
      upAccum = downAccum = 0
    }
    else if (dy < 0) {
      upAccum -= dy
      downAccum = 0
      if (upAccum > 8) visible.value = true
    }
    else if (dy > 0) {
      downAccum += dy
      upAccum = 0
      if (downAccum > 6) visible.value = false
    }
    lastY = y
  }

  const onScroll = () => { if (!frame) frame = requestAnimationFrame(update) }

  update()
  window.addEventListener('scroll', onScroll, { passive: true })
  window.addEventListener('resize', onScroll, { passive: true })
  onBeforeUnmount(() => {
    cancelAnimationFrame(frame)
    window.removeEventListener('scroll', onScroll)
    window.removeEventListener('resize', onScroll)
  })
})
</script>

<template>
  <button
    type="button"
    class="scroll-top"
    :class="{ 'is-visible': visible }"
    :tabindex="visible ? 0 : -1"
    :aria-hidden="!visible"
    aria-label="Back to top"
    title="Back to top"
    @click="toTop"
  >
    <svg class="scroll-top-ring" viewBox="0 0 44 44" aria-hidden="true">
      <defs>
        <linearGradient id="scroll-top-gradient" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" class="stop-pink" />
          <stop offset="35%" class="stop-violet" />
          <stop offset="70%" class="stop-blue" />
          <stop offset="100%" class="stop-cyan" />
        </linearGradient>
      </defs>
      <circle class="scroll-top-track" cx="22" cy="22" :r="RADIUS" />
      <circle
        class="scroll-top-progress"
        cx="22" cy="22" :r="RADIUS"
        :stroke-dasharray="CIRCUMFERENCE"
        :stroke-dashoffset="dashOffset"
      />
    </svg>
    <UIcon name="i-lucide-arrow-up" class="scroll-top-arrow size-4.5" />
  </button>
</template>

<style scoped>
/* Bottom-right, clear of the iPhone home bar. Hidden = faded, nudged down,
   slightly small, and click-through; the header's z-50 (and its mobile
   drawer) stays above it. */
.scroll-top {
  position: fixed;
  right: 16px;
  bottom: calc(16px + env(safe-area-inset-bottom));
  z-index: 30;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 9999px;
  background: var(--color-bg-elevated);
  box-shadow: var(--shadow-md);
  color: var(--color-text);
  opacity: 0;
  transform: translateY(12px) scale(0.9);
  pointer-events: none;
  transition: opacity 0.3s ease, transform 0.35s cubic-bezier(0.215, 0.61, 0.355, 1), box-shadow 0.3s ease;
}
@media (min-width: 768px) {
  .scroll-top { right: 24px; bottom: calc(24px + env(safe-area-inset-bottom)); }
}
.scroll-top.is-visible {
  opacity: 1;
  transform: none;
  pointer-events: auto;
}
.scroll-top.is-visible:hover { transform: translateY(-2px); box-shadow: var(--shadow-md), var(--shadow-glow); }
.scroll-top.is-visible:active { transform: scale(0.96); }
.scroll-top:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 3px; }

.scroll-top-ring,
.scroll-top-arrow { grid-area: 1 / 1; }

/* Starts at 12 o'clock, fills clockwise. */
.scroll-top-ring {
  width: 44px;
  height: 44px;
  transform: rotate(-90deg);
}
.scroll-top-track,
.scroll-top-progress {
  fill: none;
  stroke-width: 2.5;
}
.scroll-top-track { stroke: var(--color-border); }
.scroll-top-progress {
  stroke: url(#scroll-top-gradient);
  stroke-linecap: round;
  transition: stroke-dashoffset 0.1s linear;
}
.stop-pink { stop-color: var(--grad-aurora-pink); }
.stop-violet { stop-color: var(--grad-aurora-violet); }
.stop-blue { stop-color: var(--grad-aurora-blue); }
.stop-cyan { stop-color: var(--grad-aurora-cyan); }

@media (prefers-reduced-motion: reduce) {
  .scroll-top,
  .scroll-top-progress { transition: opacity 0.2s ease; }
  .scroll-top,
  .scroll-top.is-visible,
  .scroll-top.is-visible:hover,
  .scroll-top.is-visible:active { transform: none; }
}
</style>
