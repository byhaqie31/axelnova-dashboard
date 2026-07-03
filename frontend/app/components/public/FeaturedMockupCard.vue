<script setup lang="ts">
import { mockupUrl, mockupAccent, type RegistryMockup } from '~/composables/useMockupRegistry'

const props = defineProps<{ mockup: RegistryMockup }>()
const emit = defineEmits<{ preview: [] }>()

const url = computed(() => mockupUrl(props.mockup))

// --- Live preview ----------------------------------------------------------
// Same mShots approach as FeaturedProjectCard: screenshots generate
// asynchronously — the first hit returns a small placeholder, so we detect it
// by width and retry; on persistent failure we fall back to a tinted tile.
const SHOT_W = 1280
const SHOT_H = 800
const MAX_RETRIES = 4

const shotBase = computed(() =>
  `https://s.wp.com/mshots/v1/${encodeURIComponent(url.value)}?w=${SHOT_W}&h=${SHOT_H}`,
)

const retry = ref(0)
const loaded = ref(false)
const failed = ref(false)

// `&retry=` only busts the browser cache so a still-generating shot is refetched.
const shotSrc = computed(() =>
  retry.value ? `${shotBase.value}&retry=${retry.value}` : shotBase.value,
)

let retryTimer: ReturnType<typeof setTimeout> | undefined
const scheduleRetry = () => {
  if (retry.value >= MAX_RETRIES) {
    failed.value = true
    return
  }
  retryTimer = setTimeout(() => { retry.value += 1 }, 1800)
}

const onShotLoad = (e: Event) => {
  const img = e.target as HTMLImageElement
  // Real captures come back ~SHOT_W wide; the placeholder is far narrower.
  if (img.naturalWidth > 0 && img.naturalWidth < 600 && retry.value < MAX_RETRIES) {
    scheduleRetry()
    return
  }
  loaded.value = true
}

const onShotError = () => scheduleRetry()

onUnmounted(() => clearTimeout(retryTimer))

const host = computed(() => `axelnova.my/${props.mockup.slug}`)

// Registry lifecycle → public-friendly badge (colors from design tokens).
const statusMeta = computed(() => {
  switch (props.mockup.status) {
    case 'approved':  return { label: 'Approved',  color: 'var(--color-success)', bg: 'rgba(48,209,88,0.14)' }
    case 'in-review': return { label: 'In review', color: 'var(--color-warning)', bg: 'rgba(255,159,10,0.14)' }
    case 'sent':      return { label: 'Sent',      color: 'var(--color-accent)',  bg: 'rgba(0,113,227,0.12)' }
    case 'archived':  return { label: 'Archived',  color: 'var(--color-text-secondary)', bg: 'var(--color-bg-secondary)' }
    default:          return { label: 'Concept',   color: 'var(--color-accent)',  bg: 'rgba(0,113,227,0.12)' }
  }
})
</script>

