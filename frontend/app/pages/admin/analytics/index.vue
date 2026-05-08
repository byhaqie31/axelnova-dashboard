<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })
useHead({ title: 'Analytics — Admin' })

interface PlannedMetric {
  label: string
  description: string
  icon: string
  source: string
}

const metrics: PlannedMetric[] = [
  {
    label: 'Page views',
    description: 'Daily visit counts per route, with referrer + UA breakdown.',
    icon: 'i-lucide-eye',
    source: 'page_views table',
  },
  {
    label: 'Project likes',
    description: 'Anonymous likes per project, IP/cookie deduped.',
    icon: 'i-lucide-heart',
    source: 'entity_likes (entity_type=project)',
  },
  {
    label: 'Service interest',
    description: 'Likes + views per service package — informs which to feature.',
    icon: 'i-lucide-package',
    source: 'entity_likes + page_views',
  },
  {
    label: 'Quote funnel',
    description: 'Views → quote start → submitted leads conversion ratio.',
    icon: 'i-lucide-funnel',
    source: 'page_views + quote_requests',
  },
]
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-10 pb-32">
    <div class="mb-6">
      <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Admin</p>
      <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Analytics</h1>
      <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">Traffic, engagement, and conversion signals.</p>
    </div>

    <div
      class="mb-8 rounded-xl border p-4 flex items-start gap-3"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-accent-soft)' }"
    >
      <UIcon name="i-lucide-info" class="size-4 mt-0.5 shrink-0" :style="{ color: 'var(--color-accent)' }" />
      <div>
        <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-accent)' }">Phase B — not wired up yet</p>
        <p class="text-[12px] mt-0.5" :style="{ color: 'var(--color-text-secondary)' }">
          Tracking schema, public POST endpoints, and admin overview queries land next. Until then, this page lists the metrics that will appear here.
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div
        v-for="m in metrics"
        :key="m.label"
        class="rounded-2xl border p-5"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
      >
        <div class="flex items-start justify-between mb-3">
          <div
            class="size-9 rounded-xl inline-flex items-center justify-center"
            :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
          >
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
        <p class="text-[12px] mb-3" :style="{ color: 'var(--color-text-secondary)' }">{{ m.description }}</p>
        <p class="text-[11px] font-mono" :style="{ color: 'var(--color-text-tertiary)' }">{{ m.source }}</p>
      </div>
    </div>
  </div>
</template>
