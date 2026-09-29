<script setup lang="ts">
// One post in the /blog grid and the article's "More from the blog" row.
// Cover is a plain URL (no upload flow); a soft placeholder stands in when
// a post has none so the grid stays even.
import { blogFormatLabel, fmtBlogDate, type BlogPostCard } from '~/data/blog'

defineProps<{ post: BlogPostCard }>()
</script>

<template>
  <NuxtLink
    :to="`/blog/${post.slug}`"
    class="group block rounded-2xl border overflow-hidden transition-all duration-200 hover:-translate-y-0.5"
    :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)', boxShadow: 'var(--shadow-xs)' }"
  >
    <div class="aspect-[16/9] overflow-hidden" :style="{ background: 'var(--color-bg-secondary)' }">
      <img
        v-if="post.cover_image_url"
        :src="post.cover_image_url"
        :alt="post.cover_image_alt ?? post.title"
        class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
        loading="lazy"
      >
      <div v-else class="w-full h-full flex items-center justify-center">
        <UIcon name="i-lucide-newspaper" class="size-8" :style="{ color: 'var(--color-text-tertiary)' }" />
      </div>
    </div>
    <div class="p-5">
      <p class="text-[12px] mb-3 flex flex-wrap items-center gap-x-2 gap-y-1" :style="{ color: 'var(--color-text-tertiary)' }">
        <span class="blog-format-pill text-[10px]">{{ blogFormatLabel(post.format) }}</span>
        <span>{{ fmtBlogDate(post.published_at) }} · {{ post.reading_minutes }} min read</span>
      </p>
      <h3 class="text-[18px] font-semibold tracking-tight leading-snug mb-2" :style="{ color: 'var(--color-text)' }">{{ post.title }}</h3>
      <p class="text-[14px] leading-relaxed line-clamp-3" :style="{ color: 'var(--color-text-secondary)' }">{{ post.excerpt }}</p>
    </div>
  </NuxtLink>
</template>
