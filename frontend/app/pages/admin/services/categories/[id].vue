<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()

const isNew = computed(() => route.params.id === 'new')

useHead(() => ({ title: isNew.value ? 'New category — Admin' : 'Edit category — Admin' }))

const form = reactive({
  slug: '',
  name: '',
  icon: 'i-lucide-package',
  description: '',
  sort_order: 0,
  active: true,
  is_default: false,
})

const loading = ref(!isNew.value)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const message = ref('')

interface SiblingCategory { id: number, name: string, sort_order: number }
const siblings = ref<SiblingCategory[]>([])

const occupiedSortOrders = computed(() => {
  const positions = new Set<number>(siblings.value.map(s => s.sort_order))
  return Array.from(positions).sort((a, b) => a - b)
})

const nextAvailableSort = computed(() => {
  const others = siblings.value
    .filter(s => isNew.value || s.id !== Number(route.params.id))
    .map(s => s.sort_order)
  return (others.length ? Math.max(...others) : -1) + 1
})

function setSort(n: number) { form.sort_order = n }
function nudgeSort(delta: number) {
  form.sort_order = Math.min(nextAvailableSort.value, Math.max(0, (form.sort_order ?? 0) + delta))
}

async function loadSiblings() {
  try {
    const res = await apiFetch<{ data: SiblingCategory[] }>('/api/v1/admin/service-categories')
    siblings.value = res.data
    if (isNew.value) form.sort_order = nextAvailableSort.value
  }
  catch {
    siblings.value = []
  }
}

async function fetchCategory() {
  if (isNew.value) return
  loading.value = true
  try {
    const res = await apiFetch<{ data: typeof form }>(`/api/v1/admin/service-categories/${route.params.id}`)
    Object.assign(form, res.data)
  }
  catch {
    message.value = 'Failed to load category.'
  }
  finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  errors.value = {}
  message.value = ''
  try {
    if (isNew.value) {
      await apiFetch('/api/v1/admin/service-categories', { method: 'POST', body: form })
    }
    else {
      await apiFetch(`/api/v1/admin/service-categories/${route.params.id}`, { method: 'PUT', body: form })
    }
    await navigateTo('/admin/services')
  }
  catch (e: any) {
    if (e?.data?.errors) errors.value = e.data.errors
    message.value = e?.data?.message ?? 'Failed to save.'
  }
  finally {
    saving.value = false
  }
}

onMounted(async () => {
  await fetchCategory()
  await loadSiblings()
})
</script>

