<script setup lang="ts">
/**
 * Full-bleed ambient video background — a fixed, click-through layer behind
 * page content (z-index:-1, in front of the body canvas, behind everything
 * else). Used by the admin / team / partner login screens, each with its own
 * footage.
 *
 * Resilience:
 *   1. a solid `--color-bg` always paints underneath, so a failed load
 *      degrades to the normal app background — never a broken frame;
 *   2. footage is ambient motion — under `prefers-reduced-motion` it is paused
 *      on its first frame (same policy as the public hero video).
 */
defineProps<{
  /** mp4 source URL. */
  src: string
}>()

const videoEl = ref<HTMLVideoElement | null>(null)

onMounted(() => {
  if (!videoEl.value) return
  // Vue can drop the `muted` prop on hydration, which breaks autoplay — force it.
  videoEl.value.muted = true
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) videoEl.value.pause()
})
</script>

<template>
  <div class="video-bg" aria-hidden="true">
    <video ref="videoEl" class="video-bg__video" autoplay loop muted playsinline preload="auto">
      <source :src="src" type="video/mp4">
    </video>
  </div>
</template>

<style scoped>
.video-bg {
  position: fixed;
  inset: 0;
  z-index: -1;
  overflow: hidden;
  pointer-events: none;
  /* Offline / pre-load fallback — degrades to the normal app background. */
  background: var(--color-bg);
}

.video-bg__video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
