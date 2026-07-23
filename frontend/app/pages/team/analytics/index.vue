<script setup lang="ts">
// Marketer's read-only mirror of the cockpit traffic overview (same data,
// served by /v1/team/analytics/overview — gated founder/marketer server-side).
// Deliberately a copy-adapt of pages/admin/analytics rather than a shared
// component: the cockpit page carries admin roadmap sections and the two
// audiences are expected to diverge (this one grows Threads + channel views).
definePageMeta({ layout: 'team', middleware: 'team-auth' })
useHead({ title: 'Analytics — Team' })

const { apiFetch } = useTeamAuth()
const { me } = useTeamMe()

// Cosmetic guard — real enforcement is the backend's role gate (engineers 403).
watchEffect(() => {
  const role = me.value?.role
  if (role && role !== 'founder' && role !== 'marketer') navigateTo('/team')
})

interface Overview {
  range: number
  views: { total: number, unique: number, series: { date: string, count: number }[] }
  topPaths: { path: string, count: number }[]
  topReferrers: { referrer: string, count: number }[]
  topLikedProjects: { id: number, name: string, likes: number }[]
}

const range = ref<'7d' | '30d'>('7d')
const data = ref<Overview | null>(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await apiFetch<Overview>(`/api/v1/team/analytics/overview?range=${range.value}`)
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

function barHeight(c: number) {
  return `${c > 0 ? Math.max(6, (c / maxCount.value) * 100) : 2}%`
}
function fmtDay(d: string) {
  return new Date(d).toLocaleDateString('en-MY', { day: 'numeric', month: 'short' })
}
function hostOf(ref: string) {
  try { return new URL(ref).hostname.replace(/^www\./, '') }
  catch { return ref }
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <div class="flex items-end justify-between gap-4 flex-wrap mb-8">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Analytics</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">How the public site is doing — traffic and engagement.</p>
      </div>
      <div class="flex gap-1.5">
        <button
type="button" class="standard-pill" :style="range === '7d'
          ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
          : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
          @click="range = '7d'">Last 7 days</button>
        <button
type="button" class="standard-pill" :style="range === '30d'
          ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
          : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
          @click="range = '30d'">Last 30 days</button>
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
        <div class="flex items-end gap-1 h-44">
          <div
            v-for="pt in data?.views.series"
            :key="pt.date"
            class="flex-1 rounded-t-[3px] transition-[height] duration-300"
            :style="{ height: barHeight(pt.count), background: pt.count > 0 ? 'var(--color-accent)' : 'var(--color-border)', minWidth: '3px' }"
            :title="`${fmtDay(pt.date)}: ${pt.count} view${pt.count === 1 ? '' : 's'}`"
          />
        </div>
        <div class="flex justify-between mt-2 text-[10px] font-medium uppercase tracking-wide" style="color: var(--color-text-tertiary);">
          <span>{{ fmtDay(data!.views.series[0]!.date) }}</span>
          <span>{{ fmtDay(data!.views.series[data!.views.series.length - 1]!.date) }}</span>
        </div>
      </div>
    </section>

    <!-- Top paths + referrers -->
    <div v-if="!loading && hasViews" class="grid md:grid-cols-2 gap-4 mb-4">
      <section class="rounded-2xl border p-6" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
        <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Top pages</p>
        <div v-if="!data?.topPaths.length" class="text-[13px]" style="color: var(--color-text-tertiary);">No data.</div>
        <ul v-else class="space-y-2.5">
          <li v-for="p in data.topPaths" :key="p.path" class="flex items-center justify-between gap-3">
            <span class="font-mono text-[12px] truncate" style="color: var(--color-text);">{{ p.path }}</span>
            <span class="text-[12px] font-semibold tabular-nums shrink-0" style="color: var(--color-text-secondary);">{{ p.count.toLocaleString() }}</span>
          </li>
        </ul>
      </section>

      <section class="rounded-2xl border p-6" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
        <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Top referrers</p>
        <div v-if="!data?.topReferrers.length" class="text-[13px]" style="color: var(--color-text-tertiary);">Mostly direct — no referrers recorded.</div>
        <ul v-else class="space-y-2.5">
          <li v-for="r in data.topReferrers" :key="r.referrer" class="flex items-center justify-between gap-3">
            <span class="text-[12px] truncate" style="color: var(--color-text);">{{ hostOf(r.referrer) }}</span>
            <span class="text-[12px] font-semibold tabular-nums shrink-0" style="color: var(--color-text-secondary);">{{ r.count.toLocaleString() }}</span>
          </li>
        </ul>
      </section>
    </div>

    <!-- Most-liked projects -->
    <section v-if="!loading" class="rounded-2xl border p-6" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
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
  </div>
</template>
