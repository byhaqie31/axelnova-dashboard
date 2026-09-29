<script setup lang="ts">
import type { ClientSelection } from '~/utils/clientSelection'

/**
 * Permanently delete a client (DELETE /clients/{id}). A client with quotations,
 * orders or payments can't just go — the founder picks a replacement client
 * (existing, or created here) and every record moves there first; inquiries and
 * feedback follow along. A client with none of those gets a plain confirm.
 * §12 confirm-overlay / confirm-card pattern; destructive CTA is danger-TEXT on
 * a ghost pill.
 */
interface DeletableClient {
  id: number
  name: string
  inquiries_count: number
  quotations_count: number
  orders_count: number
  payments_count: number
}

const props = defineProps<{ client: DeletableClient | null }>()
const emit = defineEmits<{ cancel: []; deleted: [replacementId: number | null] }>()

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

const selection = ref<ClientSelection | null>(null)
const deleting = ref(false)
const error = ref('')

watch(() => props.client, () => { error.value = ''; selection.value = null })

const tieCount = computed(() => props.client
  ? props.client.quotations_count + props.client.orders_count + props.client.payments_count
  : 0)

function plural(n: number, word: string) {
  return `${n} ${word}${n === 1 ? '' : 's'}`
}

const tieSummary = computed(() => {
  const c = props.client
  if (!c) return ''
  const parts = [
    c.quotations_count && plural(c.quotations_count, 'quotation'),
    c.orders_count && plural(c.orders_count, 'order'),
    c.payments_count && plural(c.payments_count, 'payment'),
  ].filter(Boolean) as string[]
  return parts.length > 1 ? `${parts.slice(0, -1).join(', ')} and ${parts.at(-1)}` : (parts[0] ?? '')
})

onKeyStroke('Escape', () => { if (props.client && !deleting.value) emit('cancel') })

async function confirmDelete() {
  if (!props.client) return
  if (tieCount.value) {
    const invalid = clientSelectionError(selection.value, 'Pick a client to move these records to, or create a new one.')
    if (invalid) {
      error.value = invalid
      return
    }
  }

  deleting.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ replacement_id: number | null }>(`/api/v1/admin/clients/${props.client.id}`, {
      method: 'DELETE',
      body: tieCount.value ? selection.value! : {},
    })
    toast.success('Client deleted', tieCount.value
      ? `${props.client.name} was deleted and their records moved.`
      : `${props.client.name} was deleted.`)
    emit('deleted', res.replacement_id)
  }
  catch (e) {
    const data = (e as { data?: { message?: string; errors?: Record<string, string[]> } }).data
    const errs = data?.errors ? Object.values(data.errors).flat().join(' ') : ''
    error.value = errs || data?.message || 'Could not delete the client.'
  }
  finally {
    deleting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="confirm-fade">
      <div v-if="client" class="confirm-overlay" @click.self="!deleting && emit('cancel')">
        <div
          class="confirm-card max-h-[90vh] overflow-y-auto"
          :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)', maxWidth: tieCount ? '32rem' : undefined }">
          <!-- Tied: pick the replacement first. -->
          <template v-if="tieCount">
            <h2 class="text-[17px] font-bold tracking-tight mb-2" style="color: var(--color-text);">Move records, then delete</h2>
            <p class="text-[13px] leading-relaxed mb-5" style="color: var(--color-text-secondary);">
              <span class="font-medium" :style="{ color: 'var(--color-text)' }">{{ client.name }}</span> has {{ tieSummary }}.
              Choose the client they move to. Inquiries and feedback follow along, and quotations take on the new contact details.
              Then {{ client.name }} is permanently deleted.
            </p>

            <AdminClientPicker v-model="selection" :exclude-id="client.id" />

            <p class="text-[11px] rounded-lg p-2.5 mt-4" :style="{ background: 'var(--color-bg-secondary)', color: 'var(--color-text-secondary)' }">
              <UIcon name="i-lucide-info" class="size-3 inline align-[-1px] mr-1" />
              Already-issued invoices and receipts keep the details they were issued with.
            </p>
          </template>

          <!-- No ties: plain confirm. -->
          <template v-else>
            <h2 class="text-[17px] font-bold tracking-tight mb-2" style="color: var(--color-text);">Delete {{ client.name }}?</h2>
            <p class="text-[13px] leading-relaxed" style="color: var(--color-text-secondary);">
              This permanently removes the client record.
              <template v-if="client.inquiries_count">
                Their {{ client.inquiries_count === 1 ? 'inquiry stays' : `${client.inquiries_count} inquiries stay` }} in Inquiries, unlinked.
              </template>
              This can’t be undone.
            </p>
          </template>

          <p v-if="error" class="text-[12px] mt-4" style="color: var(--color-danger);">{{ error }}</p>

          <div class="flex items-center justify-end gap-2 mt-6">
            <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :disabled="deleting" @click="emit('cancel')">Cancel</button>
            <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :style="{ color: 'var(--color-danger)' }" :disabled="deleting" @click="confirmDelete">
              {{ deleting ? 'Deleting…' : tieCount ? `Move ${plural(tieCount, 'record')} & delete` : 'Delete client' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
