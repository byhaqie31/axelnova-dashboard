<script setup lang="ts">
import { projectStatuses } from '~/data/projectStatuses'

definePageMeta({ layout: 'admin', middleware: 'admin-auth' })

const route = useRoute()
const { apiFetch } = useAdminAuth()

const isNew = computed(() => route.params.id === 'new')

useHead(() => ({ title: isNew.value ? 'New project — Admin' : 'Edit project — Admin' }))

const form = reactive({
  slug: '',
  name: '',
  description: '',
  long_description: '',
  status: 'planning' as 'live' | 'soon' | 'wip' | 'planning',
  url: '',
  repo: '',
  tags: [] as string[],
  stack: [] as string[],
  featured: false,
  sort_order: 0,
  cover_image_url: '',
  active: true,
})

const tagsText = ref('')
const stackText = ref('')

const loading = ref(!isNew.value)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const message = ref('')

async function fetchProject() {
  if (isNew.value) return
  loading.value = true
  try {
    const res = await apiFetch<{ data: any }>(`/api/v1/admin/projects/${route.params.id}`)
    Object.assign(form, {
      ...res.data,
      url: res.data.url ?? '',
      repo: res.data.repo ?? '',
      cover_image_url: res.data.cover_image_url ?? '',
    })
    tagsText.value = (res.data.tags ?? []).join(', ')
    stackText.value = (res.data.stack ?? []).join(', ')
  }
  catch {
    message.value = 'Failed to load project.'
  }
  finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  errors.value = {}
  message.value = ''

  form.tags = tagsText.value.split(',').map(t => t.trim()).filter(t => t.length > 0)
  form.stack = stackText.value.split(',').map(t => t.trim()).filter(t => t.length > 0)

  // Empty strings → null on the wire (URL fields validate as URL when present).
  const payload = {
    ...form,
    url: form.url || null,
    repo: form.repo || null,
    cover_image_url: form.cover_image_url || null,
  }

  try {
    if (isNew.value) {
      await apiFetch('/api/v1/admin/projects', { method: 'POST', body: payload })
    }
    else {
      await apiFetch(`/api/v1/admin/projects/${route.params.id}`, { method: 'PUT', body: payload })
    }
    await navigateTo('/admin/projects')
  }
  catch (e: any) {
    if (e?.data?.errors) errors.value = e.data.errors
    message.value = e?.data?.message ?? 'Failed to save.'
  }
  finally {
    saving.value = false
  }
}

onMounted(fetchProject)
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-10 pb-32">

    <NuxtLink to="/admin/projects" class="inline-flex items-center gap-2 text-[13px] mb-8 transition-opacity hover:opacity-70"
      style="color: var(--color-text-secondary);">
      <UIcon name="i-lucide-arrow-left" class="size-4" /> All projects
    </NuxtLink>

    <div class="mb-6">
      <h1 class="text-[28px] font-bold tracking-tight" style="color: var(--color-text);">
        {{ isNew ? 'New project' : 'Edit project' }}
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
        </div>
      </div>

      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Short description (max 500) *</label>
        <textarea v-model="form.description" required maxlength="500" rows="2" class="contact-input w-full"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
      </div>

      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Long description *</label>
        <textarea v-model="form.long_description" required rows="5" class="contact-input w-full"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
      </div>

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Status *</label>
          <div class="flex flex-wrap gap-1.5">
            <button v-for="s in projectStatuses" :key="s.value" type="button"
              @click="form.status = s.value"
              class="standard-pill"
              :style="form.status === s.value
                ? { borderColor: s.color, background: s.bg, color: s.color }
                : { borderColor: 'var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text-secondary)' }">
              {{ s.label }}
            </button>
          </div>
        </div>
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Sort order</label>
          <input v-model.number="form.sort_order" type="number" min="0" class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
        </div>
      </div>

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Live URL</label>
          <input v-model="form.url" type="url" placeholder="https://…" class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          <p v-if="errors.url?.length" class="mt-1 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.url[0] }}</p>
        </div>
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Repo URL</label>
          <input v-model="form.repo" type="url" placeholder="https://github.com/…" class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
          <p v-if="errors.repo?.length" class="mt-1 text-[11px]" :style="{ color: 'var(--color-danger)' }">{{ errors.repo[0] }}</p>
        </div>
      </div>

      <div>
        <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Cover image URL</label>
        <input v-model="form.cover_image_url" type="url" class="contact-input w-full"
          :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
      </div>

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Tags (comma-separated)</label>
          <input v-model="tagsText" type="text" placeholder="SaaS, Fintech" class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
        </div>
        <div>
          <label class="text-[12px] font-medium block mb-1.5" :style="{ color: 'var(--color-text-secondary)' }">Stack (comma-separated)</label>
          <input v-model="stackText" type="text" placeholder="Laravel, Nuxt, Redis" class="contact-input w-full"
            :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }" />
        </div>
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
            <span class="block text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Highlighted on the public projects page</span>
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
            <span class="block text-[11px]" :style="{ color: 'var(--color-text-tertiary)' }">Visible on the public site</span>
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
          {{ saving ? 'Saving…' : isNew ? 'Create project' : 'Save changes' }}
        </button>
        <NuxtLink to="/admin/projects" class="btn-pill btn-pill-ghost text-[13px]">Cancel</NuxtLink>
      </div>
    </form>

    <div v-else class="text-center py-16" style="color: var(--color-text-secondary);">Loading…</div>
  </div>
</template>
