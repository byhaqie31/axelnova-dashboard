<script setup lang="ts">
import OgTileArt from '~/components/og/OgTileArt.vue'

// Light link-preview card for public pages (1200×630). Pages opt in through
// usePublicSeo({ card }) — see docs/frontend/OG-IMAGES.md. With `image` (a
// project screenshot) a browser-framed shot replaces the tile art.
// Rendered by Takumi, not the browser: CSS variables from main.css don't
// reach it, so colours are literal — mirrors of the light-mode tokens noted.
const {
  label,
  headline,
  subline = '',
  footer = 'axelnovaventures.com',
  image = '',
} = defineProps<{
  label: string
  headline: string
  subline?: string
  footer?: string
  image?: string
}>()

// Long headlines step down so they stay within three lines; the narrower
// column beside a screenshot starts smaller so one-word names still fit.
const headlineSize = computed(() => {
  const n = headline.length
  if (image) return n > 24 ? 52 : 64
  if (n > 52) return 60
  if (n > 34) return 68
  return 80
})
</script>

<template>
  <div
    class="w-full h-full"
    style="display: flex; position: relative; overflow: hidden; background: linear-gradient(135deg, #F7F7F9 0%, #E6E7EC 100%); font-family: 'Inter';"
  >
    <template v-if="!image">
      <OgTileArt />
      <!-- Fades the tiles out behind the copy so the headline stays crisp -->
      <div style="position: absolute; inset: 0; background: linear-gradient(90deg, rgba(240, 241, 244, 0.94) 0%, rgba(240, 241, 244, 0.80) 42%, rgba(240, 241, 244, 0) 66%);" />
    </template>

    <!-- Browser-framed screenshot -->
    <div
      v-else
      style="position: absolute; right: -70px; top: 96px; width: 620px; display: flex; flex-direction: column; border-radius: 18px; overflow: hidden; background: #FFFFFF; box-shadow: 0 36px 80px rgba(0, 0, 0, 0.22), 0 0 0 2px rgba(0, 0, 0, 0.06);"
    >
      <div style="height: 32px; display: flex; align-items: center; gap: 9px; padding-left: 16px; background: #ECEEF2;">
        <div style="width: 11px; height: 11px; border-radius: 999px; background: #C9CCD3;" />
        <div style="width: 11px; height: 11px; border-radius: 999px; background: #C9CCD3;" />
        <div style="width: 11px; height: 11px; border-radius: 999px; background: #C9CCD3;" />
      </div>
      <img :src="image" width="620" height="388" style="width: 620px; height: 388px; object-fit: cover; object-position: top;">
    </div>

    <!-- Copy column -->
    <div
      style="position: absolute; left: 80px; top: 72px; bottom: 64px; display: flex; flex-direction: column; align-items: flex-start;"
      :style="{ width: image ? '500px' : '680px' }"
    >
      <div style="display: flex; align-items: center; gap: 12px; padding: 12px 24px; border-radius: 999px; background: #FFFFFF; font-size: 20px; font-weight: 700; letter-spacing: 0.08em; color: #3F44B5;">
        <!-- --color-accent -->
        <div style="width: 11px; height: 11px; border-radius: 999px; background: #0071E3;" />
        <span>{{ label }}</span>
      </div>

      <!-- --color-text; Outfit = --font-display -->
      <div
        style="margin-top: 36px; font-family: 'Outfit'; font-weight: 600; line-height: 1.04; letter-spacing: -0.02em; color: #1D1D1F;"
        :style="{ fontSize: `${headlineSize}px` }"
      >{{ headline }}</div>

      <!-- --color-text-secondary, darkened for contrast on the grey ground -->
      <div
        v-if="subline"
        style="margin-top: 26px; font-size: 28px; line-height: 1.4; color: #55555A;"
      >{{ subline }}</div>

      <div style="flex-grow: 1;" />

      <!-- The arrow is drawn: → isn't in the latin font subset -->
      <div style="display: flex; align-items: center; gap: 14px; padding: 20px 32px; border-radius: 999px; background: #1D1D1F; color: #FFFFFF; font-size: 24px; font-weight: 500;">
        <span>{{ footer }}</span>
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </div>
    </div>
  </div>
</template>
