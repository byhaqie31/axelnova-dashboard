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
            <button type="button" class="btn-pill btn-pill-ghost text-[13px]" @click="emit('resolve', false)">Cancel</button>
            <button type="button" class="btn-pill text-[13px]" :class="ctaClass[config.variant ?? 'accent']" @click="emit('resolve', true)">
              {{ config.confirmLabel ?? 'Confirm' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