<template>
  <div class="max-w-3xl mx-auto px-6 pt-10 pb-32">

    <NuxtLink to="/admin/services" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All services
    </NuxtLink>

    <div class="mb-6">
      <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">
        {{ isNew ? 'New category' : 'Edit category' }}
      </h1>
    </div>

    <p v-if="message" class="mb-4 text-[13px]" :style="{ color: 'var(--color-danger)' }">{{ message }}</p>

    <form v-if="!loading" class="rounded-2xl border p-6 space-y-5"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
      @submit.prevent="save">

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Slug *</label>
          <input v-model="form.slug" type="text" required class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          <p v-if="errors.slug?.length" class="mt-1 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.slug[0] }}</p>
        </div>
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Name *</label>
          <input v-model="form.name" type="text" required class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          <p v-if="errors.name?.length" class="mt-1 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.name[0] }}</p>
        </div>
      </div>

      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Icon (Iconify name) *</label>
        <div class="flex items-center gap-2">
          <input v-model="form.icon" type="text" required placeholder="i-lucide-globe"
            class="contact-input w-full font-mono text-[13px]"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          <div class="size-10 shrink-0 rounded-md border inline-flex items-center justify-center"
            :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
            <UIcon :name="form.icon" class="size-5" :style="{ color: 'var(--color-text-secondary)' }" />
          </div>
        </div>
        <p class="mt-1 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Browse names at <a href="https://lucide.dev/icons" target="_blank" class="underline">lucide.dev/icons</a> — use <code>i-lucide-&lt;name&gt;</code>.</p>
      </div>

      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Description *</label>
        <textarea v-model="form.description" required rows="3" class="contact-input w-full"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
      </div>

      <div>
        <label class="text-[12px] font-medium block mb-2" :style="{ color: 'var(--color-text-secondary)' }">Sort order</label>
        <div class="flex items-center gap-1.5 flex-wrap">
          <button type="button" :disabled="(form.sort_order ?? 0) <= 0" @click="nudgeSort(-1)"
            class="size-9 rounded-lg border flex items-center justify-center transition-opacity disabled:opacity-30"
            :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)', color: 'var(--color-text-secondary)' }"
            aria-label="Move position left">
            <UIcon name="i-lucide-chevron-left" class="size-4" />
          </button>

          <button v-for="n in occupiedSortOrders" :key="n" type="button" @click="setSort(n)"
            class="size-9 rounded-lg border flex items-center justify-center text-[13px] font-medium tabular-nums transition-colors"
            :style="form.sort_order === n
              ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent)', color: 'var(--color-on-accent, #fff)' }
              : { borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)', color: 'var(--color-text)' }"
            :aria-label="`Set position ${n}`">
            {{ n }}
          </button>

          <button type="button" @click="setSort(nextAvailableSort)"
            class="size-9 rounded-lg flex items-center justify-center transition-colors"
            :style="form.sort_order === nextAvailableSort
              ? { border: '1px solid var(--color-accent)', background: 'var(--color-accent)', color: 'var(--color-on-accent, #fff)' }
              : { border: '1px dashed var(--color-border)', color: 'var(--color-text-tertiary)' }"
            aria-label="Auto-append at end">
            <UIcon name="i-lucide-plus" class="size-4" />
          </button>

          <button type="button" :disabled="(form.sort_order ?? 0) >= nextAvailableSort" @click="nudgeSort(1)"
            class="size-9 rounded-lg border flex items-center justify-center transition-opacity disabled:opacity-30"
            :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)', color: 'var(--color-text-secondary)' }"
            aria-label="Move position right">
            <UIcon name="i-lucide-chevron-right" class="size-4" />
          </button>
        </div>
        <p class="mt-2 text-[11px] leading-tight" :style="{ color: 'var(--color-text-tertiary)' }">
          <code>+</code> auto-appends at position <code>{{ nextAvailableSort }}</code>. Click an existing number to insert there — the colliding row shifts down.
        </p>
      </div>

      <div class="space-y-2 pt-1">
        <button type="button" @click="form.active = !form.active"
          class="w-full flex items-center gap-3 rounded-lg border px-4 py-3 transition-all text-left"
          :style="form.active
            ? { borderColor: '#10b981', background: 'var(--color-bg-elevated)' }
            : { borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
          <span class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors"
            :style="form.active
              ? { background: 'rgba(16, 185, 129, 0.15)', color: '#10b981' }
              : { background: 'var(--color-bg-elevated)', color: 'var(--color-text-tertiary)' }">
            <UIcon name="i-lucide-power" class="size-4" />
          </span>
          <span class="flex-1 min-w-0">
            <span class="block text-[13px] font-medium" :style="{ color: form.active ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">Active</span>
            <span class="block text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Visible on the public services page</span>
          </span>
          <span class="relative inline-block rounded-full transition-colors shrink-0"
            :style="{
              background: form.active ? '#10b981' : '#d1d5db',
              height: '1.25rem',
              width: '2.25rem',
            }">
            <span class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all"
              :style="{ left: form.active ? '1.125rem' : '0.125rem' }"></span>
          </span>
        </button>

        <button type="button" @click="form.is_default = !form.is_default"
          class="w-full flex items-center gap-3 rounded-lg border px-4 py-3 transition-all text-left"
          :style="form.is_default
            ? { borderColor: 'var(--color-accent)', background: 'var(--color-bg-elevated)' }
            : { borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
          <span class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors"
            :style="form.is_default
              ? { background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
              : { background: 'var(--color-bg-elevated)', color: 'var(--color-text-tertiary)' }">
            <UIcon name="i-lucide-star" class="size-4" />
          </span>
          <span class="flex-1 min-w-0">
            <span class="block text-[13px] font-medium" :style="{ color: form.is_default ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">Default tab</span>
            <span class="block text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Highlighted as the default on the services page (only one allowed)</span>
          </span>
          <span class="relative inline-block rounded-full transition-colors shrink-0"
            :style="{
              background: form.is_default ? 'var(--color-accent)' : '#d1d5db',
              height: '1.25rem',
              width: '2.25rem',
            }">
            <span class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all"
              :style="{ left: form.is_default ? '1.125rem' : '0.125rem' }"></span>
          </span>
        </button>
      </div>

      <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="btn-pill btn-pill-accent text-[13px]" :disabled="saving">
          {{ saving ? 'Saving…' : isNew ? 'Create category' : 'Save changes' }}
        </button>
        <NuxtLink to="/admin/services" class="btn-pill btn-pill-ghost text-[13px]">Cancel</NuxtLink>
      </div>
    </form>

    <div v-else class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
  </div>
</template>
