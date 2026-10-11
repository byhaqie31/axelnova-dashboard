<script setup lang="ts">
import { formatMyr } from '~/utils/money'
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

interface Pkg {
  id: number
  service_category_id: number
  slug: string
  name: string
  tagline: string
  price_min_myr: string
  price_max_myr: string | null
  duration_text: string
  featured: boolean
  active: boolean
  sort_order: number
}

interface Category {
  id: number
  slug: string
  name: string
  icon: string
  description: string
  sort_order: number
  active: boolean
  is_default: boolean
  packages: Pkg[]
}

const categories = ref<Category[]>([])
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Category[] }>('/api/v1/admin/service-categories')
    categories.value = res.data
  }
  catch {
    error.value = 'Failed to load categories.'
  }
  finally {
    loading.value = false
  }
}

async function deleteCategory(c: Category) {
  if (!confirm(`Delete category "${c.name}" and all ${c.packages.length} package(s)?`)) return
  try {
    await apiFetch(`/api/v1/admin/service-categories/${c.id}`, { method: 'DELETE' })
    await load()
    toast.success('Category deleted', `“${c.name}” was removed.`)
  }
  catch {
    toast.error('Couldn’t delete category', `Failed to delete “${c.name}”.`)
  }
}

async function deletePackage(p: Pkg) {
  if (!confirm(`Delete package "${p.name}"?`)) return
  try {
    await apiFetch(`/api/v1/admin/service-packages/${p.id}`, { method: 'DELETE' })
    await load()
    toast.success('Package deleted', `“${p.name}” was removed.`)
  }
  catch {
    toast.error('Couldn’t delete package', `Failed to delete “${p.name}”.`)
  }
}

onMounted(load)

const totalPackages = computed(() => categories.value.reduce((s, c) => s + c.packages.length, 0))
const featuredPackages = computed(() => categories.value.reduce((s, c) => s + c.packages.filter(p => p.featured).length, 0))

function fmtPrice(min: string | number, max: string | number | null) {
  if (max === null) return `${formatMyr(min)}+`
  return `${formatMyr(min)} – ${formatMyr(max)}`
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-6 md:pt-10 pb-24 md:pb-32">
    <div class="flex items-start justify-between mb-6 md:mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[24px] md:text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Services</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
          {{ categories.length }} categories · {{ totalPackages }} packages · {{ featuredPackages }} featured
        </p>
      </div>
      <div class="flex items-center gap-2 max-md:w-full">
        <NuxtLink to="/admin/services/addons" class="btn-pill btn-pill-ghost text-[12px] inline-flex items-center gap-1.5 max-md:flex-1">
          <UIcon name="i-lucide-puzzle" class="size-3.5" />
          Add-ons
        </NuxtLink>
        <NuxtLink to="/admin/services/categories/new" class="btn-pill btn-pill-accent text-[12px] inline-flex items-center gap-1.5 max-md:flex-1">
          <UIcon name="i-lucide-plus" class="size-3.5" />
          New category
        </NuxtLink>
      </div>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>

    <div
v-else-if="!categories.length" class="rounded-2xl border p-12 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No service categories yet</p>
      <p class="text-[12px] mb-4" :style="{ color: 'var(--color-text-secondary)' }">Add the first category to start building your service catalogue.</p>
      <NuxtLink to="/admin/services/categories/new" class="btn-pill btn-pill-accent text-[12px]">
        + New category
      </NuxtLink>
    </div>

    <div v-else class="space-y-4 md:space-y-6">
      <section
v-for="cat in categories" :key="cat.id"
        class="rounded-2xl border overflow-hidden"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <header
class="flex flex-wrap items-center gap-3 px-4 md:px-5 py-4 border-b"
          :style="{ borderColor: 'var(--color-border)', background: 'var(--color-accent-soft)' }">
          <div
class="size-9 rounded-xl inline-flex items-center justify-center shrink-0"
            :style="{ background: 'var(--color-bg)', color: 'var(--color-accent)' }">
            <UIcon :name="cat.icon" class="size-4" />
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              <p class="text-[14px] font-semibold tracking-tight" :style="{ color: 'var(--color-text)' }">{{ cat.name }}</p>
              <span
v-if="cat.is_default" class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded inline-flex items-center gap-1"
                :style="{ color: 'var(--color-accent)', background: 'var(--color-bg)' }">
                <UIcon name="i-lucide-star" class="size-3" />
                Default tab
              </span>
              <span
v-if="!cat.active" class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
                :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg)' }">Inactive</span>
            </div>
            <p class="text-[12px] truncate max-md:whitespace-normal max-md:line-clamp-2" :style="{ color: 'var(--color-text-secondary)' }">{{ cat.description }}</p>
          </div>
          <div class="flex items-center gap-2 w-full sm:w-auto sm:shrink-0 justify-end flex-wrap">
            <NuxtLink
