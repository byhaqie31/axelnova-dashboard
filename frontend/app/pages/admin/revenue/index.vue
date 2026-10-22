<script setup lang="ts">
import { useElementSize } from '@vueuse/core'
import { closeArea, formatCompactMyr, monotonePath, nearestIndex, yTicks } from '~/utils/areaChart'
import { formatMyr } from '~/utils/money'
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()

interface MonthRow {
  month: string
  label: string
  collected: number
  fees: number
  refunded: number
  payments: number
  booked: number
  orders: number
}
interface Totals {
  collected: number
  booked: number
  fees: number
  net: number
  refunded: number
  payments: number
  orders: number
}

const months = ref<6 | 12 | 24>(12)
const series = ref<MonthRow[]>([])
const totals = ref<Totals | null>(null)
const loading = ref(true)
const error = ref('')
const hovered = ref<number | null>(null)

async function fetchRevenue() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ series: MonthRow[], totals: Totals }>(
      `/api/v1/admin/revenue/monthly?months=${months.value}`,
    )
    series.value = res.series
    totals.value = res.totals
  }
  catch {
    error.value = 'Failed to load revenue. Check your session.'
  }
  finally {
    loading.value = false
  }
}

onMounted(fetchRevenue)
watch(months, fetchRevenue)

// A range switch keeps the old chart on screen, dimmed, until the new data
// lands — no skeleton, no layout jump. Only the very first load shows text.
const refetching = computed(() => loading.value && series.value.length > 0)

// Both series share one scale — they're the same unit (MYR), so a second y-axis
// would misrepresent the gap between them. That gap is the whole point here.
const scaleMax = computed(() =>
  Math.max(1, ...series.value.flatMap(r => [r.booked, r.collected])),
)

// --- Chart geometry -----------------------------------------------------------
// Hand-drawn SVG in real pixels: the frame is measured, so strokes stay 2px and
// text stays crisp at every width instead of stretching with a viewBox.
const CHART_H = 240
const PAD = { top: 14, right: 14, bottom: 26, left: 58 } as const

const chartEl = ref<HTMLElement | null>(null)
const { width: measuredWidth } = useElementSize(chartEl)
const chartW = computed(() => Math.max(measuredWidth.value, 280))

const plot = computed(() => ({
  x0: PAD.left,
  x1: chartW.value - PAD.right,
  y0: PAD.top,
  y1: CHART_H - PAD.bottom,
}))

/** One x per month, oldest → left. */
const xs = computed(() => {
  const n = series.value.length
  const { x0, x1 } = plot.value
  if (n <= 1) return n ? [(x0 + x1) / 2] : []
  const slot = (x1 - x0) / (n - 1)
  return series.value.map((_, i) => x0 + i * slot)
})

function yOf(value: number): number {
  const { y0, y1 } = plot.value
  return y1 - (Math.max(0, value) / scaleMax.value) * (y1 - y0)
}

const gridTicks = computed(() => yTicks(scaleMax.value).map(v => ({ value: v, y: yOf(v) })))

function linePath(key: 'booked' | 'collected'): string {
  return monotonePath(series.value.map((r, i) => ({ x: xs.value[i]!, y: yOf(r[key]) })))
}

const paths = computed(() => {
  const first = xs.value[0] ?? 0
  const last = xs.value[xs.value.length - 1] ?? 0
  const booked = linePath('booked')
  const collected = linePath('collected')
  return {
    booked,
    collected,
    bookedArea: closeArea(booked, first, last, plot.value.y1),
    collectedArea: closeArea(collected, first, last, plot.value.y1),
  }
})

/** The latest month gets a resting marker on each line — where we are now. */
const endDots = computed(() => {
  const i = series.value.length - 1
  const row = series.value[i]
  if (!row) return []
  const x = xs.value[i]!
  return [
    { key: 'booked', x, y: yOf(row.booked), color: 'var(--chart-secondary)' },
    { key: 'collected', x, y: yOf(row.collected), color: 'var(--chart-primary)' },
  ]
})

// Label every Nth month so ticks never collide, whatever the width — one rule
// replaces the old "every other at 24" and the mobile-only hiding.
const labelStep = computed(() => {
  const n = series.value.length
  if (n <= 1) return 1
  const slot = (plot.value.x1 - plot.value.x0) / (n - 1)
  return Math.max(1, Math.ceil(44 / slot))
})

