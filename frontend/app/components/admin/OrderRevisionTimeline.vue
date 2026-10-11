<script setup lang="ts">
// The order's revision chain (QuotationRevisions §1.3): the terms it has run on,
// oldest first — "Revised to R2 — <reason> — by <user>". Shown once an order has
// been revised; a lone R1 needs no timeline.
import { formatMyr } from '~/utils/money'

export interface OrderRevision {
  number: number
  label: string
  status: string
  is_current: boolean
  change_reason: string | null
  estimate_myr: number
  created_by: string | null
  created_at: string | null
  accepted_at: string | null
  superseded_at: string | null
}

defineProps<{ revisions: OrderRevision[], quotationId: number }>()

function fmtDay(iso?: string | null) {
  return iso ? new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' }) : ''
}
</script>

<template>
  <div
    v-if="revisions.length > 1"
    class="rounded-2xl border p-6 max-md:p-5"
    :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
    <p class="text-[11px] font-semibold uppercase tracking-widest mb-4" style="color: var(--color-text-tertiary);">Revisions</p>
    <ol class="space-y-4">
      <li v-for="r in revisions" :key="r.number" class="flex gap-3">
        <span
          class="mt-1 size-2.5 rounded-full shrink-0"
          :style="{ background: r.is_current ? 'var(--color-accent)' : 'var(--color-border)' }" />
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <NuxtLink
              :to="`/admin/quotations/${quotationId}?revision=${r.number}`"
              class="font-mono text-[13px]" :class="r.is_current ? 'font-bold' : 'font-medium'"
              style="color: var(--color-accent);">{{ r.label }}</NuxtLink>
            <AdminStatusPill :status="r.status" />
            <span class="text-[12px] tabular-nums" style="color: var(--color-text);">{{ formatMyr(r.estimate_myr) }}</span>
          </div>
          <p v-if="r.change_reason" class="text-[13px] mt-1 max-md:break-words" style="color: var(--color-text);">
            Revised to {{ r.label }} — {{ r.change_reason }}<span v-if="r.created_by" style="color: var(--color-text-tertiary);"> — by {{ r.created_by }}</span>
          </p>
          <p class="text-[11px] mt-0.5 tabular-nums" style="color: var(--color-text-tertiary);">
            <template v-if="r.accepted_at">Accepted {{ fmtDay(r.accepted_at) }}</template>
            <template v-else-if="r.created_at">Opened {{ fmtDay(r.created_at) }}</template>
            <template v-if="r.superseded_at"> · superseded {{ fmtDay(r.superseded_at) }}</template>
          </p>
        </div>
      </li>
    </ol>
  </div>
</template>
