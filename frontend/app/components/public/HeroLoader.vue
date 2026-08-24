<script setup lang="ts">
import BrandMark from '~/components/shared/BrandMark.vue'
import { MOTION } from '~/utils/motion'

// Purely visual overlay for the homepage intro. The parent (HeroEpoch) owns the
// readiness logic and flips `done` when fonts + the video's first frame are in
// (or its cap expires); this component completes the bar, fades itself out, and
// emits `reveal` the moment the fade starts so the hero entrance can begin
// underneath it. Token-based colors only — no colorMode binding (FOUC rule).
const props = defineProps<{ done: boolean }>()
const emit = defineEmits<{ reveal: [] }>()

const motion = useMotion()
const rootEl = ref<HTMLElement | null>(null)
const barEl = ref<HTMLElement | null>(null)
const visible = ref(true)

let creepTween: gsap.core.Tween | null = null
let outTl: gsap.core.Timeline | null = null

onMounted(() => {
  if (!barEl.value) return
  // Creep toward 90% while assets load so the bar never sits dead; the last
  // 10% is reserved for the actual ready signal. Duration tracks the parent's
  // LOADER_MIN_MS branding hold.
  creepTween = motion.gsap.fromTo(
    barEl.value,
    { width: '0%' },
    { width: '90%', duration: 3.4, ease: 'power2.out' },
  )
})

watch(() => props.done, (done) => {
  if (!done) return
  const { gsap } = motion
  if (!rootEl.value || !barEl.value) {
    visible.value = false
    emit('reveal')
    return
  }
  creepTween?.kill()
  outTl = gsap.timeline({ onComplete: () => { visible.value = false } })
  outTl.to(barEl.value, { width: '100%', duration: 0.25, ease: 'power1.out' })
  outTl.to(rootEl.value, {
    opacity: 0,
    duration: MOTION.dur.base,
    ease: MOTION.ease.inout,
    onStart: () => emit('reveal'),
  })
})

onUnmounted(() => {
  creepTween?.kill()
  outTl?.kill()
})
</script>

<template>
  <div
    v-if="visible"
    ref="rootEl"
    class="fixed inset-0 z-[90] flex flex-col items-center justify-center gap-6"
    style="background: var(--color-bg);"
    aria-hidden="true"
  >
    <BrandMark class="pointer-events-none" />
    <div class="h-0.5 w-40 overflow-hidden rounded-full" style="background: var(--color-border);">
      <div ref="barEl" class="h-full rounded-full" style="width: 0%; background: var(--grad-iridescent);" />
    </div>
    <span class="eyebrow loader-label" style="color: var(--color-text-secondary);">Loading</span>
  </div>
</template>

<style scoped>
/* Gentle breathing on the label so the hold never reads as frozen. The loader
   is only mounted when reduced motion is off (gated by HeroEpoch). */
.loader-label {
  animation: loader-label-pulse 1.8s ease-in-out infinite;
}
@keyframes loader-label-pulse {
  0%, 100% { opacity: 0.45; }
  50%      { opacity: 1; }
}
</style>
