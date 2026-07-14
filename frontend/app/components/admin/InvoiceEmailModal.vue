<script setup lang="ts">
// Send-invoice-to-client dialog (confirm-overlay / confirm-card pattern).
// The recipient pre-fills from the client record but whatever is typed here
// is used for THIS send only — it is never written back to the client.

const props = withDefaults(defineProps<{
  open: boolean
  invoiceNumber: string
  defaultEmail?: string | null
  emailedAt?: string | null
  emailedTo?: string | null
  sending?: boolean
}>(), { defaultEmail: null, emailedAt: null, emailedTo: null, sending: false })

const emit = defineEmits<{ close: [], send: [email: string] }>()

const email = ref('')
watch(() => props.open, (open) => {
  if (open) email.value = props.defaultEmail ?? ''
})

const valid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value))

function fmtDate(iso?: string | null) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <Teleport to="body">
    <Transition name="confirm-fade">
      <div v-if="open" class="confirm-overlay" @click.self="emit('close')">
        <div class="confirm-card" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
          <h2 class="text-[17px] font-bold tracking-tight mb-2" style="color: var(--color-text);">Email invoice</h2>
          <p class="text-[13px] leading-relaxed mb-4" style="color: var(--color-text-secondary);">
            Sends <span class="font-mono" style="color: var(--color-text);">{{ invoiceNumber }}</span> with a PDF link and attachment. The address below is used for this send only.
          </p>

          <label class="block mb-2">
            <span class="text-[11px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">Recipient email</span>
            <input v-model="email" type="email" placeholder="client@example.com" class="contact-input mt-1 w-full" @keyup.enter="valid && !sending && emit('send', email)">
          </label>

          <p v-if="emailedAt" class="text-[11px] mb-4" style="color: var(--color-text-tertiary);">
            Last sent to {{ emailedTo }} on {{ fmtDate(emailedAt) }}.
          </p>

          <div class="flex items-center justify-end gap-2 mt-4">
            <button type="button" class="btn-pill btn-pill-ghost text-[13px]" @click="emit('close')">Cancel</button>
            <button
              type="button" class="btn-pill btn-pill-accent text-[13px]"
              :class="{ 'opacity-50': !valid || sending }" :disabled="!valid || sending"
              @click="emit('send', email)">
              {{ sending ? 'Queuing…' : 'Send invoice' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
