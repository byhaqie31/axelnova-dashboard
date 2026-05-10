<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })
useHead({ title: 'Investors — Admin' })

interface PitchProject {
  slug: string
  name: string
  status: 'live' | 'wip' | 'planning'
  landingUrl: string
  materials: { label: string, url: string }[]
}

const projects: PitchProject[] = [
  {
    slug: 'roofly',
    name: 'Roofly',
    status: 'wip',
    landingUrl: '/investor/roofly',
    materials: [
      { label: 'Pitch deck', url: '/investor/roofly/deck.html' },
      { label: 'Investment package', url: '/investor/roofly/investor-package.html' },
      { label: 'Financial summary', url: '/investor/roofly/pricing-financial-summary.html' },
    ],
  },
]

const statusLabels: Record<string, string> = {
  live: 'Live',
  wip: 'In progress',
  planning: 'Planning',
}

const statusColors: Record<string, string> = {
  live: 'var(--color-success)',
  wip: 'var(--color-accent)',
  planning: 'var(--color-text-tertiary)',
}

async function copyLandingUrl(path: string) {
  const url = `${window.location.origin}${path}`
  try {
    await navigator.clipboard.writeText(url)
  }
  catch {
    // ignore — older browsers
  }
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-10 pb-32">
    <!-- Header -->
    <div class="flex items-start justify-between mb-8 flex-wrap gap-4">
      <div>
        <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Admin · Fundraising</p>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Investors</h1>
        <p class="text-[14px] mt-1 max-w-2xl" style="color: var(--color-text-secondary);">
          Manage investor relationships and pitch materials across Axel Nova Ventures projects. Investor accounts and gated deal rooms are coming soon — for now, share project landings directly.
        </p>
      </div>
      <button
        type="button"
        disabled
        class="btn-pill text-[12px] inline-flex items-center gap-1.5 opacity-60 cursor-not-allowed"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
      >
        <UIcon name="i-lucide-plus" class="size-3.5" />
        New investor (soon)
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-10">
      <div
        v-for="stat in [
          { label: 'Active investors', value: '0' },
          { label: 'Deal rooms shared', value: '0' },
          { label: 'Pitch views (30d)', value: '—' },
          { label: 'Open conversations', value: '0' },
        ]"
        :key="stat.label"
        class="rounded-2xl border p-5"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
      >
        <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">{{ stat.label }}</p>
        <p class="mt-2 text-[28px] font-semibold tabular-nums tracking-tight" style="color: var(--color-text);">{{ stat.value }}</p>
      </div>
    </div>

    <!-- Pitch material per project -->
    <section class="mb-10">
      <div class="flex items-end justify-between mb-4">
        <div>
          <h2 class="text-[18px] font-semibold tracking-tight" style="color: var(--color-text);">Pitch material</h2>
          <p class="text-[13px] mt-1" style="color: var(--color-text-secondary);">Public landing pages and supporting documents per project.</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <article
          v-for="p in projects"
          :key="p.slug"
          class="rounded-2xl border p-5 transition-shadow hover:shadow-md"
          :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
        >
          <div class="flex items-start justify-between gap-3 mb-3">
            <div class="min-w-0">
              <h3 class="text-[15px] font-semibold tracking-tight" :style="{ color: 'var(--color-text)' }">{{ p.name }}</h3>
              <p class="text-[10px] font-mono mt-0.5" :style="{ color: 'var(--color-text-tertiary)' }">{{ p.slug }}</p>
            </div>
            <span
              class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full shrink-0"
              :style="{
                color: statusColors[p.status] ?? 'var(--color-text-secondary)',
                background: `${statusColors[p.status] ?? 'var(--color-text-secondary)'}20`,
              }"
            >
              {{ statusLabels[p.status] ?? p.status }}
            </span>
          </div>

          <ul class="flex flex-col gap-1.5 mb-4">
            <li v-for="m in p.materials" :key="m.url">
              <a
                :href="m.url"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1.5 text-[12px] transition-colors hover:underline"
                :style="{ color: 'var(--color-text-secondary)' }"
              >
                <UIcon name="i-lucide-file-text" class="size-3.5" />
                {{ m.label }}
                <UIcon name="i-lucide-arrow-up-right" class="size-3" :style="{ color: 'var(--color-text-tertiary)' }" />
              </a>
            </li>
          </ul>

          <div class="flex items-center gap-2 pt-3 border-t" :style="{ borderColor: 'var(--color-border)' }">
            <NuxtLink
              :to="p.landingUrl"
              target="_blank"
              class="text-[11px] font-medium px-2.5 py-1 rounded-md border transition-colors hover:bg-(--color-bg-secondary) inline-flex items-center gap-1"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-accent)' }"
            >
              Open landing
              <UIcon name="i-lucide-external-link" class="size-3" />
            </NuxtLink>
            <button
              type="button"
              class="text-[11px] font-medium px-2.5 py-1 rounded-md border transition-colors hover:bg-(--color-bg-secondary) inline-flex items-center gap-1 ml-auto"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
              @click="copyLandingUrl(p.landingUrl)"
            >
              <UIcon name="i-lucide-link" class="size-3" />
              Copy link
            </button>
          </div>
        </article>
      </div>
    </section>

    <!-- Investor list placeholder -->
    <section>
      <div class="flex items-end justify-between mb-4">
        <div>
          <h2 class="text-[18px] font-semibold tracking-tight" style="color: var(--color-text);">Investor list</h2>
          <p class="text-[13px] mt-1" style="color: var(--color-text-secondary);">Track conversations, NDAs, and per-project access grants.</p>
        </div>
      </div>

      <div
        class="rounded-2xl border p-12 text-center"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
      >
        <div
          class="inline-flex items-center justify-center size-12 rounded-2xl mb-4"
          :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
        >
          <UIcon name="i-lucide-handshake" class="size-6" />
        </div>
        <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">Investor CRM coming soon</p>
        <p class="text-[12px] max-w-md mx-auto" :style="{ color: 'var(--color-text-secondary)' }">
          Add investors, generate magic-link deal rooms per project, log conversations, and track engagement. Until then, share landing URLs directly.
        </p>
      </div>
    </section>
  </div>
</template>
