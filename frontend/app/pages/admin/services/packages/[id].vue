<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()

const isNew = computed(() => route.params.id === 'new')

useHead(() => ({ title: isNew.value ? 'New package — Admin' : 'Edit package — Admin' }))

interface CategoryOption {
  id: number
  name: string
}

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

async function loadCategories() {
  try {
    const res = await apiFetch<{ data: CategoryOption[] }>('/api/v1/admin/service-categories')
    categoryOptions.value = res.data
  }
  catch {
    // Non-fatal — handled by the form-level error path.
  }
}

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
})
</script>

<template>
  <div class="max-w-3xl mx-auto px-6 pt-10 pb-32">

    <NuxtLink to="/admin/services" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All services
    </NuxtLink>

    <div class="mb-6">
      <p class="text-[11px] font-semibold uppercase tracking-widest mb-1" style="color: var(--color-text-tertiary);">Admin · CMS</p>
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
        <select v-model.number="form.service_category_id" required class="contact-input w-full"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }">
          <option :value="0" disabled>— pick a category —</option>
          <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
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
          <select v-model="form.eta_unit" required class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }">
            <option value="hour">hour(s)</option>
            <option value="day">day(s)</option>
            <option value="week">week(s)</option>
            <option value="month">month(s)</option>
          </select>
          <p class="mt-1 text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Used by the quote builder for math + rush logic.</p>
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

      <div class="grid sm:grid-cols-3 gap-4">
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Sort order</label>
          <input v-model.number="form.sort_order" type="number" min="0" class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
        </div>
        <label class="flex items-center gap-2 self-end pb-2 cursor-pointer">
          <input v-model="form.featured" type="checkbox" class="size-4" />
          <span class="text-[13px]" :style="{ color: 'var(--color-text)' }">Featured</span>
        </label>
        <label class="flex items-center gap-2 self-end pb-2 cursor-pointer">
          <input v-model="form.active" type="checkbox" class="size-4" />
          <span class="text-[13px]" :style="{ color: 'var(--color-text)' }">Active</span>
        </label>
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
