// TS port of the backend `App\Services\Quoting\PaymentPlan` — the deposit +
// payment-plan arithmetic the admin builder needs LIVE while editing (the
// fixed-wins rule, pct → amount rounding, the effective pct display, plan total
// / variance, schedule dates, the terms bullet). The PHP class is the source of
// truth: the PDF, the resource and the order flow all read it server-side; this
// mirror only drives the form's read-only displays. KEEP THE TWO IN SYNC.
//
// Nothing here words the PDF: the deposit / monthly cards and the Payment plan
// section are derived by the mapper at render time and labelled from the
// renderer's locale file (server/utils/pdf/locales.ts), so no PDF chrome —
// English or BM — is baked into a stored document by the builder.

import { formatAmount } from '~/utils/money'

export type PaymentPlanKind = 'lump_sum' | 'instalment' | 'partner'

/**
 * Invoice types. `instalment` bills one numbered payment of an instalment /
 * partner plan, `phase` one phase of a phased lump sum, `care_plan` one month
 * of a separate Care Plan, and `adjustment` is the top-up a revision issues
 * when it nets the new schedule against what was already paid.
 */
export type InvoiceType = 'deposit' | 'partial' | 'final' | 'instalment' | 'phase' | 'care_plan' | 'adjustment'

/** "Instalment 3" / "Phase 2" / "Care Plan · Nov 2026" / "Deposit" — a short invoice-type chip label. */
export function invoiceTypeLabel(type: string, instalmentNo?: number | null, carePeriod?: string | null): string {
  if (type === 'instalment') return instalmentNo ? `Instalment ${instalmentNo}` : 'Instalment'
  if (type === 'phase') return instalmentNo ? `Phase ${instalmentNo}` : 'Phase'
  if (type === 'care_plan') return carePeriod ? `Care Plan · ${fmtYm(carePeriod)}` : 'Care Plan'
  return ({ deposit: 'Deposit', partial: 'Partial', final: 'Final', adjustment: 'Adjustment' } as Record<string, string>)[type] ?? type
}

/** A live invoice reference on an order's plan view. */
export interface PlanInvoiceRef { id: number; number: string; status: 'issued' | 'paid' | 'void' }

/**
 * After a revision: how one schedule item was netted against what the client
 * had already paid (Order::planView → ScheduleNetting) — settled by prior
 * payment, topped up by the adjustment invoice, or due as normal.
 */
export interface NettedState {
  state: 'settled' | 'adjustment' | 'due'
  covered_myr: number
  due_myr: number
  invoice: PlanInvoiceRef | null
}

/**
 * The order's agreed plan (OrderResource `payment_plan`, built by
 * Order::planView) — each instalment / phase with the live invoice billing it
 * and, after a revise, its netting. Null for a plain lump sum never revised.
 */
export interface OrderPlanView {
  plan: PaymentPlanKind
  phased: boolean
  /** "Deposit" / "Setup fee" */
  deposit_label: string
  deposit_myr: number
  deposit_invoice: PlanInvoiceRef | null
  deposit_netting: NettedState | null
  balance_netting: NettedState | null
  months: number
  monthly_myr: number
  billing_day: number
  includes_care_plan: boolean
  care_plan_monthly_myr: number | null
  first_date: string | null
  last_date: string | null
  plan_total_myr: number
  next_instalment_no: number | null
  next_phase_no: number | null
  schedule: { n: number; label: string; date: string; amount: number; invoice: PlanInvoiceRef | null; netting: NettedState | null }[]
  phases: { n: number; label: string; name: string; pct: number; pct_label: string; amount: number; invoice: PlanInvoiceRef | null; netting: NettedState | null }[]
  netting: {
    from_revision: number | null
    revision: number | null
    netted_at: string | null
    paid_myr: number
    total_myr: number
    overpaid_myr: number
    adjustment_myr: number
    voided_invoices: string[]
    items: { key: string; label: string; amount: number; state: NettedState['state']; covered_myr: number; due_myr: number; invoice: PlanInvoiceRef | null }[]
  } | null
}

