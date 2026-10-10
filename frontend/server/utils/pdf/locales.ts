// Locale strings for the document renderer — the ONLY source of template CHROME:
// eyebrows, section headings, table captions, column headers, row labels,
// panel labels, footer, page numbers, and the month names the schedule and
// header dates are formatted with. Default `en`; `bm` (Bahasa Melayu) is the
// second locale. Picked by `DocumentData.locale`, which the quotation row
// stores explicitly — never detected from content.
//
// Founder-authored CONTENT never passes through here: the project title, intro,
// section / row titles and details, included items, option cards, care rows and
// notes print exactly as entered, whatever language they are written in.
//
// `LocaleStrings` is the contract. `bm` must define every key `en` does, so a
// missing translation is a type error; locales.test.ts also checks the two key
// sets match at runtime (and that no string is blank). Placeholders use
// `{name}` and are filled by `fmt()`.

import type { DocumentLocale } from "./types";

type Months = readonly [string, string, string, string, string, string, string, string, string, string, string, string];

export interface LocaleStrings {
  /** Month names, January first — "20 November 2026" / "20 Disember 2026". */
  months: Months;
  /** A billing day as prose: en "20th", bm "20 haribulan". */
  dayOfMonth: (day: number) => string;
  kind: { invoice: string; receipt: string };
  meta: { quotation: string; no: string; date: string; validUntil: string; due: string; status: string };
  parties: { from: string; preparedFor: string; billedTo: string; billTo: string; project: string };
  columns: { scope: string; qty: string; rate: string; amount: string; item: string; detail: string; price: string; plan: string };
  totals: { subtotal: string; discount: string; tax: string; total: string };
  deposit: { toCommence: string; balanceOnDelivery: string };
  standard: {
    terms: string; payment: string; online: string; transfer: string; account: string;
    acceptance: string; notes: string; acceptText: string; thanksText: string; signature: string;
  };
  sections: {
    packageOptions: string; provide: string; notIncluded: string; timeline: string;
    paymentTerms: string; summary: string; scopeCovered: string; howToPay: string;
  };
  howToPay: { scanTitle: string; scanText: string; transferTitle: string; reference: string; cardTitle: string; cardText: string };
  /** Running footer: "{page} 1 {of} 4". */
  page: { page: string; of: string };
  /** Deposit / balance cards, keyed by the backend's panel `role`. */
  panels: {
    lump_deposit: { label: string; note: string };
    lump_balance: { label: string; note: string };
    inst_deposit: { label: string; note: string };
    inst_monthly: { label: string };
    partner_setup: { label: string; note: string };
    partner_monthly: { label: string };
    /** "{day}" is the billing day prose, "{first}" / "{last}" formatted dates. */
    billed: string;
    billedNoSpan: string;
  };
  /** The titled Payment plan section. */
  plan: {
    eyebrow: string;
    title: { instalment: string; partner: string; lump_sum: string };
    /** One summary line under the heading, joined by `separator`. */
    line: {
      deposit: string; depositPct: string; setup: string; instalments: string;
      billed: string; balance: string; total: string; separator: string;
    };
    rows: {
      deposit: string; setup: string; monthly: string; monthlyFee: string; months: string;
      includesCare: string; billingDay: string; eachMonth: string; first: string; firstPayment: string;
      last: string; lastPayment: string; total: string; totalMonths: string;
    };
    schedule: { caption: string; payment: string; date: string; amount: string; instalment: string; month: string };
  };
}

