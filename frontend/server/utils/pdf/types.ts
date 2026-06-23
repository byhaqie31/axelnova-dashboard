// Data contracts for the Axel Nova document generator.
//
// One shared visual design (Geist + the red/gradient palette, logo-and-wordmark
// header, footer) renders in two CONTENT formats, selected by `layout`:
//   - "standard"  the simple parties → scope table → totals format. Good default
//                 for non-customized projects.
//   - "detailed"  the rich sectioned format (packages, options, care plans,
//                 promotion, summary, deposit/balance panels).
// Map these fields straight to your database rows.

export type DocumentLayout = "standard" | "detailed";
export type DocumentKind = "quotation" | "invoice" | "receipt";

export interface Studio {
  name: string;
  /** small line under the wordmark, e.g. "Simple, effortless, human." */
  tagline?: string;
  /** logomark image (URL or base64 data URI). Falls back to the bundled mark. */
  logo?: string;
  email?: string;
  site?: string;
  /** business / company registration number */
  reg?: string;
  /** footer credit, e.g. "Designed by Qie / Axel Nova Ventures" */
  designedBy?: string;
}

export interface Client {
  name: string;
  /** e.g. "Attn. Daniel Foong, Marketing Lead" */
  attn?: string;
  /** multi-line allowed; "\n" becomes <br> */
  address?: string;
  email?: string;
}

export interface PaymentInfo {
  online?: string;
  bank?: string;
  acct?: string;
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
}

export interface Panel {
  /** e.g. "DEPOSIT INVOICED" / "BALANCE DUE ON COMPLETION" */
  label: string;
  value: number;
  /** sub line(s); "\n" becomes <br> */
  note?: string;
  /** red-bordered emphasis card */
  accent?: boolean;
}

export interface NoteLine {
  /** bold lead-in, e.g. "Estimated completion:" */
  label: string;
  text: string;
}

/* ---------------------------------------------------------------- document */

export interface DocumentData {
  layout: DocumentLayout;
  kind: DocumentKind;

  /** document number, e.g. "AXN-011" or "INV-AXN-011" */
  number: string;
  issued: string; // formatted, e.g. "22 June 2026"
  /** quotes: "Valid until"; invoices: "Due" — label switches by kind */
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
  /** bottom prose, e.g. "Estimated completion: …" */
  notes?: NoteLine[];

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
