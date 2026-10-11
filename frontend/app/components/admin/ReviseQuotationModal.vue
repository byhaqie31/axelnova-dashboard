<script setup lang="ts">
// "Revise" — open a new DRAFT revision of a quotation from an accepted / sent /
// rejected / expired one, with the (required) reason it changed. The reason is
// shown on the order timeline and printed on the new revision's PDF. Nothing is
// sent; the founder edits the draft, then sends or accepts it. Revising a SENT
// revision withdraws it (the client gets the replacement); accepting a revision
// of an ACCEPTED quote later supersedes it on the same order.
const props = defineProps<{
  open: boolean
  quotationId: number
  reference: string
  /** The revision being revised (its number + status) and the one the new draft becomes. */
  source: { number: number, status: string }
  nextNumber: number
  /** The order the quote is accepted into, if any (supersede happens there on accept). */
  orderNumber?: string | null
}>()
const emit = defineEmits<{ close: [], revised: [data: Record<string, any>] }>()

const { apiFetch } = useAdminAuth()
const toast = useAdminToast()

const reason = ref('')
const saving = ref(false)
const error = ref('')
watch(() => props.open, (o) => { if (o) { reason.value = ''; error.value = '' } })

const consequence = computed(() => {
  if (props.source.status === 'sent') {
    return `R${props.source.number} was sent to the client — it is withdrawn and R${props.nextNumber} replaces it. Discarding R${props.nextNumber} later restores it.`
  }
  if (props.source.status === 'accepted') {
    return `R${props.source.number} stays the accepted terms${props.orderNumber ? ` of order ${props.orderNumber}` : ''} until R${props.nextNumber} is accepted. Then it supersedes R${props.source.number} on the same order: unpaid invoices are voided, paid ones kept, and the new schedule is netted against what was paid.`
  }
  return `R${props.nextNumber} starts as a copy of R${props.source.number}.`
})

async function submit() {
  if (!reason.value.trim()) { error.value = 'Say why the quotation is changing — it is shown on the order timeline and the PDF.'; return }
  saving.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ message: string, data: Record<string, any> }>(
      `/api/v1/admin/quotations/${props.quotationId}/revisions`,
      { method: 'POST', body: { change_reason: reason.value.trim(), from_revision: props.source.number } },
    )
    toast.success(`R${props.nextNumber} opened`, 'Edit the draft, then send or accept it.')
    emit('revised', res.data)
  }
  catch (e: any) {
    error.value = e?.data?.message ?? 'Couldn’t open a new revision. Please try again.'
  }
  finally { saving.value = false }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="confirm-fade">
      <div v-if="open" class="confirm-overlay" @click.self="emit('close')">
        <form
          class="confirm-card space-y-4" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }"
          @submit.prevent="submit"
        >
          <div>
            <h2 class="text-[17px] font-bold tracking-tight" style="color: var(--color-text);">Revise {{ reference }}</h2>
            <p class="text-[13px] mt-1" style="color: var(--color-text-secondary);">
              Opens <span class="font-mono font-semibold" style="color: var(--color-text);">R{{ nextNumber }}</span> as a draft copy of R{{ source.number }}. Nothing is sent.
            </p>
          </div>

          <div class="space-y-1.5">
            <label for="revise-reason" class="block text-[12px] font-medium" style="color: var(--color-text-secondary);">Change reason</label>
            <textarea
              id="revise-reason" v-model="reason" rows="3" maxlength="500"
              placeholder="e.g. Client switched to the 40/40/20 plan"
              class="contact-input resize-none w-full text-[13px]"
              :style="{ borderColor: error ? 'var(--color-danger)' : 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
            />
            <p class="text-[11px]" style="color: var(--color-text-tertiary);">Printed on the R{{ nextNumber }} PDF exactly as typed, and shown on the order timeline.</p>
          </div>

          <p class="text-[12px] leading-relaxed rounded-xl border px-3.5 py-3 flex gap-2" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)', color: 'var(--color-text-secondary)' }">
            <UIcon name="i-lucide-info" class="size-3.5 shrink-0 mt-0.5" style="color: var(--color-text-tertiary);" />
            <span>{{ consequence }}</span>
          </p>

          <p v-if="error" class="text-[12px]" style="color: var(--color-danger);">{{ error }}</p>

          <div class="flex items-center justify-end gap-2">
            <button type="button" class="btn-pill btn-pill-ghost text-[13px] max-md:flex-1" @click="emit('close')">Cancel</button>
            <button type="submit" class="btn-pill btn-pill-accent text-[13px] max-md:flex-1" :disabled="saving">
              {{ saving ? 'Opening…' : `Open R${nextNumber}` }}
            </button>
          </div>
        </form>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
@media (max-width: 767.98px) {
  .confirm-overlay { align-items: flex-end; padding: 12px 0 0; }
  .confirm-card {
    max-width: none;
    max-height: 90dvh;
    overflow-y: auto;
    border-radius: 20px 20px 0 0;
    border-bottom-width: 0;
    padding: 20px 20px max(20px, env(safe-area-inset-bottom));
  }
  textarea { font-size: 16px; }
}
</style>
