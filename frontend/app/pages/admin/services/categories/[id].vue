<script setup lang="ts">
import { serviceIcons } from '~/data/serviceIcons'
import ScopeFieldModal from '~/components/admin/ScopeFieldModal.vue'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

const isNew = computed(() => route.params.id === 'new')


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

// ── Scope fields (this category's quote-builder inputs) ─────────────────────
interface ScopeFieldRow {
  id: number
  field_key: string
  label: string
  type: 'slider' | 'select' | 'toggle'
  applies_to: string[]
  config: Record<string, any>
  sort_order: number
  active: boolean
}
const scopeFields = ref<ScopeFieldRow[]>([])

async function loadScopeFields() {
  if (isNew.value) return
  try {
    const res = await apiFetch<{ data: ScopeFieldRow[] }>(`/api/v1/admin/service-scope-fields?service_category_id=${route.params.id}`)
    scopeFields.value = res.data
  }
  catch {
    scopeFields.value = []
  }
}

async function toggleFieldActive(f: ScopeFieldRow) {
  try {
    await apiFetch(`/api/v1/admin/service-scope-fields/${f.id}`, {
      method: 'PUT',
      body: { ...f, service_category_id: Number(route.params.id), active: !f.active },
    })
    await loadScopeFields()
  }
  catch {
    toast.error('Couldn’t update', `Failed to toggle “${f.label}”.`)
  }
}

async function deleteField(f: ScopeFieldRow) {
  if (!confirm(`Delete scope field "${f.label}"? Existing quotes keep their stored values.`)) return
  try {
    await apiFetch(`/api/v1/admin/service-scope-fields/${f.id}`, { method: 'DELETE' })
    await loadScopeFields()
    toast.success('Scope field deleted', `“${f.label}” was removed.`)
  }
  catch {
    toast.error('Couldn’t delete', `Failed to delete “${f.label}”.`)
  }
}

function fieldPriceSummary(f: ScopeFieldRow): string {
  const rm = (n: number) => `+RM ${Math.round(n).toLocaleString('en-US')}`
  if (f.type === 'slider') {
    const p = Number(f.config.price_per_unit || 0)
    return p > 0 ? `${rm(p)} / ${f.config.unit || 'unit'} over ${f.config.free_threshold ?? 0}` : 'No charge'
  }
  if (f.type === 'toggle') {
    const a = Number(f.config.amount || 0)
    return a > 0 ? rm(a) : 'No charge'
  }
  const amounts = (f.config.options || []).map((o: any) => Number(o.amount || 0))
  const max = amounts.length ? Math.max(...amounts) : 0
  return max > 0 ? `up to ${rm(max)}` : 'No charge'
}

// Editor drawer
const modalOpen = ref(false)
const editingField = ref<ScopeFieldRow | null>(null)
function openNewField() { editingField.value = null; modalOpen.value = true }
function openEditField(f: ScopeFieldRow) { editingField.value = f; modalOpen.value = true }

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
    toast.success(isNew.value ? 'Category created' : 'Category saved', `“${form.name}” is up to date.`)
    await navigateTo('/admin/services')
  }
  catch (e: any) {
    if (e?.data?.errors) errors.value = e.data.errors
    message.value = e?.data?.message ?? 'Failed to save.'
    toast.error('Couldn’t save category', message.value)
  }
  finally {
    saving.value = false
  }
}

onMounted(async () => {
  await fetchCategory()
  await loadSiblings()
  await loadScopeFields()
})
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <NuxtLink
to="/admin/services" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All services
    </NuxtLink>

    <div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
      <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">
        {{ isNew ? 'New category' : 'Edit category' }}
      </h1>
      <div v-if="!loading" class="flex items-center gap-2 shrink-0">
        <NuxtLink to="/admin/services" class="btn-pill btn-pill-ghost text-[13px]">Cancel</NuxtLink>
        <button type="button" :disabled="saving" class="btn-pill btn-pill-accent text-[13px]" @click="save">
          {{ saving ? 'Saving…' : isNew ? 'Create category' : 'Save changes' }}
        </button>
      </div>
    </div>

    <p v-if="message" class="mb-4 text-[13px]" :style="{ color: 'var(--color-danger)' }">{{ message }}</p>

    <form
v-if="!loading" class="rounded-2xl border p-6 space-y-5"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
      @submit.prevent="save">

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Slug *</label>
          <input
v-model="form.slug" type="text" required class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" >
          <p v-if="errors.slug?.length" class="mt-1 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.slug[0] }}</p>
        </div>
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Name *</label>
          <input
v-model="form.name" type="text" required class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" >
          <p v-if="errors.name?.length" class="mt-1 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.name[0] }}</p>
        </div>
      </div>

      <div>
        <label class="text-[12px] font-medium block mb-2" :style="{ color: 'var(--color-text-secondary)' }">Icon *</label>
        <div class="grid grid-cols-6 sm:grid-cols-9 gap-1.5">
          <button
v-for="ic in serviceIcons" :key="ic.name" type="button"
            :title="ic.label" :aria-label="ic.label"
            class="aspect-square rounded-lg border inline-flex items-center justify-center transition-colors"
            :style="form.icon === ic.name
              ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
              : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }"
            @click="form.icon = ic.name">
            <UIcon :name="ic.name" class="size-4" />
          </button>
        </div>
        <p v-if="errors.icon?.length" class="mt-1.5 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.icon[0] }}</p>
        <p class="mt-1.5 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">
          Selected: <code>{{ form.icon || '—' }}</code>{{ ' ' }}
          <span v-if="serviceIcons.find(i => i.name === form.icon)">— {{ serviceIcons.find(i => i.name === form.icon)?.label }}</span>
        </p>
      </div>

      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Description *</label>
        <textarea
