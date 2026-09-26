<script setup lang="ts">
// The one article layout — used by /blog/[slug] AND the admin editor's preview
// (`preview` hides the share row + related posts and makes the CTA inert, so a
// preview can't navigate away from unsaved work). Section bodies are
// backend-rendered, sanitised HTML (App\Support\BlogMarkdown) — never raw
// Markdown and never user HTML — which is what makes the v-html safe.
import { blogCtaDefaults, fmtBlogDate, type BlogPostPublic } from '~/data/blog'
import PublicBlogToc from '~/components/public/BlogToc.vue'
import PublicBlogCard from '~/components/public/BlogCard.vue'

const props = withDefaults(defineProps<{ post: BlogPostPublic, preview?: boolean }>(), { preview: false })

const cta = computed(() => ({
  heading: props.post.cta_heading || blogCtaDefaults.heading,
  body: props.post.cta_body || blogCtaDefaults.body,
  label: props.post.cta_label || blogCtaDefaults.label,
  url: props.post.cta_url || blogCtaDefaults.url,
}))
// A TOC earns its place from three sections; shorter posts read fine without one.
const showToc = computed(() => props.post.toc.length >= 3)

const pageUrl = computed(() => `https://axelnovaventures.com/blog/${props.post.slug}`)
const shareLinks = computed(() => [
  { label: 'LinkedIn', icon: 'i-lucide-linkedin', href: `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(pageUrl.value)}` },
  { label: 'X', icon: 'i-lucide-twitter', href: `https://twitter.com/intent/tweet?url=${encodeURIComponent(pageUrl.value)}&text=${encodeURIComponent(props.post.title)}` },
  { label: 'WhatsApp', icon: 'i-lucide-message-circle', href: `https://wa.me/?text=${encodeURIComponent(`${props.post.title} ${pageUrl.value}`)}` },
])
const copied = ref(false)
async function copyLink() {
  try {
    await navigator.clipboard.writeText(pageUrl.value)
    copied.value = true
    setTimeout(() => { copied.value = false }, 1800)
  }
  catch { /* clipboard blocked — the link is in the address bar anyway */ }
}
</script>

<template>
  <article>
    <!-- Header -->
    <header class="max-w-[68ch] mx-auto">
      <p class="text-[12px] tracking-wide" :style="{ color: 'var(--color-text-tertiary)' }">
        <span v-if="post.category">{{ post.category }} · </span>{{ fmtBlogDate(post.published_at) }} · {{ post.reading_minutes }} min read
      </p>
      <h1 class="text-4xl md:text-5xl font-semibold tracking-tighter leading-[1.08] mt-3 mb-5" :style="{ color: 'var(--color-text)' }">{{ post.title }}</h1>
      <p class="text-[19px] leading-[1.6]" :style="{ color: 'var(--color-text-secondary)' }">{{ post.excerpt }}</p>
    </header>

    <!-- Cover -->
    <figure v-if="post.cover_image_url" class="max-w-4xl mx-auto my-10 rounded-2xl overflow-hidden border" :style="{ borderColor: 'var(--color-border)' }">
      <img :src="post.cover_image_url" :alt="post.cover_image_alt ?? post.title" class="w-full aspect-[16/9] object-cover">
    </figure>
    <div v-else class="my-10" />

    <div class="max-w-5xl mx-auto lg:grid lg:grid-cols-[minmax(0,1fr)_220px] lg:gap-12">
      <div class="max-w-[68ch] min-w-0">
        <!-- Inline TOC (mobile / tablet) -->
        <PublicBlogToc v-if="showToc" :items="post.toc" class="lg:hidden mb-10 rounded-2xl border p-5" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }" />

        <!-- Sections -->
        <section v-for="s in post.sections" :key="s.id" class="mb-12">
          <h2 :id="s.anchor" class="blog-section-anchor text-[26px] font-semibold tracking-tight leading-tight mb-4" :style="{ color: 'var(--color-text)' }">{{ s.heading }}</h2>
          <div class="blog-prose" v-html="s.body_html" />
          <figure v-if="s.image_url" class="mt-6 rounded-2xl overflow-hidden border" :style="{ borderColor: 'var(--color-border)' }">
            <img :src="s.image_url" :alt="s.image_alt ?? s.heading" class="w-full h-auto" loading="lazy">
            <figcaption v-if="s.image_alt" class="text-[12px] px-4 py-2" :style="{ color: 'var(--color-text-tertiary)' }">{{ s.image_alt }}</figcaption>
          </figure>
          <blockquote v-if="s.quote" class="mt-6 border-l-[3px] pl-5 text-[20px] leading-snug italic" :style="{ borderColor: 'var(--color-accent)', color: 'var(--color-text)' }">
            “{{ s.quote }}”
            <footer v-if="s.quote_by" class="text-[13px] not-italic mt-2" :style="{ color: 'var(--color-text-tertiary)' }">— {{ s.quote_by }}</footer>
          </blockquote>
        </section>

        <!-- Closing CTA -->
        <aside class="rounded-3xl border p-7 mt-4" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
          <h2 class="text-[22px] font-semibold tracking-tight mb-2" :style="{ color: 'var(--color-text)' }">{{ cta.heading }}</h2>
          <p class="text-[15px] leading-relaxed mb-5" :style="{ color: 'var(--color-text-secondary)' }">{{ cta.body }}</p>
          <NuxtLink v-if="!preview" :to="cta.url" class="btn-pill btn-pill-accent text-[13px] inline-flex items-center gap-2">
            {{ cta.label }} <UIcon name="i-lucide-arrow-right" class="size-4" />
          </NuxtLink>
          <span v-else class="btn-pill btn-pill-accent text-[13px] inline-flex items-center gap-2">{{ cta.label }} <UIcon name="i-lucide-arrow-right" class="size-4" /></span>
        </aside>

        <!-- Share -->
        <div v-if="!preview" class="flex flex-wrap items-center gap-2 mt-8">
          <span class="text-[12px] mr-1" :style="{ color: 'var(--color-text-tertiary)' }">Share</span>
          <a v-for="l in shareLinks" :key="l.label" :href="l.href" target="_blank" rel="noopener" class="btn-table-action">
            <UIcon :name="l.icon" class="size-3.5" />{{ l.label }}
          </a>
          <button type="button" class="btn-table-action" @click="copyLink">
            <UIcon :name="copied ? 'i-lucide-check' : 'i-lucide-link'" class="size-3.5" />{{ copied ? 'Copied' : 'Copy link' }}
          </button>
        </div>
      </div>

      <!-- Sticky TOC (desktop) -->
      <aside v-if="showToc" class="hidden lg:block">
        <div class="sticky top-28">
          <PublicBlogToc :items="post.toc" />
        </div>
      </aside>
    </div>

    <!-- Related -->
    <section v-if="!preview && post.related.length" class="max-w-5xl mx-auto mt-20">
      <h2 class="text-[12px] uppercase tracking-[0.08em] font-semibold mb-5" :style="{ color: 'var(--color-text-tertiary)' }">More from the blog</h2>
      <div class="grid md:grid-cols-3 gap-6">
        <PublicBlogCard v-for="r in post.related" :key="r.slug" :post="r" />
      </div>
    </section>
  </article>
</template>