export const PAYMENT_PLANS: { value: PaymentPlanKind; label: string; hint: string }[] = [
  { value: 'lump_sum', label: 'Lump sum', hint: 'Deposit to commence, balance on completion.' },
  { value: 'instalment', label: 'Instalment', hint: 'Deposit on acceptance, then a fixed monthly amount.' },
  { value: 'partner', label: 'Partner (monthly)', hint: 'Setup fee, then a monthly partnership fee — 24 months by default.' },
]

export const DEFAULT_BILLING_DAY = 20
export const PARTNER_DEFAULT_MONTHS = 24

/** A lump sum billed by phase has MIN_PHASES…MAX_PHASES phases summing to 100%. */
export const MIN_PHASES = 2
export const MAX_PHASES = 10

/** One phase of a lump sum: the founder's label (content, printed as typed) + its share. */
export interface PaymentPhase { label: string; pct: number }

/** Quick starts for the phase editor — any split that adds up to 100% works. */
export const PHASE_PRESETS: { label: string; phases: PaymentPhase[] }[] = [
  { label: '50 / 50', phases: [{ label: 'On acceptance', pct: 50 }, { label: 'On completion', pct: 50 }] },
  { label: '40 / 40 / 20', phases: [{ label: 'On acceptance', pct: 40 }, { label: 'At demo', pct: 40 }, { label: 'At go-live', pct: 20 }] },
  { label: '20 × 5', phases: [1, 2, 3, 4, 5].map(n => ({ label: n === 1 ? 'On acceptance' : `Milestone ${n}`, pct: 20 })) },
]

/** The plan inputs a writer stores on `document` beside `deposit_pct`. */
export interface PaymentPlanInputs {
  payment_plan: PaymentPlanKind
  /** Fixed deposit in ringgit — wins over deposit_pct when set. */
  deposit_amount_myr: number | null
  instalment_months: number | null
  instalment_amount_myr: number | null
  /** 1–28 */
  billing_day: number
  /** YYYY-MM-DD or '' */
  first_instalment_date: string
  includes_care_plan: boolean
  /** Lump sum only: the by-phase split ([] = the classic deposit + balance). */
  phases: PaymentPhase[]
  /** A separate monthly Care Plan subscription (RM / month), null = none. */
  care_plan_monthly_myr: number | null
}

export function defaultPlanInputs(): PaymentPlanInputs {
  return {
    payment_plan: 'lump_sum',
    deposit_amount_myr: null,
    instalment_months: null,
    instalment_amount_myr: null,
    billing_day: DEFAULT_BILLING_DAY,
    first_instalment_date: '',
    includes_care_plan: false,
    phases: [],
    care_plan_monthly_myr: null,
  }
}

/**
 * A usable phase list or [] — mirrors PaymentPlan::normalisePhases: 2…10 rows,
 * each pct > 0, summing to 100 (±0.01); a blank label becomes "Phase n".
 */
export function normalisePhases(raw: unknown): PaymentPhase[] {
  if (!Array.isArray(raw) || raw.length < MIN_PHASES || raw.length > MAX_PHASES) return []
  const phases: PaymentPhase[] = []
  for (const [i, row] of raw.entries()) {
    const pct = Math.round((Number((row as PaymentPhase)?.pct) || 0) * 100) / 100
    if (pct <= 0) return []
    const label = String((row as PaymentPhase)?.label ?? '').trim()
    phases.push({ label: label || `Phase ${i + 1}`, pct })
  }
  return Math.abs(phasesTotalPct(phases) - 100) < 0.01 ? phases : []
}

export function phasesTotalPct(phases: PaymentPhase[]): number {
  return Math.round(phases.reduce((sum, p) => sum + (Number(p.pct) || 0), 0) * 100) / 100
}

export function isPhased(p: PaymentPlanInputs): boolean {
  return p.payment_plan === 'lump_sum' && normalisePhases(p.phases).length > 0
}

/**
 * Each phase in ringgit: round(total × pct / 100) to the ringgit, the LAST
 * phase taking the remainder so the phases sum to the total exactly
 * (PaymentPlan::phaseAmounts). 40/40/20 of RM 10,800 → 4,320 / 4,320 / 2,160.
 */
