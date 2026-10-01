<script setup lang="ts">
import type { ClientSelection } from '~/utils/clientSelection'

/**
 * Search & pick an existing client, or switch to "create a new client" — shared
 * by ManageClientModal (re-link a quotation/order) and ClientDeleteDialog (pick
 * where a deleted client's records move). The model is a ClientSelection ready
 * to send as the request body; validate it with clientSelectionError(). State
 * resets on mount, so parents render it only while their dialog is open.
 */
interface ClientLite {
  id: number
  name: string | null
  email: string | null
  company: string | null
}

const props = defineProps<{
  /** Hidden from results — e.g. the client it's already on, or the one being deleted. */
  excludeId?: number | null
}>()

const selection = defineModel<ClientSelection | null>({ default: null })

const { apiFetch } = useAdminAuth()

const search = ref('')
const results = ref<ClientLite[]>([])
const searching = ref(false)
const selectedId = ref<number | null>(null)
const creatingNew = ref(false)
const newClient = reactive({ name: '', email: '', phone: '', company: '' })
let searchTimer: ReturnType<typeof setTimeout> | undefined

onMounted(() => { selection.value = null })

watch([selectedId, creatingNew, newClient], () => {
  selection.value = creatingNew.value
    ? {
        client: {
          name: newClient.name.trim(),
          email: newClient.email.trim(),
          phone: newClient.phone.trim() || null,
          company: newClient.company.trim() || null,
        },
      }
    : selectedId.value ? { client_id: selectedId.value } : null
})

watch(search, (q) => {
  clearTimeout(searchTimer)
  selectedId.value = null
  if (!q.trim()) { results.value = []; return }
  searchTimer = setTimeout(runSearch, 250)
})

async function runSearch() {
  searching.value = true
  try {
    const res = await apiFetch<{ data: ClientLite[] }>(
      `/api/v1/admin/clients?search=${encodeURIComponent(search.value.trim())}`,
    )
    results.value = res.data.filter(c => c.id !== props.excludeId)
  }
  catch {
    results.value = []
  }
  finally {
    searching.value = false
  }
}

const fieldStyle = { borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }
</script>

<template>
  <div class="space-y-4">
    <template v-if="!creatingNew">
      <div class="space-y-1.5">
        <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Search clients</label>
        <input v-model="search" type="text" placeholder="Name, email or company…" class="contact-input w-full" :style="fieldStyle">
      </div>

      <div v-if="searching" class="text-[12px] py-2" style="color: var(--color-text-tertiary);">Searching…</div>
      <div v-else-if="search.trim() && !results.length" class="text-[12px] py-2" style="color: var(--color-text-tertiary);">
        No other clients match “{{ search.trim() }}”.
      </div>
      <div v-else-if="results.length" class="space-y-1.5 max-h-56 overflow-y-auto">
        <button
          v-for="c in results" :key="c.id" type="button"
          class="w-full text-left rounded-xl border p-3 transition-colors"
          :style="selectedId === c.id
            ? { borderColor: 'var(--color-accent)', background: 'var(--color-accent-soft)' }
            : { borderColor: 'var(--color-border)' }"
          @click="selectedId = c.id">
          <div class="flex items-center justify-between gap-2">
            <span class="text-[13px] font-semibold max-md:min-w-0 max-md:wrap-anywhere" style="color: var(--color-text);">{{ c.name }}</span>
            <UIcon v-if="selectedId === c.id" name="i-lucide-check" class="size-4 shrink-0" :style="{ color: 'var(--color-accent)' }" />
          </div>
          <p class="text-[11px] mt-0.5 max-md:wrap-anywhere" style="color: var(--color-text-tertiary);">
            {{ c.email }}<span v-if="c.company"> · {{ c.company }}</span>
          </p>
        </button>
      </div>

      <button type="button" class="text-[12px] font-medium inline-flex items-center gap-1.5 max-md:text-[13px] max-md:py-2 max-md:active:opacity-60" :style="{ color: 'var(--color-accent)' }" @click="creatingNew = true">
        <UIcon name="i-lucide-plus" class="size-3.5" /> Create a new client instead
      </button>
    </template>

    <template v-else>
      <div class="flex items-center justify-between">
        <p class="text-[12px] font-medium" style="color: var(--color-text-secondary);">New client</p>
        <button type="button" class="text-[12px] max-md:py-2 max-md:active:opacity-60" :style="{ color: 'var(--color-text-tertiary)' }" @click="creatingNew = false">
          ← Back to search
        </button>
      </div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div class="space-y-1.5">
          <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Name *</label>
          <input v-model="newClient.name" type="text" class="contact-input w-full" :style="fieldStyle">
        </div>
        <div class="space-y-1.5">
          <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Email *</label>
          <input v-model="newClient.email" type="email" class="contact-input w-full" :style="fieldStyle">
        </div>
        <div class="space-y-1.5">
          <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Phone</label>
          <input v-model="newClient.phone" type="tel" class="contact-input w-full" :style="fieldStyle">
        </div>
        <div class="space-y-1.5">
          <label class="text-[12px] font-medium" style="color: var(--color-text-secondary);">Company</label>
          <input v-model="newClient.company" type="text" class="contact-input w-full" :style="fieldStyle">
        </div>
      </div>
      <p class="text-[11px]" style="color: var(--color-text-tertiary);">
        If that email already exists, we’ll link to that client instead of creating a duplicate.
      </p>
    </template>
  </div>
</template>

<style scoped>
/* Mobile: ≥16px form text stops iOS Safari zooming the page on focus. */
@media (max-width: 767.98px) {
  input:not([type='checkbox'], [type='radio']),
  textarea { font-size: 16px; }
}
</style>
