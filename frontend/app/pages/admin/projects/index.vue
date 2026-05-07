<script setup lang="ts">
import { projects } from '~/data/projects'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })
useHead({ title: 'Projects — Admin' })

const featuredCount = projects.filter(p => p.featured).length
const liveCount = projects.filter(p => p.status === 'live').length

const statusColors: Record<string, string> = {
  live: 'var(--color-success)',
  soon: 'var(--color-accent)',
  wip: '#A855F7',
  planning: 'var(--color-text-tertiary)',
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-10 pb-32">
    <div class="mb-6">
      <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Admin</p>
      <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Projects</h1>
      <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
        {{ projects.length }} total · {{ featuredCount }} featured · {{ liveCount }} live
      </p>
    </div>

    <div
      class="mb-8 rounded-xl border p-4 flex items-start gap-3"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-accent-soft)' }"
    >
      <UIcon name="i-lucide-info" class="size-4 mt-0.5 shrink-0" :style="{ color: 'var(--color-accent)' }" />
      <div>
        <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-accent)' }">Read-only preview (Phase C)</p>
        <p class="text-[12px] mt-0.5" :style="{ color: 'var(--color-text-secondary)' }">
          Currently sourced from <span class="font-mono">app/data/projects.ts</span>. CMS editing (toggle featured, change status, edit copy) wires up after migrating into a <span class="font-mono">projects</span> table.
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <article
        v-for="project in projects"
        :key="project.id"
        class="rounded-2xl border p-5 transition-shadow hover:shadow-md"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
      >
        <div class="flex items-start justify-between gap-3 mb-2">
          <h3 class="text-[15px] font-semibold tracking-tight" :style="{ color: 'var(--color-text)' }">{{ project.name }}</h3>
          <span
            class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full shrink-0"
            :style="{
              color: statusColors[project.status] ?? 'var(--color-text-secondary)',
              background: `${statusColors[project.status] ?? 'var(--color-text-secondary)'}20`,
            }"
          >
            {{ project.status }}
          </span>
        </div>
        <p class="text-[12px] line-clamp-3 mb-3" :style="{ color: 'var(--color-text-secondary)' }">{{ project.description }}</p>
        <div class="flex flex-wrap gap-1.5 mb-3">
          <span
            v-for="tag in project.tags.slice(0, 4)"
            :key="tag"
            class="text-[10px] font-medium px-2 py-0.5 rounded-full"
            :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg-secondary)' }"
          >
            {{ tag }}
          </span>
        </div>
        <div class="flex items-center gap-2 pt-3 border-t" :style="{ borderColor: 'var(--color-border)' }">
          <span
            v-if="project.featured"
            class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
            :style="{ color: 'var(--color-accent)', background: 'var(--color-accent-soft)' }"
          >
            Featured
          </span>
          <span class="text-[11px] font-mono ml-auto" :style="{ color: 'var(--color-text-tertiary)' }">{{ project.id }}</span>
        </div>
      </article>
    </div>
  </div>
</template>
