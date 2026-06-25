<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

interface Addon {
  id: number
  addon_key: string
  label: string
  amount_myr: string
  sort_order: number
  active: boolean
}

const addons = ref<Addon[]>([])
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: Addon[] }>('/api/v1/admin/service-addons')
    addons.value = res.data
  }
  catch {
    error.value = 'Failed to load add-ons.'
  }
  finally {
    loading.value = false
  }
}

async function deleteAddon(a: Addon) {
  if (!confirm(`Delete add-on "${a.label}"? Existing quotes keep their stored copy.`)) return
  try {
    await apiFetch(`/api/v1/admin/service-addons/${a.id}`, { method: 'DELETE' })
    await load()
    toast.success('Add-on deleted', `“${a.label}” was removed.`)
  }
  catch {
    toast.error('Couldn’t delete add-on', `Failed to delete “${a.label}”.`)
  }
}

onMounted(load)

const activeCount = computed(() => addons.value.filter(a => a.active).length)

// Add-on prices are exact — always precise, never the "k" range shorthand.
function fmtPrice(n: string | number) {
  return `+RM ${Math.round(Number(n) || 0).toLocaleString('en-US')}`
}
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <NuxtLink to="/admin/services" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All services
    </NuxtLink>

    <div class="flex items-start justify-between mb-8 flex-wrap gap-4">
      <div>
        <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">Add-ons</h1>
        <p class="text-[14px] mt-1" style="color: var(--color-text-secondary);">
          {{ addons.length }} total · {{ activeCount }} active · shown in every quote builder
        </p>
      </div>
      <NuxtLink to="/admin/services/addons/new" class="btn-pill btn-pill-accent text-[12px] inline-flex items-center gap-1.5">
        <UIcon name="i-lucide-plus" class="size-3.5" />
        New add-on
      </NuxtLink>
    </div>

    <p v-if="error" class="mb-6 text-[13px]" style="color: var(--color-danger);">{{ error }}</p>

    <div v-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>

    <div v-else-if="!addons.length" class="rounded-2xl border p-12 text-center"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <p class="text-[14px] font-medium mb-1" :style="{ color: 'var(--color-text)' }">No add-ons yet</p>
      <p class="text-[12px] mb-4" :style="{ color: 'var(--color-text-secondary)' }">Add-ons are the optional extras (SEO, copywriting…) shown under every package.</p>
      <NuxtLink to="/admin/services/addons/new" class="btn-pill btn-pill-accent text-[12px]">+ New add-on</NuxtLink>
    </div>

    <ul v-else class="rounded-2xl border overflow-hidden"
      :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
      <li v-for="a in addons" :key="a.id"
        class="flex items-center gap-4 px-5 py-3.5 border-b last:border-b-0"
        :style="{ borderColor: 'var(--color-border)' }">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <p class="text-[13px] font-semibold" :style="{ color: 'var(--color-text)' }">{{ a.label }}</p>
            <span v-if="!a.active" class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
              :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg-secondary)' }">Inactive</span>
          </div>
          <p class="text-[11px] font-mono mt-0.5" :style="{ color: 'var(--color-text-tertiary)' }">{{ a.addon_key }}</p>
        </div>
        <p class="text-[13px] font-semibold tabular-nums shrink-0" :style="{ color: 'var(--color-text)' }">{{ fmtPrice(a.amount_myr) }}</p>
        <div class="flex items-center gap-1 shrink-0">
          <NuxtLink :to="`/admin/services/addons/${a.id}`"
            class="text-[11px] font-medium px-2.5 py-1 rounded-md border transition-colors hover:bg-(--color-bg-secondary)"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }">
            Edit
          </NuxtLink>
          <button class="text-[11px] font-medium px-2.5 py-1 rounded-md border transition-colors hover:bg-(--color-bg-secondary)"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-danger)' }"
            @click="deleteAddon(a)">
            Delete
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>
