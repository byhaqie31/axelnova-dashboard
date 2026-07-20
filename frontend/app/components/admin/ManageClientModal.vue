<script setup lang="ts">
/**
 * Correct a mis-matched client on an Order or Quotation. Two modes:
 *  • Edit details — fix the linked client's name/email/phone/company (writes to
 *    the shared Client via PUT /clients/{id}; propagates to every doc for them).
 *  • Change client — re-point this record at the correct client (search & pick,
 *    or create a new one) via POST /{orders|quotations}/{id}/client.
 * Emits a `saved` patch the parent merges into its record. Available regardless
 * of status — the records needing correction may already be completed/accepted.
 */
interface ClientLite {
  id: number
  // Nullable because the order/quotation project these from the client, and a
  // record can (rarely) carry a missing field; search results always have them.
  name: string | null
  email: string | null
  phone: string | null
  company: string | null
}

interface ContactPatch {
  client_id: number
  name: string | null
  email: string | null
  phone: string | null
  company: string | null
}

const props = defineProps<{
  open: boolean
  context: 'order' | 'quotation'
  recordId: number
  client: ClientLite | null
}>()

const emit = defineEmits<{ close: []; saved: [patch: ContactPatch] }>()

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

const noun = computed(() => (props.context === 'order' ? 'order' : 'quotation'))
const hasClient = computed(() => !!props.client?.id)

type Tab = 'edit' | 'change'
const tab = ref<Tab>('edit')
const saving = ref(false)
const error = ref('')

// Edit-details form (seeds from the current client).
const form = reactive({ name: '', email: '', phone: '', company: '' })

// Change-client state — search & pick an existing client, or create a new one.
const search = ref('')
const results = ref<ClientLite[]>([])
const searching = ref(false)
const selectedId = ref<number | null>(null)
const creatingNew = ref(false)
const newClient = reactive({ name: '', email: '', phone: '', company: '' })
let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(() => props.open, (open) => {
  if (!open) return
  // A record with no client can only be re-linked (nothing to edit), so land on
  // the Change tab and lock Edit out.
  tab.value = hasClient.value ? 'edit' : 'change'
  error.value = ''
  const c = props.client
  form.name = c?.name ?? ''
  form.email = c?.email ?? ''
  form.phone = c?.phone ?? ''
  form.company = c?.company ?? ''
  search.value = ''
  results.value = []
  selectedId.value = null
  creatingNew.value = false
  newClient.name = ''
  newClient.email = ''
  newClient.phone = ''
  newClient.company = ''
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
    // Don't offer the client it's already on as a "change" target.
    results.value = res.data.filter(c => c.id !== props.client?.id)
  }
  catch {
    results.value = []
  }
  finally {
    searching.value = false
  }
}

onKeyStroke('Escape', () => { if (props.open) emit('close') })

async function submitEdit() {
  if (form.name.trim().length < 2 || !form.email.includes('@')) {
    error.value = 'A name and a valid email are required.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: ClientLite }>(`/api/v1/admin/clients/${props.client!.id}`, {
      method: 'PUT',
      body: {
        name: form.name.trim(),
        email: form.email.trim(),
        phone: form.phone.trim() || null,
        company: form.company.trim() || null,
      },
    })
    const c = res.data
    toast.success('Client updated', `${noun.value === 'order' ? 'Order' : 'Quotation'} contact details saved.`)
    emit('saved', { client_id: c.id, name: c.name, email: c.email, phone: c.phone, company: c.company })
    emit('close')
  }
  catch (e: any) {
    error.value = fieldErrors(e) || e?.data?.message || 'Could not save the client.'
  }
  finally {
    saving.value = false
  }
}

async function submitRelink() {
  let body: Record<string, unknown>
  if (creatingNew.value) {
    if (newClient.name.trim().length < 2 || !newClient.email.includes('@')) {
      error.value = 'A name and a valid email are required for the new client.'
      return
    }
    body = {
      client: {
        name: newClient.name.trim(),
        email: newClient.email.trim(),
        phone: newClient.phone.trim() || null,
        company: newClient.company.trim() || null,
      },
    }
  }
  else {
    if (!selectedId.value) {
      error.value = 'Pick a client to re-link to, or create a new one.'
      return
    }
    body = { client_id: selectedId.value }
  }

  saving.value = true
  error.value = ''
  try {
    const path = props.context === 'order'
      ? `/api/v1/admin/orders/${props.recordId}/client`
      : `/api/v1/admin/quotations/${props.recordId}/client`
    const res = await apiFetch<any>(path, { method: 'POST', body })
    const record = res.order ?? res.data
    if (res.linked_existing) {
      toast.success('Linked to existing client', `${record.name} was already in your clients — linked, not duplicated.`)
    }
    else {
      toast.success('Client re-linked', `This ${noun.value} now belongs to ${record.name}.`)
    }
    emit('saved', {
      client_id: record.client_id,
      name: record.name,
      email: record.email,
      phone: record.phone,
      company: record.company,
    })
    emit('close')
  }
  catch (e: any) {
    error.value = fieldErrors(e) || e?.data?.message || 'Could not re-link the client.'
  }
  finally {
    saving.value = false
  }
}

