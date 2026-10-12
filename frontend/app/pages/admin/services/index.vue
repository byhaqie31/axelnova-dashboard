<script setup lang="ts">
import { formatMyr } from '~/utils/money'
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()
const { confirmOpen, confirmConfig, confirm, resolveConfirm } = useConfirm()

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

// Listing Active switches — flip `active` alone via PATCH …/active.
// Keys of rows mid-request (`c{id}` / `p{id}`); their switch disables until it settles.
const busy = reactive(new Set<string>())

async function setActive(key: string, url: string, row: { active: boolean, name: string }, active: boolean): Promise<boolean> {
  busy.add(key)
  row.active = active // optimistic — the switch moves at once, reverts on failure
  try {
    await apiFetch(url, { method: 'PATCH', body: { active } })
    return true
  }
  catch {
    row.active = !active
    toast.error('Couldn’t change visibility', `“${row.name}” is unchanged.`)
    return false
  }
  finally {
    busy.delete(key)
  }
}

// Showing a category also publishes its active packages (a package is public only
// while it AND its category are active), so both directions confirm first.
async function toggleCategory(c: Category) {
  const key = `c${c.id}`
  if (busy.has(key)) return
  const goingLive = !c.active
  const live = c.packages.filter(p => p.active).length
  const pkgs = `${live} active package${live === 1 ? '' : 's'}`
  const ok = await confirm(goingLive
    ? {
        title: `Show “${c.name}” on the public site?`,
        message: live
          ? `It goes on the public services page with its ${pkgs}.`
          : 'It goes on the public services page, but none of its packages will — they are all inactive.',
        confirmLabel: 'Make active',
      }
    : {
        title: `Hide “${c.name}”?`,
        message: `It comes off the public services page${live ? ` along with its ${pkgs}` : ''}. Nothing is deleted — switch it back on any time.`,
        confirmLabel: 'Hide category',
        variant: 'danger',
      })
  if (!ok) return
  if (await setActive(key, `/api/v1/admin/service-categories/${c.id}/active`, c, goingLive))
    toast.success(goingLive ? 'Category is live' : 'Category hidden', `“${c.name}” is ${goingLive ? 'on' : 'off'} the public services page.`)
}

// A single package flips straight away — it's one card, and the toast says where it landed.
async function togglePackage(cat: Category, p: Pkg) {
  const key = `p${p.id}`
  if (busy.has(key)) return
  const goingLive = !p.active
  if (!(await setActive(key, `/api/v1/admin/service-packages/${p.id}/active`, p, goingLive))) return
  if (!goingLive) toast.success('Package hidden', `“${p.name}” is off the public services page.`)
  else if (cat.active) toast.success('Package is live', `“${p.name}” is on the public services page.`)
  else toast.success('Package active', `“${p.name}” goes public when “${cat.name}” does.`)
}

onMounted(load)

const totalPackages = computed(() => categories.value.reduce((s, c) => s + c.packages.length, 0))
const featuredPackages = computed(() => categories.value.reduce((s, c) => s + c.packages.filter(p => p.featured).length, 0))

// A package is public only while it AND its category are active
// (ServicePackage::isEffectivelyLive) — an active package in a hidden category is badged.
const HIDDEN_VIA_SERVICE_HINT = 'Active, but its category is hidden — it goes public when the category does.'

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
            </div>
            <p class="text-[12px] truncate max-md:whitespace-normal max-md:line-clamp-2" :style="{ color: 'var(--color-text-secondary)' }">{{ cat.description }}</p>
          </div>
          <div class="flex items-center gap-2 w-full sm:w-auto sm:shrink-0 justify-end flex-wrap">
            <AdminActiveSwitch
              :on="cat.active" :busy="busy.has(`c${cat.id}`)"
              :label="`${cat.name} on the public services page`"
              @toggle="toggleCategory(cat)" />
            <NuxtLink