const en: LocaleStrings = {
  months: ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"],
  dayOfMonth: (day) => {
    const r = day % 100;
    const suffix = r >= 11 && r <= 13 ? "th" : ({ 1: "st", 2: "nd", 3: "rd" } as Record<number, string>)[day % 10] ?? "th";
    return `${day}${suffix}`;
  },
  kind: { invoice: "Invoice", receipt: "Receipt" },
  meta: { quotation: "Quotation", no: "No.", date: "Date", validUntil: "Valid until", due: "Due", status: "Status" },
  parties: { from: "From", preparedFor: "Prepared for", billedTo: "Billed to", billTo: "Bill to", project: "Project" },
  columns: { scope: "Scope of work", qty: "Qty", rate: "Rate", amount: "Amount", item: "Item", detail: "Detail", price: "Price", plan: "Plan" },
  totals: { subtotal: "Subtotal", discount: "Discount", tax: "Tax", total: "Total" },
  deposit: { toCommence: "Deposit to commence", balanceOnDelivery: "Balance on delivery" },
  standard: {
    terms: "Terms", payment: "Payment", online: "Online", transfer: "Transfer", account: "Account",
    acceptance: "Acceptance", notes: "Notes",
    acceptText: "Approve this quotation to begin. A deposit invoice follows on acceptance.",
    thanksText: "Thank you. Payment is due by the date shown above.",
    signature: "Signature · Date",
  },
  sections: {
    packageOptions: "Package options", provide: "What you provide", notIncluded: "Not included in this version",
    timeline: "Timeline", paymentTerms: "Payment terms", summary: "Summary", scopeCovered: "Scope covered", howToPay: "How to pay",
  },
  howToPay: {
    scanTitle: "How to scan",
    scanText: "Open any Malaysian banking or e-wallet app, scan the DuitNow QR, then key in the amount due above.",
    transferTitle: "Bank transfer",
    reference: "Reference",
    cardTitle: "Card (credit & debit)",
    cardText: "Prefer to pay by card? Request a payment link from our admin at {email} and we'll send one over.",
  },
  page: { page: "Page", of: "of" },
  panels: {
    lump_deposit: { label: "Deposit ({pct})", note: "Payable to commence work." },
    lump_balance: { label: "Balance on completion", note: "Due before handover." },
    inst_deposit: { label: "Deposit on acceptance", note: "Payable before work begins." },
    inst_monthly: { label: "Monthly instalment · {months} months" },
    partner_setup: { label: "Setup fee", note: "Payable on acceptance." },
    partner_monthly: { label: "Monthly fee · {months} months" },
    billed: "Billed on the {day} of each month, {first} to {last}.",
    billedNoSpan: "Billed on the {day} of each month.",
  },
  plan: {
    eyebrow: "Payment plan",
    title: { instalment: "{months}-Month Instalment Plan", partner: "Technology Partner · {months} months", lump_sum: "One-Time Payment" },
    line: {
      deposit: "Deposit {amount}", depositPct: "Deposit {amount} ({pct})", setup: "Setup fee {amount}",
      instalments: "{months} × {amount}", billed: "billed on the {day}", balance: "balance {amount} on completion",
      total: "total {amount}", separator: " · ",
    },
    rows: {
      deposit: "Deposit", setup: "Setup fee", monthly: "Monthly instalment", monthlyFee: "Monthly fee",
      months: "× {months} months", includesCare: "includes Care Plan", billingDay: "Billing day",
      eachMonth: "{day} of each month", first: "First instalment", firstPayment: "First payment",
      last: "Last instalment", lastPayment: "Last payment", total: "Total", totalMonths: "Total ({months} months)",
    },
    schedule: { caption: "Payment schedule", payment: "Payment", date: "Date", amount: "Amount", instalment: "Instalment {n}", month: "Month {n}" },
  },
};

