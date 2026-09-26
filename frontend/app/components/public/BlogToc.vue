<script setup lang="ts">
// "On this page" — anchors built from the section headings (backend `toc`).
// Tracks the heading nearest the top of the viewport to highlight the active
// entry; degrades to a plain link list wherever IntersectionObserver is absent.
import type { BlogTocItem } from '~/data/blog'

const props = defineProps<{ items: BlogTocItem[] }>()

const active = ref<string | null>(null)
let observer: IntersectionObserver | null = null

onMounted(() => {
  if (typeof window === 'undefined' || !('IntersectionObserver' in window)) return
  const headings = props.items
    .map(i => document.getElementById(i.id))
    .filter((el): el is HTMLElement => !!el)
  if (!headings.length) return
  observer = new IntersectionObserver((entries) => {
    const visible = entries
      .filter(e => e.isIntersecting)
      .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)
    if (visible[0]) active.value = visible[0].target.id
  }, { rootMargin: '-20% 0px -70% 0px', threshold: 0 })
  headings.forEach(h => observer!.observe(h))
})
onUnmounted(() => observer?.disconnect())
</script>

<template>
  <nav class="blog-toc" aria-label="On this page">
    <p class="text-[11px] font-semibold uppercase tracking-widest mb-3" :style="{ color: 'var(--color-text-tertiary)' }">On this page</p>
    <ol class="space-y-2">
      <li v-for="item in items" :key="item.id">
        <a :href="`#${item.id}`" class="block text-[13px] leading-snug" :class="{ 'is-active': active === item.id }">{{ item.heading }}</a>
      </li>
    </ol>
  </nav>
</template>
