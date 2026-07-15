<script setup lang="ts">
// Move a payment's invoice allocation after the fact — link an unallocated
// payment, re-link a wrongly-tagged one, or unlink. A confirm step states the
// predicted outcome (paid / stays issued / over-allocated) before the PATCH;
// the server-side observer recompute stays the source of truth.
const props = defineProps<{
  paymentId: number
  orderId: number
  currentInvoiceId: number | null
  currentInvoiceNumber: string | null
  /** Net-of-refunds amount this payment contributes to an invoice. */
  netAmount: number
}>()

const emit = defineEmits<{ allocated: [] }>()

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()
const { confirmOpen, confirmConfig, confirm, resolveConfirm } = useConfirm()

interface OrderInvoice { id: number, number: string, status: string, amount_total: string, amount_paid: string | null }

const open = ref(false)
const loading = ref(false)
const error = ref('')
const invoices = ref<OrderInvoice[]>([])
const selectedId = ref<number | null>(null)
const saving = ref(false)

const changed = computed(() => selectedId.value !== props.currentInvoiceId)

async function fetchInvoices() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ data: { invoices?: OrderInvoice[] } }>(`/api/v1/admin/orders/${props.orderId}`)
    // Void invoices are frozen — the endpoint rejects them, so don't offer them.
    invoices.value = (res.data.invoices ?? []).filter(d => d.status !== 'void')
  }
  catch {
    error.value = 'Failed to load the order’s invoices.'
  }
  finally {
    loading.value = false
  }
}

function openModal() {
  open.value = true
  selectedId.value = props.currentInvoiceId
  fetchInvoices()
}

function outstanding(inv: OrderInvoice) {
  return Math.max(Number(inv.amount_total) - Number(inv.amount_paid ?? 0), 0)
}

// The confirm copy states what the recompute WILL do, so the admin approves
// an outcome, not a mutation.
function outcomeConfig() {
  const inv = invoices.value.find(d => d.id === selectedId.value)
  if (!inv) {
    return {
      title: 'Unlink this payment?',
      message: `It will no longer count toward ${props.currentInvoiceNumber ?? 'its invoice'} — the invoice’s paid total recalculates immediately.`,
      confirmLabel: 'Unlink',
      variant: 'warning' as const,
    }
  }
  const due = outstanding(inv)
  const dueCents = Math.round(due * 100)
  const netCents = Math.round(props.netAmount * 100)
  if (netCents > dueCents) {
    return {
      title: `Link to ${inv.number}?`,
      message: `This payment exceeds the invoice’s outstanding ${fmtMyr(due)} by ${fmtMyr((netCents - dueCents) / 100)}. The invoice will be marked paid.`,
      confirmLabel: 'Link anyway',
      variant: 'warning' as const,
    }
  }
  if (netCents === dueCents) {
    return {
      title: `Link to ${inv.number}?`,
      message: `${fmtMyr(props.netAmount)} fully covers the outstanding balance — the invoice will be marked paid.`,
      confirmLabel: 'Link payment',
      variant: 'accent' as const,
    }
  }
  return {
    title: `Link to ${inv.number}?`,
    message: `${fmtMyr(props.netAmount)} of ${fmtMyr(due)} outstanding will be covered — the invoice stays issued.`,
    confirmLabel: 'Link payment',
    variant: 'accent' as const,
  }
}

async function submit() {
  if (!changed.value || saving.value) return
  if (!(await confirm(outcomeConfig()))) return
  saving.value = true
  try {
    await apiFetch(`/api/v1/admin/payments/${props.paymentId}/allocation`, {
      method: 'PATCH',
      body: { invoice_id: selectedId.value },
    })
    toast.success('Allocation updated', 'The invoice totals are recalculated.')
    open.value = false
    emit('allocated')
  }
  catch {
    toast.error('Couldn’t update allocation', 'Please try again.')
  }
  finally {
    saving.value = false
  }
}

// The confirm dialog registers its own Escape handler (z-100, above us).
onKeyStroke('Escape', () => { if (open.value && !confirmOpen.value) open.value = false })

function fmtMyr(amount: string | number) {
  return `RM ${Number(amount).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}
</script>

<template>
  <button type="button" class="btn-pill btn-pill-ghost w-full justify-center text-[13px]" @click="openModal">
    {{ currentInvoiceId ? 'Change invoice allocation' : 'Allocate to invoice' }}
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
            <p class="text-[15px] font-semibold" style="color: var(--color-text);">Allocate to invoice</p>
            <button type="button" class="transition-opacity hover:opacity-70" style="color: var(--color-text-tertiary);" @click="open = false">
              <UIcon name="i-lucide-x" class="size-5" />
            </button>
          </div>

          <!-- List -->
          <div class="flex-1 overflow-y-auto px-5 pb-2 min-h-[120px] space-y-1.5">
            <p v-if="error" class="text-[13px] py-6 text-center" style="color: var(--color-danger);">{{ error }}</p>
            <p v-else-if="loading" class="text-[13px] py-6 text-center" style="color: var(--color-text-secondary);">Loading…</p>
            <template v-else>
              <p v-if="!invoices.length" class="text-[13px] py-6 text-center" style="color: var(--color-text-secondary);">No invoices on this order yet — issue one from the order page first.</p>
              <button
v-for="inv in invoices" :key="inv.id" type="button"
                class="w-full flex items-center justify-between gap-3 rounded-xl border px-3.5 py-3 text-left transition-colors"
                :style="{
                  background: selectedId === inv.id ? 'var(--color-accent-soft)' : 'var(--color-bg)',
                  borderColor: selectedId === inv.id ? 'var(--color-accent)' : 'var(--color-border)',
                }"
                @click="selectedId = inv.id">
                <span class="flex items-center gap-2 min-w-0">
                  <span class="font-mono text-[13px] truncate" style="color: var(--color-text);">{{ inv.number }}</span>
                  <AdminStatusPill :status="inv.status" />
                </span>
                <span class="text-[12px] shrink-0" style="color: var(--color-text-secondary);">
                  {{ fmtMyr(outstanding(inv)) }} of {{ fmtMyr(inv.amount_total) }} outstanding
                </span>
              </button>
              <button
v-if="currentInvoiceId" type="button"
                class="w-full flex items-center gap-2 rounded-xl border px-3.5 py-3 text-left transition-colors"
                :style="{
                  background: selectedId === null ? 'var(--color-accent-soft)' : 'var(--color-bg)',
                  borderColor: selectedId === null ? 'var(--color-accent)' : 'var(--color-border)',
                }"
                @click="selectedId = null">
                <UIcon name="i-lucide-unlink" class="size-4" style="color: var(--color-text-tertiary);" />
                <span class="text-[13px]" style="color: var(--color-text-secondary);">Not allocated — unlink from {{ currentInvoiceNumber }}</span>
              </button>
            </template>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-2 px-5 py-4 border-t" style="border-color: var(--color-border);">
            <button type="button" class="btn-pill btn-pill-ghost text-[13px]" @click="open = false">Cancel</button>
            <button
type="button" class="btn-pill btn-pill-primary text-[13px]"
              :class="{ 'opacity-50': !changed || saving }" :disabled="!changed || saving" @click="submit">
              {{ saving ? 'Updating…' : 'Update allocation' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <AdminConfirmDialog :open="confirmOpen" :config="confirmConfig" @resolve="resolveConfirm" />
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
