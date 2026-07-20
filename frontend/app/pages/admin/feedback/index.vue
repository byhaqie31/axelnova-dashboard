<script setup lang="ts">
import StatusPill from '~/components/shared/primitives/StatusPill.vue'
import { feedbackStatuses, npsBuckets } from '~/data/feedbackStatuses'
import { MOTION } from '~/utils/motion'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const motion = useMotion()

interface FeedbackRow {
  id: number
  reference_code: string
  order_number: string | null
  name: string | null
  email: string | null
  project_label: string | null
  overall: number | null
  average_rating: number | null
  nps: number | null
  nps_bucket: 'promoter' | 'passive' | 'detractor' | null
  status: 'pending' | 'approved' | 'published' | 'archived'
  source: 'self_serve' | 'admin'
  featured: boolean
  submitted_at: string | null
  created_at: string
}

interface Stats {
  total: number
  pending: number
  published: number
  avg_overall: number | null
}

const rows = ref<FeedbackRow[]>([])
const stats = ref<Stats | null>(null)
const meta = ref<{ current_page: number, last_page: number, total: number } | null>(null)
const loading = ref(true)
const error = ref('')

const filters = reactive({
  search: '',
  status: '',
  page: 1,
})

const statusOptions = [
  { value: '', label: 'All' },
  ...feedbackStatuses.map(s => ({ value: s.value, label: s.label })),
]

async function fetchFeedback() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filters.search) params.set('search', filters.search)
    if (filters.status) params.set('status', filters.status)
    params.set('page', String(filters.page))

    const res = await apiFetch<{ data: FeedbackRow[], meta: any, stats: Stats }>(`/api/v1/admin/feedback?${params}`)
    rows.value = res.data
    meta.value = res.meta
    stats.value = res.stats
  }
  catch {
    error.value = 'Failed to load feedback. Check your session.'
  }
  finally {
    loading.value = false
  }
}

const tilesGrid = ref<HTMLElement | null>(null)

onMounted(() => {
  fetchFeedback()

  // Dashboard register — tiles stagger once per navigation (§ MOTION.md).
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

let searchTimer: ReturnType<typeof setTimeout>
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filters.page = 1; fetchFeedback() }, 400)
})
watch(() => filters.status, () => {
  if (filters.page !== 1) filters.page = 1
  else fetchFeedback()
})
watch(() => filters.page, () => fetchFeedback())

const tiles = computed(() => [
  { label: 'Total reviews', value: stats.value ? String(stats.value.total) : '—', hint: 'All feedback records', icon: 'i-lucide-message-circle-heart' },
  { label: 'Pending review', value: stats.value ? String(stats.value.pending) : '—', hint: 'Waiting on moderation', icon: 'i-lucide-hourglass' },
  { label: 'Published', value: stats.value ? String(stats.value.published) : '—', hint: 'Live on the wall', icon: 'i-lucide-badge-check' },
  { label: 'Avg rating', value: stats.value?.avg_overall != null ? `${stats.value.avg_overall} / 5` : '—', hint: 'Mean overall score', icon: 'i-lucide-star' },
])

function fmtDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Feedback</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Client reviews — request, moderate, and publish to the testimonial wall.</p>
      </div>
      <NuxtLink to="/admin/feedback/new" class="btn-pill btn-pill-accent text-[13px]">
        <UIcon name="i-lucide-plus" class="size-4" /> New feedback
      </NuxtLink>
    </div>

    <!-- Stat tiles -->
    <div ref="tilesGrid" class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-8">
      <div
        v-for="tile in tiles"
        :key="tile.label"
        class="rounded-2xl border p-5"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
      >
        <div
          class="size-9 rounded-xl inline-flex items-center justify-center mb-3"
          :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
        >
          <UIcon :name="tile.icon" class="size-4" />
        </div>
        <p class="text-[11px] font-semibold uppercase tracking-wider mb-1" style="color: var(--color-text-tertiary);">{{ tile.label }}</p>
        <p class="text-[28px] font-bold tracking-tight tabular-nums" style="color: var(--color-text);">
          <span v-if="loading && !stats" class="opacity-50">—</span>
          <span v-else>{{ tile.value }}</span>
        </p>
        <p class="text-[12px] mt-1" style="color: var(--color-text-secondary);">{{ tile.hint }}</p>
      </div>
    </div>

    <!-- Filter row (§12.11 — no secondary filters, so no funnel) -->
    <div class="flex flex-wrap items-center gap-3 mb-6">
      <AdminExpandingSearch v-model="filters.search" placeholder="Search by reference, name or email…" />
      <AdminStatusFilter v-model="filters.status" :options="statusOptions" :total="meta?.total ?? null" class="ml-auto" />
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading && !rows.length" class="text-center py-16" style="color: var(--color-text-secondary);">Loading feedback…</div>

    <div
      v-else-if="!rows.length" class="rounded-2xl border p-12 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
    >
      <UIcon name="i-lucide-message-circle-heart" class="size-8 mb-3 mx-auto" :style="{ color: 'var(--color-text-tertiary)' }" />
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No feedback yet</p>
      <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
        Ask a client with <NuxtLink to="/admin/feedback/new" class="underline" :style="{ color: 'var(--color-accent)' }">New feedback</NuxtLink> — request a review or log one you received offline.
      </p>
    </div>

    <!-- Desktop table -->
    <div v-else class="hidden md:block admin-table-card">
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr>
              <th
                v-for="h in ['Reference', 'Client', 'Overall', 'NPS', 'Status', 'Submitted']" :key="h"
                class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);"
              >
                {{ h }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="f in rows" :key="f.id"
              class="admin-table-row"
              @click="navigateTo(`/admin/feedback/${f.id}`)"
            >
              <td class="px-4 py-3.5">
                <p class="font-mono text-[12px] font-medium" :style="{ color: 'var(--color-accent)' }">{{ f.reference_code }}</p>
                <p v-if="f.featured" class="text-[10px] font-semibold mt-0.5" :style="{ color: 'var(--color-warning)' }">★ Featured</p>
              </td>
              <td class="px-4 py-3.5">
                <p class="text-[13px] font-medium" :style="{ color: 'var(--color-text)' }">{{ f.name ?? '—' }}</p>
                <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ f.project_label ?? f.email ?? '' }}</p>
              </td>
              <td class="px-4 py-3.5">
                <p class="text-[13px] font-semibold tabular-nums" :style="{ color: 'var(--color-text)' }">
                  {{ f.overall != null ? `${f.overall}/5` : '—' }}
                </p>
                <p v-if="f.average_rating != null" class="text-[11px] tabular-nums" :style="{ color: 'var(--color-text-tertiary)' }">
                  dims {{ f.average_rating }}
                </p>
              </td>
              <td class="px-4 py-3.5">
                <span
                  v-if="f.nps_bucket"
                  class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
                  :style="{ background: npsBuckets[f.nps_bucket]?.bg, color: npsBuckets[f.nps_bucket]?.color }"
                >{{ npsBuckets[f.nps_bucket]?.label }} · {{ f.nps }}</span>
                <span v-else class="text-[12px]" :style="{ color: 'var(--color-text-tertiary)' }">—</span>
              </td>
              <td class="px-4 py-3.5">
                <StatusPill :status="f.status" type="feedback" />
              </td>
              <td class="px-4 py-3.5 text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
                {{ f.submitted_at ? fmtDate(f.submitted_at) : 'Awaiting client' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Mobile cards (§12.10) -->
    <div v-if="rows.length" class="md:hidden space-y-2.5">
      <button
        v-for="f in rows"
        :key="f.id"
        type="button"
        class="w-full text-left rounded-xl border p-4 transition-colors hover:bg-(--color-bg-secondary)"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
        @click="navigateTo(`/admin/feedback/${f.id}`)"
      >
        <div class="flex items-start justify-between gap-3 mb-2">
          <p class="font-mono text-[12px] font-medium" :style="{ color: 'var(--color-accent)' }">{{ f.reference_code }}</p>
          <StatusPill :status="f.status" type="feedback" />
        </div>
        <p class="text-[13px] font-medium leading-tight" :style="{ color: 'var(--color-text)' }">{{ f.name ?? '—' }}</p>
        <p class="text-[11px] mb-3" :style="{ color: 'var(--color-text-tertiary)' }">{{ f.project_label ?? f.email ?? '' }}</p>
        <div class="pt-2 border-t space-y-1" :style="{ borderColor: 'var(--color-border)' }">
          <div class="flex items-center justify-between gap-3">
            <p class="text-[13px] font-semibold tabular-nums" :style="{ color: 'var(--color-text)' }">
              {{ f.overall != null ? `${f.overall}/5` : 'No score yet' }}
            </p>
            <span
              v-if="f.nps_bucket"
              class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
              :style="{ background: npsBuckets[f.nps_bucket]?.bg, color: npsBuckets[f.nps_bucket]?.color }"
            >{{ npsBuckets[f.nps_bucket]?.label }}</span>
          </div>
          <p class="text-[11px]" :style="{ color: 'var(--color-text-secondary)' }">
            {{ f.submitted_at ? `Submitted ${fmtDate(f.submitted_at)}` : 'Awaiting client' }}
          </p>
        </div>
      </button>
    </div>

    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
      <button :disabled="filters.page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page--">← Prev</button>
      <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ filters.page }} / {{ meta.last_page }}</span>
      <button :disabled="filters.page >= meta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="filters.page++">Next →</button>
    </div>
  </div>
</template>
