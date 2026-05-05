<script setup lang="ts">
import type { Project } from '~/data/projects'

defineProps<{ project: Project }>()

const statusMeta = (status: Project['status']) => {
  switch (status) {
    case 'live':     return { label: 'Live',        color: 'var(--color-success)',         bg: 'rgba(48,209,88,0.14)' }
    case 'wip':      return { label: 'In progress', color: 'var(--color-warning)',         bg: 'rgba(255,159,10,0.14)' }
    case 'planning': return { label: 'Planning',    color: 'var(--color-accent)',          bg: 'rgba(0,113,227,0.12)' }
    case 'soon':     return { label: 'Planning',    color: 'var(--color-accent)',          bg: 'rgba(0,113,227,0.12)' }
  }
}
</script>

<template>
  <NuxtLink
    :to="`/projects/${project.id}`"
    class="card group relative block rounded-2xl border p-7 transition-all duration-300 overflow-hidden h-full"
    style="background: var(--color-bg-elevated); border-color: var(--color-border);"
  >
    <!-- Subtle gradient hover wash -->
    <span
      aria-hidden
      class="card-glow pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-500"
      style="
        background:
          radial-gradient(60% 80% at 0% 0%, rgba(0,113,227,0.10) 0%, transparent 55%),
          radial-gradient(60% 80% at 100% 100%, rgba(168,85,247,0.10) 0%, transparent 55%);
      "
    />

    <div class="relative flex items-start justify-between mb-6">
      <div
        class="size-10 rounded-xl flex items-center justify-center"
        style="background: var(--color-accent-soft); border: 1px solid var(--color-border);"
      >
        <UIcon name="i-fluent-stack-24-regular" class="size-4" :style="{ color: 'var(--color-accent)' }" />
      </div>
      <span
        class="text-[11px] font-medium px-2.5 py-1 rounded-full inline-flex items-center gap-1.5"
        :style="{ color: statusMeta(project.status).color, background: statusMeta(project.status).bg }"
      >
        <span class="size-1.5 rounded-full" :style="{ background: statusMeta(project.status).color }" />
        {{ statusMeta(project.status).label }}
      </span>
    </div>

    <h3 class="relative text-[20px] font-semibold tracking-tight mb-2" style="color: var(--color-text);">
      {{ project.name }}
    </h3>
    <p class="relative text-[14px] leading-relaxed mb-5" style="color: var(--color-text-secondary);">
      {{ project.description }}
    </p>

    <div class="relative flex flex-wrap gap-1.5">
      <span
        v-for="tag in project.stack"
        :key="tag"
        class="text-[11px] px-2 py-0.5 rounded-md border"
        :style="{ color: 'var(--color-text-secondary)', borderColor: 'var(--color-border)' }"
      >
        {{ tag }}
      </span>
    </div>

    <span
      class="absolute bottom-7 right-7 size-8 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300"
      style="background: var(--color-accent); color: #fff; transform: translate(4px, 4px); box-shadow: 0 6px 16px rgba(0,113,227,0.32);"
    >
      <UIcon name="i-fluent-arrow-right-24-regular" class="size-4" />
    </span>
  </NuxtLink>
</template>

<style scoped>
.card:hover {
  transform: translateY(-4px);
  border-color: var(--color-border-strong) !important;
  box-shadow: var(--shadow-card-hover);
}
.card:hover .card-glow {
  opacity: 1;
}
</style>
