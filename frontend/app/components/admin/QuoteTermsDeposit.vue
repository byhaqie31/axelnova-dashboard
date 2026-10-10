<script setup lang="ts">
// Shared "Deposit + payment plan + Terms (one per line)" block used by the
// QuotationBuilder (and its inline detailed-proposal section). Binds its state via
// v-model; markup lives here only so the deposit / plan / terms UI never drifts.
//
// Deposit is EITHER a percentage (legacy `deposit_pct`) OR a fixed ringgit amount
// (`deposit_amount_myr`, which wins). The effective % of a fixed amount is derived
// read-only — never stored. The payment plan adds the instalment / partner
// schedule fields; every figure shown here mirrors the backend PaymentPlan class
// (see composables/paymentPlan.ts), which is what the PDF and the order read.
import type { PaymentPlanInputs } from '~/composables/paymentPlan'
import {
  PAYMENT_PLANS,
  PARTNER_DEFAULT_MONTHS,
  depositAmountFor,
  depositLabel,
  fmtRm,
  fmtYmd,
  isScheduled,
  planMonths,
  planTotal,
  scheduleDates,
  variance,
} from '~/composables/paymentPlan'

const props = withDefaults(defineProps<{
  /** Adds the top divider used when this sits inside a multi-section card (detailed builder). */
  separated?: boolean
  /** The quotation total the deposit / plan reconcile against (the document total). */
  total?: number
}>(), { separated: false, total: 0 })

const terms = defineModel<string>('terms', { required: true })
const depositPct = defineModel<number>('depositPct', { required: true })
const plan = defineModel<PaymentPlanInputs>('plan', { required: true })

// ── Deposit: pct ↔ fixed amount ─────────────────────────────────────────────
const depositMode = computed<'pct' | 'amount'>(() => plan.value.deposit_amount_myr != null ? 'amount' : 'pct')

function setDepositMode(mode: 'pct' | 'amount') {
  if (mode === 'amount' && plan.value.deposit_amount_myr == null) {
    // Start the fixed figure at what the pct currently yields, so switching is lossless.
    plan.value.deposit_amount_myr = depositAmountFor(props.total, depositPct.value, null)
  }
  if (mode === 'pct') plan.value.deposit_amount_myr = null
}

const depositDue = computed(() => depositAmountFor(props.total, Number(depositPct.value) || 0, plan.value.deposit_amount_myr))
const depositDerived = computed(() =>
  props.total > 0
    ? `${depositLabel(props.total, depositDue.value)} of RM ${fmtRm(props.total)}`
    : 'Add line items to see the deposit figure.')

// ── Payment plan ─────────────────────────────────────────────────────────────
const planItems = PAYMENT_PLANS.map(p => ({ label: p.label, value: p.value }))
const planHint = computed(() => PAYMENT_PLANS.find(p => p.value === plan.value.payment_plan)?.hint ?? '')
const scheduled = computed(() => isScheduled(plan.value))
const months = computed(() => planMonths(plan.value))
const dates = computed(() => scheduleDates(plan.value))
const planTotalMyr = computed(() => planTotal(props.total, plan.value, depositDue.value))
const varianceMyr = computed(() => variance(props.total, plan.value, depositDue.value))
const reconciles = computed(() => Math.abs(varianceMyr.value) < 0.005)
const showSchedule = ref(false)

const partnerPlan = computed(() => plan.value.payment_plan === 'partner')
const monthlyMyr = computed(() => Number(plan.value.instalment_amount_myr) || 0)
const round2 = (n: number) => Math.round(n * 100) / 100

