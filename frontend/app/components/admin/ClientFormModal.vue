<script setup lang="ts">
interface Client {
  id: number
  name: string
  email: string
  phone: string | null
  company: string | null
  notes: string | null
  tags: string[]
}

const props = defineProps<{ open: boolean; client?: Client | null }>()
const emit = defineEmits<{ close: []; saved: [client: Client] }>()

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

const isEdit = computed(() => !!props.client?.id)
const form = reactive({ name: '', email: '', phone: '', company: '', notes: '', tags: '' })
const saving = ref(false)
const error = ref('')

// Reset the form each time the modal opens (create = blank, edit = prefilled).
watch(() => props.open, (open) => {
  if (!open) return
  error.value = ''
  const c = props.client
  form.name = c?.name ?? ''
  form.email = c?.email ?? ''
  form.phone = c?.phone ?? ''
  form.company = c?.company ?? ''
  form.notes = c?.notes ?? ''
  form.tags = (c?.tags ?? []).join(', ')
})

onKeyStroke('Escape', () => { if (props.open) emit('close') })

async function submit() {
  if (form.name.trim().length < 2 || !form.email.includes('@')) {
    error.value = 'A name and a valid email are required.'
    return
  }
  saving.value = true
  error.value = ''
  const body = {
    name: form.name.trim(),
    email: form.email.trim(),
    phone: form.phone.trim() || null,
    company: form.company.trim() || null,
    notes: form.notes.trim() || null,
    tags: form.tags.split(',').map(t => t.trim()).filter(Boolean),
  }
  try {
    const res = await apiFetch<{ data: Client }>(
      isEdit.value ? `/api/v1/admin/clients/${props.client!.id}` : '/api/v1/admin/clients',
      { method: isEdit.value ? 'PUT' : 'POST', body },
    )
    toast.success(isEdit.value ? 'Client updated' : 'Client added', res.data.name)
    emit('saved', res.data)
    emit('close')
  }
  catch (e: any) {
    const errs = e?.data?.errors ? Object.values(e.data.errors).flat().join(' ') : ''
    error.value = errs || e?.data?.message || 'Could not save the client.'
  }
  finally {
    saving.value = false
  }
}

const fieldStyle = { borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }
</script>

<template>
  <Transition name="dropdown-panel">
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <button class="absolute inset-0 cursor-default" style="background: rgba(0,0,0,0.4); backdrop-filter: blur(2px);" aria-label="Close" @click="emit('close')" />

      <div
class="relative w-full max-w-lg rounded-2xl border p-6 max-h-[90vh] overflow-y-auto"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
        <div class="flex items-center justify-between mb-5">
          <p class="text-[16px] font-semibold tracking-tight" style="color: var(--color-text);">{{ isEdit ? 'Edit client' : 'New client' }}</p>
          <button type="button" class="size-8 rounded-lg flex items-center justify-center transition-colors hover:bg-(--color-bg-secondary)" style="color: var(--color-text-tertiary);" aria-label="Close" @click="emit('close')">
            <UIcon name="i-lucide-x" class="size-4" />
          </button>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
          <div class="grid sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Name *</label>
              <input v-model="form.name" type="text" class="contact-input w-full" :style="fieldStyle">
            </div>
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Email *</label>
              <input v-model="form.email" type="email" class="contact-input w-full" :style="fieldStyle">
            </div>
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Phone</label>
              <input v-model="form.phone" type="tel" class="contact-input w-full" :style="fieldStyle">
            </div>
            <div class="space-y-1.5">
              <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Company</label>
              <input v-model="form.company" type="text" class="contact-input w-full" :style="fieldStyle">
            </div>
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Tags <span style="color: var(--color-text-tertiary);">(comma-separated)</span></label>
            <input v-model="form.tags" type="text" placeholder="e.g. retainer, priority" class="contact-input w-full" :style="fieldStyle">
          </div>
          <div class="space-y-1.5">
            <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Notes</label>
            <textarea v-model="form.notes" rows="3" class="contact-input resize-none w-full" :style="fieldStyle" />
          </div>

          <p v-if="error" class="text-[12px]" style="color: var(--color-danger);">{{ error }}</p>

          <div class="flex items-center justify-end gap-2 pt-1">
            <button type="button" class="btn-pill btn-pill-ghost text-[13px]" @click="emit('close')">Cancel</button>
            <button type="submit" class="btn-pill btn-pill-accent text-[13px]" :disabled="saving">
              {{ saving ? 'Saving…' : isEdit ? 'Save changes' : 'Add client' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Transition>
</template>