const bm: LocaleStrings = {
  months: ["Januari", "Februari", "Mac", "April", "Mei", "Jun", "Julai", "Ogos", "September", "Oktober", "November", "Disember"],
  dayOfMonth: (day) => `${day} haribulan`,
  kind: { invoice: "Invois", receipt: "Resit" },
  meta: { quotation: "Sebut harga", no: "No.", date: "Tarikh", validUntil: "Sah sehingga", due: "Perlu dibayar", status: "Status" },
  parties: { from: "Daripada", preparedFor: "Disediakan untuk", billedTo: "Dibilkan kepada", billTo: "Bil kepada", project: "Projek" },
  columns: { scope: "Skop kerja", qty: "Kuantiti", rate: "Kadar", amount: "Jumlah", item: "Item", detail: "Butiran", price: "Harga", plan: "Pelan" },
  totals: { subtotal: "Subjumlah", discount: "Diskaun", tax: "Cukai", total: "Jumlah" },
  deposit: { toCommence: "Deposit untuk memulakan", balanceOnDelivery: "Baki semasa penyerahan" },
  standard: {
    terms: "Terma", payment: "Pembayaran", online: "Dalam talian", transfer: "Pindahan", account: "Akaun",
    acceptance: "Penerimaan", notes: "Nota",
    acceptText: "Sahkan sebut harga ini untuk bermula. Invois deposit menyusul selepas penerimaan.",
    thanksText: "Terima kasih. Bayaran perlu dijelaskan sebelum tarikh di atas.",
    signature: "Tandatangan · Tarikh",
  },
  sections: {
    packageOptions: "Pilihan pakej", provide: "Apa yang anda sediakan", notIncluded: "Tidak termasuk dalam versi ini",
    timeline: "Garis masa", paymentTerms: "Terma pembayaran", summary: "Ringkasan", scopeCovered: "Skop yang dilindungi", howToPay: "Cara membayar",
  },
  howToPay: {
    scanTitle: "Cara mengimbas",
    scanText: "Buka mana-mana aplikasi perbankan atau e-dompet Malaysia, imbas QR DuitNow, kemudian masukkan jumlah yang perlu dibayar di atas.",
    transferTitle: "Pindahan bank",
    reference: "Rujukan",
    cardTitle: "Kad (kredit & debit)",
    cardText: "Mahu membayar dengan kad? Minta pautan pembayaran daripada admin kami di {email} dan kami akan hantarkan.",
  },
  page: { page: "Halaman", of: "daripada" },
  panels: {
    lump_deposit: { label: "Deposit ({pct})", note: "Dibayar untuk memulakan kerja." },
    lump_balance: { label: "Baki selepas siap", note: "Perlu dijelaskan sebelum penyerahan." },
    inst_deposit: { label: "Deposit semasa penerimaan", note: "Dibayar sebelum kerja bermula." },
    inst_monthly: { label: "Ansuran bulanan · {months} bulan" },
    partner_setup: { label: "Yuran penyediaan", note: "Dibayar semasa penerimaan." },
    partner_monthly: { label: "Bayaran bulanan · {months} bulan" },
    billed: "Dibil pada {day} setiap bulan, {first} hingga {last}.",
    billedNoSpan: "Dibil pada {day} setiap bulan.",
  },
  plan: {
    eyebrow: "Pelan pembayaran",
    title: { instalment: "Pelan Ansuran {months} Bulan", partner: "Rakan Teknologi · {months} bulan", lump_sum: "Bayaran Sekali Gus" },
    line: {
      deposit: "Deposit {amount}", depositPct: "Deposit {amount} ({pct})", setup: "Yuran penyediaan {amount}",
      instalments: "{months} × {amount}", billed: "dibil pada {day}", balance: "baki {amount} selepas siap",
      total: "jumlah {amount}", separator: " · ",
    },
    rows: {
      deposit: "Deposit", setup: "Yuran penyediaan", monthly: "Ansuran bulanan", monthlyFee: "Bayaran bulanan",
      months: "× {months} bulan", includesCare: "termasuk Care Plan", billingDay: "Tarikh bil",
      eachMonth: "{day} setiap bulan", first: "Ansuran pertama", firstPayment: "Bayaran pertama",
      last: "Ansuran terakhir", lastPayment: "Bayaran terakhir", total: "Jumlah", totalMonths: "Jumlah ({months} bulan)",
    },
    schedule: { caption: "Jadual bayaran", payment: "Bayaran", date: "Tarikh", amount: "Jumlah", instalment: "Ansuran {n}", month: "Bulan {n}" },
  },
};

export const LOCALES: Record<DocumentLocale, LocaleStrings> = { en, bm };

export const DOCUMENT_LOCALES = Object.keys(LOCALES) as DocumentLocale[];

/** The strings for a document locale; anything unknown or unset falls back to `en`. */
export function strings(locale?: string | null): LocaleStrings {
  return (locale && locale in LOCALES ? LOCALES[locale as DocumentLocale] : LOCALES.en);
}

/** Fill `{name}` placeholders. Unknown placeholders are left as-is. */
export function fmt(template: string, vars: Record<string, string | number>): string {
  return template.replace(/\{(\w+)\}/g, (m, key: string) => (key in vars ? String(vars[key]) : m));
}

/**
 * "20 November 2026" / "20 Disember 2026" from an ISO `YYYY-MM-DD` date, using
 * the locale's month names. Anything that isn't ISO (a frozen invoice payload's
 * pre-formatted "22 June 2026") passes through untouched.
 */
export function formatDate(iso: string | null | undefined, L: LocaleStrings): string {
  if (!iso) return "";
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
  if (!m) return iso;
  const month = L.months[Number(m[2]) - 1];
  return month ? `${Number(m[3])} ${month} ${m[1]}` : iso;
}
