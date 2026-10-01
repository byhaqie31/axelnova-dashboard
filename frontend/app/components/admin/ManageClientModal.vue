<script setup lang="ts">
import type { ClientSelection } from '~/utils/clientSelection'

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

// Change-client target — from <AdminClientPicker> (search & pick, or create new).
const selection = ref<ClientSelection | null>(null)

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
})

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
  const invalid = clientSelectionError(selection.value, 'Pick a client to re-link to, or create a new one.')
  if (invalid) {
    error.value = invalid
    return
  }

  saving.value = true
  error.value = ''
  try {
    const path = props.context === 'order'
      ? `/api/v1/admin/orders/${props.recordId}/client`
      : `/api/v1/admin/quotations/${props.recordId}/client`
    const res = await apiFetch<any>(path, { method: 'POST', body: selection.value! })
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
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 max-md:items-end max-md:p-0 max-md:pt-6">
      <button class="absolute inset-0 cursor-default" style="background: rgba(0,0,0,0.4); backdrop-filter: blur(2px);" aria-label="Close" @click="emit('close')" />

      <div
        class="relative w-full max-w-lg max-md:max-w-none rounded-2xl max-md:rounded-b-none border max-md:border-b-0 p-6 max-md:p-5 max-md:pb-[max(1.25rem,env(safe-area-inset-bottom))] max-h-[90vh] max-md:max-h-[92dvh] overflow-y-auto max-md:overscroll-contain"
        :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
        <div class="flex items-center justify-between mb-1">
          <p class="text-[16px] font-semibold tracking-tight" style="color: var(--color-text);">Manage client</p>
          <button type="button" class="size-8 max-md:size-10 max-md:-mr-2 rounded-lg flex items-center justify-center transition-colors hover:bg-(--color-bg-secondary) max-md:active:bg-(--color-bg-secondary)" style="color: var(--color-text-tertiary);" aria-label="Close" @click="emit('close')">
            <UIcon name="i-lucide-x" class="size-4" />
          </button>
        </div>
        <p class="text-[12px] mb-4" style="color: var(--color-text-tertiary);">
          Fix the contact details, or re-point this {{ noun }} at the correct client.
        </p>

        <!-- Mode switch -->
        <div class="inline-flex max-md:flex max-md:w-full p-0.5 rounded-xl mb-5" style="background: var(--color-bg-secondary);">
          <button
            type="button" class="px-3.5 py-1.5 max-md:flex-1 max-md:py-2.5 max-md:text-[13px] rounded-lg text-[12px] font-medium transition-colors"
            :style="tab === 'edit'
              ? { background: 'var(--color-bg-elevated)', color: 'var(--color-text)', boxShadow: 'var(--shadow-sm)' }
              : { color: 'var(--color-text-secondary)' }"
            :disabled="!hasClient"
            :class="{ 'opacity-40 cursor-not-allowed': !hasClient }"
            @click="tab = 'edit'">
            Edit details
          </button>
          <button
            type="button" class="px-3.5 py-1.5 max-md:flex-1 max-md:py-2.5 max-md:text-[13px] rounded-lg text-[12px] font-medium transition-colors"
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
            <button type="button" class="btn-pill btn-pill-ghost text-[13px] max-md:flex-1" @click="emit('close')">Cancel</button>
            <button type="submit" class="btn-pill btn-pill-accent text-[13px] max-md:flex-1" :disabled="saving">
              {{ saving ? 'Saving…' : 'Save changes' }}
            </button>
          </div>
        </form>

        <!-- Change client -->
        <form v-else class="space-y-4" @submit.prevent="submitRelink">
          <!-- Don't offer the client it's already on as a "change" target. -->
          <AdminClientPicker v-model="selection" :exclude-id="client?.id" />

          <p class="text-[11px] rounded-lg p-2.5" :style="{ background: 'var(--color-bg-secondary)', color: 'var(--color-text-secondary)' }">
            <UIcon name="i-lucide-info" class="size-3 inline align-[-1px] mr-1" />
            <template v-if="context === 'order'">Re-points this order to the chosen client. Its source quotation follows too; already-issued invoices/receipts stay as-is.</template>
            <template v-else>Re-points this quotation to the chosen client and refreshes its contact snapshot.</template>
          </p>

          <p v-if="error" class="text-[12px]" style="color: var(--color-danger);">{{ error }}</p>

          <div class="flex items-center justify-end gap-2 pt-1">
            <button type="button" class="btn-pill btn-pill-ghost text-[13px] max-md:flex-1" @click="emit('close')">Cancel</button>
            <button type="submit" class="btn-pill btn-pill-accent text-[13px] max-md:flex-1" :disabled="saving">
              {{ saving ? 'Re-linking…' : 'Re-link client' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
/* Mobile: ≥16px form text stops iOS Safari zooming the page on focus. */
@media (max-width: 767.98px) {
  input:not([type='checkbox'], [type='radio']),
  textarea { font-size: 16px; }
}
</style>
