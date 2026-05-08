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
})

const loading = ref(!isNew.value)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const message = ref('')

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

onMounted(fetchCategory)
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

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Sort order</label>
          <input v-model.number="form.sort_order" type="number" min="0" class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
        </div>
        <label class="flex items-center gap-2 self-end pb-2 cursor-pointer">
          <input v-model="form.active" type="checkbox" class="size-4" />
          <span class="text-[13px]" :style="{ color: 'var(--color-text)' }">Active (visible on public site)</span>
        </label>
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