// The three summary cards: what's paid up front, the monthly run, the plan total.
const planCards = computed(() => [
  {
    key: 'upfront',
    label: partnerPlan.value ? 'Setup fee' : 'Deposit',
    meta: 'On acceptance',
    amount: `RM ${fmtRm(depositDue.value)}`,
    tone: 'text',
  },
  {
    key: 'monthly',
    label: partnerPlan.value ? 'Monthly fee' : 'Monthly instalment',
    meta: dates.value.length
      ? `${months.value} × · ${fmtYmd(dates.value[0]!)} → ${fmtYmd(dates.value[dates.value.length - 1]!)}`
      : `${months.value} × · set a first date`,
    amount: `RM ${fmtRm(monthlyMyr.value)}`,
    tone: 'text',
  },
  {
    key: 'total',
    label: 'Plan total',
    meta: `${partnerPlan.value ? 'Setup' : 'Deposit'} + ${months.value} × monthly`,
    amount: `RM ${fmtRm(planTotalMyr.value)}`,
    tone: props.total > 0 && !reconciles.value ? 'danger' : 'success',
  },
])

// One vertical schedule: the up-front payment, then every instalment, each with
// the balance still owed on the plan total once it is paid.
const scheduleRows = computed(() => {
  const total = planTotalMyr.value
  let paid = depositDue.value
  const rows = [{
    key: 'upfront',
    label: partnerPlan.value ? 'Setup fee' : 'Deposit',
    due: 'On acceptance',
    amount: depositDue.value,
    remaining: Math.max(round2(total - paid), 0),
  }]
  dates.value.forEach((d, i) => {
    paid += monthlyMyr.value
    rows.push({
      key: `${d}-${i}`,
      label: `${partnerPlan.value ? 'Month' : 'Instalment'} ${i + 1}`,
      due: fmtYmd(d),
      amount: monthlyMyr.value,
      remaining: Math.max(round2(total - paid), 0),
    })
  })
  return rows
})

// Partner defaults to 24 months — prefill so the figure is visible, not implied.
watch(() => plan.value.payment_plan, (p) => {
  if (p === 'partner' && !plan.value.instalment_months) plan.value.instalment_months = PARTNER_DEFAULT_MONTHS
})

const fieldStyle = { borderColor: 'var(--color-border)', color: 'var(--color-text)', background: 'var(--color-bg)' }
</script>

