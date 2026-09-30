<script setup lang="ts">
// /blog — published posts, newest first. Two filters, both query params so
// the swr cache keys per combination and the URL is shareable: the pill row
// (a dropdown on mobile) filters by FORMAT (guide / article / …) and the
// dropdown on the right by TOPIC (category ∪ tags). Fed by GET /api/v1/blog/posts; see BLOG.md.
import { onClickOutside } from '@vueuse/core'
import SectionHeader from '~/components/shared/SectionHeader.vue'
import PublicBlogCard from '~/components/public/BlogCard.vue'
import { blogFormatLabel, type BlogPostCard } from '~/data/blog'

definePageMeta({ layout: 'public' })

usePublicSeo({
  title: 'Websites, UX & Business Systems Blog | Axel Nova',
  description: 'Practical notes from Axel Nova Ventures on websites, UI/UX and custom systems for business owners in Malaysia.',
  path: '/blog',
  card: {
    label: 'BLOG',
    headline: 'Notes from the workbench.',
  },
})

interface ListResponse {
  data: BlogPostCard[]
  meta: { current_page: number, last_page: number, total: number }
  formats: { value: string, count: number }[]
  topics: { name: string, count: number }[]
}

const route = useRoute()
const format = computed(() => (route.query.format as string) || '')
const topic = computed(() => (route.query.topic as string) || '')
const page = computed(() => Math.max(1, Number(route.query.page) || 1))
const apiBase = useApiBase()

const { data } = await useFetch<ListResponse>(
  () => `${apiBase}/api/v1/blog/posts?format=${encodeURIComponent(format.value)}&topic=${encodeURIComponent(topic.value)}&page=${page.value}`,
  { key: () => `public-blog-${format.value}-${topic.value}-${page.value}` },
)

const posts = computed(() => data.value?.data ?? [])
const formats = computed(() => data.value?.formats ?? [])
const topics = computed(() => data.value?.topics ?? [])
const meta = computed(() => data.value?.meta)

function queryFor(f: string, t: string, p = 1) {
  const q: Record<string, string> = {}
  if (f) q.format = f
  if (t) q.topic = t
  if (p > 1) q.page = String(p)
  return q
}

const pillStyle = (on: boolean) => ({
  borderColor: on ? 'transparent' : 'var(--color-border-strong)',
  background: on ? 'var(--color-text)' : 'transparent',
  color: on ? 'var(--color-bg)' : 'var(--color-text-secondary)',
  fontWeight: on ? 500 : 400,
  boxShadow: on ? 'var(--shadow-sm)' : 'none',
})

// Topic dropdown — same pattern as the status dropdown on /projects.
const topicOpen = ref(false)
const topicRef = ref<HTMLElement | null>(null)
onClickOutside(topicRef, () => { topicOpen.value = false })
function selectTopic(t: string) {
  topicOpen.value = false
  navigateTo({ path: '/blog', query: queryFor(format.value, t) })
}

