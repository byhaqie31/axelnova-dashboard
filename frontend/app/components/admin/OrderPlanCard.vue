<script setup lang="ts">
// The order's payment plan, row by row, each payment with the invoice billing
// it (current revision only) — and, once the order has been revised, how each
// was netted against what the client had already paid: settled by those
// payments, topped up by the revision's adjustment invoice, or due as normal.
// Handles every plan shape: instalment / partner (deposit + monthly schedule),
// a lump sum billed by phase (40/40/20 …), and a plain lump sum after a revise.
// Care Plan coverage periods sit underneath. Read-only: billing happens in the
// Invoices module, reached through each row's "Issue" link.
import type { NettedState, OrderPlanView, PlanInvoiceRef } from '~/composables/paymentPlan'
import { fmtRm, fmtYm, fmtYmd } from '~/composables/paymentPlan'

export interface CarePlanPeriod {
  kind: 'included' | 'separate'
  monthly_myr: number | null
  starts_on: string
  ends_on: string | null
  ended_at: string | null
  end_reason: string | null
  revision_number: number | null
}

const props = defineProps<{ orderId: number, plan: OrderPlanView | null, carePlans?: CarePlanPeriod[] }>()

interface Row {
  key: string
  label: string
  due: string
  amount: number
  invoice: PlanInvoiceRef | null
  netting: NettedState | null
  issueHref: string
  next: boolean
}

const scheduled = computed(() => props.plan?.plan === 'instalment' || props.plan?.plan === 'partner')

const rows = computed<Row[]>(() => {
  const p = props.plan
  if (!p) return []
  const base = `/admin/invoices/new?order_id=${props.orderId}`
  if (p.phased) {
    return p.phases.map(ph => ({
      key: `phase:${ph.n}`, label: ph.label, due: ph.pct_label, amount: ph.amount,
      invoice: ph.invoice, netting: ph.netting, issueHref: `${base}&phase=${ph.n}`, next: ph.n === p.next_phase_no,
    }))
  }
  const deposit: Row = {
    key: 'deposit', label: p.deposit_label, due: 'On acceptance', amount: p.deposit_myr,
    invoice: p.deposit_invoice, netting: p.deposit_netting, issueHref: base, next: false,
  }
  if (scheduled.value) {
    return [deposit, ...p.schedule.map(r => ({
      key: `instalment:${r.n}`, label: r.label, due: fmtYmd(r.date), amount: r.amount,
      invoice: r.invoice, netting: r.netting, issueHref: `${base}&instalment=${r.n}`, next: r.n === p.next_instalment_no,
    }))]
  }
  // A plain lump sum only shows here after a revise — deposit + balance as netted.
  const balance = p.netting?.items.find(i => i.key === 'balance')
  return [deposit, ...(balance ? [{
    key: 'balance', label: balance.label, due: 'On completion', amount: balance.amount,
    invoice: null, netting: p.balance_netting, issueHref: base, next: false,
  }] : [])]
})

const planChip = computed(() => {
  const p = props.plan
  if (!p) return ''
  if (p.phased) return 'By phase'
  return ({ instalment: 'Instalment', partner: 'Partner', lump_sum: 'Lump sum' } as Record<string, string>)[p.plan] ?? p.plan
})

const summary = computed(() => {
  const p = props.plan
  if (!p) return ''
  if (p.phased) return `${p.phases.map(ph => ph.pct_label).join(' / ')} · total RM ${fmtRm(p.plan_total_myr)}`
  if (scheduled.value) {
    return `${p.deposit_label} RM ${fmtRm(p.deposit_myr)} + ${p.months} × RM ${fmtRm(p.monthly_myr)} on the ${p.billing_day}`
      + `${p.includes_care_plan ? ' · includes care plan' : ''} · total RM ${fmtRm(p.plan_total_myr)}`
  }
  return `${p.deposit_label} RM ${fmtRm(p.deposit_myr)} + balance on completion · total RM ${fmtRm(p.plan_total_myr)}`
})

const careActive = computed(() => (props.carePlans ?? []).filter(c => !c.ended_at))
const separateActive = computed(() => careActive.value.find(c => c.kind === 'separate') ?? null)
function carePeriodLabel(c: CarePlanPeriod): string {
  const empty = c.ends_on && c.ends_on < c.starts_on
  if (empty) return 'no month billed'
  return `${fmtYm(c.starts_on)} → ${c.ends_on ? fmtYm(c.ends_on) : 'ongoing'}`
}
</script>

