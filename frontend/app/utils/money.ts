// House money format — the single formatter for every amount the app prints.
// "RM 2,700.00": "RM", one space, comma thousands, ALWAYS two decimals (also
// for whole numbers and zero). Mirrors the backend's number_format($n, 2).
// A negative value prints its sign ahead of the currency ("-RM 500.00"), as
// Intl's currency style did; call sites with their own convention (e.g.
// "−RM 500.00") pass the absolute value and prefix the sign themselves.
// Compact chart-axis ticks ("RM 12k") are scales, not amounts, and keep their
// own formatter.

const AMOUNT = new Intl.NumberFormat('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2, signDisplay: 'negative' })

/** "2,700.00" — comma thousands, always two decimals. Accepts number | numeric string | null. */
export function formatAmount(value: number | string | null | undefined): string {
  const n = typeof value === 'number' ? value : Number(value)
  return AMOUNT.format(Number.isFinite(n) ? n : 0)
}

/** "RM 2,700.00" — the house money format everywhere in the app. */
export function formatMyr(value: number | string | null | undefined): string {
  const s = formatAmount(value)
  return s.startsWith('-') ? `-RM ${s.slice(1)}` : `RM ${s}`
}
