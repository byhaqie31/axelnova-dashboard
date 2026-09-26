<script setup lang="ts">
// Catalog › Blog — every post (drafts + published), newest edited first, with
// status / search filters and page-view counts from the analytics table.
import { blogStatusOptions, fmtBlogDate, type BlogPostAdmin } from '~/data/blog'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

interface ListMeta { current_page: number, last_page: number, total: number }

const posts = ref<BlogPostAdmin[]>([])
const meta = ref<ListMeta | null>(null)
const loading = ref(true)
const error = ref('')
const page = ref(1)
const filters = reactive({ q: '', status: '' })

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filters.q) params.set('q', filters.q)
    if (filters.status) params.set('status', filters.status)
    params.set('page', String(page.value))
    const res = await apiFetch<{ data: BlogPostAdmin[], meta: ListMeta }>(`/api/v1/admin/blog/posts?${params}`)
    posts.value = res.data
    meta.value = res.meta
  }
  catch {
    error.value = 'Failed to load posts.'
  }
  finally {
    loading.value = false
  }
}

async function deletePost(p: BlogPostAdmin) {
  if (!confirm(`Delete "${p.title}"? It disappears from the site immediately.`)) return
  try {
    await apiFetch(`/api/v1/admin/blog/posts/${p.id}`, { method: 'DELETE' })
    toast.success('Post deleted', `“${p.title}” was removed.`)
    await load()
  }
  catch {
    toast.error('Couldn’t delete post', 'Please try again.')
  }
}

onMounted(load)

let searchTimer: ReturnType<typeof setTimeout>
watch(() => filters.q, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { page.value = 1; load() }, 400)
})
watch(() => filters.status, () => { page.value = 1; load() })
watch(page, load)

const publishedCount = computed(() => posts.value.filter(p => p.status === 'published').length)
const draftCount = computed(() => posts.value.filter(p => p.status === 'draft').length)

const statusStyle = (s: BlogPostAdmin['status']) => s === 'published'
  ? { background: 'var(--status-succeeded-bg)', color: 'var(--status-succeeded-fg)' }
  : { background: 'var(--status-draft-bg)', color: 'var(--status-draft-fg)' }
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <div class="flex items-start justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Blog</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
          {{ meta?.total ?? posts.length }} total · {{ publishedCount }} published · {{ draftCount }} drafts on this page
        </p>
      </div>
      <NuxtLink to="/admin/blog/new" class="btn-pill btn-pill-accent text-[12px] inline-flex items-center gap-1.5">
        <UIcon name="i-lucide-plus" class="size-3.5" />
        New post
      </NuxtLink>
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-6">
      <AdminExpandingSearch v-model="filters.q" placeholder="Search by title…" />
      <AdminStatusFilter v-model="filters.status" :options="blogStatusOptions" :total="meta?.total ?? posts.length" class="ml-auto" />
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>

    <div
      v-else-if="!posts.length" class="rounded-2xl border p-12 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No posts yet</p>
      <p class="text-[12px] mb-4" :style="{ color: 'var(--color-text-secondary)' }">Write the first one — paste a Markdown draft or start from the template.</p>
      <NuxtLink to="/admin/blog/new" class="btn-pill btn-pill-accent text-[12px]">+ New post</NuxtLink>
    </div>

    <div v-else class="rounded-2xl border overflow-x-auto" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <table class="w-full text-[13px] min-w-[760px]">
        <thead>
          <tr class="text-left text-[11px] uppercase tracking-wider" :style="{ color: 'var(--color-text-tertiary)' }">
            <th class="px-4 py-3 font-medium">Title</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 font-medium">Category</th>
            <th class="px-4 py-3 font-medium">Published</th>
            <th class="px-4 py-3 font-medium text-right">Read</th>
            <th class="px-4 py-3 font-medium text-right">Views</th>
            <th class="px-4 py-3" />
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in posts" :key="p.id" class="border-t" :style="{ borderColor: 'var(--color-border)' }">
            <td class="px-4 py-3 min-w-0">
              <NuxtLink :to="`/admin/blog/${p.id}`" class="font-medium block truncate max-w-[380px]" :style="{ color: 'var(--color-text)' }">{{ p.title }}</NuxtLink>
              <p class="text-[10px] font-mono truncate max-w-[380px]" :style="{ color: 'var(--color-text-tertiary)' }">/blog/{{ p.slug }}</p>
            </td>
            <td class="px-4 py-3">
              <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full" :style="statusStyle(p.status)">{{ p.status }}</span>
            </td>
            <td class="px-4 py-3" :style="{ color: 'var(--color-text-secondary)' }">{{ p.category ?? '—' }}</td>
            <td class="px-4 py-3 whitespace-nowrap" :style="{ color: 'var(--color-text-secondary)' }">{{ p.status === 'published' ? fmtBlogDate(p.published_at) : '—' }}</td>
            <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap" :style="{ color: 'var(--color-text-secondary)' }">{{ p.reading_minutes }} min</td>
            <td class="px-4 py-3 text-right tabular-nums" :style="{ color: 'var(--color-text-secondary)' }">{{ p.views ?? 0 }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-1">
                <NuxtLink :to="`/admin/blog/${p.id}`" class="btn-table-action"><UIcon name="i-lucide-pencil" class="size-3.5" />Edit</NuxtLink>
                <a v-if="p.status === 'published'" :href="`/blog/${p.slug}`" target="_blank" rel="noopener" class="btn-table-action"><UIcon name="i-lucide-external-link" class="size-3.5" />View</a>
                <button type="button" class="btn-table-action is-danger" @click="deletePost(p)"><UIcon name="i-lucide-trash-2" class="size-3.5" />Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
      <button :disabled="page <= 1" class="btn-pill btn-pill-ghost text-[12px]" @click="page--">← Prev</button>
      <span class="text-[13px]" style="color: var(--color-text-secondary);">{{ page }} / {{ meta.last_page }}</span>
      <button :disabled="page >= meta.last_page" class="btn-pill btn-pill-ghost text-[12px]" @click="page++">Next →</button>
    </div>
  </div>
</template>
