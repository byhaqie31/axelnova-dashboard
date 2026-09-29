<script setup lang="ts">
import OgTileArt from '~/components/og/OgTileArt.vue'

// Dark link-preview card for /feedback/{token} (1200×630). Carries the
// project name only — never the client's name: the preview is cached by
// WhatsApp/Meta and visible to anyone the link is forwarded to.
// Rendered by Takumi, so colours are literal mirrors of the dark-mode tokens.
const { project = '' } = defineProps<{ project?: string }>()

const headline = computed(() => (project ? `How did we do on ${project}?` : 'How did we do?'))
const headlineSize = computed(() => {
  const n = headline.value.length
  if (n > 52) return 60
  if (n > 34) return 68
  return 80
})
</script>

<template>
  <div
    class="w-full h-full"
    style="display: flex; position: relative; overflow: hidden; background: linear-gradient(135deg, #0A0F1F 0%, #14203F 100%); font-family: 'Inter';"
  >
    <OgTileArt dark />
    <div style="position: absolute; inset: 0; background: linear-gradient(90deg, rgba(12, 18, 38, 0.92) 0%, rgba(12, 18, 38, 0.70) 42%, rgba(12, 18, 38, 0) 66%);" />

    <div style="position: absolute; left: 80px; top: 72px; bottom: 64px; width: 680px; display: flex; flex-direction: column; align-items: flex-start;">
      <div style="display: flex; align-items: center; gap: 12px; padding: 12px 24px; border-radius: 999px; background: rgba(255, 255, 255, 0.10); font-size: 20px; font-weight: 700; letter-spacing: 0.08em; color: #CFE3FF;">
        <!-- dark --color-accent -->
        <div style="width: 11px; height: 11px; border-radius: 999px; background: #2997FF;" />
        <span>CLIENT FEEDBACK</span>
      </div>

      <!-- dark --color-text; Outfit = --font-display -->
      <div
        style="margin-top: 36px; font-family: 'Outfit'; font-weight: 600; line-height: 1.04; letter-spacing: -0.02em; color: #F5F5F7;"
        :style="{ fontSize: `${headlineSize}px` }"
      >{{ headline }}</div>

      <div style="margin-top: 26px; font-size: 28px; line-height: 1.4; color: #C2C4CC;">Two minutes, five questions.</div>

      <div style="flex-grow: 1;" />

      <!-- light --color-accent: white text keeps 4.5:1 on it -->
      <div style="display: flex; align-items: center; gap: 14px; padding: 20px 32px; border-radius: 999px; background: #0071E3; color: #FFFFFF; font-size: 24px; font-weight: 500;">
        <span>Share your feedback</span>
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </div>
    </div>
  </div>
</template>
