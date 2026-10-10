// Data contracts for the Axel Nova document generator.
//
// One shared visual design (Satoshi + the pink/purple house palette, the
// logo + "AXEL NOVA VENTURES" / SSM-line letterhead, footer) renders in two
// CONTENT formats, selected by `layout`:
//   - "standard"  the simple parties → scope table → totals format. Good default
//                 for non-customized projects.
//   - "detailed"  the rich sectioned format (packages, options, care plans,
//                 promotion, summary, deposit/balance panels).
// Map these fields straight to your database rows.

export type DocumentLayout = "standard" | "detailed";
export type DocumentKind = "quotation" | "invoice" | "receipt";
/**
 * The language of the TEMPLATE CHROME (eyebrows, headings, captions, labels,
 * footer, formatted dates) — see locales.ts. Never the content, which prints
 * as authored. Absent = "en" (frozen invoice/receipt payloads predate it).
 */
export type DocumentLocale = "en" | "bm";

export interface Studio {
  name: string;
  /** small line under the wordmark, e.g. "Simple, effortless, human." */
  tagline?: string;
  /** logomark image (URL or base64 data URI). Falls back to the bundled mark. */
  logo?: string;
  email?: string;
  site?: string;
  /** business / company registration number. Frozen into payloads but NOT read
   *  by the header — the letterhead renders from `STUDIO_IDENTITY` in template.ts. */
  reg?: string;
  /** footer credit, e.g. "Designed by Qie / Axel Nova Ventures" */
  designedBy?: string;
}

export interface Client {
  name: string;
  /** omitted by the mapper when it already serves as the display name */
  company?: string;
  /** e.g. "Attn. Daniel Foong, Marketing Lead" */
  attn?: string;
  /** multi-line allowed; "\n" becomes <br> */
  address?: string;
  email?: string;
  phone?: string;
}

export interface PaymentInfo {
  online?: string;
  bank?: string;
  acct?: string;
  /** account holder — shown on its own line in the invoice "How to pay" block */
  holder?: string;
  /** free prose, e.g. "Payable by card, online banking (FPX)… to Axel Nova Ventures." */
  note?: string;
}

/* ------------------------------------------------------------------ standard */

export interface LineItem {
  title: string;
  desc?: string;
  qty: number;
  unit?: string;
  /** price per unit, in the document currency */
  rate: number;
}

/* ------------------------------------------------------------------ detailed */

/** One row in a package/summary table. `price` is numeric; `priceText` overrides
 *  it with a status word ("Included" / "Free"). */
export interface DetailRow {
  title: string;
  detail?: string;
  price?: number;
  /** e.g. "Free", "Included" — rendered in red unless `priceMuted` */
  priceText?: string;
  priceMuted?: boolean;
  /** struck-through original price (launch promotion) */
  priceWas?: number;
}

export interface Section {
  /** e.g. "Core package: website build" */
  title: string;
  rows: DetailRow[];
  /** e.g. "Core package total" */
  totalLabel?: string;
  total?: number;
  /** overrides the numeric total, e.g. "Free" */
  totalText?: string;
  /** muted footnote under the section */
  note?: string;
}

/** A red-dot bullet group ("what's included", "what you provide", …). */
export interface BulletList {
  /** optional red eyebrow above the list, e.g. "BASIC SEO" */
  eyebrow?: string;
  items: string[];
  /** 2 = render as two columns; default 1 */
  columns?: 1 | 2;
  /** muted footnote under the list */
  note?: string;
}

export interface OptionCard {
  /** e.g. "OPTION A" or "OPTION B · SELF-SERVICE" */
  badge: string;
  /** red border + red badge/price — the recommended option */
  accent?: boolean;
  title: string;
  sub?: string;
  price: number;
  /** struck-through original (launch promotion) */
  priceWas?: number;
  /** e.g. "one-time" */
  priceNote?: string;
}

export interface CarePlanRow {
  label: string;
  detail: string;
  price: number;
  /** "month" / "year" → rendered as "/ month" */
  period?: string;
}

export interface SummaryRow {
  label: string;
  price?: number;
  /** override, e.g. "Included" / "Free (launch promotion)" */
  priceText?: string;
  priceMuted?: boolean;
  /** show as "− RM…" (discount line) */
  negative?: boolean;
  /** bold "Project total" row with a strong top border */
  total?: boolean;
  /** emphasize value in red */
  red?: boolean;
  /** emphasize value in green — money already received ("Paid to date") */
  green?: boolean;
  /** stable hook for render-time display switches (never match on label text) */
  role?: "remaining";
}

/**
 * Quotation deposit / monthly cards arrive from the backend as a ROLE plus
 * figures (PaymentPlan::panels) — no label or note; the template words them
 * from the locale file. Invoice / receipt panels (frozen payloads) still carry
 * their own `label` / `note` and the `balance` display-switch role.
 */
export type PanelRole =
  | "balance"
  | "lump_deposit"
  | "lump_balance"
  | "inst_deposit"
  | "inst_monthly"
  | "partner_setup"
  | "partner_monthly";

