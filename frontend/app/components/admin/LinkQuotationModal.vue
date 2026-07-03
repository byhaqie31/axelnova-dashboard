<script setup lang="ts">
// Picker for attaching an inquiry to an ALREADY-EXISTING quotation (the
// alternative to "Build quotation", which creates a fresh one). Reuses the
// admin quotations search endpoint; on link it POSTs to the inquiry's
// link-quotation action and emits `linked` so the parent can refetch.
const props = defineProps<{
  inquiryId: number
}>()

const emit = defineEmits<{ linked: [] }>()

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

interface QuotationRow {
  id: number
  reference_code: string
  name: string
  email: string
  status: string
}

const open = ref(false)
const search = ref('')
const rows = ref<QuotationRow[]>([])
const loading = ref(false)
const error = ref('')
const selectedId = ref<number | null>(null)
const linking = ref(false)

async function fetchQuotations() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams()
    if (search.value) params.set('search', search.value)
    // Any quotation is linkable — surface accepted ones too (they live on Orders).
    params.set('include_accepted', '1')
    const res = await apiFetch<{ data: QuotationRow[] }>(`/api/v1/admin/quotations?${params}`)
    rows.value = res.data
  }
  catch {
    error.value = 'Failed to load quotations.'
  }
  finally {
    loading.value = false
  }
}

function openModal() {
  open.value = true
  selectedId.value = null
  if (!rows.value.length) fetchQuotations()
}

let searchTimer: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(fetchQuotations, 400)
})

async function link() {
  if (!selectedId.value) return
  linking.value = true
  try {
    await apiFetch(`/api/v1/admin/inquiries/${props.inquiryId}/quotation`, {
      method: 'POST',
      body: { quotation_id: selectedId.value },
    })
    toast.success('Quotation linked', 'This inquiry is now linked to the selected quotation.')
    open.value = false
    emit('linked')
  }
  catch {
    toast.error('Couldn’t link quotation', 'Something went wrong. Please try again.')
  }
  finally {
    linking.value = false
  }
}

onKeyStroke('Escape', () => { if (open.value) open.value = false })
</script>

<template>
  <button type="button" class="btn-pill btn-pill-ghost w-full justify-center text-[13px]" @click="openModal">
    Link existing quotation
  </button>

  <Teleport to="body">
    <Transition name="link-modal">
      <div v-if="open" class="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-6" @click.self="open = false">
        <div class="absolute inset-0" style="background: rgba(0,0,0,0.55); backdrop-filter: blur(2px);" @click="open = false" />

        <div
class="relative w-full max-w-[520px] max-h-[85vh] flex flex-col rounded-2xl border shadow-2xl"
          :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }" @click.stop>

          <!-- Header -->
          <div class="flex items-center justify-between px-5 pt-5 pb-3">
            <p class="text-[15px] font-semibold" style="color: var(--color-text);">Link existing quotation</p>
            <button type="button" class="transition-opacity hover:opacity-70" style="color: var(--color-text-tertiary);" @click="open = false">
              <UIcon name="i-lucide-x" class="size-5" />
            </button>
          </div>

          <!-- Search -->
          <div class="px-5 pb-3">
            <input
v-model="search" type="text" placeholder="Search by reference, name, email…"
              class="w-full rounded-xl border px-3.5 py-2.5 text-[13px] outline-none transition-colors focus:border-(--color-accent)"
              :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', color: 'var(--color-text)' }">
          </div>

          <!-- List -->
          <div class="flex-1 overflow-y-auto px-5 pb-2 min-h-[120px]">
            <p v-if="error" class="text-[13px] py-6 text-center" style="color: var(--color-danger);">{{ error }}</p>
            <p v-else-if="loading" class="text-[13px] py-6 text-center" style="color: var(--color-text-secondary);">Loading…</p>
            <p v-else-if="!rows.length" class="text-[13px] py-6 text-center" style="color: var(--color-text-secondary);">No quotations found.</p>

            <ul v-else class="space-y-1.5">
              <li v-for="q in rows" :key="q.id">
                <button
type="button"
                  class="w-full text-left rounded-xl border px-3.5 py-2.5 transition-colors"
                  :style="{
                    borderColor: selectedId === q.id ? 'var(--color-accent)' : 'var(--color-border)',
                    background: selectedId === q.id ? 'var(--color-bg-secondary)' : 'var(--color-bg)',
                  }"
                  @click="selectedId = q.id">
                  <div class="flex items-center justify-between gap-3">
                    <span class="font-mono text-[12px] font-medium" style="color: var(--color-accent);">{{ q.reference_code }}</span>
                    <AdminStatusPill :status="q.status" />
                  </div>
                  <p class="text-[13px] font-medium mt-1" style="color: var(--color-text);">{{ q.name }}</p>
                  <p class="text-[11px]" style="color: var(--color-text-tertiary);">{{ q.email }}</p>
                </button>
              </li>
            </ul>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-2 px-5 py-4 border-t" style="border-color: var(--color-border);">
            <button type="button" class="btn-pill btn-pill-ghost text-[13px]" @click="open = false">Cancel</button>
            <button
type="button" class="btn-pill btn-pill-accent text-[13px]"
              :class="{ 'opacity-50': !selectedId || linking }"
              :disabled="!selectedId || linking"
              @click="link">
              {{ linking ? 'Linking…' : 'Link quotation' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.link-modal-enter-active,
.link-modal-leave-active {
  transition: opacity 0.18s ease;
}
.link-modal-enter-from,
.link-modal-leave-to {
  opacity: 0;
}
</style>