export function phaseAmounts(total: number, phases: PaymentPhase[]): number[] {
  const t = Math.max(Number(total) || 0, 0)
  let running = 0
  return phases.map((p, i) => {
    const amount = i === phases.length - 1
      ? Math.round((t - running) * 100) / 100
      : Math.round(t * (Number(p.pct) || 0) / 100)
    running += amount
    return Math.max(amount, 0)
  })
}

/** "40%" / "33.33%" — a phase share, trimmed when integral (PaymentPlan::pctLabel). */
export function phasePctLabel(pct: number): string {
  const n = Math.round((Number(pct) || 0) * 100) / 100
  return `${Number.isInteger(n) ? n.toFixed(0) : String(n)}%`
}

/** Hydrate from a stored `document` (absent keys → defaults, i.e. a legacy pct-only lump sum). */
export function planInputsFromDocument(doc: Record<string, unknown> | null | undefined): PaymentPlanInputs {
  const d = doc ?? {}
  const plan = (['lump_sum', 'instalment', 'partner'] as string[]).includes(String(d.payment_plan)) ? d.payment_plan as PaymentPlanKind : 'lump_sum'
  const num = (v: unknown): number | null => (v === null || v === undefined || v === '' || Number.isNaN(Number(v))) ? null : Number(v)
  return {
    payment_plan: plan,
    deposit_amount_myr: num(d.deposit_amount_myr),
    instalment_months: num(d.instalment_months),
    instalment_amount_myr: num(d.instalment_amount_myr),
    billing_day: Math.min(28, Math.max(1, Number(d.billing_day) || DEFAULT_BILLING_DAY)),
    first_instalment_date: typeof d.first_instalment_date === 'string' ? d.first_instalment_date.slice(0, 10) : '',
    includes_care_plan: !!d.includes_care_plan,
    phases: plan === 'lump_sum' ? normalisePhases(d.phases) : [],
    care_plan_monthly_myr: num(d.care_plan_monthly_myr),
  }
}

/**
 * The keys to write onto `document`. Mirrors PaymentPlan::inputsFrom: a plain
 * pct-only lump sum writes NOTHING here, so an untouched legacy draft re-saves
 * in its legacy shape; a fixed amount or a non-default plan writes the inputs.
 */
export function toDocumentKeys(p: PaymentPlanInputs): Record<string, unknown> {
  const out: Record<string, unknown> = {}
  if (p.deposit_amount_myr != null) out.deposit_amount_myr = Number(p.deposit_amount_myr) || 0
  if (p.payment_plan !== 'lump_sum' || p.deposit_amount_myr != null) out.payment_plan = p.payment_plan
  if (p.payment_plan !== 'lump_sum') {
    out.instalment_months = planMonths(p)
    out.instalment_amount_myr = Number(p.instalment_amount_myr) || 0
    out.billing_day = p.billing_day
    if (p.first_instalment_date) out.first_instalment_date = p.first_instalment_date
    out.includes_care_plan = !!p.includes_care_plan
  }
  else if (p.phases.length) {
    // Sent as typed — an unbalanced split comes back as the API's 422
    // ("must add up to 100%") instead of silently saving a plain lump sum.
    out.payment_plan = 'lump_sum'
    out.phases = p.phases.map(ph => ({ label: String(ph.label ?? '').trim(), pct: Number(ph.pct) || 0 }))
  }
  if (p.care_plan_monthly_myr != null && Number(p.care_plan_monthly_myr) > 0) {
    out.care_plan_monthly_myr = Number(p.care_plan_monthly_myr)
  }
  return out
}

/** The up-front payment: phase 1 of a phased lump sum, else the deposit (fixed wins over pct). */
export function planDeposit(total: number, pct: number, p: PaymentPlanInputs): number {
  if (isPhased(p)) return phaseAmounts(total, normalisePhases(p.phases))[0] ?? 0
  return depositAmountFor(total, pct, p.deposit_amount_myr)
}

export function isScheduled(p: PaymentPlanInputs): boolean {
  return p.payment_plan !== 'lump_sum'
}

/** Partner defaults to 24 months when blank. */
export function planMonths(p: PaymentPlanInputs): number {
  const m = Number(p.instalment_months) || 0
  return m > 0 ? m : (p.payment_plan === 'partner' ? PARTNER_DEFAULT_MONTHS : 0)
}

