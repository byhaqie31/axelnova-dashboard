// Data contracts for the Axel Nova document generator.
// Map these fields straight to your database rows.

export type DocumentKind = "quotation" | "invoice";

export interface Party {
  name: string;
  /** e.g. "Attn. Daniel Foong, Marketing Lead" — optional */
  attn?: string;
  /** multi-line allowed; "\n" becomes <br> */
  address?: string;
  email?: string;
  location?: string;
  /** business / company registration number */
  reg?: string;
  site?: string;
  tagline?: string;
}

export interface LineItem {
  title: string;
  desc?: string;
  qty: number;
  unit?: string;
  /** price per unit, in the document currency */
  rate: number;
}

export interface PaymentInfo {
  /** e.g. "Card & FPX online banking via secure link" */
  online?: string;
  /** e.g. "Maybank Islamic — Axel Nova Ventures" */
  bank?: string;
  /** account number */
  acct?: string;
}

export interface DocumentData {
  kind: DocumentKind;
  /** document number, e.g. "AXN-Q-2026-014" (quote) or "AXN-INV-2026-031" (invoice) */
  number: string;
  issued: string;        // formatted date string, e.g. "22 June 2026"
  validUntil?: string;   // quotes: "Valid until"; invoices: reuse as "Due"
  dueLabel?: string;     // override the right-column second label (default depends on kind)
  currency: string;      // e.g. "RM"

  studio: Party;         // From
  client: Party;         // Prepared for / Billed to

  project: string;       // one-line project title
  intro?: string;        // short paragraph under the title

  items: LineItem[];

  discount?: number;     // absolute amount in currency; 0/undefined hides the row
  taxLabel?: string;     // e.g. "SST 8%"
  taxRate?: number;      // 0..1; 0/undefined hides the tax row

  /** Quotes: deposit to collect first. Invoices: usually the amount being billed. */
  depositPct?: number;   // e.g. 50

  terms?: string[];
  pay?: PaymentInfo;

  /** Invoice-only: replaces the deposit panel headline label */
  amountDueLabel?: string;
}

export interface ComputedTotals {
  subtotal: number;
  discount: number;
  tax: number;
  total: number;
  deposit: number;
  balance: number;
}