// The crosshair snaps to the nearest month: readers aim at a date, not a line.
function onPointerMove(e: PointerEvent) {
  const rect = (e.currentTarget as SVGSVGElement).getBoundingClientRect()
  const i = nearestIndex(xs.value, e.clientX - rect.left)
  hovered.value = i >= 0 ? i : null
}

function onChartClick() {
  const row = hovered.value !== null ? series.value[hovered.value] : undefined
  if (row) openMonth(row.month)
}

/** Tooltip anchored to the hovered month, flipped inward near either edge. */
const tooltip = computed(() => {
  const i = hovered.value
  const row = i !== null ? series.value[i] : undefined
  if (i === null || !row) return null
  const x = xs.value[i]!
  const top = Math.min(yOf(row.booked), yOf(row.collected))
  const edge = x < 120 ? 'left' : x > chartW.value - 120 ? 'right' : 'center'
  return {
    row,
    style: {
      left: `${x}px`,
      bottom: `${CHART_H - top + 12}px`,
      transform: edge === 'center' ? 'translateX(-50%)' : edge === 'right' ? 'translateX(-100%)' : 'none',
    },
  }
})

// Every month drills into its own breakdown page.
function openMonth(month: string) {
  navigateTo(`/admin/revenue/${month}`)
}

const hasData = computed(() => series.value.some(r => r.booked > 0 || r.collected > 0))

// The table reads newest-first — the month you want is almost always the latest.
// The chart deliberately stays oldest→left: a time axis running backwards is
// hard to read. Each entry carries its index in `series` so hovering a row still
// highlights the matching month (and vice versa) after the reverse.
const tableRows = computed(() =>
  series.value.map((row, index) => ({ row, index })).reverse(),
)

/** Collection rate over the window — how much of what we sold actually landed. */
const collectionRate = computed(() => {
  if (!totals.value || totals.value.booked <= 0) return null
  return Math.round((totals.value.collected / totals.value.booked) * 100)
})