function fieldErrors(e: any): string {
  return e?.data?.errors ? Object.values(e.data.errors).flat().join(' ') : ''
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
        <div class="flex items-center justify-between mb-1">
          <p class="text-[16px] font-semibold tracking-tight" style="color: var(--color-text);">Manage client</p>
          <button type="button" class="size-8 rounded-lg flex items-center justify-center transition-colors hover:bg-(--color-bg-secondary)" style="color: var(--color-text-tertiary);" aria-label="Close" @click="emit('close')">
            <UIcon name="i-lucide-x" class="size-4" />
          </button>
        </div>
        <p class="text-[12px] mb-4" style="color: var(--color-text-tertiary);">
          Fix the contact details, or re-point this {{ noun }} at the correct client.
        </p>

        <!-- Mode switch -->
        <div class="inline-flex p-0.5 rounded-xl mb-5" style="background: var(--color-bg-secondary);">
          <button
            type="button" class="px-3.5 py-1.5 rounded-lg text-[12px] font-medium transition-colors"
            :style="tab === 'edit'
              ? { background: 'var(--color-bg-elevated)', color: 'var(--color-text)', boxShadow: 'var(--shadow-sm)' }
              : { color: 'var(--color-text-secondary)' }"
            :disabled="!hasClient"
            :class="{ 'opacity-40 cursor-not-allowed': !hasClient }"
            @click="tab = 'edit'">
            Edit details
          </button>
          <button
            type="button" class="px-3.5 py-1.5 rounded-lg text-[12px] font-medium transition-colors"
            :style="tab === 'change'
              ? { background: 'var(--color-bg-elevated)', color: 'var(--color-text)', boxShadow: 'var(--shadow-sm)' }
              : { color: 'var(--color-text-secondary)' }"
            @click="tab = 'change'">
            Change client
          </button>
        </div>

        <!-- Edit details -->
        <form v-if="tab === 'edit'" class="space-y-4" @submit.prevent="submitEdit">
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
          <p class="text-[11px]" style="color: var(--color-text-tertiary);">
            Updates the shared client record — reflected on every quotation, order &amp; invoice for this client.
          </p>

          <p v-if="error" class="text-[12px]" style="color: var(--color-danger);">{{ error }}</p>

          <div class="flex items-center justify-end gap-2 pt-1">
            <button type="button" class="btn-pill btn-pill-ghost text-[13px]" @click="emit('close')">Cancel</button>
            <button type="submit" class="btn-pill btn-pill-accent text-[13px]" :disabled="saving">
              {{ saving ? 'Saving…' : 'Save changes' }}
            </button>
          </div>
        </form>

        <!-- Change client -->
        <form v-else class="space-y-4" @submit.prevent="submitRelink">
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
                  <span class="text-[13px] font-semibold" style="color: var(--color-text);">{{ c.name }}</span>
                  <UIcon v-if="selectedId === c.id" name="i-lucide-check" class="size-4 shrink-0" :style="{ color: 'var(--color-accent)' }" />
                </div>
                <p class="text-[11px] mt-0.5" style="color: var(--color-text-tertiary);">
                  {{ c.email }}<span v-if="c.company"> · {{ c.company }}</span>
                </p>
              </button>
            </div>

            <button type="button" class="text-[12px] font-medium inline-flex items-center gap-1.5" :style="{ color: 'var(--color-accent)' }" @click="creatingNew = true">
              <UIcon name="i-lucide-plus" class="size-3.5" /> Create a new client instead
            </button>
          </template>

          <template v-else>
            <div class="flex items-center justify-between">
              <p class="text-[12px] font-medium" style="color: var(--color-text-secondary);">New client</p>
              <button type="button" class="text-[12px]" :style="{ color: 'var(--color-text-tertiary)' }" @click="creatingNew = false">
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

          <p class="text-[11px] rounded-lg p-2.5" :style="{ background: 'var(--color-bg-secondary)', color: 'var(--color-text-secondary)' }">
            <UIcon name="i-lucide-info" class="size-3 inline align-[-1px] mr-1" />
            <template v-if="context === 'order'">Re-points this order to the chosen client. Its source quotation follows too; already-issued invoices/receipts stay as-is.</template>
            <template v-else>Re-points this quotation to the chosen client and refreshes its contact snapshot.</template>
          </p>

          <p v-if="error" class="text-[12px]" style="color: var(--color-danger);">{{ error }}</p>

          <div class="flex items-center justify-end gap-2 pt-1">
            <button type="button" class="btn-pill btn-pill-ghost text-[13px]" @click="emit('close')">Cancel</button>
            <button type="submit" class="btn-pill btn-pill-accent text-[13px]" :disabled="saving">
              {{ saving ? 'Re-linking…' : 'Re-link client' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Transition>
</template>
