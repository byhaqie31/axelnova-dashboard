<script setup lang="ts">
// /blog/[slug] — one article. The fetch awaits during SSR so crawlers get the
// real title, description, Open Graph card and BlogPosting JSON-LD; a draft or
// unknown slug throws a real 404 (the backend only serves published posts).
import PublicBlogArticle from '~/components/public/BlogArticle.vue'
import { blogFormatLabel, type BlogPostPublic } from '~/data/blog'

// footerGap: false — the article ends on its CTA card, so it keeps a modest
// pb-16 of its own instead of the layout's mt-32 stacked on top of it.
definePageMeta({ layout: 'public', footerGap: false })

const route = useRoute()
const slug = computed(() => route.params.slug as string)
const apiBase = useApiBase()

const { data } = await useFetch<{ data: BlogPostPublic }>(
  () => `${apiBase}/api/v1/blog/posts/${slug.value}`,
  { key: () => `public-blog-post-${slug.value}` },
)

const post = computed(() => data.value?.data)
if (!post.value) {
  throw createError({ statusCode: 404, statusMessage: 'Post not found', fatal: true })
}

const siteUrl = 'https://axelnovaventures.com'
const pageUrl = `${siteUrl}/blog/${slug.value}`
// Search results show ~60 title / ~155 description characters. A post with no
// SEO overrides would otherwise send its whole introduction as the description,
// so the fallback is clipped at a word boundary, and the brand suffix is only
// added while the title still fits.
const clip = (s: string, max = 155) => {
  const flat = s.replace(/\s+/g, ' ').trim()
  if (flat.length <= max) return flat
  const cut = flat.slice(0, max - 1)
  return `${cut.slice(0, cut.lastIndexOf(' ') > 0 ? cut.lastIndexOf(' ') : cut.length).replace(/[,;:.\s]+$/, '')}…`
}
const seoTitle = post.value.seo_title || post.value.title
const seoDescription = post.value.seo_description || clip(post.value.excerpt)
const fullTitle = seoTitle.length <= 38 ? `${seoTitle} — Axel Nova Ventures` : seoTitle

usePublicSeo({
  title: fullTitle,
  description: seoDescription,
  path: `/blog/${slug.value}`,
  image: post.value.cover_image_url ?? undefined,
  card: { label: blogFormatLabel(post.value.format).toUpperCase(), headline: post.value.title },
})

useHead({
  script: [
    {
      type: 'application/ld+json' as const,
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BlogPosting',
        'headline': post.value.title,
        'description': seoDescription,
        ...(post.value.cover_image_url && { image: post.value.cover_image_url }),
        'datePublished': post.value.published_at,
        'dateModified': post.value.updated_at ?? post.value.published_at,
        'author': { '@type': 'Person', 'name': 'Ahmad Baihaqie' },
        'publisher': { '@type': 'Organization', 'name': 'Axel Nova Ventures', 'url': siteUrl },
        'mainEntityOfPage': pageUrl,
        ...(post.value.category && { articleSection: post.value.category }),
        ...(post.value.tags.length && { keywords: post.value.tags.join(', ') }),
      }),
    },
    {
      type: 'application/ld+json' as const,
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        'itemListElement': [
          { '@type': 'ListItem', 'position': 1, 'name': 'Home', 'item': siteUrl },
          { '@type': 'ListItem', 'position': 2, 'name': 'Blog', 'item': `${siteUrl}/blog` },
          { '@type': 'ListItem', 'position': 3, 'name': post.value.title, 'item': pageUrl },
        ],
      }),
    },
  ],
})
</script>

<template>
  <div class="max-w-6xl mx-auto px-6 pt-16 pb-16">
    <NuxtLink
      to="/blog"
      class="text-[13px] inline-flex items-center gap-1.5 mb-10 transition-colors hover:opacity-80"
      :style="{ color: 'var(--color-text-secondary)' }"
    >
      <span aria-hidden>←</span> All posts
    </NuxtLink>

    <PublicBlogArticle v-if="post" :post="post" />
  </div>
</template>