:to="`/admin/services/categories/${cat.id}`" :aria-label="`Edit ${cat.name}`" :title="`Edit ${cat.name}`"
              class="size-8 max-md:size-9 rounded-md inline-flex items-center justify-center transition-colors hover:bg-(--color-bg) active:bg-(--color-bg)"
              :style="{ color: 'var(--color-text-secondary)' }">
              <UIcon name="i-lucide-pencil" class="size-4" />
            </NuxtLink>
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
v-if="pkg.active && !cat.active" :title="HIDDEN_VIA_SERVICE_HINT" class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded inline-flex items-center gap-1"
                    :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg-secondary)' }">
                    <UIcon name="i-lucide-eye-off" class="size-3" />
                    Hidden via service
                  </span>
                </div>
                <p class="text-[12px] mt-0.5 truncate" :style="{ color: 'var(--color-text-secondary)' }">{{ pkg.tagline }}</p>
              </div>
              <div class="text-right shrink-0">
                <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">{{ fmtPrice(pkg.price_min_myr, pkg.price_max_myr) }}</p>
                <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ pkg.duration_text }}</p>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <AdminActiveSwitch
                  :on="pkg.active" :busy="busy.has(`p${pkg.id}`)"
                  :label="`${pkg.name} on the public services page`"
                  @toggle="togglePackage(cat, pkg)" />
                <NuxtLink
:to="`/admin/services/packages/${pkg.id}`" :aria-label="`Edit ${pkg.name}`" :title="`Edit ${pkg.name}`"
                  class="size-8 max-md:size-9 rounded-md inline-flex items-center justify-center transition-colors hover:bg-(--color-bg-secondary) active:bg-(--color-bg-secondary)"
                  :style="{ color: 'var(--color-text-secondary)' }">
                  <UIcon name="i-lucide-pencil" class="size-4" />
                </NuxtLink>
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
v-if="pkg.active && !cat.active" :title="HIDDEN_VIA_SERVICE_HINT" class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded inline-flex items-center gap-1"
                  :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg-secondary)' }">
                  <UIcon name="i-lucide-eye-off" class="size-3" />
                  Hidden via service
                </span>
              </div>
              <p class="text-[12px] break-words" :style="{ color: 'var(--color-text-secondary)' }">{{ pkg.tagline }}</p>
              <div class="flex items-center justify-between gap-3 pt-1">
                <div>
                  <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">{{ fmtPrice(pkg.price_min_myr, pkg.price_max_myr) }}</p>
                  <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">{{ pkg.duration_text }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <AdminActiveSwitch
                    :on="pkg.active" :busy="busy.has(`p${pkg.id}`)"
                    :label="`${pkg.name} on the public services page`"
                    @toggle="togglePackage(cat, pkg)" />
                  <NuxtLink
:to="`/admin/services/packages/${pkg.id}`" :aria-label="`Edit ${pkg.name}`" :title="`Edit ${pkg.name}`"
                    class="size-8 max-md:size-9 rounded-md inline-flex items-center justify-center transition-colors hover:bg-(--color-bg-secondary) active:bg-(--color-bg-secondary)"
                    :style="{ color: 'var(--color-text-secondary)' }">
                    <UIcon name="i-lucide-pencil" class="size-4" />
                  </NuxtLink>
                </div>
              </div>
            </div>
          </li>
        </ul>
        <p v-else class="px-5 py-6 text-center text-[12px]" :style="{ color: 'var(--color-text-tertiary)' }">
          No packages yet.
        </p>
        <!-- Closing row of the list — transparent, adds a package to this category. -->
        <NuxtLink
:to="`/admin/services/packages/new?category=${cat.id}`"
          class="flex items-center gap-2 px-4 md:px-5 py-3 max-md:py-3.5 border-t text-[12px] font-medium transition-colors hover:bg-(--color-bg-secondary) active:bg-(--color-bg-secondary)"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-accent)' }">
          <UIcon name="i-lucide-plus" class="size-3.5" />
          New package
        </NuxtLink>
      </section>
    </div>

    <AdminConfirmDialog :open="confirmOpen" :config="confirmConfig" @resolve="resolveConfirm" />
  </div>
</template>
