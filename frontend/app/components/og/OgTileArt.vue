<script setup lang="ts">
// The glass-tile motif from the site's link-preview card (og-image.jpg),
// redrawn in CSS so the generated OG cards (components/OgImage/*) can sit text
// beside it. Rendered by the OG image renderer (Takumi), not the browser — so
// literal colours: CSS variables from main.css don't reach the renderer.
// Values mirror the tokens noted inline.
const { dark = false } = defineProps<{ dark?: boolean }>()

// 3×3 grid; index 4 (centre) is the raised blue tile.
const tiles = Array.from({ length: 9 }, (_, i) => i)
</script>

<template>
  <div
    style="position: absolute; right: -40px; top: -60px; width: 760px; height: 760px; display: flex; flex-wrap: wrap; gap: 30px; transform: scaleY(0.62) rotate(45deg);"
    :style="{ opacity: dark ? 0.9 : 1 }"
  >
    <div
      v-for="i in tiles"
      :key="i"
      style="width: 230px; height: 230px; border-radius: 40px;"
      :style="i === 4
        ? {
          // accent #0071E3 family — a lit top face over a darker body
          background: 'linear-gradient(135deg, #4d8df5 0%, #1f5fd6 55%, #1747b8 100%)',
          boxShadow: '12px 12px 0 #123a99, 24px 24px 48px rgba(23, 71, 184, 0.45)',
          transform: 'translate(-14px, -14px)',
        }
        : dark
          ? {
            background: 'rgba(255, 255, 255, 0.05)',
            boxShadow: 'inset 0 0 0 2px rgba(255, 255, 255, 0.10)',
          }
          : {
            background: 'rgba(255, 255, 255, 0.32)',
            boxShadow: 'inset 0 0 0 2px rgba(255, 255, 255, 0.85)',
          }"
    />
  </div>
</template>