/**
 * The deposit due: the fixed amount wins; else pct × total rounded to the
 * nearest ringgit (halves up — Math.round, same result as PHP for positives).
 * Never above the total.
 */
export function depositAmountFor(total: number, pct: number, fixed: number | null | undefined): number {
  const t = Math.max(Number(total) || 0, 0)
  const raw = fixed != null ? Number(fixed) || 0 : Math.round(t * ((Number(pct) || 0) / 100))
  return Math.min(Math.max(raw, 0), t)
}

export function effectivePct(total: number, amount: number): number {
  return total > 0 ? (amount / total) * 100 : 0
}

/** "18.8%" — one decimal, trimmed when integral ("50%"). */
export function pctLabel(total: number, amount: number): string {
  const pct = Math.round(effectivePct(total, amount) * 10) / 10
  return `${Number.isInteger(pct) ? pct.toFixed(0) : pct.toFixed(1)}%`
}

/** "2,700.00" / "970.50" — always two decimals (mirrors PHP `PaymentPlan::fmt()`). */
export function fmtRm(n: number): string {
  return formatAmount(n)
}

/** "RM 2,700.00 · 18.8%" */
export function depositLabel(total: number, amount: number): string {
  return `RM ${fmtRm(amount)} · ${pctLabel(total, amount)}`
}

/** deposit + months × monthly for a scheduled plan; the quotation total otherwise. */
export function planTotal(total: number, p: PaymentPlanInputs, deposit: number): number {
  if (!isScheduled(p)) return total
  return Math.round((deposit + planMonths(p) * (Number(p.instalment_amount_myr) || 0)) * 100) / 100
}

/** total − planTotal: positive = the plan collects less than quoted, negative = more. */
export function variance(total: number, p: PaymentPlanInputs, deposit: number): number {
  return Math.round((total - planTotal(total, p, deposit)) * 100) / 100
}

// ── Schedule (UTC date-only arithmetic, no timezone drift) ───────────────────

function parseYmd(s: string): Date | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s)
  if (!m) return null
  return new Date(Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3])))
}

