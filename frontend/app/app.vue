<script setup lang="ts">
import { MOTION } from '~/utils/motion'

// Route transitions: quick fade+slide via GSAP JS hooks (total < 0.8s).
// Under reduced motion the hooks complete instantly. Hooks only run on the
// client, where plugins/gsap.client.ts has provided $gsap.
const motion = import.meta.client ? useMotion() : null

const pageTransition = {
  css: false,
  mode: 'out-in' as const,
  onLeave(el: Element, done: () => void) {
    if (!motion || motion.reduced) return done()
    motion.gsap.to(el, {
      opacity: 0,
      y: -20,
      duration: 0.3,
      ease: 'power2.in',
      onComplete: done,
    })
  },
  onEnter(el: Element, done: () => void) {
    if (!motion || motion.reduced) return done()
    motion.gsap.fromTo(el,
      { opacity: 0, y: 24 },
      {
        opacity: 1,
        y: 0,
        duration: 0.45,
        ease: MOTION.ease.out,
        clearProps: 'opacity,transform',
        onComplete: done,
      },
    )
  },
}
</script>

<template>
  <UApp>
    <NuxtRouteAnnouncer />
    <NuxtLayout>
      <NuxtPage :transition="pageTransition" />
    </NuxtLayout>
  </UApp>
</template>