:to="`/admin/services/packages/new?category=${cat.id}`"
              class="text-[11px] font-medium px-2.5 py-1 max-md:px-3.5 max-md:py-2 rounded-md border transition-colors hover:bg-(--color-bg-secondary) active:bg-(--color-bg-secondary)"
              :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }">
              + Package
            </NuxtLink>
            <NuxtLink
:to="`/admin/services/categories/${cat.id}`"
              class="text-[11px] font-medium px-2.5 py-1 max-md:px-3.5 max-md:py-2 rounded-md border transition-colors hover:bg-(--color-bg-secondary) active:bg-(--color-bg-secondary)"
              :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }">
              Edit
            </NuxtLink>
            <button
class="text-[11px] font-medium px-2.5 py-1 max-md:px-3.5 max-md:py-2 rounded-md border transition-colors hover:bg-(--color-bg-secondary) active:bg-(--color-bg-secondary)"
              :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-danger)' }"
              @click="deleteCategory(cat)">
              Delete
            </button>
          </div>
        </header>

        <ul v-if="cat.packages.length">
          <li
v-for="pkg in cat.packages" :key="pkg.id"
            class="px-4 md:px-5 py-3.5 border-b last:border-b-0"
            :style="{ borderColor: 'var(--color-border)' }">
            <!-- Desktop: row layout -->
            <div class="hidden md:flex items-center gap-4">
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                  <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">{{ pkg.name }}</p>
                  <span
v-if="pkg.featured" class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
                    :style="{ color: 'var(--color-accent)', background: 'var(--color-accent-soft)' }">Featured</span>
                  <span
v-if="!pkg.active" class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
                    :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg-secondary)' }">Inactive</span>
                </div>
                <p class="text-[12px] mt-0.5 truncate" :style="{ color: 'var(--color-text-secondary)' }">{{ pkg.tagline }}</p>
              </div>
              <div class="text-right shrink-0">
                <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">{{ fmtPrice(pkg.price_min_myr, pkg.price_max_myr) }}</p>
                <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ pkg.duration_text }}</p>
              </div>
              <div class="flex items-center gap-1 shrink-0">
                <NuxtLink
:to="`/admin/services/packages/${pkg.id}`"
                  class="text-[11px] font-medium px-2.5 py-1 max-md:px-3.5 max-md:py-2 rounded-md border transition-colors hover:bg-(--color-bg-secondary) active:bg-(--color-bg-secondary)"
                  :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }">
                  Edit
                </NuxtLink>
                <button
class="text-[11px] font-medium px-2.5 py-1 max-md:px-3.5 max-md:py-2 rounded-md border transition-colors hover:bg-(--color-bg-secondary) active:bg-(--color-bg-secondary)"
                  :style="{ borderColor: 'var(--color-border)', color: 'var(--color-danger)' }"
                  @click="deletePackage(pkg)">
                  Delete
                </button>
              </div>
            </div>

            <!-- Mobile: stacked card -->
            <div class="md:hidden space-y-2">
              <div class="flex items-center gap-2 flex-wrap">
                <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">{{ pkg.name }}</p>
                <span
v-if="pkg.featured" class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
                  :style="{ color: 'var(--color-accent)', background: 'var(--color-accent-soft)' }">Featured</span>
                <span
v-if="!pkg.active" class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
                  :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg-secondary)' }">Inactive</span>
              </div>
              <p class="text-[12px] break-words" :style="{ color: 'var(--color-text-secondary)' }">{{ pkg.tagline }}</p>
              <div class="flex items-center justify-between gap-3 pt-1">
                <div>
                  <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">{{ fmtPrice(pkg.price_min_myr, pkg.price_max_myr) }}</p>
                  <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ pkg.duration_text }}</p>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                  <NuxtLink
:to="`/admin/services/packages/${pkg.id}`"
                    class="text-[11px] font-medium px-2.5 py-1 max-md:px-3.5 max-md:py-2 rounded-md border transition-colors hover:bg-(--color-bg-secondary) active:bg-(--color-bg-secondary)"
                    :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }">
                    Edit
                  </NuxtLink>
                  <button
class="text-[11px] font-medium px-2.5 py-1 max-md:px-3.5 max-md:py-2 rounded-md border transition-colors hover:bg-(--color-bg-secondary) active:bg-(--color-bg-secondary)"
                    :style="{ borderColor: 'var(--color-border)', color: 'var(--color-danger)' }"
                    @click="deletePackage(pkg)">
                    Delete
                  </button>
                </div>
              </div>
            </div>
          </li>
        </ul>
        <p v-else class="px-5 py-6 text-center text-[12px]" :style="{ color: 'var(--color-text-tertiary)' }">
          No packages yet. <NuxtLink :to="`/admin/services/packages/new?category=${cat.id}`" class="underline" :style="{ color: 'var(--color-accent)' }">Add the first one</NuxtLink>.
        </p>
      </section>
    </div>
  </div>
</template>
