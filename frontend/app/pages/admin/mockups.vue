<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

import { MOTION } from '~/utils/motion'
import { useMockupRegistry, mockupUrl, mockupAccent, MOCKUP_LISTING_URL } from '~/composables/useMockupRegistry'

// Every public mockup from the axelnova.my registry (internal rows excluded)
// — the dashboard used to feature the top six; this page lists them all.
const { mockups, loading, load } = useMockupRegistry(Infinity)
const motion = useMotion()
const grid = ref<HTMLElement | null>(null)

onMounted(async () => {
  await load()
  // Staggered card entrance once the real cards exist — dashboard register.
  await nextTick()
  const els = Array.from(grid.value?.children ?? [])
  if (import.meta.client && !motion.reduced && els.length) {
    motion.gsap.fromTo(els,
      { opacity: 0, y: 16 },
      {
        opacity: 1, y: 0,
        duration: 0.4, ease: MOTION.ease.out, stagger: MOTION.stagger.tight,
        clearProps: 'opacity,transform',
      },
    )
  }
})

// Registry statuses map onto the existing status-pill vocabulary so the
// pill tokens in main.css stay the single source of truth.
function pillStatus(status: string) {
  const map: Record<string, string> = { 'in-review': 'reviewing', 'approved': 'accepted' }
  return map[status] ?? status
}

function fmtDate(iso?: string) {
  if (!iso) return null
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <div class="flex items-start justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Mockups</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
          Live client prototypes on axelnova.my{{ !loading && mockups.length ? ` — ${mockups.length} public` : '' }}.
        </p>
      </div>
      <a
        :href="MOCKUP_LISTING_URL"
        target="_blank"
        rel="noopener"
        class="view-all inline-flex items-center gap-1.5 text-[12px] font-medium px-3.5 py-1.5 rounded-full border"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
      >
        Open listing
        <UIcon name="i-lucide-arrow-up-right" class="size-3.5" />
      </a>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
      <div
        v-for="n in 6"
        :key="n"
        class="rounded-2xl border p-5"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
      >
        <div class="mockup-shimmer size-9 rounded-xl mb-4" />
        <div class="mockup-shimmer h-4 w-3/4 rounded-md mb-2" />
        <div class="mockup-shimmer h-3 w-1/2 rounded-md mb-4" />
        <div class="mockup-shimmer h-3 w-full rounded-md mb-1.5" />
        <div class="mockup-shimmer h-3 w-2/3 rounded-md" />
      </div>
    </div>

    <!-- Empty state — registry reachable but nothing public to show -->
    <div
      v-else-if="!mockups.length"
      class="rounded-2xl border p-10 text-center text-[13px]"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
    >
      No public mockups in the registry right now.
    </div>

    <div v-else ref="grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
      <a
        v-for="m in mockups"
        :key="m.slug"
        :href="mockupUrl(m)"
        target="_blank"
        rel="noopener"
        class="mockup-card group relative rounded-2xl border p-5 flex flex-col"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
      >
        <!-- Hover-revealed open button, top-right — matches the stat tiles -->
        <span
          class="open-btn absolute top-5 right-5 inline-flex items-center justify-center size-8 rounded-lg"
          :style="{ background: mockupAccent(m, 0.12), color: mockupAccent(m) }"
          :title="`Open ${m.name}`"
          aria-hidden="true"
        >
          <UIcon name="i-lucide-arrow-up-right" class="size-4" />
        </span>

        <div
          class="size-9 rounded-xl inline-flex items-center justify-center mb-3"
          :style="{ background: mockupAccent(m, 0.12), color: mockupAccent(m) }"
        >
          <UIcon name="i-lucide-monitor-smartphone" class="size-4" />
        </div>

        <p class="text-[15px] font-semibold tracking-tight leading-snug line-clamp-1 pr-9" style="color: var(--color-text);">
          {{ m.name }}
        </p>
        <p class="text-[12px] mt-0.5 mb-2.5 line-clamp-1" style="color: var(--color-text-tertiary);">{{ m.client }}</p>

        <p v-if="m.summary" class="text-[12px] leading-relaxed line-clamp-2 mb-4" style="color: var(--color-text-secondary);">
          {{ m.summary }}
        </p>

        <div class="mt-auto flex items-center justify-between gap-2">
          <div class="flex items-center gap-2 min-w-0">
            <span
              class="text-[11px] px-2 py-0.5 rounded-md border truncate"
              :style="{ color: 'var(--color-text-secondary)', borderColor: 'var(--color-border)' }"
            >
              {{ m.type }}
            </span>
            <span v-if="fmtDate(m.updatedAt)" class="shrink-0 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">
              {{ fmtDate(m.updatedAt) }}
            </span>
          </div>
          <AdminStatusPill :status="pillStatus(m.status)" fallback="draft" />
        </div>
      </a>
    </div>
  </div>
</template>

<style scoped>
.mockup-card {
  transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}
.mockup-card:hover {
  border-color: var(--color-border-strong) !important;
  box-shadow: var(--shadow-sm);
  transform: translateY(-2px);
}

/* Open button: hidden at rest, fades + rises in on card hover. */
.open-btn {
  opacity: 0;
  transform: translateY(-3px) scale(0.92);
  transition: opacity 0.18s ease, transform 0.18s ease;
  pointer-events: none;
}
.mockup-card:hover .open-btn,
.mockup-card:focus-visible .open-btn {
  opacity: 1;
  transform: translateY(0) scale(1);
}

.view-all {
  transition: border-color 0.18s ease, color 0.18s ease, background 0.18s ease;
}
.view-all:hover {
  border-color: var(--color-border-strong);
  color: var(--color-text);
}

.mockup-shimmer {
  background: linear-gradient(100deg, var(--color-bg-secondary) 20%, var(--color-bg-elevated) 50%, var(--color-bg-secondary) 80%);
  background-size: 200% 100%;
  animation: mockup-shimmer 1.4s ease-in-out infinite;
}
@keyframes mockup-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

@media (prefers-reduced-motion: reduce) {
  .mockup-card { transition: none; }
  .mockup-card:hover { transform: none; }
  .open-btn { transition: none; }
  .mockup-shimmer { animation: none; }
}
</style>
