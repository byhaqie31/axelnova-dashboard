<script setup lang="ts">
import SectionHeader from '~/components/shared/SectionHeader.vue'
import PublicBlogCard from '~/components/public/BlogCard.vue'
import type { BlogPostCard } from '~/data/blog'

/**
 * The newest published posts on the public home page, below the client
 * previews. Reads page 1 of the public feed (newest first) and keeps the top
 * three — `transform` trims the rest so they never ride along in the SSR
 * payload. Renders nothing at all when nothing is published (or the feed
 * fails), same as TestimonialWall. See docs/global/BLOG.md.
 */
const { data: posts } = await useFetch(
  `${useApiBase()}/api/v1/blog/posts`,
  {
    key: 'public-blog-home',
    transform: (res: { data: BlogPostCard[] }) => (res.data ?? []).slice(0, 3),
  },
)
</script>

<template>
  <section v-if="posts?.length" class="max-w-7xl mx-auto px-6 pb-32 reveal">
    <SectionHeader
      eyebrow="From the blog"
      title="Latest notes."
      subtitle="Notes on website design, business systems and the choices behind a useful interface."
      :action="{ label: 'View all', to: '/blog' }"
    />

    <!-- Two-up at md would orphan the third card on its own row — it only
         shows once the grid is three-up. -->
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
      <PublicBlogCard
        v-for="(p, i) in posts"
        :key="p.slug"
        :post="p"
        class="reveal"
        :class="{ 'md:max-lg:hidden': i === 2 }"
      />
    </div>
  </section>
</template>
