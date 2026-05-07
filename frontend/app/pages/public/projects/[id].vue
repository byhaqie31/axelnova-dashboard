<script setup lang="ts">
definePageMeta({ layout: 'public' })

import { projects } from '~/data/projects'

const route = useRoute()
const id = computed(() => route.params.id as string)
const project = computed(() => projects.find(p => p.id === id.value))
</script>

<template>
  <div class="max-w-3xl mx-auto px-6 pt-24 pb-32">
    <NuxtLink
      to="/projects"
      class="text-[13px] inline-flex items-center gap-1.5 mb-10"
      style="color: var(--color-text-secondary);"
    >
      <span aria-hidden>←</span> Back to registry
    </NuxtLink>

    <div v-if="project">
      <h1 class="text-5xl md:text-6xl font-semibold tracking-tight mb-5">{{ project.name }}</h1>
      <p class="text-[17px] leading-[1.6] mb-8" style="color: var(--color-text-secondary);">
        {{ project.longDescription }}
      </p>

      <div class="flex flex-wrap gap-1.5 mb-10">
        <span
          v-for="t in project.stack" :key="t"
          class="text-[12px] px-2.5 py-1 rounded-md border"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
        >{{ t }}</span>
      </div>

      <div class="flex flex-wrap gap-3">
        <a
          v-if="project.url"
          :href="project.url" target="_blank" rel="noopener"
          class="btn-pill btn-pill-primary"
        >Visit project</a>
        <a
          v-if="project.repo"
          :href="project.repo" target="_blank" rel="noopener"
          class="btn-pill btn-pill-ghost"
        >Source</a>
      </div>
    </div>

    <div v-else class="text-center py-20">
      <p class="text-3xl font-semibold tracking-tight mb-3">Project not found.</p>
      <NuxtLink to="/projects" class="text-[14px]" style="color: var(--color-accent);">
        Back to registry →
      </NuxtLink>
    </div>
  </div>
</template>