function ymd(d: Date): string {
  return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}-${String(d.getUTCDate()).padStart(2, '0')}`
}

function daysInMonth(y: number, m0: number): number {
  return new Date(Date.UTC(y, m0 + 1, 0)).getUTCDate()
}

/** Instalment i (0-based): i = 0 is the first date as given; later ones fall on the billing day, clamped to the month's last day. */
export function scheduleDate(first: Date, i: number, billingDay: number): Date {
  if (i <= 0) return first
  const y = first.getUTCFullYear()
  const m0 = first.getUTCMonth() + i
  const yy = y + Math.floor(m0 / 12)
  const mm = ((m0 % 12) + 12) % 12
  return new Date(Date.UTC(yy, mm, Math.min(Math.max(billingDay, 1), daysInMonth(yy, mm))))
}

/** The first instalment: the stored date, else the first billing day strictly after `anchor` (today). */
export function firstInstalmentDate(p: PaymentPlanInputs, anchor = new Date()): Date | null {
  if (!isScheduled(p) || planMonths(p) <= 0) return null
  const stored = p.first_instalment_date ? parseYmd(p.first_instalment_date) : null
  if (stored) return stored
  const y = anchor.getUTCFullYear()
  const m0 = anchor.getUTCMonth()
  if (anchor.getUTCDate() < p.billing_day) return new Date(Date.UTC(y, m0, Math.min(p.billing_day, daysInMonth(y, m0))))
  const ny = y + Math.floor((m0 + 1) / 12)
  const nm = (m0 + 1) % 12
  return new Date(Date.UTC(ny, nm, Math.min(p.billing_day, daysInMonth(ny, nm))))
}

/** YYYY-MM-DD per instalment (the deposit is not an instalment). */
export function scheduleDates(p: PaymentPlanInputs, anchor = new Date()): string[] {
  const first = firstInstalmentDate(p, anchor)
  if (!first) return []
  return Array.from({ length: planMonths(p) }, (_, i) => ymd(scheduleDate(first, i, p.billing_day)))
}

/** "Nov 2026" from "2026-11" — a Care Plan month in the admin UI. */
export function fmtYm(s: string): string {
  const d = parseYmd(`${s.slice(0, 7)}-01`)
  return d ? d.toLocaleDateString('en-MY', { month: 'short', year: 'numeric', timeZone: 'UTC' }) : s
}

/** "20 Nov 2026" for the admin UI (the PDF formats its own dates per document locale). */
export function fmtYmd(s: string): string {
  const d = parseYmd(s)
  return d ? d.toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }) : s
}

// ── Terms (mirror PaymentPlan::depositTerm / alignTerms) ─────────────────────

function ordinal(n: number): string {
  const r = n % 100
  const suffix = r >= 11 && r <= 13 ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' } as Record<number, string>)[n % 10] ?? 'th'
  return `${n}${suffix}`
}

const STANDARD_TAIL = '; balance due on delivery before handover.'

export function depositTermOpening(total: number, pct: number, p: PaymentPlanInputs): string {
  const dep = planDeposit(total, pct, p)
  const rm = `RM ${fmtRm(dep)}`
  if (isPhased(p)) return `${rm} (${phasePctLabel(normalisePhases(p.phases)[0]!.pct)}) to commence`
  if (p.payment_plan === 'partner') return `${rm} setup fee to commence`
  if (p.payment_plan === 'instalment') return `${rm} deposit to commence`
  if (p.deposit_amount_myr != null) return `${rm} deposit (${pctLabel(total, dep)}) to commence`
  return `${Number(pct) || 0}% deposit to commence`
}

/** The standard deposit bullet for this plan — the first of the default terms. */
export function depositTerm(total: number, pct: number, p: PaymentPlanInputs): string {
  const opening = depositTermOpening(total, pct, p)
  if (isPhased(p)) {
    const phases = normalisePhases(p.phases)
    const amounts = phaseAmounts(total, phases)
    const rest = phases.slice(1).map((ph, i) => `RM ${fmtRm(amounts[i + 1] ?? 0)} (${phasePctLabel(ph.pct)}) ${ph.label}`)
    return `${opening}; then ${rest.join('; ')}.`
  }
  const dep = depositAmountFor(total, pct, p.deposit_amount_myr)
  const monthly = Number(p.instalment_amount_myr) || 0
  if (p.payment_plan === 'instalment') {
    return `${opening}; balance of RM ${fmtRm(Math.max(total - dep, 0))} payable in ${planMonths(p)} monthly instalments of RM ${fmtRm(monthly)}, billed on the ${ordinal(p.billing_day)} of each month.`
  }
  if (p.payment_plan === 'partner') {
    return `${opening}; then RM ${fmtRm(monthly)} monthly for ${planMonths(p)} months, billed on the ${ordinal(p.billing_day)} of each month.`
  }
  return opening + STANDARD_TAIL
}

const OPENING_RE = /^(?:\d+(?:\.\d+)?%|RM ?[\d,]+(?:\.\d+)?) (?:deposit(?: \([\d.]+%\))?|setup fee|\([\d.]+%\)) to commence/
const SCHEDULED_TAIL_RE = /^(?:\d+(?:\.\d+)?%|RM ?[\d,]+(?:\.\d+)?) (?:deposit(?: \([\d.]+%\))?|setup fee|\([\d.]+%\)) to commence; (?:balance of RM ?[\d,]+(?:\.\d+)? payable in \d+ monthly instalments|then RM ?[\d,]+(?:\.\d+)? monthly for \d+ months|then RM ?[\d,]+(?:\.\d+)? \([\d.]+%\) )/

/**
 * Keep the boilerplate deposit bullet truthful as the deposit / plan changes
 * (the backend does the same on read — PaymentPlan::alignTerms). A bullet that
 * IS the boilerplate is replaced whole; one with a hand-edited tail keeps the
 * tail and only the opening figure moves; anything else is left alone.
 */
export function alignDepositTerms(lines: string[], total: number, pct: number, p: PaymentPlanInputs): string[] {
  const full = depositTerm(total, pct, p)
  return lines.map((line) => {
    const t = line.trim()
    if (!OPENING_RE.test(t)) return line
    if (t.endsWith(STANDARD_TAIL) || SCHEDULED_TAIL_RE.test(t)) return full
    return t.replace(OPENING_RE, depositTermOpening(total, pct, p))
  })
}