v-model="form.description" required rows="3" class="contact-input w-full"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
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
          <code>+</code> auto-appends at position <code>{{ nextAvailableSort }}</code>. Click an existing number to insert there — the colliding row shifts down.
        </p>
      </div>

      <div class="space-y-2 pt-1">
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
            <span class="block text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Visible on the public services page</span>
          </span>
          <span
class="relative inline-block rounded-full transition-colors shrink-0"
            :style="{
              background: form.active ? 'var(--color-success)' : '#d1d5db',
              height: '1.25rem',
              width: '2.25rem',
            }">
            <span
class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all"
              :style="{ left: form.active ? '1.125rem' : '0.125rem' }"/>
          </span>
        </button>

        <button
type="button" class="w-full flex items-center gap-3 rounded-lg border px-4 py-3 transition-all text-left"
          :style="form.is_default
            ? { borderColor: 'var(--color-accent)', background: 'var(--color-bg-elevated)' }
            : { borderColor: 'var(--color-border)', background: 'var(--color-bg)' }"
          @click="form.is_default = !form.is_default">
          <span
class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors"
            :style="form.is_default
              ? { background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
              : { background: 'var(--color-bg-elevated)', color: 'var(--color-text-tertiary)' }">
            <UIcon name="i-lucide-star" class="size-4" />
          </span>
          <span class="flex-1 min-w-0">
            <span class="block text-[13px] font-medium" :style="{ color: form.is_default ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">Default tab</span>
            <span class="block text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Highlighted as the default on the services page (only one allowed)</span>
          </span>
          <span
class="relative inline-block rounded-full transition-colors shrink-0"
            :style="{
              background: form.is_default ? 'var(--color-accent)' : '#d1d5db',
              height: '1.25rem',
              width: '2.25rem',
            }">
            <span
class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all"
              :style="{ left: form.is_default ? '1.125rem' : '0.125rem' }"/>
          </span>
        </button>
      </div>
    </form>

    <!-- New category: scope fields attach to a saved category, so they appear after creating it. -->
    <p
v-if="!loading && isNew" class="mt-6 rounded-2xl border border-dashed p-6 text-center text-[12px]"
      :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-tertiary)' }">
      <UIcon name="i-lucide-sliders-horizontal" class="size-4 inline-block mb-1" /><br>
      Scope fields (the quote-builder inputs for this category) can be added once you’ve created it — save first, then they’ll appear here.
    </p>

    <!-- Scope fields — the quote-builder inputs + pricing for this category. -->
    <section
v-if="!loading && !isNew" class="mt-6 rounded-2xl border p-6"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
      <div class="mb-4">
        <h2 class="text-[15px] font-semibold" :style="{ color: 'var(--color-text)' }">Scope fields</h2>
        <p class="text-[12px]" :style="{ color: 'var(--color-text-secondary)' }">
          Quote-builder inputs for this category — sliders, toggles & selects, each with its own pricing.
        </p>
      </div>

      <p v-if="!scopeFields.length" class="text-[12px] py-6 text-center" :style="{ color: 'var(--color-text-tertiary)' }">
        No scope fields yet. Add one to capture project scope + pricing in the quote builder.
      </p>

      <ul v-else class="rounded-xl border overflow-hidden" :style="{ borderColor: 'var(--color-border)' }">
        <li
v-for="f in scopeFields" :key="f.id"
          class="flex items-center gap-3 px-4 py-3 border-b last:border-b-0" :style="{ borderColor: 'var(--color-border)' }">
          <span
class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded shrink-0"
            :style="{ color: 'var(--color-accent)', background: 'var(--color-accent-soft)' }">{{ f.type }}</span>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              <p class="text-[13px] font-medium" :style="{ color: 'var(--color-text)' }">{{ f.label }}</p>
              <span
v-if="!f.active" class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded"
                :style="{ color: 'var(--color-text-tertiary)', background: 'var(--color-bg-secondary)' }">Off</span>
            </div>
            <p class="text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">
              <code>{{ f.field_key }}</code> · {{ fieldPriceSummary(f) }}
            </p>
          </div>
          <button
type="button" class="text-[11px] font-medium px-2.5 py-1 rounded-md border transition-colors hover:bg-(--color-bg-secondary)"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
            @click="toggleFieldActive(f)">{{ f.active ? 'Disable' : 'Enable' }}</button>
          <button
type="button" class="text-[11px] font-medium px-2.5 py-1 rounded-md border transition-colors hover:bg-(--color-bg-secondary)"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
            @click="openEditField(f)">Edit</button>
          <button
type="button" class="text-[11px] font-medium px-2.5 py-1 rounded-md border transition-colors hover:bg-(--color-bg-secondary)"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-danger)' }"
            @click="deleteField(f)">Delete</button>
        </li>
      </ul>

      <div class="flex justify-end mt-3">
        <button
type="button" class="btn-pill btn-pill-ghost text-[12px] inline-flex items-center gap-1.5"
          @click="openNewField">
          <UIcon name="i-lucide-plus" class="size-3.5" /> New scope field
        </button>
      </div>
    </section>

    <div v-else-if="loading" class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>

    <ScopeFieldModal
:open="modalOpen" :field="editingField" :category-id="Number(route.params.id)"
      @close="modalOpen = false" @saved="loadScopeFields" />
  </div>
</template>
