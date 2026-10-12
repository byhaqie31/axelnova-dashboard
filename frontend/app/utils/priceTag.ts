// Catalog price display — the one format for package prices and estimate ranges
// on the services pages, the services estimator and the quote builder's guide
// estimate (rendered by components/shared/primitives/PriceTag.vue):
//
//   range     "RM 2,500 – 4,000"   currency once, en dash, no decimals on whole ringgit
//   no max    "From RM 20,000"     never "From" with a range
//   one price "RM 2,500"           (min = max)
//
// Document money (invoices, quotation line items, totals) keeps the house
// 2-decimal format in utils/money.ts — this is for catalog / estimate ranges.

type Amount = number | string | null | undefined

const WHOLE = new Intl.NumberFormat('en-MY', { maximumFractionDigits: 0 })
const CENTS = new Intl.NumberFormat('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

function toNumber(value: Amount): number | null {
  if (value === null || value === undefined || value === '') return null
  const n = typeof value === 'number' ? value : Number(value)
  return Number.isFinite(n) ? n : null
}

const hasCents = (n: number) => Math.round(n * 100) % 100 !== 0

/** "RM " for a letter symbol, "$" / "S$" / "£" with no space. */
function currencyPrefix(symbol: string): string {
  return /[A-Za-z]$/.test(symbol) ? `${symbol} ` : symbol
}

export interface PriceTagInput {
  min: Amount
  /** Null / absent = open-ended ("From …"). */
  max?: Amount
  /** Currency symbol, default "RM". */
  symbol?: string
}

/** The price text alone — the unit ("per project") is rendered after it. */
export function formatPriceTag({ min, max, symbol = 'RM' }: PriceTagInput): string {
  const lo = toNumber(min) ?? 0
  const hi = toNumber(max)
  // Cents on either end → both ends show them, so a range never mixes formats.
  const fmt = hasCents(lo) || (hi !== null && hasCents(hi)) ? CENTS : WHOLE
  const prefix = currencyPrefix(symbol)

  if (hi === null) return `From ${prefix}${fmt.format(lo)}`
  if (hi === lo) return `${prefix}${fmt.format(lo)}`
  return `${prefix}${fmt.format(lo)} – ${fmt.format(hi)}`
}

/** Price + unit as one string ("RM 2,500 – 4,000 per project") — aria labels, plain text. */
export function formatPriceTagWithUnit(input: PriceTagInput & { unit?: string | null }): string {
  const price = formatPriceTag(input)
  return input.unit ? `${price} ${input.unit}` : price
}
