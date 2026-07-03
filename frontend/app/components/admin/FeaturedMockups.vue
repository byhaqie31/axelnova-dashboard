<script setup lang="ts">
import { MOTION } from '~/utils/motion'

// Live client prototypes from the public Axel Nova mockup registry.
// The registry is CORS-open (`access-control-allow-origin: *`), so we fetch
// it straight from the browser; if the fetch fails we fall back to a frozen
// snapshot so the section never breaks the dashboard.
interface RegistryMockup {
  id?: string
  name: string
  client: string
  type: string
  status: string
  slug: string
  updatedAt?: string
  summary?: string
  archetype?: string
  tint?: { h: number, c: number }
  internal?: boolean
}

const REGISTRY_URL = 'https://axelnova.my/projects/registry.json'
const LISTING_URL = 'https://axelnova.my/projects/'
const MAX_ITEMS = 6

// Frozen snapshot of the top 6 public mockups — used only when the live
// registry is unreachable (offline, DNS, CORS regression).
const FALLBACK: RegistryMockup[] = [
  { name: 'Setia Air-Cond & Electrical', client: 'Setia Air-Cond and Electrical', type: 'HVAC & electrical site', status: 'draft', slug: 'setiaaircond' },
  { name: 'Baaqeeelah', client: 'Baaqeeelah', type: 'Bridal assistant booking', status: 'draft', slug: 'baaqeeelah' },
  { name: "MU'MIN by Al-Meswak", client: "Al-Meswak Mu'min", type: 'Halal personal care', status: 'draft', slug: 'muminalmeswak' },
  { name: 'One Malaysia Taxi', client: 'One Malaysia Taxi', type: 'Private chauffeur', status: 'draft', slug: 'onemalaysiataxi' },
  { name: 'Hz Academy', client: 'Hz Academy', type: 'Tuition academy', status: 'draft', slug: 'hzacademy' },
  { name: 'missmacaron.co', client: 'missmacaron.co', type: 'Macaron doorgift vendor', status: 'draft', slug: 'missmacaron' },
]

const mockups = ref<RegistryMockup[]>([])
const loading = ref(true)
const motion = useMotion()
const grid = ref<HTMLElement | null>(null)

// internal === true rows are admin-only and must never render publicly.
function pickPublicTop(items: RegistryMockup[]): RegistryMockup[] {
  return items
    .filter(m => m.internal !== true && m.slug && m.name)
    .sort((a, b) => (b.updatedAt ?? '').localeCompare(a.updatedAt ?? ''))
    .slice(0, MAX_ITEMS)
}

async function load() {
  loading.value = true
  try {
    const items = await $fetch<RegistryMockup[]>(REGISTRY_URL, { timeout: 10_000 })
    mockups.value = pickPublicTop(Array.isArray(items) ? items : [])
  }
  catch {
    mockups.value = FALLBACK
  }
  finally {
    loading.value = false
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
  }
}

onMounted(load)

function mockupUrl(m: RegistryMockup) {
  return `https://axelnova.my/${encodeURIComponent(m.slug)}/`
}

// Registry statuses map onto the existing status-pill vocabulary so the
// pill tokens in main.css stay the single source of truth.
function pillStatus(status: string) {
  const map: Record<string, string> = { 'in-review': 'reviewing', 'approved': 'accepted' }
  return map[status] ?? status
}

// tint {h, c} → hsl(h, c*400%, 55%), kept subtle: soft wash for the icon
// chip, full strength only for the icon + accent dot.
function accent(m: RegistryMockup) {
  if (!m.tint) return 'var(--color-accent)'
  const h = Math.round(Number(m.tint.h) || 0)
  const s = Math.min(100, Math.max(0, Math.round((Number(m.tint.c) || 0) * 400)))
  return `hsl(${h} ${s}% 55%)`
}

function accentSoft(m: RegistryMockup) {
  if (!m.tint) return 'var(--color-accent-soft)'
  const h = Math.round(Number(m.tint.h) || 0)
  const s = Math.min(100, Math.max(0, Math.round((Number(m.tint.c) || 0) * 400)))
  return `hsl(${h} ${s}% 55% / 0.12)`
}
</script>

<template>
  <section>
    <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
      <h2 class="text-[18px] font-semibold tracking-tight" style="color: var(--color-text);">Featured Mockups</h2>
      <p class="text-[12px]" style="color: var(--color-text-secondary);">Live client prototypes on axelnova.my</p>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
      <div
        v-for="n in MAX_ITEMS"
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
      No mockups to feature right now.
    </div>

    <template v-else>
      <div ref="grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
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
            :style="{ background: accentSoft(m), color: accent(m) }"
            :title="`Open ${m.name}`"
            aria-hidden="true"
          >
            <UIcon name="i-lucide-arrow-up-right" class="size-4" />
          </span>

          <div
            class="size-9 rounded-xl inline-flex items-center justify-center mb-3"
            :style="{ background: accentSoft(m), color: accent(m) }"
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
            <span
              class="text-[11px] px-2 py-0.5 rounded-md border line-clamp-1"
              :style="{ color: 'var(--color-text-secondary)', borderColor: 'var(--color-border)' }"
            >
              {{ m.type }}
            </span>
            <AdminStatusPill :status="pillStatus(m.status)" fallback="draft" />
          </div>
        </a>
      </div>

      <div class="flex justify-end mt-4">
        <a
          :href="LISTING_URL"
          target="_blank"
          rel="noopener"
          class="view-all inline-flex items-center gap-1.5 text-[12px] font-medium px-3.5 py-1.5 rounded-full border"
          :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
        >
          View all
          <UIcon name="i-lucide-arrow-up-right" class="size-3.5" />
        </a>
      </div>
    </template>
  </section>
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
