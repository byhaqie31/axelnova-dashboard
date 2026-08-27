<script setup lang="ts">
import { MOTION } from '~/utils/motion'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const motion = useMotion()

interface Inquiry {
  id: number
  name: string
  email: string
  company: string | null
  project_type: string | null
  status: string
  created_at: string
}

const recentInquiries = ref<Inquiry[]>([])
const inquiriesLoading = ref(true)
const range = ref<'today' | '7d' | '30d'>('today')
const ranges = [
  { value: 'today' as const, label: 'Today' },
  { value: '7d' as const, label: 'Last 7 days' },
  { value: '30d' as const, label: 'Last 30 days' },
]
const totalQuotations = ref<number | null>(null)
const activeReferrals = ref<number | null>(null)
const activeOrders = ref<number | null>(null)
const openInquiries = ref<number | null>(null)
const draftQuotations = ref<number | null>(null)
const pageViews7d = ref<number | null>(null)
const ordersRevenue = ref<number | null>(null)
const ordersCollected = ref<number | null>(null)
const ordersPending = ref<number | null>(null)
const loading = ref(true)
const error = ref('')

// Displayed metric values — counted up briefly (dashboard register, ~0.45s)
// when the real numbers arrive. Instant under reduced motion. Kept short:
// the number the user came for should be readable almost immediately.
const shown = reactive({ total: 0, refs: 0, orders: 0, inq: 0, draft: 0, views: 0, revenue: 0, collected: 0, pending: 0 })

function countTo(key: keyof typeof shown, end: number) {
  if (!import.meta.client || motion.reduced) {
    shown[key] = end
    return
  }
  const proxy = { v: shown[key] }
  motion.gsap.to(proxy, {
    v: end,
    duration: 0.45,
    ease: MOTION.ease.settle,
    snap: { v: 1 },
    onUpdate: () => { shown[key] = Math.round(proxy.v) },
  })
}

interface DashboardCounts {
  quotations_total: number
  quotations_draft: number
  referrals_active: number
  orders_total: number
  inquiries_new: number
  views_7d: number
}

async function load() {
  loading.value = true
  error.value = ''

  // Two independent requests, fired together, each releasing its own part of
  // the UI as it lands — nothing waits on the slower of the two. The counts
  // endpoint replaces the five paginated list fetches (used only for
  // meta.total) and the analytics overview call this page used to make.
  const counts = (async () => {
    try {
      const c = await apiFetch<DashboardCounts>('/api/v1/admin/dashboard/counts')
      totalQuotations.value = c.quotations_total
      activeReferrals.value = c.referrals_active
      activeOrders.value = c.orders_total
      openInquiries.value = c.inquiries_new
      draftQuotations.value = c.quotations_draft
      pageViews7d.value = c.views_7d
      countTo('total', c.quotations_total)
      countTo('refs', c.referrals_active)
      countTo('orders', c.orders_total)
      countTo('inq', c.inquiries_new)
      countTo('draft', c.quotations_draft)
      countTo('views', c.views_7d)
    }
    catch {
      error.value = 'Failed to load dashboard. Check your session.'
    }
    finally {
      loading.value = false
    }
  })()

  // Orders money roll-up — best-effort; never let it break the dashboard.
  const money = (async () => {
    try {
      const st = await apiFetch<{ revenue: number; collected: number; pending: number; active_count: number }>('/api/v1/admin/orders/stats')
      ordersRevenue.value = st.revenue
      ordersCollected.value = st.collected
      ordersPending.value = st.pending
      countTo('revenue', Math.round(st.revenue))
      countTo('collected', Math.round(st.collected))
      countTo('pending', Math.round(st.pending))
    }
    catch { /* leave the figures as — */ }
  })()

  await Promise.all([counts, money])
}

// Recent inquiries feed — Today / Last 7 days / Last 30 days (quotations keep to
// their own page; the dashboard surfaces fresh inquiries to action).
function rangeDateFrom(): string {
  const d = new Date()
  if (range.value === '7d') d.setDate(d.getDate() - 7)
  else if (range.value === '30d') d.setDate(d.getDate() - 30)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

async function fetchInquiries() {
  inquiriesLoading.value = true
  try {
    const res = await apiFetch<{ data: Inquiry[] }>(`/api/v1/admin/inquiries?date_from=${rangeDateFrom()}&page=1`)
    recentInquiries.value = res.data.slice(0, 8)
  }
  catch {
    recentInquiries.value = []
  }
  finally {
    inquiriesLoading.value = false
  }
}

watch(range, fetchInquiries)

const tilesGrid = ref<HTMLElement | null>(null)

onMounted(() => {
  load()
  fetchInquiries()

  // Staggered tile entrance — once per navigation, dashboard register.
  const { gsap, reduced } = motion
  const tileEls = Array.from(tilesGrid.value?.children ?? [])
  if (reduced || !tileEls.length) return
  gsap.fromTo(tileEls,
    { opacity: 0, y: 16 },
    {
      opacity: 1, y: 0,
      duration: 0.4, ease: MOTION.ease.out, stagger: MOTION.stagger.tight,
      clearProps: 'opacity,transform',
    },
  )
})

function fmtDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short' })
}

