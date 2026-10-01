<script setup lang="ts">
import type { ConfirmConfig } from '~/composables/useConfirm'

// The shared confirm-before-act dialog (§12 confirm-overlay / confirm-card
// pattern). Driven by useConfirm(); emits the yes/no result. The CTA colour
// follows config.variant (positive actions stay accent).
defineProps<{ open: boolean, config: ConfirmConfig }>()
const emit = defineEmits<{ resolve: [ok: boolean] }>()

const ctaClass: Record<string, string> = {
  accent: 'btn-pill-accent',
  warning: 'btn-pill-warning',
  danger: 'btn-pill-danger',
}
</script>

<template>
  <Teleport to="body">
    <Transition name="confirm-fade">
      <div v-if="open" class="confirm-overlay" @click.self="emit('resolve', false)">
        <div class="confirm-card" :style="{ background: 'var(--color-bg)', borderColor: 'var(--color-border)', boxShadow: 'var(--shadow-lg)' }">
          <h2 class="text-[17px] font-bold tracking-tight mb-2" style="color: var(--color-text);">{{ config.title }}</h2>
          <p v-if="config.message" class="text-[13px] leading-relaxed mb-6" style="color: var(--color-text-secondary);">{{ config.message }}</p>
          <div class="flex items-center justify-end gap-2">
            <button type="button" class="btn-pill btn-pill-ghost text-[13px] max-md:flex-1" @click="emit('resolve', false)">Cancel</button>
            <button type="button" class="btn-pill text-[13px] max-md:flex-1" :class="ctaClass[config.variant ?? 'accent']" @click="emit('resolve', true)">
              {{ config.confirmLabel ?? 'Confirm' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* Mobile: the dialog becomes a bottom sheet — full width, anchored to the
   bottom edge, scrolls internally, clears the home indicator. Desktop keeps
   the shared centered .confirm-card from main.css. */
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
}
</style>