<template>
  <div
    class="space-y-5"
    :class="separated ? 'pt-2 border-t' : ''"
    :style="separated ? { borderColor: 'var(--color-border)' } : undefined"
  >
    <!-- Deposit: mode toggle, then the field, then the derived read-only figure -->
    <div class="space-y-2">
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <label class="block text-[12px] font-medium" style="color: var(--color-text-secondary);">Deposit</label>
        <div class="inline-flex shrink-0 items-center rounded-lg border p-0.5" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }">
          <button
            type="button" class="px-2.5 py-1 max-md:px-3.5 max-md:py-2 rounded-md text-[12px] font-medium transition-colors"
            :style="depositMode === 'pct' ? { background: 'var(--color-accent-soft)', color: 'var(--color-accent)' } : { color: 'var(--color-text-secondary)' }"
            @click="setDepositMode('pct')">Percentage</button>
          <button
            type="button" class="px-2.5 py-1 max-md:px-3.5 max-md:py-2 rounded-md text-[12px] font-medium transition-colors"
            :style="depositMode === 'amount' ? { background: 'var(--color-accent-soft)', color: 'var(--color-accent)' } : { color: 'var(--color-text-secondary)' }"
            @click="setDepositMode('amount')">Fixed amount</button>
        </div>
      </div>
      <div class="flex items-center gap-3 flex-wrap">
        <div v-if="depositMode === 'pct'" class="relative">
          <input
            v-model.number="depositPct"
            type="number"
            min="0"
            max="100"
            class="contact-input pr-8 text-right"
            :style="{ width: '6.5rem', ...fieldStyle }"
          >
          <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[12px] pointer-events-none" style="color: var(--color-text-tertiary);">%</span>
        </div>
        <div v-else class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[12px] pointer-events-none" style="color: var(--color-text-tertiary);">RM</span>
          <input
            v-model.number="plan.deposit_amount_myr"
            type="number"
            min="0"
            step="0.01"
            class="contact-input pl-9 text-right"
            :style="{ width: '9rem', ...fieldStyle }"
          >
        </div>
        <p class="text-[12px] tabular-nums" :style="{ color: props.total > 0 ? 'var(--color-text)' : 'var(--color-text-tertiary)' }">
          <UIcon name="i-lucide-equal" class="size-3 inline-block align-[-1px] mr-1" style="color: var(--color-text-tertiary);" />{{ depositDerived }}
        </p>
      </div>
      <p v-if="depositMode === 'amount'" class="text-[11px]" style="color: var(--color-text-tertiary);">
        The fixed amount is what the client sees; the percentage is derived for display and never stored.
      </p>
    </div>

    <!-- Payment plan -->
    <div class="space-y-3 pt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
      <div class="grid sm:grid-cols-[14rem_1fr] gap-3 sm:items-end">
        <div class="space-y-1.5">
          <label class="block text-[12px] font-medium" style="color: var(--color-text-secondary);">Payment plan</label>
          <AdminSelect v-model="plan.payment_plan" :items="planItems" class="w-full" />
        </div>
        <p class="text-[11px] sm:pb-2.5" style="color: var(--color-text-tertiary);">{{ planHint }}</p>
      </div>

      <template v-if="scheduled">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div class="space-y-1.5">
            <span class="d-label">{{ plan.payment_plan === 'partner' ? 'Months' : 'Instalments' }}</span>
            <input v-model.number="plan.instalment_months" type="number" min="1" max="120" :placeholder="plan.payment_plan === 'partner' ? String(PARTNER_DEFAULT_MONTHS) : '12'" class="contact-input w-full text-[13px] text-right" :style="fieldStyle">
          </div>
          <div class="space-y-1.5">
            <span class="d-label">{{ plan.payment_plan === 'partner' ? 'Monthly fee' : 'Monthly amount' }}</span>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[12px] pointer-events-none" style="color: var(--color-text-tertiary);">RM</span>
              <input v-model.number="plan.instalment_amount_myr" type="number" min="0" step="0.01" class="contact-input w-full text-[13px] pl-9 text-right" :style="fieldStyle">
            </div>
          </div>
          <div class="space-y-1.5">
            <span class="d-label">Billing day (1–28)</span>
            <input v-model.number="plan.billing_day" type="number" min="1" max="28" class="contact-input w-full text-[13px] text-right" :style="fieldStyle">
          </div>
          <div class="space-y-1.5">
            <span class="d-label">First {{ plan.payment_plan === 'partner' ? 'payment' : 'instalment' }}</span>
            <input v-model="plan.first_instalment_date" type="date" class="contact-input w-full text-[13px] max-md:min-w-0" :style="fieldStyle">
          </div>
        </div>
        <label class="inline-flex items-center gap-2 text-[12px] max-md:text-[13px]" style="color: var(--color-text-secondary);">
          <input v-model="plan.includes_care_plan" type="checkbox"> Monthly figure includes the care plan
        </label>

        <!-- Derived: up-front / monthly / plan-total cards, reconciliation, then the schedule -->
        <div class="rounded-xl border p-4 max-md:p-3 space-y-3" :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg)' }">
          <div class="grid sm:grid-cols-3 gap-2.5">
            <div
              v-for="c in planCards" :key="c.key"
              class="rounded-lg border px-3.5 py-3 flex flex-col gap-1 min-w-0"
              :style="{ borderColor: 'var(--color-border)', background: 'var(--color-bg-elevated)' }"
            >
              <span class="d-label">{{ c.label }}</span>
              <span
                class="text-[17px] font-semibold tabular-nums tracking-tight"
                :style="{ color: c.tone === 'danger' ? 'var(--color-danger)' : c.tone === 'success' ? 'var(--color-success)' : 'var(--color-text)' }"
              >{{ c.amount }}<span v-if="c.key === 'monthly'" class="text-[11px] font-normal" style="color: var(--color-text-tertiary);"> / month</span></span>
              <span class="text-[11px] tabular-nums truncate" style="color: var(--color-text-tertiary);" :title="c.meta">{{ c.meta }}</span>
            </div>
          </div>

          <p class="text-[12px] tabular-nums flex items-start gap-1.5" :style="{ color: reconciles ? 'var(--color-success)' : 'var(--color-danger)' }">
            <UIcon :name="reconciles ? 'i-lucide-circle-check' : 'i-lucide-triangle-alert'" class="size-3.5 shrink-0 mt-px" />
            <span>
              <template v-if="props.total <= 0">Add line items to reconcile the plan total.</template>
              <template v-else-if="reconciles">Plan total matches the quotation total.</template>
              <template v-else>Plan total is RM {{ fmtRm(Math.abs(varianceMyr)) }} {{ varianceMyr < 0 ? 'over' : 'under' }} the quotation total (RM {{ fmtRm(props.total) }}). Fine if you rounded the monthly figure on purpose — the PDF prints the plan total.</template>
            </span>
          </p>

          <div v-if="dates.length" class="pt-1 border-t" :style="{ borderColor: 'var(--color-border)' }">
            <button
              type="button"
              class="mt-2 inline-flex items-center gap-1.5 text-[12px] max-md:text-[13px] max-md:py-1.5 font-medium"
              style="color: var(--color-accent);"
              :aria-expanded="showSchedule"
              @click="showSchedule = !showSchedule"
            >
              <UIcon :name="showSchedule ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'" class="size-3.5" />
              {{ showSchedule ? 'Hide schedule' : `Show schedule (${scheduleRows.length} payments)` }}
            </button>

            <div v-if="showSchedule" class="mt-2 max-h-[22rem] overflow-y-auto rounded-lg border" :style="{ borderColor: 'var(--color-border)' }">
              <table class="w-full text-[12px] tabular-nums">
                <thead class="sticky top-0" :style="{ background: 'var(--color-bg-elevated)' }">
                  <!-- Not .d-label: its display:block would pull each <th> out of the table row. -->
                  <tr class="text-left text-[10px] font-semibold uppercase tracking-wide" style="color: var(--color-text-tertiary);">
                    <th class="px-3 py-2 font-semibold">Payment</th>
                    <th class="px-3 py-2 font-semibold">Due</th>
                    <th class="px-3 py-2 font-semibold text-right">Amount</th>
                    <th class="px-3 py-2 font-semibold text-right">Balance after</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="r in scheduleRows" :key="r.key"
                    class="border-t" :style="{ borderColor: 'var(--color-border)' }"
                  >
                    <td class="px-3 py-1.5 whitespace-nowrap" :style="{ color: r.key === 'upfront' ? 'var(--color-text)' : 'var(--color-text-secondary)', fontWeight: r.key === 'upfront' ? 500 : 400 }">{{ r.label }}</td>
                    <td class="px-3 py-1.5 whitespace-nowrap" style="color: var(--color-text-secondary);">{{ r.due }}</td>
                    <td class="px-3 py-1.5 text-right whitespace-nowrap" style="color: var(--color-text);">RM {{ fmtRm(r.amount) }}</td>
                    <td class="px-3 py-1.5 text-right whitespace-nowrap" :style="{ color: r.remaining > 0 ? 'var(--color-text-tertiary)' : 'var(--color-success)' }">{{ r.remaining > 0 ? `RM ${fmtRm(r.remaining)}` : 'Paid off' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <p v-else class="text-[11px]" style="color: var(--color-text-tertiary);">Set the first {{ partnerPlan ? 'payment' : 'instalment' }} date to see the schedule.</p>
        </div>
      </template>
    </div>

    <!-- Terms: title above, full-width field below -->
    <div class="space-y-1.5 pt-2 border-t" :style="{ borderColor: 'var(--color-border)' }">
      <label class="block text-[12px] font-medium" style="color: var(--color-text-secondary);">Terms (one per line)</label>
      <textarea
        v-model="terms"
        rows="4"
        class="contact-input resize-none w-full text-[12px]"
        :style="fieldStyle"
      />
      <p class="text-[11px]" style="color: var(--color-text-tertiary);">The standard deposit bullet follows the deposit and plan above; edit its wording freely — only the figures are kept in step.</p>
    </div>
  </div>
</template>

<style scoped>
.d-label {
  display: block;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-tertiary);
}

/* Mobile: ≥16px form text stops iOS Safari zooming the page on focus. */
@media (max-width: 767.98px) {
  input:not([type='checkbox'], [type='radio']),
  textarea { font-size: 16px; }
}
</style>
