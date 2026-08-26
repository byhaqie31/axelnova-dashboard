<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()

interface Overview {
  range: number
  views: { total: number; unique: number; series: { date: string; count: number }[] }
  topPaths: { path: string; count: number }[]
  topReferrers: { referrer: string; count: number }[]
  topLikedProjects: { id: number; name: string; likes: number }[]
}

// Keys must match the whitelist in AnalyticsController::overview — an unknown
// value there silently falls back to 7 days rather than erroring.
const RANGES = [
  { key: '7d', label: 'Last 7 days' },
  { key: '30d', label: 'Last 30 days' },
  { key: '90d', label: 'Last 90 days' },
] as const
type RangeKey = typeof RANGES[number]['key']

const range = ref<RangeKey>('7d')
const data = ref<Overview | null>(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await apiFetch<Overview>(`/api/v1/admin/analytics/overview?range=${range.value}`)
  }
  catch {
    error.value = 'Failed to load analytics. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
watch(range, load)

const maxCount = computed(() => Math.max(1, ...(data.value?.views.series.map(s => s.count) ?? [0])))
const hasViews = computed(() => (data.value?.views.total ?? 0) > 0)

// Bars are scaled, not resized: at 90 days that is 90 elements animating at
// once on every range change, and transform stays off the layout path.
function barScale(c: number) {
  return c > 0 ? Math.max(0.06, c / maxCount.value) : 0.02
}
// 90 days will not fit at the 7-day spacing on a phone; tighten it up.
const dense = computed(() => (data.value?.views.series.length ?? 0) > 45)
function fmtDay(d: string) {
  return new Date(d).toLocaleDateString('en-MY', { day: 'numeric', month: 'short' })
}
function hostOf(ref: string) {
  try { return new URL(ref).hostname.replace(/^www\./, '') }
  catch { return ref }
}

// --- Bar readout -----------------------------------------------------------
// The old `title` attribute is a hover-only native tooltip, so on a phone the
// chart's daily numbers were unreachable. Tap toggles a card on the bar; mouse
// still gets it on hover. Guarded by pointerType so a tap doesn't also fire the
// synthetic mouse events and immediately re-open what the tap just closed.
const activeBar = ref<number | null>(null)

function onBarEnter(i: number, e: PointerEvent) {
  if (e.pointerType === 'mouse') activeBar.value = i
}
function onBarLeave(e: PointerEvent) {
  if (e.pointerType === 'mouse') activeBar.value = null
}
function onBarClick(i: number) {
  activeBar.value = activeBar.value === i ? null : i
}
// Centre of the active bar as a percentage, clamped so the card never hangs off
// either edge of the chart.
function readoutLeft(i: number) {
  const n = data.value?.views.series.length ?? 1
  return `clamp(64px, ${((i + 0.5) / n) * 100}%, calc(100% - 64px))`
}
const activePoint = computed(() =>
  activeBar.value === null ? null : data.value?.views.series[activeBar.value] ?? null,
)
// Any range change re-renders the chart — a stale index would point at the
// wrong day.
watch(() => data.value, () => { activeBar.value = null })

// --- Top referrers ---------------------------------------------------------
// The API groups by full referrer URL, so one site shows up several times
// (different paths on the same host). Fold them onto the host and sum.
const mergedReferrers = computed(() => {
  const totals = new Map<string, number>()
  for (const r of data.value?.topReferrers ?? []) {
    const host = hostOf(r.referrer)
    totals.set(host, (totals.get(host) ?? 0) + r.count)
  }
  return [...totals.entries()]
    .map(([host, count]) => ({ host, count }))
    .sort((a, b) => b.count - a.count)
})

// --- Top pages -------------------------------------------------------------
// Raw paths are hard to scan. Most routes title-case cleanly from their slug;
// only the ones whose slug does not say what the page is need an override.
const PAGE_LABELS: Record<string, string> = {
  '/': 'Main page',
  '/quote': 'Quote builder',
  '/partners/refer': 'Refer a project',
}

// Routes whose child segment is an opaque identifier, never a page name. The
// feedback one matters: that segment is the one-time access token from the
// client's email, so title-casing it would print a live token in the admin UI.
const PREFIX_LABELS: [string, string][] = [
  ['/feedback/', 'Feedback form'],
]

function titleCase(s: string) {
  return s.replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

// Catch-all for ids we haven't named: long and separator-less is an identifier,
// not a slug. Real page slugs are hyphenated ("trip-money", "privacy-policy").
function looksOpaque(s: string) {
  return s.length > 20 && !s.includes('-')
}

function pageLabel(path: string) {
  const clean = path.replace(/\/+$/, '') || '/'
  if (PAGE_LABELS[clean]) return PAGE_LABELS[clean]
  for (const [prefix, label] of PREFIX_LABELS) {
    if (clean.startsWith(prefix)) return label
  }
  const segs = clean.split('/').filter(Boolean)
  if (!segs.length) return 'Main page'
  const section = titleCase(segs[0]!)
  const tail = segs[segs.length - 1]!
  if (segs.length > 1 && looksOpaque(tail)) return section
  // Detail pages read better with their section: "Trip Money · Project".
  return segs.length > 1 ? `${titleCase(tail)} · ${section.replace(/s$/, '')}` : titleCase(tail)
}

// Not-yet-built metrics (later Phase B slices).
const planned = [
  { label: 'Service interest', description: 'Likes + views per service package — informs which to feature.', icon: 'i-lucide-package' },
  { label: 'Quote funnel', description: 'Views → quote start → submitted leads conversion.', icon: 'i-lucide-funnel' },
]
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <div class="flex items-end justify-between gap-4 flex-wrap mb-8">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Analytics</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Traffic, engagement, and conversion signals.</p>
      </div>
      <div class="flex gap-1.5 flex-wrap">
        <button
          v-for="r in RANGES"
          :key="r.key"
          type="button"
          class="standard-pill"
          :style="range === r.key
            ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
            : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
          :aria-pressed="range === r.key"
          @click="range = r.key"
        >
          {{ r.label }}
        </button>
      </div>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <!-- Page views -->
    <section class="rounded-2xl border p-6 mb-4" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
      <div class="flex items-start justify-between gap-6 flex-wrap mb-6">
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Page views</p>
          <p class="text-[34px] font-bold tracking-tight tabular-nums leading-none" style="color: var(--color-text);">
            <span v-if="loading" class="opacity-40">—</span>
            <span v-else>{{ data?.views.total.toLocaleString() ?? 0 }}</span>
          </p>
        </div>
        <div class="text-right">
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Unique visitors</p>
          <p class="text-[34px] font-bold tracking-tight tabular-nums leading-none" style="color: var(--color-text);">
            <span v-if="loading" class="opacity-40">—</span>
            <span v-else>{{ data?.views.unique.toLocaleString() ?? 0 }}</span>
          </p>
        </div>
      </div>

      <div v-if="loading" class="h-44 flex items-center justify-center text-[13px]" style="color: var(--color-text-secondary);">Loading…</div>
      <div v-else-if="!hasViews" class="h-44 flex flex-col items-center justify-center text-center gap-1">
        <UIcon name="i-lucide-chart-line" class="size-6 mb-1" :style="{ color: 'var(--color-text-tertiary)' }" />
        <p class="text-[13px] font-medium" :style="{ color: 'var(--color-text)' }">No page views yet</p>
        <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">Visits to the public site will appear here.</p>
      </div>
      <div v-else>
        <!-- pt-14 reserves a band for the readout card so it never overlaps the
             totals above, and the bars keep their original h-44 scale. -->
        <div class="relative pt-14">
          <!-- Readout card. Anchored to the active bar and clamped to the chart
               so it stays on screen at either edge. -->
          <Transition name="readout-fade">
            <div
              v-if="activePoint"
              class="readout-card absolute top-0 z-10 -translate-x-1/2 rounded-xl border px-3 py-2 pointer-events-none whitespace-nowrap"
              :style="{
                left: readoutLeft(activeBar!),
                borderColor: 'var(--color-border)',
                background: 'var(--color-bg-elevated)',
                boxShadow: 'var(--shadow-lg)',
              }"
              role="status"
            >
              <p class="text-[10px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">
                {{ fmtDay(activePoint.date) }}
              </p>
              <p class="text-[17px] font-bold tabular-nums leading-tight" style="color: var(--color-text);">
                {{ activePoint.count.toLocaleString() }}
                <span class="text-[11px] font-medium" style="color: var(--color-text-secondary);">
                  view{{ activePoint.count === 1 ? '' : 's' }}
                </span>
              </p>
            </div>
          </Transition>

          <!-- Each button is the full column height, not just the drawn bar, so
               a near-zero day (2% tall) and a 3px-wide 90-day bar are both
               still tappable. -->
          <div class="flex items-stretch h-44" :class="dense ? 'gap-px' : 'gap-1'">
            <button
              v-for="(pt, i) in data?.views.series"
              :key="pt.date"
              type="button"
              class="analytics-bar flex-1 h-full flex items-end"
              :style="{ minWidth: dense ? '2px' : '3px' }"
              :aria-label="`${fmtDay(pt.date)}: ${pt.count} view${pt.count === 1 ? '' : 's'}`"
              :aria-pressed="activeBar === i"
              @pointerenter="onBarEnter(i, $event)"
              @pointerleave="onBarLeave($event)"
              @click="onBarClick(i)"
            >
              <span
                class="analytics-bar-fill block w-full h-full rounded-t-[3px]"
                :style="{
                  transform: `scaleY(${barScale(pt.count)})`,
                  background: pt.count > 0 ? 'var(--color-accent)' : 'var(--color-border)',
                  opacity: activeBar !== null && activeBar !== i ? 0.45 : 1,
                }"
              />
            </button>
          </div>
        </div>
        <div class="flex justify-between mt-2 text-[10px] font-medium uppercase tracking-wide" style="color: var(--color-text-tertiary);">
          <span>{{ fmtDay(data!.views.series[0]!.date) }}</span>
          <span>{{ fmtDay(data!.views.series[data!.views.series.length - 1]!.date) }}</span>
        </div>
      </div>
    </section>

    <!-- Top paths + referrers -->
    <div v-if="!loading && hasViews" class="grid md:grid-cols-2 gap-4 mb-10">
      <section class="rounded-2xl border p-6" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
        <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Top pages</p>
        <div v-if="!data?.topPaths.length" class="text-[13px]" style="color: var(--color-text-tertiary);">No data.</div>
        <ul v-else class="space-y-2.5">
          <li v-for="p in data.topPaths" :key="p.path" class="flex items-center justify-between gap-3">
            <span class="text-[13px] truncate" style="color: var(--color-text);">{{ pageLabel(p.path) }}</span>
            <span class="text-[12px] font-semibold tabular-nums shrink-0" style="color: var(--color-text-secondary);">{{ p.count.toLocaleString() }}</span>
          </li>
        </ul>
      </section>

      <section class="rounded-2xl border p-6" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
        <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Top referrers</p>
        <div v-if="!mergedReferrers.length" class="text-[13px]" style="color: var(--color-text-tertiary);">Mostly direct — no referrers recorded.</div>
        <ul v-else class="space-y-2.5">
          <li v-for="r in mergedReferrers" :key="r.host" class="flex items-center justify-between gap-3">
            <span class="text-[12px] truncate" style="color: var(--color-text);">{{ r.host }}</span>
            <span class="text-[12px] font-semibold tabular-nums shrink-0" style="color: var(--color-text-secondary);">{{ r.count.toLocaleString() }}</span>
          </li>
        </ul>
      </section>
    </div>

    <!-- Most-liked projects -->
    <section v-if="!loading" class="rounded-2xl border p-6 mb-10" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
      <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Most-liked projects</p>
      <div v-if="!data?.topLikedProjects.length" class="text-[13px]" style="color: var(--color-text-tertiary);">No project likes yet.</div>
      <ul v-else class="space-y-2.5">
        <li v-for="p in data.topLikedProjects" :key="p.id" class="flex items-center justify-between gap-3">
          <span class="text-[13px] truncate" style="color: var(--color-text);">{{ p.name }}</span>
          <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold tabular-nums shrink-0" style="color: var(--color-danger);">
            <UIcon name="i-fluent-heart-24-filled" class="size-3.5" />
            {{ p.likes.toLocaleString() }}
          </span>
        </li>
      </ul>
    </section>

    <!-- Coming in later Phase B slices -->
    <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" style="color: var(--color-text-tertiary);">Coming next</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div
        v-for="m in planned"
        :key="m.label"
        class="rounded-2xl border p-5"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
      >
        <div class="flex items-start justify-between mb-3">
          <div class="size-9 rounded-xl inline-flex items-center justify-center" :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }">
            <UIcon :name="m.icon" class="size-4" />
          </div>
          <span
            class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
            :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg-secondary)' }"
          >
            Soon
          </span>
        </div>
        <p class="text-[14px] font-semibold tracking-tight mb-1" :style="{ color: 'var(--color-text)' }">{{ m.label }}</p>
        <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">{{ m.description }}</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Bars are buttons now — give them a real touch target and a hover cue without
   changing the chart's geometry. */
.analytics-bar {
  cursor: pointer;
  appearance: none;
  border: 0;
  padding: 0;
  background: none;
}
.analytics-bar:hover .analytics-bar-fill {
  filter: brightness(1.08);
}

/* scaleY from the bottom, so a range change animates 90 bars on the compositor
   instead of triggering 90 layout passes. */
.analytics-bar-fill {
  transform-origin: bottom;
  transition: transform 0.3s, opacity 0.2s, filter 0.2s;
}
.analytics-bar:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

.readout-fade-enter-active,
.readout-fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.readout-fade-enter-from,
.readout-fade-leave-to {
  opacity: 0;
  transform: translate(-50%, 4px);
}

@media (prefers-reduced-motion: reduce) {
  .readout-fade-enter-active,
  .readout-fade-leave-active { transition: none; }
  .analytics-bar-fill { transition: none; }
}
</style>
