<script setup lang="ts">
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

const myr = new Intl.NumberFormat('en-MY', {
  style: 'currency',
  currency: 'MYR',
  maximumFractionDigits: 0,
})
const myrExact = new Intl.NumberFormat('en-MY', {
  style: 'currency',
  currency: 'MYR',
  minimumFractionDigits: 2,
})

// Both series share one scale — they're the same unit (MYR), so a second y-axis
// would misrepresent the gap between them. That gap is the whole point here.
const scaleMax = computed(() =>
  Math.max(1, ...series.value.flatMap(r => [r.booked, r.collected])),
)

function barHeight(value: number): string {
  if (value <= 0) return '2px'
  return `${Math.max(2, (value / scaleMax.value) * 100)}%`
}

const hasData = computed(() => series.value.some(r => r.booked > 0 || r.collected > 0))

// The table reads newest-first — the month you want is almost always the latest.
// The chart deliberately stays oldest→left: a time axis running backwards is
// hard to read. Each entry carries its index in `series` so hovering a row still
// highlights the matching bar (and vice versa) after the reverse.
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
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <div class="flex items-end justify-between gap-4 flex-wrap mb-8">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Revenue</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
          What we sold versus what we banked, by month.
        </p>
      </div>
      <div class="flex gap-1.5">
        <button
          v-for="n in ([6, 12, 24] as const)"
          :key="n"
          type="button"
          class="standard-pill"
          :style="months === n
            ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
            : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
          @click="months = n"
        >{{ n }} months</button>
      </div>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <!-- Totals -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
      <section
        v-for="t in tiles"
        :key="t.key"
        class="rounded-2xl border p-5"
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
        <p class="text-[26px] font-bold tracking-tight tabular-nums leading-none" style="color: var(--color-text);">
          <span v-if="loading" class="opacity-40">—</span>
          <span v-else>{{ myr.format(t.value ?? 0) }}</span>
        </p>
        <p class="text-[11px] mt-2" style="color: var(--color-text-secondary);">{{ t.hint }}</p>
      </section>
    </div>

    <!-- Booked vs collected -->
    <section
      class="rounded-2xl border p-6 mb-4"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }"
    >
      <div class="flex items-start justify-between gap-6 flex-wrap mb-6">
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

      <div v-if="loading" class="h-56 flex items-center justify-center text-[13px]" style="color: var(--color-text-secondary);">
        Loading…
      </div>
      <div v-else-if="!hasData" class="h-56 flex flex-col items-center justify-center text-center gap-1">
        <UIcon name="i-lucide-chart-column" class="size-6 mb-1" :style="{ color: 'var(--color-text-tertiary)' }" />
        <p class="text-[13px] font-medium" :style="{ color: 'var(--color-text)' }">No revenue yet</p>
        <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">Orders won and payments received will appear here.</p>
      </div>
      <div v-else class="relative">
        <!-- Scale ceiling, kept recessive — the table below carries exact figures. -->
        <div class="flex justify-between items-center mb-1">
          <span class="text-[10px] tabular-nums" style="color: var(--color-text-tertiary);">{{ myr.format(scaleMax) }}</span>
        </div>
        <div
          class="flex items-end gap-1 h-56 border-t border-b"
          :style="{ borderColor: 'var(--color-border)' }"
        >
          <div
            v-for="(row, i) in series"
            :key="row.month"
            class="flex-1 h-full flex items-end justify-center gap-[2px] relative"
            @mouseenter="hovered = i"
            @mouseleave="hovered = null"
          >
            <div
              class="w-1/2 max-w-[18px] rounded-t-[4px] transition-[height] duration-300"
              :style="{ height: barHeight(row.booked), background: 'var(--chart-secondary)', opacity: hovered === null || hovered === i ? 1 : 0.45 }"
            />
            <div
              class="w-1/2 max-w-[18px] rounded-t-[4px] transition-[height] duration-300"
              :style="{ height: barHeight(row.collected), background: 'var(--chart-primary)', opacity: hovered === null || hovered === i ? 1 : 0.45 }"
            />

            <!-- Hover detail. Values live here rather than on every bar, so the
                 chart reads as shape first and numbers on demand. -->
            <div
              v-if="hovered === i"
              class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 z-10 rounded-xl border px-3 py-2 whitespace-nowrap pointer-events-none shadow-lg"
              :style="{ borderColor: 'var(--color-border-strong)', background: 'var(--color-bg)' }"
            >
              <p class="text-[11px] font-semibold mb-1.5" style="color: var(--color-text);">{{ row.label }}</p>
              <p class="text-[11px] flex items-center gap-1.5" style="color: var(--color-text-secondary);">
                <span class="size-2 rounded-[2px]" :style="{ background: 'var(--chart-secondary)' }" />
                Booked
                <span class="tabular-nums font-medium ml-1" style="color: var(--color-text);">{{ myrExact.format(row.booked) }}</span>
              </p>
              <p class="text-[11px] flex items-center gap-1.5 mt-0.5" style="color: var(--color-text-secondary);">
                <span class="size-2 rounded-[2px]" :style="{ background: 'var(--chart-primary)' }" />
                Collected
                <span class="tabular-nums font-medium ml-1" style="color: var(--color-text);">{{ myrExact.format(row.collected) }}</span>
              </p>
              <p v-if="row.refunded > 0" class="text-[11px] mt-0.5" style="color: var(--color-danger);">
                Refunded {{ myrExact.format(row.refunded) }}
              </p>
            </div>
          </div>
        </div>
        <div class="flex gap-1 mt-2">
          <div v-for="(row, i) in series" :key="row.month" class="flex-1 text-center">
            <!-- Label every other month at 24 so ticks never collide. -->
            <span
              v-if="series.length <= 12 || i % 2 === 0"
              class="text-[10px] font-medium uppercase tracking-wide"
              :style="{ color: hovered === i ? 'var(--color-text)' : 'var(--color-text-tertiary)' }"
            >{{ row.label.split(' ')[0] }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Table view — the exact figures behind the chart. -->
    <section
      v-if="!loading && hasData"
      class="rounded-2xl border overflow-hidden"
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
              class="border-b last:border-0 transition-colors"
              :style="{ borderColor: 'var(--color-border)', background: hovered === index ? 'var(--color-bg-secondary)' : 'transparent' }"
              @mouseenter="hovered = index"
              @mouseleave="hovered = null"
            >
              <td class="px-5 py-3 font-medium" style="color: var(--color-text);">{{ row.label }}</td>
              <td class="px-5 py-3 text-right tabular-nums" style="color: var(--color-text);">{{ myrExact.format(row.booked) }}</td>
              <td class="px-5 py-3 text-right tabular-nums" style="color: var(--color-text);">{{ myrExact.format(row.collected) }}</td>
              <td
                class="px-5 py-3 text-right tabular-nums"
                :style="{ color: row.collected - row.booked < 0 ? 'var(--color-text-secondary)' : 'var(--color-success)' }"
              >{{ row.collected - row.booked >= 0 ? '+' : '' }}{{ myrExact.format(row.collected - row.booked) }}</td>
              <td class="px-5 py-3 text-right tabular-nums" style="color: var(--color-text-secondary);">{{ row.orders }}</td>
              <td class="px-5 py-3 text-right tabular-nums" style="color: var(--color-text-secondary);">{{ row.payments }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <p class="text-[11px] mt-4 leading-relaxed" style="color: var(--color-text-tertiary);">
      <strong style="color: var(--color-text-secondary);">Booked</strong> is the contracted value of orders won in a month.
      <strong style="color: var(--color-text-secondary);">Collected</strong> is cash received that month from the payments ledger, with refunds netted off.
      With deposit terms the two rarely land in the same month, so a gap is normal.
    </p>
  </div>
</template>
