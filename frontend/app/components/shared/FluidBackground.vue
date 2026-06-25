<script setup lang="ts">
/**
 * Fluid generative-art background — https://fluid.krackeddevs.com
 *
 * A fixed, full-bleed, click-through layer that sits behind page content
 * (z-index:-1, in front of the body canvas, behind everything else). The art
 * renders on the visitor's GPU inside an embedded iframe — nothing to host, no
 * build step, free.
 *
 * Resilience (most-resilient pattern from the Fluid docs):
 *   1. a solid `--color-bg` always paints underneath, so an offline Fluid never
 *      leaves a broken frame — the page just falls back to the app background;
 *   2. pass `fallbackImage` (a self-hosted export of the same preset) to show a
 *      static frame instead of the flat colour when the live layer can't load;
 *   3. under `prefers-reduced-motion` the animated iframe is dropped entirely and
 *      the static frame / solid colour shows instead (matches how the hero pauses
 *      its background video).
 *
 * A translucent scrim over the art keeps foreground text legible without hiding
 * the motion — tune via `scrim` (0 = none, 1 = opaque app background).
 *
 * Default preset: OCEAN · Noise · 16:9 — zoom 2.20 · warp 3.0 · speed 0.40 ·
 * grain 0.030 · seed 25. Remix/export:
 * https://fluid.krackeddevs.com/#p=0.4,2.2,3,0.03,1,10,0,2,25,0,0,1.7778
 */
const props = withDefaults(defineProps<{
  /** Fluid hash params — everything after `#p=` (the embed string). */
  params?: string
  /** Optional self-hosted static frame shown beneath the live layer. */
  fallbackImage?: string
  /** Legibility scrim strength, 0–1 (share of `--color-bg` mixed over the art). */
  scrim?: number
  /**
   * Let the art react to the cursor (warp toward the pointer, like the live
   * preview). The iframe becomes hittable; the host page must mark the areas
   * that should pass through it `pointer-events: none` and re-enable
   * `pointer-events: auto` on its real controls (see the admin login).
   */
  interactive?: boolean
}>(), {
  params: '0.4,2.2,3,0.03,1,10,0,2,25,0,0,1.7778,0,1',
  scrim: 0.42,
  interactive: false,
})

const src = computed(() => `https://fluid.krackeddevs.com/#p=${props.params}`)
const scrimBg = computed(
  () => `color-mix(in srgb, var(--color-bg) ${Math.round(props.scrim * 100)}%, transparent)`,
)
</script>

<template>
  <div class="fluid-bg" aria-hidden="true">
    <div
      v-if="fallbackImage"
      class="fluid-bg__static"
      :style="{ backgroundImage: `url('${fallbackImage}')` }"
    />
    <iframe
      :src="src"
      title=""
      tabindex="-1"
      class="fluid-bg__frame"
      :class="{ 'fluid-bg__frame--interactive': interactive }"
    />
    <div class="fluid-bg__scrim" :style="{ background: scrimBg }" />
  </div>
</template>

<style scoped>
.fluid-bg {
  position: fixed;
  inset: 0;
  z-index: -1;
  overflow: hidden;
  pointer-events: none;
  /* Offline / pre-load fallback — degrades to the normal app background. */
  background: var(--color-bg);
}

/* Layers stack in DOM order (all absolute, same stacking context):
   static frame → live iframe (covers it once loaded) → scrim (on top). */
.fluid-bg__static,
.fluid-bg__frame,
.fluid-bg__scrim {
  position: absolute;
  inset: 0;
}

.fluid-bg__static {
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
}

.fluid-bg__frame {
  width: 100%;
  height: 100%;
  border: 0;
  display: block;
}

/* Interactive mode: the iframe re-enables hit-testing (the wrapper + scrim stay
   click-through), so the art can react to the cursor. */
.fluid-bg__frame--interactive {
  pointer-events: auto;
}

/* Reduced motion: drop the animated layer; the static frame or solid colour
   underneath remains. */
@media (prefers-reduced-motion: reduce) {
  .fluid-bg__frame {
    display: none;
  }
}
</style>
