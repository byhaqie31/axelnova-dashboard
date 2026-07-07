<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

const isNew = computed(() => route.params.id === 'new')

const form = reactive({
  addon_key: '',
  label: '',
  amount_myr: 0,
  sort_order: 0,
  active: true,
})

const loading = ref(!isNew.value)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const message = ref('')

interface Sibling { id: number, label: string, sort_order: number }
const siblings = ref<Sibling[]>([])

const occupiedSortOrders = computed(() => {
  const positions = new Set<number>(siblings.value.map(s => s.sort_order))
  return Array.from(positions).sort((a, b) => a - b)
})

// Auto-append target — excludes self when editing so "move to end" doesn't over-shoot.
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

// Keep the key URL-safe + stable while typing a new add-on.
watch(() => form.addon_key, (v) => {
  const cleaned = v.toLowerCase().replace(/[^a-z0-9_]/g, '_').replace(/_+/g, '_')
  if (cleaned !== v) form.addon_key = cleaned
})

async function loadSiblings() {
  try {
    const res = await apiFetch<{ data: Sibling[] }>('/api/v1/admin/service-addons')
    siblings.value = res.data
    if (isNew.value) form.sort_order = nextAvailableSort.value
  }
  catch {
    siblings.value = []
  }
}

async function fetchAddon() {
  if (isNew.value) return
  loading.value = true
  try {
    const res = await apiFetch<{ data: typeof form }>(`/api/v1/admin/service-addons/${route.params.id}`)
    Object.assign(form, { ...res.data, amount_myr: Number(res.data.amount_myr) })
  }
  catch {
    message.value = 'Failed to load add-on.'
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
      await apiFetch('/api/v1/admin/service-addons', { method: 'POST', body: form })
    }
    else {
      await apiFetch(`/api/v1/admin/service-addons/${route.params.id}`, { method: 'PUT', body: form })
    }
    toast.success(isNew.value ? 'Add-on created' : 'Add-on saved', `“${form.label}” is up to date.`)
    await navigateTo('/admin/services/addons')
  }
  catch (e: any) {
    if (e?.data?.errors) errors.value = e.data.errors
    message.value = e?.data?.message ?? 'Failed to save.'
    toast.error('Couldn’t save add-on', message.value)
  }
  finally {
    saving.value = false
  }
}

onMounted(async () => {
  await fetchAddon()
  await loadSiblings()
})
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <NuxtLink
to="/admin/services/addons" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All add-ons
    </NuxtLink>

    <div class="mb-6">
      <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">
        {{ isNew ? 'New add-on' : 'Edit add-on' }}
      </h1>
    </div>

    <p v-if="message" class="mb-4 text-[13px]" :style="{ color: 'var(--color-danger)' }">{{ message }}</p>

    <form
v-if="!loading" class="rounded-2xl border p-6 space-y-5"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
      @submit.prevent="save">

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Key *</label>
          <input
v-model="form.addon_key" type="text" required placeholder="e.g. seo"
            class="contact-input w-full font-mono text-[12px]"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" >
          <p v-if="errors.addon_key?.length" class="mt-1 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.addon_key[0] }}</p>
          <p v-else class="mt-1 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">snake_case; stored on quotes. Changing it won’t alter past quotes.</p>
        </div>
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Label *</label>
          <input
v-model="form.label" type="text" required placeholder="e.g. SEO setup"
            class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" >
          <p v-if="errors.label?.length" class="mt-1 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.label[0] }}</p>
        </div>
      </div>

      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Price (MYR) *</label>
        <input
v-model.number="form.amount_myr" type="number" min="0" step="50" required
          class="contact-input w-full"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" >
        <p v-if="errors.amount_myr?.length" class="mt-1 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.amount_myr[0] }}</p>
        <p v-else class="mt-1 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Added to the quote total when selected (shown as “+RM {{ form.amount_myr || 0 }}”).</p>
      </div>

      <div>
        <label class="text-[12px] font-medium block mb-2" :style="{ color: 'var(--color-text-secondary)' }">Sort order</label>
        <div class="flex items-center gap-1.5 flex-wrap">
          <button
type="button" :disabled="(form.sort_order ?? 0) <= 0" class="size-9 rounded-lg border flex items-center justify-center transition-opacity disabled:opacity-30"
            :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)', color: 'var(--color-text-secondary)' }"
            aria-label="Move position left"
            @click="nudgeSort(-1)">
            <UIcon name="i-lucide-chevron-left" class="size-4" />
          </button>

          <button
v-for="n in occupiedSortOrders" :key="n" type="button" class="size-9 rounded-lg border flex items-center justify-center text-[13px] font-medium tabular-nums transition-colors"
            :style="form.sort_order === n
              ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent)', color: 'var(--color-on-accent, #fff)' }
              : { borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)', color: 'var(--color-text)' }"
            :aria-label="`Set position ${n}`"
            @click="setSort(n)">
            {{ n }}
          </button>

          <button
type="button" class="size-9 rounded-lg flex items-center justify-center transition-colors"
            :style="form.sort_order === nextAvailableSort
              ? { border: '1px solid var(--color-accent)', background: 'var(--color-accent)', color: 'var(--color-on-accent, #fff)' }
              : { border: '1px dashed var(--color-border)', color: 'var(--color-text-tertiary)' }"
            aria-label="Auto-append at end"
            @click="setSort(nextAvailableSort)">
            <UIcon name="i-lucide-plus" class="size-4" />
          </button>

          <button
type="button" :disabled="(form.sort_order ?? 0) >= nextAvailableSort" class="size-9 rounded-lg border flex items-center justify-center transition-opacity disabled:opacity-30"
            :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)', color: 'var(--color-text-secondary)' }"
            aria-label="Move position right"
            @click="nudgeSort(1)">
            <UIcon name="i-lucide-chevron-right" class="size-4" />
          </button>
        </div>
        <p class="mt-2 text-[11px] leading-tight" :style="{ color: 'var(--color-text-tertiary)' }">
          Order shown in the quote builder. <code>+</code> appends at position <code>{{ nextAvailableSort }}</code>.
        </p>
      </div>

      <button
type="button" class="w-full flex items-center gap-3 rounded-lg border px-4 py-3 transition-all text-left"
        :style="form.active
          ? { borderColor: 'var(--color-success)', background: 'var(--color-bg-elevated)' }
          : { borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
        @click="form.active = !form.active">
        <span
class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors"
          :style="form.active
            ? { background: 'var(--color-success-soft)', color: 'var(--color-success)' }
            : { background: 'var(--color-bg-elevated)', color: 'var(--color-text-tertiary)' }">
          <UIcon name="i-lucide-power" class="size-4" />
        </span>
        <span class="flex-1 min-w-0">
          <span class="block text-[13px] font-medium" :style="{ color: form.active ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">Active</span>
          <span class="block text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Offered in the quote builder</span>
        </span>
        <span
class="relative inline-block rounded-full transition-colors shrink-0"
          :style="{ background: form.active ? 'var(--color-success)' : '#d1d5db', height: '1.25rem', width: '2.25rem' }">
          <span
class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all"
            :style="{ left: form.active ? '1.125rem' : '0.125rem' }"/>
        </span>
      </button>

      <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="btn-pill btn-pill-accent text-[13px]" :disabled="saving">
          {{ saving ? 'Saving…' : isNew ? 'Create add-on' : 'Save changes' }}
        </button>
        <NuxtLink to="/admin/services/addons" class="btn-pill btn-pill-ghost text-[13px]">Cancel</NuxtLink>
      </div>
    </form>

    <div v-else class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
  </div>
</template>