export interface Panel {
  /** e.g. "DEPOSIT INVOICED" / "BALANCE DUE ON COMPLETION" — absent on quotation plan roles */
  label?: string;
  value: number;
  /** sub line(s); "\n" becomes <br> — absent on quotation plan roles */
  note?: string;
  /** red-bordered emphasis card */
  accent?: boolean;
  /** stable hook: render-time display switches + locale labelling (never match on label text) */
  role?: PanelRole;
  /** lump_deposit: the effective deposit pct for the label, e.g. "18.8%" */
  pctLabel?: string;
  /** inst_monthly / partner_monthly: the schedule the note describes */
  months?: number;
  billingDay?: number;
  /** ISO YYYY-MM-DD, formatted per locale */
  firstDate?: string | null;
  lastDate?: string | null;
}

export interface NoteLine {
  /** bold lead-in, e.g. "Estimated completion:" */
  label: string;
  text: string;
}

/* ------------------------------------------------------------ payment plan */

export interface PaymentPlanScheduleRow {
  /** 1-based instalment number */
  n: number;
  /** ISO YYYY-MM-DD, formatted per locale by the template */
  date: string;
  amount: number;
}

/**
 * The data behind the titled "Payment plan" section of a quotation — DATA
 * ONLY, derived by the backend (PaymentPlan::documentBlock): figures, ISO
 * dates and the dated schedule. Every heading, row label, caption and month
 * name comes from the locale file. A lump sum carries deposit + balance and an
 * empty schedule (heading + cards, flows inline); instalment / partner carry the
 * monthly figures + schedule (the section opens on its own page).
 */
export interface PaymentPlanBlock {
  plan: "lump_sum" | "instalment" | "partner";
  deposit: number;
  /** e.g. "18.8%" — the lump-sum deposit label */
  depositPctLabel: string;
  /** total − deposit */
  balance: number;
  monthly: number;
  months: number;
  billingDay: number;
  firstDate?: string | null;
  lastDate?: string | null;
  includesCarePlan: boolean;
  /** the plan total (deposit + months × monthly, or the quotation total) */
  total: number;
  schedule: PaymentPlanScheduleRow[];
}

/* ---------------------------------------------------------------- document */

export interface DocumentData {
  layout: DocumentLayout;
  kind: DocumentKind;
  /** template-chrome language; absent = "en" */
  locale?: DocumentLocale | null;

  /** document number, e.g. "AXN-011" or "INV-AXN-011" */
  number: string;
  /** ISO "2026-10-10" (quotations — formatted per locale by the template) or a
   *  pre-formatted "22 June 2026" (frozen invoice/receipt payloads, printed as-is) */
  issued: string;
  /** quotes: "Valid until"; invoices: "Due" — label switches by kind. Same date forms as `issued`. */
  validUntil?: string;
  /** override the right-column second meta label (default depends on kind) */
  metaLabel2?: string;
  /** invoice/receipt header status, e.g. "Deposit received" */
  status?: string;
  currency: string; // e.g. "RM"

  studio: Studio;
  client: Client;

  /** hero title (detailed) / project line (standard) */
  project: string;
  /** e.g. "Website quotation" */
  subtitle?: string;
  intro?: string;

  // ---- standard layout ----
  items?: LineItem[];
  terms?: string[];

  // ---- totals (standard + invoice summary helpers) ----
  discount?: number;
  taxLabel?: string;
  taxRate?: number; // 0..1
  /** quotes: deposit to collect first; invoices: amount being billed */
  depositPct?: number;
  /** quotes: the deposit actually due — a fixed amount, or the pct rounded to
   *  the ringgit by the backend. Wins over depositPct when present. */
  depositAmount?: number;
  /** quotes: the effective deposit pct for the card label, e.g. "18.8%" */
  depositPctLabel?: string;
  /** quotes on an instalment / partner plan: the derived Payment plan block */
  paymentPlan?: PaymentPlanBlock | null;

  // ---- detailed layout ----
  sections?: Section[];
  /** "what's included" groups shown under the package sections */
  included?: BulletList[];
  options?: { title?: string; promo?: string; cards: OptionCard[] };
  care?: {
    title: string;
    intro?: string;
    headers?: [string, string, string];
    rows: CarePlanRow[];
    note?: string;
  };
  provide?: { title?: string } & BulletList;
  notIncluded?: { title?: string } & BulletList;
  timeline?: { title?: string; text: string };
  paymentTerms?: { title?: string; items: string[] };
  summary?: { title?: string; rows: SummaryRow[] };
  panels?: Panel[];
  /** Render-time switches. Absent = everything shown (frozen payloads predate
   *  this). Hidden parts are not emitted at all — the data stays in the payload
   *  because `amount_total` is derived from the summary rows. */
  display?: { summary?: boolean; remaining?: boolean };
  /** Right half of the accent amount panel: what this bill is for. */
  billingFor?: { title: string; label?: string; text?: string };
  /** Bullet section between the panels and How to pay. */
  scope?: { title?: string } & BulletList;
  /** bottom prose, e.g. "Estimated completion: …". Frozen invoice/receipt
   *  payloads may store the admin's free-text notes as a plain string —
   *  the template normalizes it to a single unlabelled line. */
  notes?: NoteLine[] | string;

  pay?: PaymentInfo;
}

export interface ComputedTotals {
  subtotal: number;
  discount: number;
  tax: number;
  total: number;
  deposit: number;
  balance: number;
}