<template>
  <div
    v-if="plan || (carePlans?.length ?? 0) > 0"
    class="rounded-2xl border p-6 max-md:p-5"
    :style="{ background: 'var(--color-bg-elevated)', borderColor: 'var(--color-border)' }">
    <template v-if="plan">
      <div class="flex items-center justify-between gap-3 mb-1">
        <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Payment plan</p>
        <span
          class="text-[11px] font-semibold uppercase tracking-wider rounded-full px-2.5 py-1"
          :style="{ background: 'var(--color-accent-soft)', color: 'var(--color-accent)' }">{{ planChip }}</span>
      </div>
      <p class="text-[12px] tabular-nums mb-4" style="color: var(--color-text-secondary);">{{ summary }}</p>

      <!-- After a revise: what was carried over, voided, and topped up. -->
      <div
        v-if="plan.netting"
        class="rounded-xl border px-3.5 py-3 mb-4 flex items-start gap-2.5 text-[12px] max-md:text-[13px]"
        :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
        <UIcon name="i-lucide-git-compare-arrows" class="size-4 shrink-0 mt-px" style="color: var(--color-accent);" />
        <div class="min-w-0 space-y-0.5 tabular-nums">
          <p style="color: var(--color-text);">
            <span class="font-semibold">Netted on R{{ plan.netting.revision }}</span>
            · RM {{ fmtRm(plan.netting.paid_myr) }} paid under R{{ plan.netting.from_revision }} carried into this schedule
          </p>
          <p style="color: var(--color-text-tertiary);">
            <template v-if="plan.netting.overpaid_myr > 0">Overpaid by RM {{ fmtRm(plan.netting.overpaid_myr) }} — nothing new is billed.</template>
            <template v-else-if="plan.netting.adjustment_myr > 0">Adjustment invoice for RM {{ fmtRm(plan.netting.adjustment_myr) }}.</template>
            <template v-else>No top-up needed.</template>
            <template v-if="plan.netting.voided_invoices.length"> · voided {{ plan.netting.voided_invoices.join(', ') }}</template>
          </p>
        </div>
      </div>

      <div data-lenis-prevent class="max-h-[24rem] overflow-y-auto overscroll-contain rounded-xl border" :style="{ borderColor: 'var(--color-border)' }">
        <table class="w-full text-[12px] max-md:text-[13px] tabular-nums">
          <thead class="sticky top-0" :style="{ background: 'var(--color-bg)' }">
            <tr class="text-left text-[10px] font-semibold uppercase tracking-wide" style="color: var(--color-text-tertiary);">
              <th class="px-3 py-2 font-semibold">Payment</th>
              <th class="px-3 py-2 font-semibold">{{ plan.phased ? 'Share' : 'Due' }}</th>
              <th class="px-3 py-2 font-semibold text-right">Amount</th>
              <th class="px-3 py-2 font-semibold text-right">Invoice</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.key" class="border-t align-top" :style="{ borderColor: 'var(--color-border)' }">
              <td class="px-3 py-2" :style="{ color: r.key === 'deposit' || r.key === 'phase:1' ? 'var(--color-text)' : 'var(--color-text-secondary)', fontWeight: r.key === 'deposit' || r.key === 'phase:1' ? 500 : 400 }">{{ r.label }}</td>
              <td class="px-3 py-2 whitespace-nowrap" style="color: var(--color-text-secondary);">{{ r.due }}</td>
              <td class="px-3 py-2 text-right whitespace-nowrap" style="color: var(--color-text);">
                RM {{ fmtRm(r.amount) }}
                <span v-if="r.netting?.state === 'adjustment'" class="block text-[11px]" style="color: var(--color-text-tertiary);">
                  RM {{ fmtRm(r.netting.covered_myr) }} paid · RM {{ fmtRm(r.netting.due_myr) }} top-up
                </span>
              </td>
              <td class="px-3 py-2 text-right whitespace-nowrap">
                <NuxtLink v-if="r.invoice" :to="`/admin/invoices/${r.invoice.id}`" class="inline-flex items-center gap-1.5 font-mono" style="color: var(--color-accent);">
                  {{ r.invoice.number }} <AdminStatusPill :status="r.invoice.status" />
                </NuxtLink>
                <span v-else-if="r.netting?.state === 'settled'" class="inline-flex items-center gap-1 text-[11px] font-medium" style="color: var(--color-success);">
                  <UIcon name="i-lucide-circle-check" class="size-3.5" /> Settled by earlier payments
                </span>
                <NuxtLink
                  v-else-if="r.netting?.state === 'adjustment' && r.netting.invoice" :to="`/admin/invoices/${r.netting.invoice.id}`"
                  class="inline-flex items-center gap-1.5 font-mono" style="color: var(--color-accent);">
                  {{ r.netting.invoice.number }} <AdminStatusPill :status="r.netting.invoice.status" />
                </NuxtLink>
                <NuxtLink
                  v-else :to="r.issueHref" class="font-medium"
                  :style="{ color: r.next ? 'var(--color-accent)' : 'var(--color-text-tertiary)' }">
                  Issue
                </NuxtLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Care Plan coverage: closed and reopened on a revise, so no month is billed twice or skipped. -->
    <div v-if="carePlans?.length" :class="plan ? 'mt-5 pt-5 border-t' : ''" :style="plan ? { borderColor: 'var(--color-border)' } : undefined">
      <div class="flex items-center justify-between gap-3 mb-2">
        <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--color-text-tertiary);">Care Plan</p>
        <NuxtLink
          v-if="separateActive" :to="`/admin/invoices/new?order_id=${orderId}&care=next`"
          class="text-[12px] font-medium" style="color: var(--color-accent);">Bill next month</NuxtLink>
      </div>
      <ul class="space-y-1.5">
        <li
          v-for="(c, i) in carePlans" :key="i"
          class="flex items-start justify-between gap-3 text-[12px] max-md:text-[13px] tabular-nums"
          :style="{ color: c.ended_at ? 'var(--color-text-tertiary)' : 'var(--color-text)' }">
          <span class="min-w-0">
            {{ c.kind === 'included' ? 'Included in the monthly figure' : `Separate · RM ${fmtRm(Number(c.monthly_myr) || 0)} / month` }}
            <span v-if="c.revision_number" style="color: var(--color-text-tertiary);"> · R{{ c.revision_number }}</span>
            <span v-if="c.end_reason" class="block text-[11px]">{{ c.end_reason }}</span>
          </span>
          <span class="shrink-0 text-right">{{ carePeriodLabel(c) }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>