const tiles = computed(() => [
  { key: 'booked', label: 'Booked', value: totals.value?.booked, hint: 'Contracted value of orders won', swatch: 'var(--chart-secondary)' },
  { key: 'collected', label: 'Collected', value: totals.value?.collected, hint: 'Cash received, net of refunds', swatch: 'var(--chart-primary)' },
  { key: 'net', label: 'Net of fees', value: totals.value?.net, hint: 'Collected less gateway fees', swatch: null },
  { key: 'fees', label: 'Fees', value: totals.value?.fees, hint: 'Gateway charges over the period', swatch: null },
])
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-6 md:pt-10 pb-24 md:pb-32">
    <div class="flex items-end justify-between gap-4 flex-wrap mb-6 md:mb-8">
      <div>
        <h1 class="text-[24px] md:text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Revenue</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
          What we sold versus what we banked, by month.
        </p>
      </div>
      <div class="flex gap-1.5 max-md:w-full">
        <button
          v-for="n in ([6, 12, 24] as const)"
          :key="n"
          type="button"
          class="standard-pill max-md:flex-1 max-md:justify-center max-md:py-2!"
          :style="months === n
            ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
            : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
          @click="months = n"
        >{{ n }} months</button>
      </div>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <!-- Totals -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 max-md:gap-3 mb-4 max-md:mb-3">
      <section
        v-for="t in tiles"
        :key="t.key"
        class="rounded-2xl border p-5 max-md:p-4 max-md:min-w-0"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }"
      >
        <div class="flex items-center gap-1.5 mb-1">
          <span
            v-if="t.swatch"
            class="size-2 rounded-[2px] shrink-0"
            :style="{ background: t.swatch }"
          />
          <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">
            {{ t.label }}
          </p>
        </div>
        <p class="text-[26px] max-md:text-[19px] font-bold tracking-tight tabular-nums leading-none max-md:break-words" style="color: var(--color-text);">
          <span v-if="loading" class="opacity-40">—</span>
          <span v-else>{{ formatMyr(t.value ?? 0) }}</span>
        </p>
        <p class="text-[11px] mt-2" style="color: var(--color-text-secondary);">{{ t.hint }}</p>
      </section>
    </div>

    <!-- Booked vs collected -->
    <section
      class="rounded-2xl border p-6 max-md:p-4 mb-4 max-md:mb-3"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }"
    >
      <div class="flex items-start justify-between gap-6 max-md:gap-3 flex-wrap mb-6 max-md:mb-4">
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">
            Booked vs collected
          </p>
          <p v-if="collectionRate !== null && !loading" class="text-[13px]" style="color: var(--color-text-secondary);">
            <span class="font-semibold tabular-nums" style="color: var(--color-text);">{{ collectionRate }}%</span>
            of booked value collected over this window
          </p>
        </div>
        <!-- Legend: two series, so identity is never carried by colour alone. -->
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-1.5">
            <span class="size-2.5 rounded-[2px]" :style="{ background: 'var(--chart-secondary)' }" />
            <span class="text-[12px]" style="color: var(--color-text-secondary);">Booked</span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="size-2.5 rounded-[2px]" :style="{ background: 'var(--chart-primary)' }" />
            <span class="text-[12px]" style="color: var(--color-text-secondary);">Collected</span>
          </div>
        </div>
      </div>

      <div v-if="loading && !series.length" class="h-60 flex items-center justify-center text-[13px]" style="color: var(--color-text-secondary);">
        Loading…
      </div>
      <div v-else-if="!hasData && !loading" class="h-60 flex flex-col items-center justify-center text-center gap-1">
        <UIcon name="i-lucide-chart-spline" class="size-6 mb-1" :style="{ color: 'var(--color-text-tertiary)' }" />
        <p class="text-[13px] font-medium" :style="{ color: 'var(--color-text)' }">No revenue yet</p>
        <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">Orders won and payments received will appear here.</p>
      </div>
      <div
        v-else
        ref="chartEl"
        class="relative w-full transition-opacity duration-200"
        :class="{ 'opacity-40 pointer-events-none': refetching }"
        :style="{ height: `${CHART_H}px` }"
      >
        <svg
          :width="chartW"
          :height="CHART_H"
          :viewBox="`0 0 ${chartW} ${CHART_H}`"
          class="block cursor-pointer select-none"
          style="touch-action: pan-y;"
          role="img"
          aria-label="Booked versus collected by month. Exact figures are in the table below."
          @pointermove="onPointerMove"
          @pointerleave="hovered = null"
          @click="onChartClick"
        >
          <defs>
            <!-- A wash, not a block: the series hue fading to the baseline. -->
            <linearGradient id="rev-fill-booked" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" :style="{ stopColor: 'var(--chart-secondary)', stopOpacity: 0.26 }" />
              <stop offset="1" :style="{ stopColor: 'var(--chart-secondary)', stopOpacity: 0.02 }" />
            </linearGradient>
            <linearGradient id="rev-fill-collected" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" :style="{ stopColor: 'var(--chart-primary)', stopOpacity: 0.26 }" />
              <stop offset="1" :style="{ stopColor: 'var(--chart-primary)', stopOpacity: 0.02 }" />
            </linearGradient>
          </defs>

          <!-- Grid: solid hairlines, one step off the surface, with compact ticks.
               These label a scale — every real figure uses formatMyr below. -->
          <g v-for="t in gridTicks" :key="t.value">
            <line
              :x1="plot.x0" :x2="plot.x1" :y1="t.y" :y2="t.y"
              :stroke="t.value === 0 ? 'var(--color-border-strong)' : 'var(--color-border)'"
              stroke-width="1"
            />
            <text
              :x="plot.x0 - 10" :y="t.y + 3"
              text-anchor="end"
              class="tabular-nums"
              font-size="10"
              :fill="'var(--color-text-tertiary)'"
            >{{ formatCompactMyr(t.value) }}</text>
          </g>

          <!-- Booked first, Collected on top: the gap between them is the story. -->
          <path :d="paths.bookedArea" fill="url(#rev-fill-booked)" />
          <path :d="paths.collectedArea" fill="url(#rev-fill-collected)" />
          <path
            :d="paths.booked" fill="none"
            stroke="var(--chart-secondary)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"
          />
          <path
            :d="paths.collected" fill="none"
            stroke="var(--chart-primary)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"
          />

          <!-- Crosshair on the hovered month. -->
          <line
            v-if="hovered !== null && xs[hovered] !== undefined"
            :x1="xs[hovered]" :x2="xs[hovered]" :y1="plot.y0" :y2="plot.y1"
            stroke="var(--color-border-strong)" stroke-width="1"
          />

          <!-- Markers: the latest month always, the hovered month on demand.
               Each wears a 2px ring in the surface colour so it stays legible
               where the two lines cross. -->
          <circle
            v-for="d in endDots" :key="`end-${d.key}`"
            :cx="d.x" :cy="d.y" r="4"
            :fill="d.color" stroke="var(--color-bg-elevated)" stroke-width="2"
          />
          <template v-if="hovered !== null && series[hovered]">
            <circle
              :cx="xs[hovered]" :cy="yOf(series[hovered]!.booked)" r="5"
              fill="var(--chart-secondary)" stroke="var(--color-bg-elevated)" stroke-width="2"
            />
            <circle
              :cx="xs[hovered]" :cy="yOf(series[hovered]!.collected)" r="5"
              fill="var(--chart-primary)" stroke="var(--color-bg-elevated)" stroke-width="2"
            />
          </template>

          <!-- Month ticks. -->
          <text
            v-for="(row, i) in series" :key="row.month"
            :x="xs[i]" :y="CHART_H - 8"
            text-anchor="middle"
            font-size="10"
            font-weight="500"
            letter-spacing="0.04em"
            :fill="hovered === i ? 'var(--color-text)' : 'var(--color-text-tertiary)'"
            :opacity="hovered === i || i % labelStep === 0 ? 1 : 0"
          >{{ row.label.split(' ')[0]!.toUpperCase() }}</text>
        </svg>

        <!-- Hover detail. Values lead, series names follow; the chart reads as
             shape first and numbers on demand. -->
        <div
          v-if="tooltip"
          class="absolute z-10 rounded-xl border px-3 py-2 whitespace-nowrap pointer-events-none shadow-lg"
          :style="{ ...tooltip.style, borderColor: 'var(--color-border-strong)', background: 'var(--color-bg)' }"
        >
          <p class="text-[11px] font-semibold mb-1.5" style="color: var(--color-text);">{{ tooltip.row.label }}</p>
          <p class="text-[11px] flex items-center gap-2" style="color: var(--color-text-secondary);">
            <span class="w-2.5 h-0.5 rounded-full shrink-0" :style="{ background: 'var(--chart-secondary)' }" />
            <span class="tabular-nums font-semibold" style="color: var(--color-text);">{{ formatMyr(tooltip.row.booked) }}</span>
            Booked
          </p>
          <p class="text-[11px] flex items-center gap-2 mt-0.5" style="color: var(--color-text-secondary);">
            <span class="w-2.5 h-0.5 rounded-full shrink-0" :style="{ background: 'var(--chart-primary)' }" />
            <span class="tabular-nums font-semibold" style="color: var(--color-text);">{{ formatMyr(tooltip.row.collected) }}</span>
            Collected
          </p>
          <p v-if="tooltip.row.refunded > 0" class="text-[11px] mt-1 tabular-nums" style="color: var(--color-danger);">
            Refunded {{ formatMyr(tooltip.row.refunded) }}
          </p>
          <p class="text-[10px] mt-1.5" style="color: var(--color-text-tertiary);">Click for the breakdown</p>
        </div>
      </div>
    </section>

    <!-- Table view — the exact figures behind the chart. -->
    <section
      v-if="!loading && hasData"
      class="hidden md:block rounded-2xl border overflow-hidden"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }"
    >
      <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
          <thead>
            <tr class="border-b" :style="{ borderColor: 'var(--color-border)' }">
              <th class="text-left font-semibold px-5 py-3 text-[11px] uppercase tracking-widest" style="color: var(--color-text-tertiary);">Month</th>
              <th class="text-right font-semibold px-5 py-3 text-[11px] uppercase tracking-widest" style="color: var(--color-text-tertiary);">Booked</th>
              <th class="text-right font-semibold px-5 py-3 text-[11px] uppercase tracking-widest" style="color: var(--color-text-tertiary);">Collected</th>
              <th class="text-right font-semibold px-5 py-3 text-[11px] uppercase tracking-widest" style="color: var(--color-text-tertiary);">Difference</th>
              <th class="text-right font-semibold px-5 py-3 text-[11px] uppercase tracking-widest" style="color: var(--color-text-tertiary);">Orders</th>
              <th class="text-right font-semibold px-5 py-3 text-[11px] uppercase tracking-widest" style="color: var(--color-text-tertiary);">Payments</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="{ row, index } in tableRows"
              :key="row.month"
              class="border-b last:border-0 transition-colors cursor-pointer outline-none focus-visible:bg-(--color-bg-secondary)"
              :style="{ borderColor: 'var(--color-border)', background: hovered === index ? 'var(--color-bg-secondary)' : 'transparent' }"
              tabindex="0"
              :aria-label="`Open ${row.label} breakdown`"
              @mouseenter="hovered = index"
              @mouseleave="hovered = null"
              @click="openMonth(row.month)"
              @keydown.enter="openMonth(row.month)"
            >
              <td class="px-5 py-3 font-medium" style="color: var(--color-text);">
                <span class="inline-flex items-center gap-1.5">
                  {{ row.label }}
                  <UIcon
                    name="i-lucide-chevron-right" class="size-3.5 transition-opacity"
                    :style="{ color: 'var(--color-text-tertiary)', opacity: hovered === index ? 1 : 0 }" />
                </span>
              </td>
              <td class="px-5 py-3 text-right tabular-nums" style="color: var(--color-text);">{{ formatMyr(row.booked) }}</td>
              <td class="px-5 py-3 text-right tabular-nums" style="color: var(--color-text);">{{ formatMyr(row.collected) }}</td>
              <td
                class="px-5 py-3 text-right tabular-nums"
                :style="{ color: row.collected - row.booked < 0 ? 'var(--color-text-secondary)' : 'var(--color-success)' }"
              >{{ row.collected - row.booked >= 0 ? '+' : '' }}{{ formatMyr(row.collected - row.booked) }}</td>
              <td class="px-5 py-3 text-right tabular-nums" style="color: var(--color-text-secondary);">{{ row.orders }}</td>
              <td class="px-5 py-3 text-right tabular-nums" style="color: var(--color-text-secondary);">{{ row.payments }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Mobile: cards — same rows (newest first) and drill-down as the table. -->
    <div v-if="!loading && hasData" class="md:hidden space-y-2.5">
      <button
        v-for="{ row } in tableRows"
        :key="row.month"
        type="button"
        class="w-full text-left rounded-xl border p-4 transition-colors active:bg-(--color-bg-secondary)"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }"
        :aria-label="`Open ${row.label} breakdown`"
        @click="openMonth(row.month)"
      >
        <div class="flex items-center justify-between gap-3 mb-3">
          <span class="text-[14px] font-semibold" :style="{ color: 'var(--color-text)' }">{{ row.label }}</span>
          <UIcon name="i-lucide-chevron-right" class="size-4 shrink-0" :style="{ color: 'var(--color-text-tertiary)' }" />
        </div>
        <div class="grid grid-cols-2 gap-x-3 gap-y-2">
          <div class="min-w-0">
            <p class="text-[11px] flex items-center gap-1.5" :style="{ color: 'var(--color-text-tertiary)' }">
              <span class="size-2 rounded-[2px] shrink-0" :style="{ background: 'var(--chart-secondary)' }" />Booked
            </p>
            <p class="text-[13px] font-semibold tabular-nums break-words" :style="{ color: 'var(--color-text)' }">{{ formatMyr(row.booked) }}</p>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] flex items-center gap-1.5" :style="{ color: 'var(--color-text-tertiary)' }">
              <span class="size-2 rounded-[2px] shrink-0" :style="{ background: 'var(--chart-primary)' }" />Collected
            </p>
            <p class="text-[13px] font-semibold tabular-nums break-words" :style="{ color: 'var(--color-text)' }">{{ formatMyr(row.collected) }}</p>
          </div>
        </div>
        <div class="flex items-center justify-between gap-3 pt-2 mt-3 border-t text-[11px]" :style="{ borderColor: 'var(--color-border)' }">
          <span
            class="tabular-nums min-w-0"
            :style="{ color: row.collected - row.booked < 0 ? 'var(--color-text-secondary)' : 'var(--color-success)' }"
          >{{ row.collected - row.booked >= 0 ? '+' : '' }}{{ formatMyr(row.collected - row.booked) }}</span>
          <span class="tabular-nums shrink-0" :style="{ color: 'var(--color-text-secondary)' }">{{ row.orders }} orders · {{ row.payments }} payments</span>
        </div>
      </button>
    </div>

    <p class="text-[11px] mt-4 leading-relaxed" style="color: var(--color-text-tertiary);">
      <strong style="color: var(--color-text-secondary);">Booked</strong> is the contracted value of orders won in a month.
      <strong style="color: var(--color-text-secondary);">Collected</strong> is cash received that month from the payments ledger, with refunds netted off.
      With deposit terms the two rarely land in the same month, so a gap is normal. Click a month for its orders, payments and clients.
    </p>
  </div>
</template>
