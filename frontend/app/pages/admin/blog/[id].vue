<script setup lang="ts">
// Catalog › Blog › Editor — one route for New (`/admin/blog/new`) and Edit.
// Everything the founder needs to write a post without touching code: title,
// introduction, ordered sections (Markdown-backed rich text), cover / category
// / tags / CTA / SEO in a side rail, Start-from-template and Import-Markdown
// helpers, a preview rendered by the backend (so it matches the public page
// exactly), and a Publish gate. See docs/global/BLOG.md.
import { onKeyStroke } from '@vueuse/core'
import PublicBlogArticle from '~/components/public/BlogArticle.vue'
import AdminBlogSectionEditor from '~/components/admin/BlogSectionEditor.vue'
import AdminSelect from '~/components/admin/Select.vue'
import {
  blogFormatOptions, newSection, parseMarkdownImport, readingMinutes, slugify, templateSections,
  type BlogFormat, type BlogGuide, type BlogPostAdmin, type BlogPostPublic, type BlogRenderedSection, type BlogSection, type BlogTocItem,
} from '~/data/blog'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

function errMessage(e: unknown): string | undefined {
  return (e as { data?: { message?: string } } | null)?.data?.message
}
function errFields(e: unknown): Record<string, string[]> {
  return (e as { data?: { errors?: Record<string, string[]> } } | null)?.data?.errors ?? {}
}

const isNew = computed(() => route.params.id === 'new')
const post = ref<BlogPostAdmin | null>(null)
const loading = ref(!isNew.value)
const loadError = ref('')
const saving = ref(false)
const acting = ref(false)
const errors = ref<Record<string, string[]>>({})
const errorList = computed(() => Object.values(errors.value).flat())

const form = reactive({
  title: '',
  slug: '',
  excerpt: '',
  sections: [] as BlogSection[],
  cover_image_url: '',
  cover_image_alt: '',
  category: '',
  format: 'article' as BlogFormat,
  tags: '',
  cta_heading: '',
  cta_body: '',
  cta_label: '',
  cta_url: '',
  seo_title: '',
  seo_description: '',
})
const autoSlug = ref(true)
let savedSnapshot = JSON.stringify(form)
const dirty = computed(() => JSON.stringify(form) !== savedSnapshot)
const liveMinutes = computed(() => readingMinutes(form.excerpt, form.sections))

// The introduction takes inline emphasis only — the backend flattens anything
// else (BlogMarkdown::introHtml), so the toolbar doesn't offer it.
const introToolbar = [
  { kind: 'mark' as const, mark: 'bold' as const, icon: 'i-lucide-bold', label: 'Bold' },
  { kind: 'mark' as const, mark: 'italic' as const, icon: 'i-lucide-italic', label: 'Italic' },
]
const splitTags = (s: string) => s.split(',').map(t => t.trim()).filter(Boolean)
const nullable = (s: string) => s.trim() || null

// Auto-slug follows the title until the founder edits the slug by hand, and
// only while the post is unpublished — a live URL never changes on its own.
watch(() => form.title, (t) => {
  if (autoSlug.value && (isNew.value || post.value?.status === 'draft')) form.slug = slugify(t)
})
const slugChangedOnPublished = computed(() => post.value?.status === 'published' && form.slug !== post.value.slug)

useHead(() => ({ title: isNew.value ? 'New post — Blog' : form.title ? `${form.title} — Blog` : 'Edit post — Blog' }))

function hydrate(p: BlogPostAdmin) {
  post.value = p
  Object.assign(form, {
    title: p.title,
    slug: p.slug,
    excerpt: p.excerpt ?? '',
    sections: p.sections.map(s => ({ ...s })),
    cover_image_url: p.cover_image_url ?? '',
    cover_image_alt: p.cover_image_alt ?? '',
    category: p.category ?? '',
    format: p.format ?? 'article',
    tags: p.tags.join(', '),
    cta_heading: p.cta_heading ?? '',
    cta_body: p.cta_body ?? '',
    cta_label: p.cta_label ?? '',
    cta_url: p.cta_url ?? '',
    seo_title: p.seo_title ?? '',
    seo_description: p.seo_description ?? '',
  })
  autoSlug.value = false
  savedSnapshot = JSON.stringify(form)
}

