<script setup lang="ts">
// Stub until Phase 5 (in-system payroll ledger, record-only). Everyone will see
// their own payslips here; the backend `/v1/team/payslips` endpoint lands with
// that phase. Kept as a real route so the nav entry resolves today.
definePageMeta({ layout: 'team', middleware: 'team-auth' })
useHead({ title: 'Payslips — Team' })
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-10 pb-32">
    <h1 class="text-[28px] font-bold tracking-tight mb-1" style="color: var(--color-text);">Payslips</h1>
    <p class="text-[14px] mb-8" style="color: var(--color-text-secondary);">Your payroll records, once the ledger is live.</p>

    <div
      class="rounded-2xl border px-6 py-12 text-center"
      :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }"
    >
      <span
        class="size-12 rounded-2xl inline-flex items-center justify-center mb-4"
        :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }"
      >
        <UIcon name="i-lucide-wallet" class="size-6" />
      </span>
      <p class="text-[15px] font-semibold tracking-tight mb-1" style="color: var(--color-text);">Coming with payroll (Phase 5)</p>
      <p class="text-[13px] max-w-sm mx-auto leading-relaxed" style="color: var(--color-text-secondary);">
        The in-system payroll ledger is record-only. When it ships you'll find your own payslips listed here.
      </p>
    </div>
  </div>
</template>
