<script setup lang="ts">
// Shared "Deposit % + Terms (one per line)" block, used by BOTH quotation builders
// (standard QuotationBuilder + DetailedQuotationBuilder) so the two never drift.
// Each builder binds its own state via v-model; markup lives here only.
withDefaults(defineProps<{
  /** Adds the top divider used when this sits inside a multi-section card (detailed builder). */
  separated?: boolean
}>(), { separated: false })

const terms = defineModel<string>('terms', { required: true })
const depositPct = defineModel<number>('depositPct', { required: true })
</script>

<template>
  <div
    class="space-y-4"
    :class="separated ? 'pt-2 border-t' : ''"
    :style="separated ? { borderColor: 'var(--color-border)' } : undefined"
  >
    <!-- Deposit %: title above, field below -->
    <div class="space-y-1.5">
      <label class="block text-[12px] font-medium" style="color: var(--color-text-secondary);">Deposit %</label>
      <input
        v-model.number="depositPct"
        type="number"
        min="0"
        max="100"
        class="contact-input"
        :style="{ width: '6rem', borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
      >
    </div>
    <!-- Terms: title above, full-width field below -->
    <div class="space-y-1.5">
      <label class="block text-[12px] font-medium" style="color: var(--color-text-secondary);">Terms (one per line)</label>
      <textarea
        v-model="terms"
        rows="4"
        class="contact-input resize-none w-full text-[12px]"
        :style="{ borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }"
      />
    </div>
  </div>
</template>
