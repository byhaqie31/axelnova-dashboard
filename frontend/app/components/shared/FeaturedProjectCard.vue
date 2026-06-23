<script setup lang="ts">
import type { Project } from '~/data/projects'
import LikeButton from '~/components/shared/LikeButton.vue'

const props = defineProps<{ project: Project }>()

// --- Live preview ----------------------------------------------------------
// WordPress mShots turns a live URL into a screenshot with no API key and no
// watermark. It generates asynchronously: the first hit returns a small
// "generating preview" placeholder, then serves the real capture once ready.
// We detect the placeholder by its width and retry a few times; on persistent
// failure (or no URL) we fall back to a gradient tile.
const SHOT_W = 1280
const SHOT_H = 800
const MAX_RETRIES = 4

const shotBase = computed(() =>
  props.project.url
    ? `https://s.wp.com/mshots/v1/${encodeURIComponent(props.project.url)}?w=${SHOT_W}&h=${SHOT_H}`
    : null,
)

const retry = ref(0)
const loaded = ref(false)
const failed = ref(false)

// `&retry=` only busts the browser cache so a still-generating shot is refetched.
const shotSrc = computed(() =>
  shotBase.value
    ? (retry.value ? `${shotBase.value}&retry=${retry.value}` : shotBase.value)
    : null,
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

// Pretty host for the fake address bar.
const host = computed(() => {
  if (!props.project.url) return null
  try {
    return new URL(props.project.url).host.replace(/^www\./, '')
  }
  catch {
    return props.project.url.replace(/^https?:\/\//, '').replace(/\/.*$/, '')
  }
})

const statusMeta = (status: Project['status']) => {
  switch (status) {
    case 'live':     return { label: 'Live',        color: 'var(--color-success)', bg: 'rgba(48,209,88,0.14)' }
    case 'wip':      return { label: 'In progress', color: 'var(--color-warning)', bg: 'rgba(255,159,10,0.14)' }
    case 'planning': return { label: 'Planning',    color: 'var(--color-accent)',  bg: 'rgba(0,113,227,0.12)' }
    case 'soon':     return { label: 'Planning',    color: 'var(--color-accent)',  bg: 'rgba(0,113,227,0.12)' }
  }
}
</script>

<template>
  <div
    class="fcard group relative rounded-2xl border overflow-hidden flex flex-col"
    style="background: var(--color-bg-elevated); border-color: var(--color-border);"
  >
    <!-- Stretched link covers the whole card → detail page -->
    <NuxtLink
      :to="`/projects/${project.id}`"
      class="absolute inset-0 z-10"
      :aria-label="`View ${project.name} details`"
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
          v-if="host"
          class="ml-2 flex-1 truncate text-center text-[11px] px-3 py-0.5 rounded-md border"
          style="color: var(--color-text-secondary); background: var(--color-bg-elevated); border-color: var(--color-border);"
        >
          {{ host }}
        </span>
      </div>

      <!-- viewport -->
      <div class="relative aspect-3/2 overflow-hidden" style="background: var(--color-bg-secondary);">
        <!-- skeleton while the shot loads -->
        <span v-if="!loaded && !failed" aria-hidden class="fshimmer absolute inset-0" />

        <img
          v-if="shotSrc && !failed"
          :key="shotSrc"
          :src="shotSrc"
          :alt="`Live preview of ${project.name}`"
          loading="lazy"
          decoding="async"
          class="fshot absolute inset-0 size-full object-cover object-top"
          :style="{ opacity: loaded ? 1 : 0 }"
          @load="onShotLoad"
          @error="onShotError"
        >

        <!-- fallback: no URL or screenshot failed -->
        <div
          v-if="!shotSrc || failed"
          class="absolute inset-0 flex items-center justify-center"
          style="background: radial-gradient(120% 120% at 0% 0%, var(--color-accent-soft) 0%, transparent 60%), var(--color-bg-secondary);"
        >
          <UIcon name="i-fluent-stack-24-regular" class="size-10" :style="{ color: 'var(--color-accent)' }" />
        </div>

        <!-- visit-live shortcut on hover -->
        <a
          v-if="project.url"
          :href="project.url"
          target="_blank"
          rel="noopener"
          :aria-label="`Open ${project.name} in a new tab`"
          class="fopen absolute bottom-3 right-3 z-20 inline-flex items-center gap-1.5 text-[12px] font-medium px-3 py-1.5 rounded-full opacity-0 group-hover:opacity-100 transition-all duration-300"
          style="background: var(--color-accent); color:#fff; box-shadow: 0 6px 16px rgba(0,113,227,0.32);"
          @click.stop
        >
          Visit live <UIcon name="i-fluent-arrow-up-right-24-regular" class="size-3.5" />
        </a>
      </div>
    </div>

    <!-- META -->
    <div class="relative flex flex-col flex-1 p-6">
      <div class="flex items-start justify-between gap-3 mb-2">
        <h3 class="text-[19px] font-semibold tracking-tight" style="color: var(--color-text);">
          {{ project.name }}
        </h3>
        <div class="flex items-center gap-2 shrink-0">
          <LikeButton
            v-if="project.dbId"
            class="relative z-20"
            type="project"
            :id="project.dbId"
            :count="project.likes ?? 0"
          />
          <span
            class="text-[11px] font-medium px-2.5 py-1 rounded-full inline-flex items-center gap-1.5"
            :style="{ color: statusMeta(project.status).color, background: statusMeta(project.status).bg }"
          >
            <span class="size-1.5 rounded-full" :style="{ background: statusMeta(project.status).color }" />
            {{ statusMeta(project.status).label }}
          </span>
        </div>
      </div>

      <p class="text-[14px] leading-relaxed mb-5 line-clamp-2" style="color: var(--color-text-secondary);">
        {{ project.description }}
      </p>

      <div class="mt-auto flex flex-wrap gap-1.5">
        <span
          v-for="tag in project.stack"
          :key="tag"
          class="text-[11px] px-2 py-0.5 rounded-md border"
          :style="{ color: 'var(--color-text-secondary)', borderColor: 'var(--color-border)' }"
        >
          {{ tag }}
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.fcard {
  transition: transform 0.3s, border-color 0.3s, box-shadow 0.3s;
}
.fcard:hover {
  transform: translateY(-4px);
  border-color: var(--color-border-strong) !important;
  box-shadow: var(--shadow-card-hover);
}

/* Gentle zoom on the screenshot as the card is hovered. */
.fshot {
  transition: opacity 0.5s ease, transform 0.6s cubic-bezier(0.33, 1, 0.68, 1);
}
.fcard:hover .fshot {
  transform: scale(1.04);
}

/* Loading shimmer behind the screenshot. */
.fshimmer {
  background: linear-gradient(100deg, transparent 20%, var(--color-bg-elevated) 50%, transparent 80%);
  background-size: 200% 100%;
  animation: fshimmer 1.4s ease-in-out infinite;
}
@keyframes fshimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

@media (prefers-reduced-motion: reduce) {
  .fcard:hover .fshot { transform: none; }
  .fshimmer { animation: none; }
}
</style>
