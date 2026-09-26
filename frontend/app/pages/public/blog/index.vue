<script setup lang="ts">
// /blog — published posts, newest first, filterable by category (query param
// so the swr cache keys per filter and the URL is shareable). Fed by
// GET /api/v1/blog/posts; see docs/global/BLOG.md.
import SectionHeader from '~/components/shared/SectionHeader.vue'
import PublicBlogCard from '~/components/public/BlogCard.vue'
import type { BlogPostCard } from '~/data/blog'

definePageMeta({ layout: 'public' })

usePublicSeo({
  title: 'Blog — Axel Nova Ventures',
  description: 'Notes on UI/UX, custom systems and the everyday tech decisions Malaysian business owners and founders run into.',
  path: '/blog',
})

interface ListResponse {
  data: BlogPostCard[]
  meta: { current_page: number, last_page: number, total: number }
  categories: { name: string, count: number }[]
}

const route = useRoute()
const category = computed(() => (route.query.category as string) || '')
const page = computed(() => Math.max(1, Number(route.query.page) || 1))
const apiBase = useApiBase()

const { data } = await useFetch<ListResponse>(
  () => `${apiBase}/api/v1/blog/posts?category=${encodeURIComponent(category.value)}&page=${page.value}`,
  { key: () => `public-blog-${category.value}-${page.value}` },
)

const posts = computed(() => data.value?.data ?? [])
const categories = computed(() => data.value?.categories ?? [])
const meta = computed(() => data.value?.meta)

function queryFor(cat: string, p = 1) {
  const q: Record<string, string> = {}
  if (cat) q.category = cat
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

useScrollReveal('.reveal')
</script>

<template>
  <div class="max-w-7xl mx-auto px-6 pt-20 pb-24">
    <SectionHeader
      eyebrow="Blog"
      title="Notes from the workbench."
      subtitle="Thoughts on interfaces, systems and the decisions behind them — written for business owners and founders."
    />

    <!-- Category pills -->
    <div v-if="categories.length" class="flex items-center gap-2 flex-wrap mb-12">
      <NuxtLink :to="{ path: '/blog', query: queryFor('') }" class="text-[13px] px-4 py-1.5 rounded-full border transition-all duration-200" :style="pillStyle(!category)">All</NuxtLink>
      <NuxtLink
        v-for="c in categories" :key="c.name"
        :to="{ path: '/blog', query: queryFor(c.name) }"
        class="text-[13px] px-4 py-1.5 rounded-full border transition-all duration-200"
        :style="pillStyle(category === c.name)"
      >
        {{ c.name }}
      </NuxtLink>
    </div>

    <!-- Empty -->
    <div v-if="!posts.length" class="rounded-2xl border px-6 py-16 text-center" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
      <span class="size-12 rounded-2xl inline-flex items-center justify-center mb-4" :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }">
        <UIcon name="i-lucide-pen-line" class="size-6" />
      </span>
      <p class="text-[15px] font-semibold tracking-tight mb-1" :style="{ color: 'var(--color-text)' }">Nothing published yet</p>
      <p class="text-[13px]" :style="{ color: 'var(--color-text-secondary)' }">The first notes are on their way. Check back soon.</p>
    </div>

    <!-- Grid -->
    <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
      <PublicBlogCard v-for="p in posts" :key="p.slug" :post="p" class="reveal" />
    </div>

    <!-- Pagination -->
    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-3 mt-12">
      <NuxtLink v-if="page > 1" :to="{ path: '/blog', query: queryFor(category, page - 1) }" class="btn-pill btn-pill-ghost text-[12px]">← Newer</NuxtLink>
      <span class="text-[13px]" :style="{ color: 'var(--color-text-secondary)' }">{{ page }} / {{ meta.last_page }}</span>
      <NuxtLink v-if="page < meta.last_page" :to="{ path: '/blog', query: queryFor(category, page + 1) }" class="btn-pill btn-pill-ghost text-[12px]">Older →</NuxtLink>
    </div>
  </div>
</template>
