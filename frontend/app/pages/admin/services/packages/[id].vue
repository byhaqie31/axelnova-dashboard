<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()

const isNew = computed(() => route.params.id === 'new')


interface CategoryOption {
  id: number
  name: string
  icon?: string
}

const etaUnitOptions: { value: 'hour' | 'day' | 'week' | 'month', label: string }[] = [
  { value: 'hour',  label: 'hour(s)' },
  { value: 'day',   label: 'day(s)' },
  { value: 'week',  label: 'week(s)' },
  { value: 'month', label: 'month(s)' },
]

const categoryOpen = ref(false)
const selectedCategory = computed(() =>
  categoryOptions.value.find(c => c.id === form.service_category_id),
)
onKeyStroke('Escape', () => { if (categoryOpen.value) categoryOpen.value = false })

const form = reactive({
  service_category_id: 0,
  slug: '',
  name: '',
  tagline: '',
  price_min_myr: 0,
  price_max_myr: null as number | null,
  unit: 'per project',
  duration_text: '',
  eta_value: 4,
  eta_unit: 'week' as 'hour' | 'day' | 'week' | 'month',
  revisions: '',
  featured: false,
  features: [] as string[],
  cta: 'Get a quote',
  quote_key: null as { category: string, package: string } | null,
  sort_order: 0,
  active: true,
})

const featuresText = ref('')
const useQuoteKey = ref(false)
const quoteKeyCategory = ref('')
const quoteKeyPackage = ref('')

const categoryOptions = ref<CategoryOption[]>([])
const loading = ref(!isNew.value)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const message = ref('')

interface SiblingPackage { id: number, name: string, sort_order: number }
const siblings = ref<SiblingPackage[]>([])

// Positions occupied in the current category — used to render the sort-order pills.
// Includes self when editing (so the user sees their own pill highlighted).
const occupiedSortOrders = computed(() => {
  const positions = new Set<number>(siblings.value.map(s => s.sort_order))
  return Array.from(positions).sort((a, b) => a - b)
})

// What "auto-append" resolves to. Excludes self when editing so moving-to-end doesn't
// over-shoot.
const nextAvailableSort = computed(() => {
  const others = siblings.value
    .filter(s => isNew.value || s.id !== Number(route.params.id))
    .map(s => s.sort_order)
  return (others.length ? Math.max(...others) : -1) + 1
})

function setSort(n: number) { form.sort_order = n }
function nudgeSort(delta: number) {
  const next = Math.min(nextAvailableSort.value, Math.max(0, (form.sort_order ?? 0) + delta))
  form.sort_order = next
}

async function loadCategories() {
  try {
    const res = await apiFetch<{ data: CategoryOption[] }>('/api/v1/admin/service-categories')
    categoryOptions.value = res.data
  }
  catch {
    // Non-fatal — handled by the form-level error path.
  }
}

async function loadSiblings(categoryId: number) {
  if (!categoryId) { siblings.value = []; return }
  try {
    const res = await apiFetch<{ data: SiblingPackage[] }>(`/api/v1/admin/service-packages?service_category_id=${categoryId}`)
    siblings.value = res.data
    // For a new package, default the position to "auto-append" once we know what that is.
    if (isNew.value) form.sort_order = nextAvailableSort.value
  }
  catch {
    siblings.value = []
  }
}

watch(() => form.service_category_id, (id) => {
  if (id) loadSiblings(id)
}, { immediate: false })