async function fetchPost() {
  if (isNew.value) return
  loading.value = true
  loadError.value = ''
  try {
    const res = await apiFetch<{ data: BlogPostAdmin }>(`/api/v1/admin/blog/posts/${route.params.id}`)
    hydrate(res.data)
  }
  catch (e: unknown) {
    const status = (e as { status?: number } | null)?.status
    loadError.value = status === 404 ? 'Post not found.' : 'Failed to load the post. Check your session.'
  }
  finally {
    loading.value = false
  }
}

// Category suggestions — whatever is already in use across posts.
const categories = ref<string[]>([])
async function fetchCategories() {
  try {
    const res = await apiFetch<{ data: BlogPostAdmin[] }>('/api/v1/admin/blog/posts?page=1')
    categories.value = [...new Set(res.data.map(p => p.category).filter((c): c is string => !!c))]
  }
  catch { /* the datalist just stays empty */ }
}
// The writing guide + CTA defaults — one copy on the backend (config/blog.php),
// also what Claude writes to via the MCP connector. If it fails the editor still
// works; the Voice panel and CTA placeholders just stay empty.
const guide = ref<BlogGuide | null>(null)
async function fetchGuide() {
  try {
    guide.value = (await apiFetch<{ data: BlogGuide }>('/api/v1/admin/blog/guide')).data
  }
  catch { /* optional — see above */ }
}
const ctaDefaults = computed(() => guide.value?.cta_defaults ?? null)

onMounted(() => {
  fetchPost()
  fetchCategories()
  fetchGuide()
})
watch(() => route.params.id, () => { if (!isNew.value) fetchPost() })

function payload() {
  return {
    title: form.title.trim(),
    slug: nullable(form.slug),
    excerpt: form.excerpt.trim(),
    sections: form.sections,
    cover_image_url: nullable(form.cover_image_url),
    cover_image_alt: nullable(form.cover_image_alt),
    category: nullable(form.category),
    format: form.format,
    tags: splitTags(form.tags),
    cta_heading: nullable(form.cta_heading),
    cta_body: nullable(form.cta_body),
    cta_label: nullable(form.cta_label),
    cta_url: nullable(form.cta_url),
    seo_title: nullable(form.seo_title),
    seo_description: nullable(form.seo_description),
  }
}

async function save(): Promise<boolean> {
  if (saving.value) return false
  if (!form.title.trim()) {
    toast.error('Title required', 'Give the post a title.')
    return false
  }
  saving.value = true
  errors.value = {}
  try {
    if (isNew.value) {
      const res = await apiFetch<{ data: BlogPostAdmin }>('/api/v1/admin/blog/posts', { method: 'POST', body: payload() })
      hydrate(res.data)
      toast.success('Draft created')
      await navigateTo(`/admin/blog/${res.data.id}`, { replace: true })
    }
    else {
      const res = await apiFetch<{ data: BlogPostAdmin }>(`/api/v1/admin/blog/posts/${post.value!.id}`, { method: 'PUT', body: payload() })
      hydrate(res.data)
      toast.success(res.data.status === 'published' ? 'Changes are live' : 'Draft saved')
    }
    return true
  }
  catch (e) {
    errors.value = errFields(e)
    toast.error('Couldn’t save', errMessage(e) ?? 'Please try again.')
    return false
  }
  finally {
    saving.value = false
  }
}

async function publish() {
  if (acting.value) return
  if (dirty.value || isNew.value) {
    const ok = await save()
    if (!ok) return
  }
  acting.value = true
  try {
    const res = await apiFetch<{ data: BlogPostAdmin }>(`/api/v1/admin/blog/posts/${post.value!.id}/publish`, { method: 'POST' })
    hydrate(res.data)
    toast.success('Published', `Live at /blog/${res.data.slug} within a few minutes.`)
  }
  catch (e) {
    errors.value = errFields(e)
    toast.error('Not ready to publish', errorList.value[0] ?? errMessage(e) ?? 'Please try again.')
  }
  finally {
    acting.value = false
  }
}

async function unpublish() {
  if (acting.value || !post.value) return
  if (!confirm('Unpublish this post? It disappears from the site until you publish it again.')) return
  acting.value = true
  try {
    const res = await apiFetch<{ data: BlogPostAdmin }>(`/api/v1/admin/blog/posts/${post.value.id}/unpublish`, { method: 'POST' })
    hydrate(res.data)
    toast.success('Unpublished', 'The post is back to a draft.')
  }
  catch (e) {
    toast.error('Couldn’t unpublish', errMessage(e) ?? 'Please try again.')
  }
  finally {
    acting.value = false
  }
}

