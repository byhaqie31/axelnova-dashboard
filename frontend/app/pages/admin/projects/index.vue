<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })
useHead({ title: 'Projects — Admin' })

const { apiFetch } = useAdminAuth()

interface Project {
  id: number
  slug: string
  name: string
  description: string
  status: string
  url: string | null
  repo: string | null
  tags: string[]
  stack: string[]
  featured: boolean
  active: boolean
  sort_order: number
}

const projects = ref<Project[]>([])
const loading = ref(true)
const error = ref('')

const filters = reactive({
  search: '',
  status: '',
})

const statusOptions = [
  { value: '', label: 'All' },
  { value: 'live', label: 'Live' },
  { value: 'wip', label: 'In progress' },
  { value: 'soon', label: 'Soon' },
  { value: 'planning', label: 'Planning' },
]

const statusLabels: Record<string, string> = {
  live: 'Live',
  wip: 'In progress',
  soon: 'Soon',
  planning: 'Planning',
}

const statusColors: Record<string, string> = {
  live: 'var(--color-success)',
  wip: 'var(--color-accent)',
  soon: '#A855F7',
  planning: 'var(--color-text-tertiary)',
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (filters.search) params.set('search', filters.search)
    if (filters.status) params.set('status', filters.status)
    const res = await apiFetch<{ data: Project[] }>(`/api/v1/admin/projects?${params}`)
    projects.value = res.data
  }
  catch {
    error.value = 'Failed to load projects.'
  }
  finally {
    loading.value = false
  }
}

async function deleteProject(p: Project) {
  if (!confirm(`Delete project "${p.name}"?`)) return
  try {
    await apiFetch(`/api/v1/admin/projects/${p.id}`, { method: 'DELETE' })
    await load()
  }
  catch {
    error.value = `Failed to delete "${p.name}".`
  }
}

onMounted(load)

let searchTimer: ReturnType<typeof setTimeout>
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 400)
})
watch(() => filters.status, load)

const featuredCount = computed(() => projects.value.filter(p => p.featured).length)
const liveCount = computed(() => projects.value.filter(p => p.status === 'live').length)
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <div class="flex items-start justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Projects</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
          {{ projects.length }} total · {{ featuredCount }} featured · {{ liveCount }} live
        </p>
      </div>
      <NuxtLink to="/admin/projects/new" class="btn-pill btn-pill-accent text-[12px] inline-flex items-center gap-1.5">
        <UIcon name="i-lucide-plus" class="size-3.5" />
        New project
      </NuxtLink>
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-6">
      <AdminExpandingSearch v-model="filters.search" placeholder="Search by name or slug…" />
      <AdminStatusFilter v-model="filters.status" :options="statusOptions" class="ml-auto" />
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>

    <div v-else-if="!projects.length" class="rounded-2xl border p-12 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No projects found</p>
      <p class="text-[12px] mb-4" :style="{ color: 'var(--color-text-secondary)' }">Add the first one to start filling your portfolio.</p>
      <NuxtLink to="/admin/projects/new" class="btn-pill btn-pill-accent text-[12px]">
        + New project
      </NuxtLink>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <article v-for="p in projects" :key="p.id"
        class="rounded-2xl border p-5 transition-shadow hover:shadow-md"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <div class="flex items-start justify-between gap-3 mb-2">
          <div class="min-w-0">
            <h3 class="text-[15px] font-semibold tracking-tight truncate" :style="{ color: 'var(--color-text)' }">{{ p.name }}</h3>
            <p class="text-[10px] font-mono" :style="{ color: 'var(--color-text-tertiary)' }">{{ p.slug }}</p>
          </div>
          <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full shrink-0"
            :style="{
              color: statusColors[p.status] ?? 'var(--color-text-secondary)',
              background: `${statusColors[p.status] ?? 'var(--color-text-secondary)'}20`,
            }">
            {{ statusLabels[p.status] ?? p.status }}
          </span>
        </div>
        <p class="text-[12px] line-clamp-3 mb-3" :style="{ color: 'var(--color-text-secondary)' }">{{ p.description }}</p>
        <div class="flex flex-wrap gap-1.5 mb-3">
          <span v-for="tag in p.tags.slice(0, 4)" :key="tag"
            class="text-[10px] font-medium px-2 py-0.5 rounded-full"
            :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg-secondary)' }">
            {{ tag }}
          </span>
        </div>
        <div class="flex items-center gap-2 pt-3 border-t" :style="{ borderColor: 'var(--color-border)' }">
          <span v-if="p.featured" class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
            :style="{ color: 'var(--color-accent)', background: 'var(--color-accent-soft)' }">Featured</span>
          <span v-if="!p.active" class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
            :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg-secondary)' }">Inactive</span>
          <div class="flex items-center gap-1 ml-auto">
            <NuxtLink :to="`/admin/projects/${p.id}`"
              class="text-[11px] font-medium px-2.5 py-1 rounded-md border transition-colors hover:bg-(--color-bg-secondary)"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }">
              Edit
            </NuxtLink>
            <button class="text-[11px] font-medium px-2.5 py-1 rounded-md border transition-colors hover:bg-(--color-bg-secondary)"
              :style="{ borderColor: 'var(--color-border)', color: 'var(--color-danger)' }"
              @click="deleteProject(p)">
              Delete
            </button>
          </div>
        </div>
      </article>
    </div>
  </div>
</template>