async function fetchPackage() {
  if (isNew.value) {
    const presetCat = Number(route.query.category)
    if (presetCat) form.service_category_id = presetCat
    return
  }
  loading.value = true
  try {
    const res = await apiFetch<{ data: typeof form & { features: string[]; quote_key: { category: string, package: string } | null } }>(
      `/api/v1/admin/service-packages/${route.params.id}`,
    )
    Object.assign(form, res.data)
    featuresText.value = (res.data.features ?? []).join('\n')
    if (res.data.quote_key) {
      useQuoteKey.value = true
      quoteKeyCategory.value = res.data.quote_key.category
      quoteKeyPackage.value = res.data.quote_key.package
    }
  }
  catch {
    message.value = 'Failed to load package.'
  }
  finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  errors.value = {}
  message.value = ''

  form.features = featuresText.value
    .split('\n')
    .map(line => line.trim())
    .filter(line => line.length > 0)

  form.quote_key = useQuoteKey.value && quoteKeyCategory.value && quoteKeyPackage.value
    ? { category: quoteKeyCategory.value, package: quoteKeyPackage.value }
    : null

  try {
    if (isNew.value) {
      await apiFetch('/api/v1/admin/service-packages', { method: 'POST', body: form })
    }
    else {
      await apiFetch(`/api/v1/admin/service-packages/${route.params.id}`, { method: 'PUT', body: form })
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
  await loadCategories()
  await fetchPackage()
  if (form.service_category_id) await loadSiblings(form.service_category_id)
})
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <NuxtLink to="/admin/services" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All services
    </NuxtLink>

    <div class="mb-6">
      <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">
        {{ isNew ? 'New package' : 'Edit package' }}
      </h1>
    </div>

    <p v-if="message" class="mb-4 text-[13px]" :style="{ color: 'var(--color-danger)' }">{{ message }}</p>

    <form v-if="!loading" class="rounded-2xl border p-6 space-y-5"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
      @submit.prevent="save">

      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Category *</label>
        <div class="relative">
          <button type="button"
            class="standard-select-trigger"
            :aria-expanded="categoryOpen"
            @click="categoryOpen = !categoryOpen">
            <UIcon v-if="selectedCategory?.icon" :name="selectedCategory.icon" class="size-4 shrink-0" :style="{ color: 'var(--color-accent)' }" />
            <span class="flex-1 truncate" :style="{ color: selectedCategory ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">
              {{ selectedCategory?.name ?? '— pick a category —' }}
            </span>
            <UIcon name="i-lucide-chevron-down" class="size-4 shrink-0 transition-transform"
              :class="{ 'rotate-180': categoryOpen }"
              :style="{ color: 'var(--color-text-tertiary)' }" />
          </button>
          <div v-if="categoryOpen" class="fixed inset-0 z-40 cursor-default" @click="categoryOpen = false" />
          <Transition name="dropdown-panel">
            <ul v-if="categoryOpen" class="standard-select-panel" role="listbox">
              <li v-for="c in categoryOptions" :key="c.id">
                <button type="button"
                  class="standard-select-option"
                  :aria-selected="form.service_category_id === c.id"
                  @click="form.service_category_id = c.id; categoryOpen = false">
                  <UIcon v-if="c.icon" :name="c.icon" class="size-4 shrink-0" />
                  <span class="flex-1 truncate">{{ c.name }}</span>
                  <UIcon v-if="form.service_category_id === c.id" name="i-lucide-check" class="size-4 shrink-0" />
                </button>
              </li>
            </ul>
          </Transition>
        </div>
        <p v-if="errors.service_category_id?.length" class="mt-1 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.service_category_id[0] }}</p>
      </div>

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
        </div>
      </div>

      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Tagline *</label>
        <input v-model="form.tagline" type="text" required class="contact-input w-full"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
      </div>

      <div class="grid sm:grid-cols-3 gap-4">
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Price min (MYR) *</label>
          <input v-model.number="form.price_min_myr" type="number" min="0" required class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
        </div>
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Price max (MYR)</label>
          <input v-model.number="form.price_max_myr" type="number" min="0"
            placeholder="empty = open-ended"
            class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
        </div>
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Unit *</label>
          <input v-model="form.unit" type="text" required class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
        </div>
      </div>

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Duration label *</label>
          <input v-model="form.duration_text" type="text" required placeholder="e.g. 2 weeks"
            class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          <p class="mt-1 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Human-readable, shown on cards (e.g. "5–6 weeks").</p>
        </div>
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Revisions</label>
          <input v-model="form.revisions" type="text" placeholder="e.g. 2 rounds"
            class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
        </div>
      </div>

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">ETA value *</label>
          <input v-model.number="form.eta_value" type="number" required min="1" max="999"
            class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          <p v-if="errors.eta_value?.length" class="mt-1 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.eta_value[0] }}</p>
        </div>
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">ETA unit *</label>
          <div class="flex flex-wrap gap-1.5">
            <button v-for="u in etaUnitOptions" :key="u.value" type="button"
              @click="form.eta_unit = u.value"
              class="standard-pill"
              :style="form.eta_unit === u.value
                ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
                : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }">
              {{ u.label }}
            </button>
          </div>
          <p class="mt-1.5 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Used by the quote builder for math + rush logic.</p>
        </div>
      </div>

      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Features (one per line) *</label>
        <textarea v-model="featuresText" required rows="6" class="contact-input w-full font-mono text-[12px]"
          placeholder="Single-page, mobile-first layout&#10;WhatsApp + contact form integration&#10;…"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
      </div>

      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">CTA label</label>
        <input v-model="form.cta" type="text" class="contact-input w-full"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
      </div>

      <!-- Quote-builder deep link -->
      <div class="rounded-xl border p-4"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="useQuoteKey" type="checkbox" class="size-4" />
          <span class="text-[13px] font-medium" :style="{ color: 'var(--color-text)' }">Wire CTA to the quote builder</span>
        </label>
        <p class="text-[11px] mt-1" :style="{ color: 'var(--color-text-tertiary)' }">
          When enabled, clicking the package CTA opens <code>/quote?category=&hellip;&package=&hellip;</code> with the right preset.
        </p>
        <div v-if="useQuoteKey" class="grid sm:grid-cols-2 gap-3 mt-3">
          <input v-model="quoteKeyCategory" type="text" placeholder="quote category key (e.g. web)"
            class="contact-input w-full font-mono text-[12px]"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
          <input v-model="quoteKeyPackage" type="text" placeholder="quote package key (e.g. web_business)"
            class="contact-input w-full font-mono text-[12px]"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg-elevated)' }" />
        </div>
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
        <button type="button" @click="form.featured = !form.featured"
          class="w-full flex items-center gap-3 rounded-lg border px-4 py-3 transition-all text-left"
          :style="form.featured
            ? { borderColor: 'var(--color-accent)', background: 'var(--color-bg-elevated)' }
            : { borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
          <span class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors"
            :style="form.featured
              ? { background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }
              : { background: 'var(--color-bg-elevated)', color: 'var(--color-text-tertiary)' }">
            <UIcon name="i-lucide-crown" class="size-4" />
          </span>
          <span class="flex-1 min-w-0">
            <span class="block text-[13px] font-medium" :style="{ color: form.featured ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">Featured</span>
            <span class="block text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Highlighted on the public services page</span>
          </span>
          <span class="relative inline-block rounded-full transition-colors shrink-0"
            :style="{
              background: form.featured ? 'var(--color-accent)' : '#d1d5db',
              height: '1.25rem',
              width: '2.25rem',
            }">
            <span class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all"
              :style="{ left: form.featured ? '1.125rem' : '0.125rem' }"></span>
          </span>
        </button>

        <button type="button" @click="form.active = !form.active"
          class="w-full flex items-center gap-3 rounded-lg border px-4 py-3 transition-all text-left"
          :style="form.active
            ? { borderColor: 'var(--color-success)', background: 'var(--color-bg-elevated)' }
            : { borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
          <span class="size-9 rounded-lg flex items-center justify-center shrink-0 transition-colors"
            :style="form.active
              ? { background: 'var(--color-success-soft)', color: 'var(--color-success)' }
              : { background: 'var(--color-bg-elevated)', color: 'var(--color-text-tertiary)' }">
            <UIcon name="i-lucide-power" class="size-4" />
          </span>
          <span class="flex-1 min-w-0">
            <span class="block text-[13px] font-medium" :style="{ color: form.active ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">Active</span>
            <span class="block text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Visible on the public services page</span>
          </span>
          <span class="relative inline-block rounded-full transition-colors shrink-0"
            :style="{
              background: form.active ? 'var(--color-success)' : '#d1d5db',
              height: '1.25rem',
              width: '2.25rem',
            }">
            <span class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all"
              :style="{ left: form.active ? '1.125rem' : '0.125rem' }"></span>
          </span>
        </button>
      </div>

      <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="btn-pill btn-pill-accent text-[13px]" :disabled="saving">
          {{ saving ? 'Saving…' : isNew ? 'Create package' : 'Save changes' }}
        </button>
        <NuxtLink to="/admin/services" class="btn-pill btn-pill-ghost text-[13px]">Cancel</NuxtLink>
      </div>
    </form>

    <div v-else class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
  </div>
</template>