<template>
  <div
    class="mcard group relative rounded-2xl border overflow-hidden flex flex-col"
    style="background: var(--color-bg-elevated); border-color: var(--color-border);"
  >
    <!-- Stretched button covers the whole card → preview popup -->
    <button
      type="button"
      class="absolute inset-0 z-10 cursor-pointer"
      :aria-label="`Preview ${mockup.name}`"
      @click="emit('preview')"
    />

    <!-- PREVIEW (browser-window frame) -->
    <div class="relative">
      <!-- chrome -->
      <div
        class="flex items-center gap-2 px-4 h-9 border-b"
        style="border-color: var(--color-border); background: var(--color-bg-secondary);"
      >
        <span class="flex gap-1.5 shrink-0">
          <span class="size-2.5 rounded-full" style="background:#ff5f57" />
          <span class="size-2.5 rounded-full" style="background:#febc2e" />
          <span class="size-2.5 rounded-full" style="background:#28c840" />
        </span>
        <span
          class="ml-2 flex-1 truncate text-center text-[11px] px-3 py-0.5 rounded-md border"
          style="color: var(--color-text-secondary); background: var(--color-bg-elevated); border-color: var(--color-border);"
        >
          {{ host }}
        </span>
      </div>

      <!-- viewport -->
      <div class="relative aspect-3/2 overflow-hidden" style="background: var(--color-bg-secondary);">
        <!-- skeleton while the shot loads -->
        <span v-if="!loaded && !failed" aria-hidden class="mshimmer absolute inset-0" />

        <img
          v-if="!failed"
          :key="shotSrc"
          :src="shotSrc"
          :alt="`Live preview of ${mockup.name}`"
          loading="lazy"
          decoding="async"
          class="mshot absolute inset-0 size-full object-cover object-top"
          :style="{ opacity: loaded ? 1 : 0 }"
          @load="onShotLoad"
          @error="onShotError"
        >

        <!-- fallback: screenshot failed — tinted tile from the registry accent -->
        <div
          v-if="failed"
          class="absolute inset-0 flex items-center justify-center"
          :style="{ background: `radial-gradient(120% 120% at 0% 0%, ${mockupAccent(mockup, 0.16)} 0%, transparent 60%), var(--color-bg-secondary)` }"
        >
          <UIcon name="i-lucide-monitor-smartphone" class="size-10" :style="{ color: mockupAccent(mockup) }" />
        </div>

        <!-- visit-live shortcut on hover (skips the popup) -->
        <a
          :href="url"
          target="_blank"
          rel="noopener"
          :aria-label="`Open ${mockup.name} in a new tab`"
          class="mopen absolute bottom-3 right-3 z-20 inline-flex items-center gap-1.5 text-[12px] font-medium px-3 py-1.5 rounded-full opacity-0 group-hover:opacity-100 transition-all duration-300"
          style="background: var(--color-accent); color:#fff; box-shadow: 0 6px 16px rgba(0,113,227,0.32);"
          @click.stop
        >
          Visit live <UIcon name="i-fluent-arrow-up-right-24-regular" class="size-3.5" />
        </a>
      </div>
    </div>

    <!-- META -->
    <div class="relative flex flex-col flex-1 p-6">
      <div class="flex items-start justify-between gap-3 mb-1">
        <h3 class="text-[19px] font-semibold tracking-tight line-clamp-1" style="color: var(--color-text);">
          {{ mockup.name }}
        </h3>
        <span
          class="shrink-0 text-[11px] font-medium px-2.5 py-1 rounded-full inline-flex items-center gap-1.5"
          :style="{ color: statusMeta.color, background: statusMeta.bg }"
        >
          <span class="size-1.5 rounded-full" :style="{ background: statusMeta.color }" />
          {{ statusMeta.label }}
        </span>
      </div>

      <p class="text-[13px] mb-3 line-clamp-1" style="color: var(--color-text-tertiary);">{{ mockup.client }}</p>

      <p v-if="mockup.summary" class="text-[14px] leading-relaxed mb-5 line-clamp-2" style="color: var(--color-text-secondary);">
        {{ mockup.summary }}
      </p>

      <div class="mt-auto flex flex-wrap items-center gap-1.5">
        <span
          class="text-[11px] px-2 py-0.5 rounded-md border"
          :style="{ color: 'var(--color-text-secondary)', borderColor: 'var(--color-border)' }"
        >
          {{ mockup.type }}
        </span>
        <span
          class="text-[11px] px-2 py-0.5 rounded-md border inline-flex items-center gap-1"
          :style="{ color: mockupAccent(mockup), borderColor: 'var(--color-border)', background: mockupAccent(mockup, 0.08) }"
        >
          <UIcon name="i-lucide-eye" class="size-3" />
          Preview
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.mcard {
  transition: transform 0.3s, border-color 0.3s, box-shadow 0.3s;
}
.mcard:hover {
  transform: translateY(-4px);
  border-color: var(--color-border-strong) !important;
  box-shadow: var(--shadow-card-hover);
}

/* Gentle zoom on the screenshot as the card is hovered. */
.mshot {
  transition: opacity 0.5s ease, transform 0.6s cubic-bezier(0.33, 1, 0.68, 1);
}
.mcard:hover .mshot {
  transform: scale(1.04);
}

/* Loading shimmer behind the screenshot. */
.mshimmer {
  background: linear-gradient(100deg, transparent 20%, var(--color-bg-elevated) 50%, transparent 80%);
  background-size: 200% 100%;
  animation: mshimmer 1.4s ease-in-out infinite;
}
@keyframes mshimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

@media (prefers-reduced-motion: reduce) {
  .mcard { transition: none; }
  .mcard:hover { transform: none; }
  .mcard:hover .mshot { transform: none; }
  .mshimmer { animation: none; }
}
</style>