// ── Sections ───────────────────────────────────────────────────────────────
function swap(i: number, j: number) {
  if (j < 0 || j >= form.sections.length) return
  const a = form.sections[i]!
  const b = form.sections[j]!
  form.sections.splice(i, 1, b)
  form.sections.splice(j, 1, a)
}
function addSection() {
  form.sections.push(newSection())
}
function removeSection(i: number) {
  const s = form.sections[i]
  if ((s?.heading || s?.body_md) && !confirm(`Remove section "${s?.heading || i + 1}"?`)) return
  form.sections.splice(i, 1)
}
function applyTemplate() {
  if (form.sections.length && !confirm('Replace the current sections with the template?')) return
  form.sections = templateSections()
  const d = ctaDefaults.value
  if (d) {
    if (!form.cta_heading) form.cta_heading = d.heading
    if (!form.cta_body) form.cta_body = d.body
    if (!form.cta_label) form.cta_label = d.label
    if (!form.cta_url) form.cta_url = d.url
  }
  toast.success('Template applied', 'Rename the headings to fit this post.')
}

// ── Import Markdown ────────────────────────────────────────────────────────
const importOpen = ref(false)
const importText = ref('')
function importMarkdown() {
  const parsed = parseMarkdownImport(importText.value)
  if (!parsed.sections.length && !parsed.title && !parsed.excerpt) {
    toast.error('Nothing to import', 'Paste Markdown with a ## heading for each section.')
    return
  }
  if (parsed.title && !form.title.trim()) form.title = parsed.title
  if (parsed.excerpt && !form.excerpt.trim()) form.excerpt = parsed.excerpt
  form.sections.push(...parsed.sections)
  importOpen.value = false
  importText.value = ''
  toast.success(`Imported ${parsed.sections.length} section${parsed.sections.length === 1 ? '' : 's'}`)
}

const guideOpen = ref(false)

// ── Preview — rendered by the backend so it matches the public page exactly. ─
const previewOpen = ref(false)
const previewPost = ref<BlogPostPublic | null>(null)
const previewing = ref(false)
async function openPreview() {
  if (previewing.value) return
  previewing.value = true
  try {
    const res = await apiFetch<{ excerpt_html: string, sections: BlogRenderedSection[], toc: BlogTocItem[], reading_minutes: number }>('/api/v1/admin/blog/render', {
      method: 'POST',
      body: { excerpt: form.excerpt, sections: form.sections.map(s => ({ ...s, heading: s.heading || 'Untitled section' })) },
    })
    previewPost.value = {
      slug: form.slug || 'preview',
      title: form.title || 'Untitled post',
      excerpt: form.excerpt,
      excerpt_html: res.excerpt_html,
      cover_image_url: nullable(form.cover_image_url),
      cover_image_alt: nullable(form.cover_image_alt),
      category: nullable(form.category),
      format: form.format,
      tags: splitTags(form.tags),
      reading_minutes: res.reading_minutes,
      published_at: post.value?.published_at ?? new Date().toISOString(),
      sections: res.sections,
      toc: res.toc,
      // Empty fields show the shared defaults, as the public API serves them.
      cta_heading: nullable(form.cta_heading) ?? ctaDefaults.value?.heading ?? null,
      cta_body: nullable(form.cta_body) ?? ctaDefaults.value?.body ?? null,
      cta_label: nullable(form.cta_label) ?? ctaDefaults.value?.label ?? null,
      cta_url: nullable(form.cta_url) ?? ctaDefaults.value?.url ?? null,
      seo_title: nullable(form.seo_title),
      seo_description: nullable(form.seo_description),
      updated_at: null,
      related: [],
    }
    previewOpen.value = true
  }
  catch (e) {
    toast.error('Couldn’t build the preview', errMessage(e) ?? 'Please try again.')
  }
  finally {
    previewing.value = false
  }
}
onKeyStroke('Escape', () => {
  if (previewOpen.value) previewOpen.value = false
  else if (importOpen.value) importOpen.value = false
})

// ── Unsaved-changes guard ──────────────────────────────────────────────────
onBeforeRouteLeave(() => !dirty.value || confirm('You have unsaved changes. Leave anyway?'))
function beforeUnload(e: BeforeUnloadEvent) {
  if (dirty.value) e.preventDefault()
}
onMounted(() => window.addEventListener('beforeunload', beforeUnload))
onUnmounted(() => window.removeEventListener('beforeunload', beforeUnload))

