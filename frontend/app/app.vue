<script setup lang="ts">
import { MOTION } from '~/utils/motion'
import { jumpToHashOrTop } from '~/utils/hashScroll'

// Route transitions: quick fade+slide via GSAP JS hooks (total < 0.8s).
// Under reduced motion the hooks complete instantly. Hooks only run on the
// client, where plugins/gsap.client.ts has provided $gsap.
const motion = import.meta.client ? useMotion() : null
const router = useRouter()

// Auth surfaces (admin/portal/team/partners/investor) skip the animation
// entirely: with mode 'out-in' the incoming page — and therefore its onMounted
// data fetch — cannot start until the leave animation finishes, so the fade
// serialized 300ms in front of every API call. Work tools stay instant.
const AUTH_SURFACE = /^\/(admin|portal|team|partners|investor)(\/|$)/

function skipTransition(): boolean {
  if (!motion || motion.reduced) return true
  // By transition time the router has already committed the target route.
  return AUTH_SURFACE.test(router.currentRoute.value.path)
}

const pageTransition = {
  css: false,
  mode: 'out-in' as const,
  onLeave(el: Element, done: () => void) {
    if (skipTransition()) return done()
    motion!.gsap.to(el, {
      opacity: 0,
      y: -20,
      duration: 0.3,
      ease: 'power2.in',
      onComplete: done,
    })
  },
  onEnter(el: Element, done: () => void) {
    // Arriving at another page's #hash (e.g. a package card's "+N more
    // features"): land on it now, before the fade-in, so the page appears
    // already there. This is the first moment the new page is in the DOM —
    // page:finish fires while the old one is still leaving (utils/hashScroll.ts).
    if (window.location.hash) jumpToHashOrTop(motion?.lenis ?? null)
    if (skipTransition()) return done()
    motion!.gsap.fromTo(el,
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
  <UApp :toaster="{ position: 'top-right' }">
    <NuxtRouteAnnouncer />
    <NuxtLayout>
      <NuxtPage :transition="pageTransition" />
    </NuxtLayout>
  </UApp>
</template>