// Full ringgit with separators — for the headline orders figures.
function fmtMoney(n: number) {
  return `RM ${Math.round(n).toLocaleString('en-MY')}`
}

const ordersSummary = computed(() => [
  { key: 'revenue', label: 'Revenue', value: shown.revenue, ready: ordersRevenue.value !== null, hint: 'Total contracted value', color: 'var(--color-text)' },
  { key: 'collected', label: 'Collected', value: shown.collected, ready: ordersCollected.value !== null, hint: 'Received so far', color: 'var(--color-success)' },
  { key: 'pending', label: 'Pending', value: shown.pending, ready: ordersPending.value !== null, hint: 'Outstanding balance', color: 'var(--color-warning)' },
])

interface StatTile {
  label: string
  value: string
  hint: string
  icon: string
  to: string
  cta: string
  pending?: boolean
}

const tiles = computed<StatTile[]>(() => [
  {
    label: 'Open inquiries',
    value: openInquiries.value === null ? '—' : String(shown.inq),
    hint: 'New project inquiries',
    icon: 'i-lucide-inbox',
    to: '/admin/inquiries?status=new',
    cta: 'View inquiries',
  },
  {
    label: 'Draft quotations',
    value: draftQuotations.value === null ? '—' : String(shown.draft),
    hint: 'Building, not yet sent',
    icon: 'i-lucide-file-pen',
    to: '/admin/quotations?status=draft',
    cta: 'View drafts',
  },
  {
    label: 'Total quotations',
    value: totalQuotations.value === null ? '—' : String(shown.total),
    hint: 'All-time inquiries',
    icon: 'i-lucide-file-text',
    to: '/admin/quotations',
    cta: 'View quotations',
  },
  {
    label: 'Active referrals',
    value: activeReferrals.value === null ? '—' : String(shown.refs),
    hint: 'Excludes rejected',
    icon: 'i-lucide-share-2',
    to: '/admin/referrals?view=referrals',
    cta: 'View referrals',
  },
  {
    label: 'Active orders',
    value: activeOrders.value === null ? '—' : String(shown.orders),
    hint: 'Converted engagements',
    icon: 'i-lucide-package-check',
    to: '/admin/orders',
    cta: 'View orders',
  },
  {
    label: 'Page views (7d)',
    value: pageViews7d.value === null ? '—' : String(shown.views),
    hint: 'Public site visits',
    icon: 'i-lucide-eye',
    to: '/admin/analytics',
    cta: 'View analytics',
  },
])
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <div class="mb-8">
      <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Dashboard</h1>
      <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Fresh inquiries, orders, and traffic at a glance.</p>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <!-- Orders overview — headline money card -->
    <NuxtLink
      to="/admin/orders"
      class="orders-hero group block rounded-2xl border p-6 sm:p-8 mb-8"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }"
    >
      <div class="flex items-start justify-between gap-4 mb-6">
        <p class="text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">Orders overview</p>
        <span class="inline-flex items-center gap-1 text-[12px] font-medium shrink-0" :style="{ color: 'var(--color-accent)' }">
          View orders
          <UIcon name="i-lucide-arrow-right" class="size-3.5 transition-transform group-hover:translate-x-0.5" />
        </span>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 sm:gap-8">
        <div
          v-for="(s, idx) in ordersSummary"
          :key="s.key"
          :class="{ 'sm:border-l sm:pl-8': idx > 0 }"
          :style="{ borderColor: 'var(--color-border)' }"
        >
          <p class="text-[11px] font-semibold uppercase tracking-wider mb-1.5" style="color: var(--color-text-tertiary);">{{ s.label }}</p>
          <p class="text-[30px] sm:text-[34px] font-bold tracking-tight tabular-nums" :style="{ color: s.color }">
            <span v-if="loading || !s.ready" class="opacity-40">—</span>
            <span v-else>{{ fmtMoney(s.value) }}</span>
          </p>
          <p class="text-[12px] mt-1" style="color: var(--color-text-secondary);">{{ s.hint }}</p>
        </div>
      </div>
    </NuxtLink>

    <!-- Stat tiles -->
    <div ref="tilesGrid" class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mb-10">
      <NuxtLink
        v-for="tile in tiles"
        :key="tile.label"
        :to="tile.to"
        class="stat-tile group relative rounded-2xl border p-5"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
      >
        <!-- Hover-revealed view button, top-right — doesn't disturb the resting card -->
        <span
          class="view-btn absolute top-5 right-5 inline-flex items-center justify-center size-8 rounded-lg"
          :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
          :title="tile.cta"
          aria-hidden="true"
        >
          <UIcon name="i-lucide-arrow-up-right" class="size-4" />
        </span>

        <div
          class="size-9 rounded-xl inline-flex items-center justify-center mb-3"
          :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
        >
          <UIcon :name="tile.icon" class="size-4" />
        </div>
        <p class="text-[11px] font-semibold uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">{{ tile.label }}</p>
        <p class="text-[28px] font-bold tracking-tight tabular-nums" style="color: var(--color-text);">
          <span v-if="loading && !tile.pending" class="opacity-50">—</span>
          <span v-else>{{ tile.value }}</span>
        </p>
        <div class="flex items-end justify-between gap-2 mt-1">
          <p class="text-[12px]" style="color: var(--color-text-secondary);">{{ tile.hint }}</p>
          <span
            v-if="tile.pending"
            class="shrink-0 text-[10px] font-semibold px-2 py-0.5 rounded-full"
            :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg-secondary)' }"
          >
            Soon
          </span>
        </div>
      </NuxtLink>
    </div>

    <!-- Recent inquiries -->
    <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
      <h2 class="text-[18px] font-semibold tracking-tight" style="color: var(--color-text);">Recent inquiries</h2>
      <div class="inline-flex rounded-full border p-0.5" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <button
          v-for="r in ranges"
          :key="r.value"
          type="button"
          class="px-3 py-1 rounded-full text-[12px] font-medium transition-colors"
          :style="range === r.value
            ? { background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
            : { color: 'var(--color-text-secondary)', background: 'transparent' }"
          @click="range = r.value"
        >
          {{ r.label }}
        </button>
      </div>
    </div>

    <div v-if="inquiriesLoading" class="text-center py-10 text-[13px]" style="color: var(--color-text-secondary);">Loading…</div>

    <div
      v-else-if="!recentInquiries.length"
      class="rounded-2xl border p-10 text-center text-[13px]"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
    >
      No inquiries in this period.
    </div>

    <!-- Desktop: table -->
    <div
      v-else
      class="hidden md:block admin-table-card"
    >
      <div class="overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr>
            <th
v-for="h in ['Name', 'Project type', 'Status', 'Received']" :key="h"
              class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">
              {{ h }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="i in recentInquiries"
            :key="i.id"
            class="admin-table-row"
            @click="navigateTo(`/admin/inquiries/${i.id}`)"
          >
            <td class="px-4 py-3.5">
              <p class="text-[13px] font-medium" :style="{ color: 'var(--color-text)' }">{{ i.name }}</p>
              <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ i.email }}</p>
            </td>
            <td class="px-4 py-3.5">
              <span class="text-[13px]" :style="{ color: 'var(--color-text-secondary)' }">{{ i.project_type ?? '—' }}</span>
            </td>
            <td class="px-4 py-3.5">
              <AdminStatusPill :status="i.status" />
            </td>
            <td class="px-4 py-3.5 text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
              {{ fmtDate(i.created_at) }}
            </td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <!-- Mobile: cards -->
    <div v-if="!inquiriesLoading && recentInquiries.length" class="md:hidden space-y-2.5">
      <button
        v-for="i in recentInquiries"
        :key="i.id"
        type="button"
        class="w-full text-left rounded-xl border p-4 transition-colors hover:bg-(--color-bg-secondary)"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
        @click="navigateTo(`/admin/inquiries/${i.id}`)"
      >
        <div class="flex items-start justify-between gap-3 mb-1">
          <p class="text-[13px] font-medium leading-tight" :style="{ color: 'var(--color-text)' }">{{ i.name }}</p>
          <AdminStatusPill :status="i.status" />
        </div>
        <p class="text-[11px] mb-2" :style="{ color: 'var(--color-text-tertiary)' }">{{ i.email }}</p>
        <div class="flex items-center justify-between gap-3 pt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
          <p class="text-[13px]" :style="{ color: 'var(--color-text-secondary)' }">{{ i.project_type ?? '—' }}</p>
          <p class="text-[11px]" :style="{ color: 'var(--color-text-secondary)' }">{{ fmtDate(i.created_at) }}</p>
        </div>
      </button>
    </div>
  </div>
</template>

<style scoped>
.orders-hero {
  transition: border-color 0.18s ease, box-shadow 0.18s ease;
}
.orders-hero:hover {
  border-color: var(--color-border-strong) !important;
  box-shadow: var(--shadow-sm);
}

.stat-tile {
  transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}
.stat-tile:hover {
  border-color: var(--color-border-strong) !important;
  box-shadow: var(--shadow-sm);
  transform: translateY(-2px);
}

/* View button: hidden at rest, fades + rises in on card hover. */
.view-btn {
  opacity: 0;
  transform: translateY(-3px) scale(0.92);
  transition: opacity 0.18s ease, transform 0.18s ease;
  pointer-events: none;
}
.stat-tile:hover .view-btn,
.stat-tile:focus-visible .view-btn {
  opacity: 1;
  transform: translateY(0) scale(1);
}

@media (prefers-reduced-motion: reduce) {
  .stat-tile { transition: none; }
  .stat-tile:hover { transform: none; }
  .view-btn { transition: none; }
}
</style>