const statusStyle = computed(() => post.value?.status === 'published'
  ? { background: 'var(--status-succeeded-bg)', color: 'var(--status-succeeded-fg)' }
  : { background: 'var(--status-draft-bg)', color: 'var(--status-draft-fg)' })
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <NuxtLink to="/admin/blog" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70" style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All posts
    </NuxtLink>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
    <div v-else-if="loadError" class="text-center py-16" style="color: var(--color-danger);">{{ loadError }}</div>

    <template v-else>
      <!-- Header + actions -->
      <div class="flex items-start justify-between flex-wrap gap-4 mb-6">
        <div class="flex items-center gap-3 flex-wrap">
          <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">{{ isNew ? 'New post' : 'Edit post' }}</h1>
          <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full" :style="statusStyle">{{ post?.status ?? 'draft' }}</span>
          <span v-if="dirty" class="text-[11px]" :style="{ color: 'var(--color-warning)' }">Unsaved changes</span>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :disabled="previewing" @click="openPreview">
            <UIcon name="i-lucide-eye" class="size-4" /> {{ previewing ? 'Rendering…' : 'Preview' }}
          </button>
          <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :disabled="saving || acting" @click="save">
            <UIcon name="i-lucide-save" class="size-4" /> {{ saving ? 'Saving…' : post?.status === 'published' ? 'Save changes' : 'Save draft' }}
          </button>
          <button v-if="post?.status === 'published'" type="button" class="btn-pill btn-pill-warning text-[13px]" :disabled="saving || acting" @click="unpublish">
            <UIcon name="i-lucide-eye-off" class="size-4" /> Unpublish
          </button>
          <button v-else type="button" class="btn-pill btn-pill-accent text-[13px]" :disabled="saving || acting" @click="publish">
            <UIcon name="i-lucide-send" class="size-4" /> {{ acting ? 'Publishing…' : 'Publish' }}
          </button>
        </div>
      </div>

      <ul v-if="errorList.length" class="rounded-xl border px-4 py-3 mb-6 space-y-1 text-[13px]" :style="{ borderColor: 'var(--color-danger)', color: 'var(--color-danger)', background: 'var(--color-danger-soft)' }">
        <li v-for="(msg, i) in errorList" :key="i">{{ msg }}</li>
      </ul>

      <div class="grid lg:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
        <!-- Main column -->
        <div class="space-y-5 min-w-0">
          <div class="rounded-2xl border p-5 space-y-4" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <div>
              <label class="text-[11px] font-medium uppercase tracking-wider block mb-1.5" :style="{ color: 'var(--color-text-tertiary)' }">Title</label>
              <input v-model="form.title" type="text" maxlength="160" placeholder="Still Managing Enquiries Through WhatsApp?" class="contact-input w-full text-[20px] font-semibold">
            </div>
            <div>
              <div class="flex items-baseline justify-between mb-1.5">
                <label class="text-[11px] font-medium uppercase tracking-wider" :style="{ color: 'var(--color-text-tertiary)' }">Introduction</label>
                <!-- Counts the raw Markdown (markers included) — the same 500 the API enforces. -->
                <span class="text-[11px] tabular-nums" :style="{ color: form.excerpt.length > 500 ? 'var(--color-danger)' : 'var(--color-text-tertiary)' }">{{ form.excerpt.length }}/500 · ~{{ liveMinutes }} min read</span>
              </div>
              <!-- Markdown-backed like the sections, but bold / italic only. -->
              <UEditor
                v-slot="{ editor }"
                v-model="form.excerpt"
                content-type="markdown"
                placeholder="The opening hook — two or three sentences that set up the question."
                class="blog-editor is-compact rounded-xl border overflow-hidden"
                :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
              >
                <UEditorToolbar
                  :editor="editor"
                  :items="introToolbar"
                  layout="fixed"
                  class="border-b px-2 py-1"
                  :style="{ borderColor: 'var(--color-border)' }"
                />
              </UEditor>
            </div>
          </div>

          <!-- Helpers -->
          <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn-table-action" @click="applyTemplate"><UIcon name="i-lucide-layout-template" class="size-3.5" />Start from template</button>
            <button type="button" class="btn-table-action" @click="importOpen = true"><UIcon name="i-lucide-clipboard-paste" class="size-3.5" />Import Markdown</button>
            <button type="button" class="btn-table-action" @click="guideOpen = !guideOpen"><UIcon name="i-lucide-book-open" class="size-3.5" />Voice & structure</button>
          </div>
          <div v-if="guideOpen" class="rounded-2xl border p-5 grid sm:grid-cols-2 gap-5 text-[13px]" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }">
            <div>
              <p class="text-[11px] font-semibold uppercase tracking-widest mb-2" :style="{ color: 'var(--color-text-tertiary)' }">Voice</p>
              <ul class="space-y-1.5 list-disc pl-4"><li v-for="v in guide?.voice ?? []" :key="v">{{ v }}</li></ul>
            </div>
            <div>
              <p class="text-[11px] font-semibold uppercase tracking-widest mb-2" :style="{ color: 'var(--color-text-tertiary)' }">Default structure</p>
              <ol class="space-y-1.5 list-decimal pl-4"><li v-for="s in guide?.structure ?? []" :key="s">{{ s }}</li></ol>
            </div>
          </div>

          <!-- Sections -->
          <AdminBlogSectionEditor
            v-for="(s, i) in form.sections" :key="s.id"
            :model-value="s" :index="i" :count="form.sections.length"
            @update:model-value="v => form.sections.splice(i, 1, v)"
            @move-up="swap(i, i - 1)" @move-down="swap(i, i + 1)" @remove="removeSection(i)"
          />
          <button type="button" class="btn-pill btn-pill-ghost text-[13px] w-full justify-center" @click="addSection">
            <UIcon name="i-lucide-plus" class="size-4" /> Add section
          </button>
        </div>

        <!-- Side rail -->
        <div class="lg:sticky lg:top-20 space-y-4">
          <div class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <h2 class="text-[11px] font-semibold uppercase tracking-widest mb-3" :style="{ color: 'var(--color-text-tertiary)' }">Cover image</h2>
            <input v-model="form.cover_image_url" type="text" placeholder="https://… or /path" class="contact-input w-full mb-2">
            <input v-model="form.cover_image_alt" type="text" maxlength="160" placeholder="Image description (alt text)" class="contact-input w-full">
            <img v-if="form.cover_image_url" :src="form.cover_image_url" :alt="form.cover_image_alt || 'Cover preview'" class="mt-3 w-full aspect-[16/9] object-cover rounded-xl border" :style="{ borderColor: 'var(--color-border)' }">
            <p class="mt-2 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Paste an image URL — there’s no upload. It’s also the link preview when the post is shared.</p>
          </div>

          <div class="rounded-2xl border p-5 space-y-3" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <h2 class="text-[11px] font-semibold uppercase tracking-widest" :style="{ color: 'var(--color-text-tertiary)' }">Format, category & topics</h2>
            <div>
              <span class="text-[11px] block mb-1" :style="{ color: 'var(--color-text-tertiary)' }">Format — the label before the date</span>
              <AdminSelect v-model="form.format" :items="blogFormatOptions" class="w-full" />
            </div>
            <input v-model="form.category" list="blog-categories" type="text" maxlength="60" placeholder="Category (e.g. Systems) — filters the index" class="contact-input w-full">
            <datalist id="blog-categories"><option v-for="c in categories" :key="c" :value="c" /></datalist>
            <input v-model="form.tags" type="text" placeholder="Topics, comma-separated (e.g. Websites, Cloudflare)" class="contact-input w-full">
            <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">On /blog the pills filter by format and the dropdown by topic — the category counts as a topic too.</p>
          </div>

          <div class="rounded-2xl border p-5 space-y-3" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <h2 class="text-[11px] font-semibold uppercase tracking-widest" :style="{ color: 'var(--color-text-tertiary)' }">Closing call to action</h2>
            <input v-model="form.cta_heading" type="text" maxlength="120" :placeholder="ctaDefaults?.heading" class="contact-input w-full">
            <textarea v-model="form.cta_body" rows="2" maxlength="500" :placeholder="ctaDefaults?.body" class="contact-input w-full" />
            <div class="grid grid-cols-[1fr_1.4fr] gap-2">
              <input v-model="form.cta_label" type="text" maxlength="60" :placeholder="ctaDefaults?.label" class="contact-input w-full">
              <input v-model="form.cta_url" type="text" :placeholder="ctaDefaults?.url" class="contact-input w-full">
            </div>
            <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Leave blank to use the defaults shown.</p>
          </div>

          <div class="rounded-2xl border p-5 space-y-3" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <h2 class="text-[11px] font-semibold uppercase tracking-widest" :style="{ color: 'var(--color-text-tertiary)' }">SEO</h2>
            <div>
              <input v-model="form.seo_title" type="text" maxlength="70" placeholder="Custom search title (falls back to the title)" class="contact-input w-full">
              <p class="text-[11px] text-right tabular-nums mt-1" :style="{ color: 'var(--color-text-tertiary)' }">{{ form.seo_title.length }}/70</p>
            </div>
            <div>
              <textarea v-model="form.seo_description" rows="2" maxlength="160" placeholder="Custom search description (falls back to the introduction)" class="contact-input w-full" />
              <p class="text-[11px] text-right tabular-nums mt-1" :style="{ color: 'var(--color-text-tertiary)' }">{{ form.seo_description.length }}/160</p>
            </div>
          </div>

          <div class="rounded-2xl border p-5" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <h2 class="text-[11px] font-semibold uppercase tracking-widest mb-3" :style="{ color: 'var(--color-text-tertiary)' }">URL slug</h2>
            <div class="flex items-center gap-2">
              <span class="text-[12px] shrink-0" :style="{ color: 'var(--color-text-tertiary)' }">/blog/</span>
              <input v-model="form.slug" type="text" maxlength="120" class="contact-input w-full font-mono text-[12px]" @input="autoSlug = false">
            </div>
            <label class="flex items-center gap-2 mt-3 text-[12px] cursor-pointer select-none" :style="{ color: 'var(--color-text-secondary)' }">
              <input v-model="autoSlug" type="checkbox" class="size-3.5" style="accent-color: var(--color-accent);" :disabled="post?.status === 'published'">
              Generate from the title
            </label>
            <p v-if="slugChangedOnPublished" class="mt-2 text-[11px]" :style="{ color: 'var(--color-warning)' }">
              Changing the slug of a published post breaks the old link. Anyone who saved it will get a 404.
            </p>
          </div>
        </div>
      </div>
    </template>

    <!-- Import Markdown -->
    <Teleport to="body">
      <Transition name="confirm-fade">
        <div v-if="importOpen" class="confirm-overlay" @click.self="importOpen = false">
          <div class="confirm-card w-full max-w-2xl" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
            <h2 class="text-[17px] font-bold tracking-tight mb-1" style="color: var(--color-text);">Import Markdown</h2>
            <p class="text-[13px] mb-4" style="color: var(--color-text-secondary);">Paste a draft. <code>#</code> becomes the title, the paragraphs before the first <code>##</code> become the introduction, and each <code>##</code> becomes a section. Sections are added after any you already have.</p>
            <textarea v-model="importText" rows="14" class="contact-input w-full font-mono text-[12px]" placeholder="# Title&#10;&#10;Opening paragraph…&#10;&#10;## First section&#10;…" />
            <div class="flex items-center justify-end gap-2 mt-4">
              <button type="button" class="btn-pill btn-pill-ghost text-[13px]" @click="importOpen = false">Cancel</button>
              <button type="button" class="btn-pill btn-pill-accent text-[13px]" :disabled="!importText.trim()" @click="importMarkdown">Import</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Preview -->
    <Teleport to="body">
      <Transition name="confirm-fade">
        <!-- data-lenis-prevent: Lenis owns wheel/touch scrolling for the window, so a
             fixed overlay's own scroll area stays frozen without it (same as the admin
             layout's scroll panes). -->
        <div v-if="previewOpen && previewPost" data-lenis-prevent class="fixed inset-0 z-[80] overflow-y-auto overscroll-contain" :style="{ background: 'var(--color-bg)' }">
          <div class="sticky top-0 z-10 flex items-center justify-between px-4 sm:px-6 py-3 border-b" :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
            <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">Preview <span class="font-normal" :style="{ color: 'var(--color-text-tertiary)' }">· exactly what readers will see</span></p>
            <button type="button" class="btn-pill btn-pill-ghost text-[13px]" @click="previewOpen = false"><UIcon name="i-lucide-x" class="size-4" /> Close</button>
          </div>
          <div class="max-w-6xl mx-auto px-6 py-12">
            <PublicBlogArticle :post="previewPost" preview />
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
