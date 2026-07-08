<script setup lang="ts">
import type { DeletableQuotation, DeleteBlocked } from '~/composables/useQuotationDelete'

// The shared confirm-before-delete dialog for quotations (list + detail). Renders
// nothing until `target` is set. Reuses the §12 confirm-overlay / confirm-card
// pattern; destructive CTA is danger-TEXT on a ghost pill (never white-on-danger).
defineProps<{
  target: DeletableQuotation | null
  blocked: DeleteBlocked | null
  deleting: boolean
}>()

const emit = defineEmits<{ cancel: [], confirm: [] }>()
</script>

<template>
  <Teleport to="body">
    <Transition name="confirm-fade">
      <div v-if="target" class="confirm-overlay" @click.self="emit('cancel')">
        <div class="confirm-card" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
          <!-- Blocked: anchored to an order — show why + a link to it. -->
          <template v-if="blocked">
            <h2 class="text-[17px] font-bold tracking-tight mb-2" style="color: var(--color-text);">Can’t delete this quotation</h2>
            <p class="text-[13px] leading-relaxed mb-4" style="color: var(--color-text-secondary);">{{ blocked.message }}</p>
            <NuxtLink :to="`/admin/orders/${blocked.order_id}`" class="inline-flex items-center gap-1.5 text-[13px] font-medium mb-6" :style="{ color: 'var(--color-accent)' }">
              <UIcon name="i-lucide-arrow-up-right" class="size-3.5" /> View order {{ blocked.order_number }}
            </NuxtLink>
            <div class="flex items-center justify-end">
              <button type="button" class="btn-pill btn-pill-ghost text-[13px]" @click="emit('cancel')">Close</button>
            </div>
          </template>

          <!-- Confirm delete. -->
          <template v-else>
            <h2 class="text-[17px] font-bold tracking-tight mb-2" style="color: var(--color-text);">Delete quotation {{ target.reference_code }}?</h2>
            <p class="text-[13px] leading-relaxed mb-6" style="color: var(--color-text-secondary);">
              This removes the quotation for <span class="font-medium" :style="{ color: 'var(--color-text)' }">{{ target.name }}</span> from every list, and unlinks any inquiry or referral tied to it. It’s a soft delete — recoverable from the database.
            </p>
            <div class="flex items-center justify-end gap-2">
              <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :disabled="deleting" @click="emit('cancel')">Cancel</button>
              <button type="button" class="btn-pill btn-pill-ghost text-[13px]" :style="{ color: 'var(--color-danger)' }" :disabled="deleting" @click="emit('confirm')">
                {{ deleting ? 'Deleting…' : 'Delete quotation' }}
              </button>
            </div>
          </template>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
