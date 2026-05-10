<script setup lang="ts">
definePageMeta({ layout: 'public' })

interface ApiProject {
  slug: string
  name: string
  description: string
  long_description: string
  status: 'live' | 'soon' | 'wip' | 'planning'
  url: string | null
  repo: string | null
  tags: string[]
  stack: string[]
  cover_image_url: string | null
  featured: boolean
}

const route = useRoute()
const slug = computed(() => route.params.id as string)
const apiBase = useApiBase()

const { data: apiResponse, error } = await useFetch<{ data: ApiProject }>(
  () => `${apiBase}/api/v1/projects/${slug.value}`,
  { key: () => `public-project-${slug.value}` },
)

const project = computed(() => apiResponse.value?.data)
const notFound = computed(() => Boolean(error.value) || (!project.value && !apiResponse.value))

const statusMeta = (status: ApiProject['status']) => {
  switch (status) {
    case 'live':     return { label: 'Live',        color: 'var(--color-success)', bg: 'rgba(48,209,88,0.14)' }
    case 'wip':      return { label: 'In progress', color: 'var(--color-warning)', bg: 'rgba(255,159,10,0.14)' }
    case 'planning': return { label: 'Planning',    color: 'var(--color-accent)',  bg: 'rgba(0,113,227,0.12)' }
    case 'soon':     return { label: 'Planning',    color: 'var(--color-accent)',  bg: 'rgba(0,113,227,0.12)' }
  }
}

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-4xl mx-auto px-6 pt-20 pb-32">
    <NuxtLink
      to="/projects"
      class="text-[13px] inline-flex items-center gap-1.5 mb-10 transition-colors hover:opacity-80"
      style="color: var(--color-text-secondary);"
    >
      <span aria-hidden>←</span> Back to registry
    </NuxtLink>

    <article v-if="project">
      <!-- Status + featured -->
      <div class="reveal flex items-center gap-2 mb-5">
        <span
          class="text-[11px] font-medium px-2.5 py-1 rounded-full inline-flex items-center gap-1.5"
          :style="{ color: statusMeta(project.status)?.color, background: statusMeta(project.status)?.bg }"
        >
          <span class="size-1.5 rounded-full" :style="{ background: statusMeta(project.status)?.color }" />
          {{ statusMeta(project.status)?.label }}
        </span>
        <span
          v-if="project.featured"
          class="text-[11px] font-medium px-2.5 py-1 rounded-full inline-flex items-center gap-1.5"
          :style="{ color: 'var(--color-accent)', background: 'var(--color-accent-soft)' }"
        >
          <UIcon name="i-fluent-star-24-regular" class="size-3" />
          Featured
        </span>
      </div>

      <!-- Title -->
      <h1 class="reveal text-5xl md:text-6xl font-semibold tracking-tighter mb-5" style="color: var(--color-text);">
        {{ project.name }}
      </h1>

      <!-- Short description as lede -->
      <p class="reveal text-[18px] leading-[1.55] mb-10 max-w-2xl" style="color: var(--color-text-secondary);">
        {{ project.description }}
      </p>

      <!-- CTAs -->
      <div class="reveal flex flex-wrap gap-3 mb-14">
        <a
          v-if="project.url"
          :href="project.url" target="_blank" rel="noopener"
          class="btn-pill btn-pill-primary inline-flex items-center gap-2"
        >
          Visit project
          <UIcon name="i-fluent-arrow-up-right-24-regular" class="size-4" />
        </a>
        <a
          v-if="project.repo"
          :href="project.repo" target="_blank" rel="noopener"
          class="btn-pill btn-pill-ghost inline-flex items-center gap-2"
        >
          <UIcon name="i-fluent-code-24-regular" class="size-4" />
          Source
        </a>
      </div>

      <!-- Cover image -->
      <div
        v-if="project.cover_image_url"
        class="reveal mb-14 rounded-2xl overflow-hidden border"
        :style="{ borderColor: 'var(--color-border)' }"
      >
        <img
          :src="project.cover_image_url"
          :alt="`${project.name} cover image`"
          class="w-full h-auto object-cover"
          loading="lazy"
        />
      </div>

      <!-- Long description -->
      <section v-if="project.long_description" class="reveal mb-14">
        <h2 class="text-[12px] uppercase tracking-[0.08em] font-semibold mb-4" style="color: var(--color-text-tertiary);">
          About
        </h2>
        <p class="text-[16px] leading-[1.7] whitespace-pre-line" style="color: var(--color-text-secondary);">
          {{ project.long_description }}
        </p>
      </section>

      <!-- Stack + tags -->
      <div class="grid md:grid-cols-2 gap-10">
        <section v-if="project.stack?.length" class="reveal">
          <h2 class="text-[12px] uppercase tracking-[0.08em] font-semibold mb-3" style="color: var(--color-text-tertiary);">
            Stack
          </h2>
          <div class="flex flex-wrap gap-1.5">
            <span
              v-for="t in project.stack" :key="t"
              class="text-[12px] px-2.5 py-1 rounded-md border"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
            >{{ t }}</span>
          </div>
        </section>

        <section v-if="project.tags?.length" class="reveal">
          <h2 class="text-[12px] uppercase tracking-[0.08em] font-semibold mb-3" style="color: var(--color-text-tertiary);">
            Tags
          </h2>
          <div class="flex flex-wrap gap-1.5">
            <span
              v-for="t in project.tags" :key="t"
              class="text-[12px] px-2.5 py-1 rounded-full"
              :style="{ background: 'var(--color-bg-secondary)', color: 'var(--color-text-secondary)' }"
            >#{{ t }}</span>
          </div>
        </section>
      </div>
    </article>

    <div v-else-if="notFound" class="text-center py-20">
      <p class="text-3xl font-semibold tracking-tight mb-3">Project not found.</p>
      <NuxtLink to="/projects" class="text-[14px]" style="color: var(--color-accent);">
        Back to registry →
      </NuxtLink>
    </div>
  </div>
</template>
