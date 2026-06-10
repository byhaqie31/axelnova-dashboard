<script setup lang="ts">
import { MOTION } from '~/utils/motion'

defineProps<{
  eyebrow?: string
  title: string
  subtitle?: string
  action?: { label: string, to: string }
}>()

const ruleEl = ref<HTMLElement | null>(null)
const motion = useMotion()
let tween: gsap.core.Tween | null = null

onMounted(() => {
  if (import.meta.server) return
  const { gsap, reduced } = motion
  const el = ruleEl.value
  if (!el || reduced) return // rule stays visible under reduced motion / JS-off

  tween = gsap.fromTo(el,
    { scaleX: 0 },
    {
      scaleX: 1,
      duration: 0.9,
      delay: 0.2,
      ease: MOTION.ease.out,
      scrollTrigger: { trigger: el, start: 'top 88%', once: true },
    },
  )
})

onUnmounted(() => {
  tween?.scrollTrigger?.kill()
  tween?.kill()
})
</script>

<template>
  <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-12">
    <div class="max-w-2xl">
      <p v-if="eyebrow" class="eyebrow mb-3 flex items-center gap-3">
        <span ref="ruleEl" class="section-rule" aria-hidden />
        {{ eyebrow }}
      </p>
      <h2
        class="text-4xl md:text-5xl font-semibold tracking-tight"
        style="color: var(--color-text);"
      >
        {{ title }}
      </h2>
      <p
        v-if="subtitle"
        class="mt-4 text-[17px] leading-relaxed"
        style="color: var(--color-text-secondary);"
      >
        {{ subtitle }}
      </p>
    </div>

    <NuxtLink
      v-if="action"
      :to="action.to"
      class="text-[14px] font-medium whitespace-nowrap inline-flex items-center gap-1.5 transition-all hover:gap-2.5"
      style="color: var(--color-accent);"
    >
      {{ action.label }} <span aria-hidden>→</span>
    </NuxtLink>
  </div>
</template>

<style scoped>
/* Draws in scaleX 0 → 1 (GSAP) when the header enters view. */
.section-rule {
  display: inline-block;
  width: 38px;
  height: 1.5px;
  background: var(--color-accent);
  transform-origin: left center;
  flex-shrink: 0;
}
</style>