// Format dropdown — mobile only; the pill row replaces it from md up.
const formatOpen = ref(false)
const formatRef = ref<HTMLElement | null>(null)
onClickOutside(formatRef, () => { formatOpen.value = false })
function selectFormat(f: string) {
  formatOpen.value = false
  navigateTo({ path: '/blog', query: queryFor(f, topic.value) })
}

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-20 pb-24">
    <SectionHeader
      as="h1"
      eyebrow="Blog"
      title="Notes from the workbench."
      subtitle="Clear explanations of website decisions, interface design and business systems, drawn from the work behind each build."
    />

    <!-- Filters: mobile = format dropdown (left) · topic dropdown (right), equal halves;
         md+ = format pills (left) · topic dropdown (right) -->
    <div v-if="formats.length || topics.length" class="grid grid-cols-2 gap-3 mb-10 md:flex md:items-center md:justify-between md:gap-4 md:mb-12">
      <div ref="formatRef" class="relative min-w-0 md:hidden">
        <button
          type="button"
          class="w-full flex items-center gap-2 text-[13px] pl-4 pr-3 py-2 rounded-full border transition-colors"
          :style="{ borderColor: 'var(--color-border-strong)', background: 'var(--color-bg-elevated)', color: 'var(--color-text)' }"
          :aria-expanded="formatOpen"
          aria-haspopup="listbox"
          @click="formatOpen = !formatOpen"
        >
          <span class="text-[11px] uppercase tracking-wider shrink-0" :style="{ color: 'var(--color-text-tertiary)' }">Format</span>
          <span class="truncate">{{ format ? blogFormatLabel(format) : 'All' }}</span>
          <UIcon name="i-lucide-chevron-down" class="size-3.5 ml-auto shrink-0 transition-transform" :class="{ 'rotate-180': formatOpen }" />
        </button>
        <Transition name="confirm-fade">
          <ul
            v-if="formatOpen"
            role="listbox"
            class="absolute left-0 mt-2 w-full min-w-[200px] max-h-[60vh] overflow-y-auto rounded-2xl border py-2 z-20"
            :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }"
          >
            <li>
              <button type="button" role="option" :aria-selected="!format" class="w-full text-left text-[13px] px-4 py-2 flex items-center gap-2 hover:bg-(--color-bg-secondary)" :style="{ color: 'var(--color-text)' }" @click="selectFormat('')">
                <UIcon name="i-lucide-check" class="size-3.5" :class="{ invisible: format }" /> All formats
              </button>
            </li>
            <li v-for="f in formats" :key="f.value">
              <button type="button" role="option" :aria-selected="format === f.value" class="w-full text-left text-[13px] px-4 py-2 flex items-center gap-2 hover:bg-(--color-bg-secondary)" :style="{ color: 'var(--color-text)' }" @click="selectFormat(f.value)">
                <UIcon name="i-lucide-check" class="size-3.5" :class="{ invisible: format !== f.value }" /> {{ blogFormatLabel(f.value) }}
                <span class="ml-auto text-[11px] tabular-nums" :style="{ color: 'var(--color-text-tertiary)' }">{{ f.count }}</span>
              </button>
            </li>
          </ul>
        </Transition>
      </div>

      <div class="hidden md:flex items-center gap-2 flex-wrap">
        <NuxtLink :to="{ path: '/blog', query: queryFor('', topic) }" class="text-[13px] px-4 py-1.5 rounded-full border transition-all duration-200" :style="pillStyle(!format)">All</NuxtLink>
        <NuxtLink
          v-for="f in formats" :key="f.value"
          :to="{ path: '/blog', query: queryFor(f.value, topic) }"
          class="text-[13px] px-4 py-1.5 rounded-full border transition-all duration-200"
          :style="pillStyle(format === f.value)"
        >
          {{ blogFormatLabel(f.value) }}
        </NuxtLink>
      </div>

      <div v-if="topics.length" ref="topicRef" class="relative min-w-0 md:shrink-0">
        <button
          type="button"
          class="w-full md:w-auto flex md:inline-flex items-center gap-2 text-[13px] pl-4 pr-3 py-2 md:px-4 md:py-1.5 rounded-full border transition-colors"
          :style="{ borderColor: 'var(--color-border-strong)', background: 'var(--color-bg-elevated)', color: 'var(--color-text)' }"
          :aria-expanded="topicOpen"
          aria-haspopup="listbox"
          @click="topicOpen = !topicOpen"
        >
          <span class="text-[11px] uppercase tracking-wider shrink-0" :style="{ color: 'var(--color-text-tertiary)' }">Topic</span>
          <span class="truncate">{{ topic || 'All topics' }}</span>
          <UIcon name="i-lucide-chevron-down" class="size-3.5 ml-auto md:ml-0 shrink-0 transition-transform" :class="{ 'rotate-180': topicOpen }" />
        </button>
        <Transition name="confirm-fade">
          <ul
            v-if="topicOpen"
            role="listbox"
            class="absolute right-0 mt-2 w-full md:w-auto min-w-[220px] max-h-[60vh] overflow-y-auto rounded-2xl border py-2 z-20"
            :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }"
          >
            <li>
              <button type="button" role="option" :aria-selected="!topic" class="w-full text-left text-[13px] px-4 py-2 flex items-center gap-2 hover:bg-(--color-bg-secondary)" :style="{ color: 'var(--color-text)' }" @click="selectTopic('')">
                <UIcon name="i-lucide-check" class="size-3.5" :class="{ invisible: topic }" /> All topics
              </button>
            </li>
            <li v-for="t in topics" :key="t.name">
              <button type="button" role="option" :aria-selected="topic === t.name" class="w-full text-left text-[13px] px-4 py-2 flex items-center gap-2 hover:bg-(--color-bg-secondary)" :style="{ color: 'var(--color-text)' }" @click="selectTopic(t.name)">
                <UIcon name="i-lucide-check" class="size-3.5 shrink-0" :class="{ invisible: topic !== t.name }" /> {{ t.name }}
                <span class="ml-auto text-[11px] tabular-nums" :style="{ color: 'var(--color-text-tertiary)' }">{{ t.count }}</span>
              </button>
            </li>
          </ul>
        </Transition>
      </div>
    </div>

    <!-- Empty -->
    <div v-if="!posts.length" class="rounded-2xl border px-6 py-16 text-center" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
      <span class="size-12 rounded-2xl inline-flex items-center justify-center mb-4" :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }">
        <UIcon name="i-lucide-pen-line" class="size-6" />
      </span>
      <p class="text-[15px] font-semibold tracking-tight mb-1" :style="{ color: 'var(--color-text)' }">{{ format || topic ? 'Nothing here yet' : 'Nothing published yet' }}</p>
      <p class="text-[13px]" :style="{ color: 'var(--color-text-secondary)' }">
        <template v-if="format || topic">No posts match that filter. <NuxtLink to="/blog" class="underline">Show everything</NuxtLink>.</template>
        <template v-else>The first notes are on their way. Check back soon.</template>
      </p>
    </div>

    <!-- Grid -->
    <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
      <PublicBlogCard v-for="p in posts" :key="p.slug" :post="p" class="reveal" />
    </div>

    <!-- Pagination -->
    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-3 mt-12">
      <NuxtLink v-if="page > 1" :to="{ path: '/blog', query: queryFor(format, topic, page - 1) }" class="btn-pill btn-pill-ghost text-[12px]">← Newer</NuxtLink>
      <span class="text-[13px]" :style="{ color: 'var(--color-text-secondary)' }">{{ page }} / {{ meta.last_page }}</span>
      <NuxtLink v-if="page < meta.last_page" :to="{ path: '/blog', query: queryFor(format, topic, page + 1) }" class="btn-pill btn-pill-ghost text-[12px]">Older →</NuxtLink>
    </div>
  </div>
</template>
