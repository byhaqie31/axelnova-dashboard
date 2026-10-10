import { FONT_FACES } from "./fonts";
import { STUDIO_LOGO } from "./logo";
import { DUITNOW_QR } from "./qr";
import { fmt, formatDate, strings, type LocaleStrings } from "./locales";
import type {
  Client,
  DocumentData,
  DocumentLocale,
  ComputedTotals,
  DetailRow,
  Section,
  BulletList,
  OptionCard,
  SummaryRow,
  Panel,
  NoteLine,
  PaymentInfo,
  PaymentPlanBlock,
} from "./types";

// LANGUAGE MODEL. Every string this file emits on its own — eyebrows, section
// headings, table captions, column headers, row labels, panel labels, footer,
// "Page x of y" and formatted dates — comes from locales.ts, picked by
// `data.locale` (default en, second locale bm). Founder-authored CONTENT in the
// payload (project, intro, section / row titles and details, included items,
// option cards, care rows, notes) is printed exactly as entered, in whatever
// language it was written — never translated, never detected.

/* ----------------------------------------------------------------- helpers */

/** Minimal HTML escaping for user-supplied text fields. */
function esc(s: string | undefined | null): string {
  if (!s) return "";
  return s
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

/** Escape a value for use inside a CSS string literal (content: "…"). */
function cssStr(s: string): string {
  return s.replace(/\\/g, "\\\\").replace(/"/g, '\\"');
}

function group(n: number, dec: number): string {
  return n.toLocaleString("en-US", {
    minimumFractionDigits: dec,
    maximumFractionDigits: dec,
  });
}

/** Document money: "RM1,800" (line items) or "RM1,300.00" (panels, dec=2). */
function money(n: number, cur: string, dec = 0): string {
  return `${cur}${group(n, dec)}`;
}

/** Payment plan money: "RM 2,700.00" — spaced, two decimals, in every locale. */
function rm(n: number, cur: string): string {
  return `${cur} ${group(n, 2)}`;
}

export function computeTotals(d: DocumentData): ComputedTotals {
  const subtotal = (d.items ?? []).reduce((s, i) => s + i.qty * i.rate, 0);
  const discount = d.discount ?? 0;
  const base = subtotal - discount;
  const tax = Math.round(base * (d.taxRate ?? 0) * 100) / 100;
  const total = base + tax;
  // The backend-derived deposit (fixed amount, or pct rounded to the ringgit)
  // wins; the pct fallback only serves payloads that predate depositAmount.
  const deposit =
    d.depositAmount != null
      ? Math.min(d.depositAmount, total)
      : Math.round(total * ((d.depositPct ?? 100) / 100) * 100) / 100;
  const balance = total - deposit;
  return { subtotal, discount, tax, total, deposit, balance };
}

/* -------------------------------------------------------------------- CSS */

/* One shared design system — Satoshi + the Axel Nova house palette (pink /
   purple, soft pink surfaces). Every colour lives in :root; no literal hex
   below it. Sizes are tuned to the AXN-011 reference render — re-check
   one-page fit before reflowing vertical spacing. */
const CSS = `
:root{
  --paper:#FFFFFF;
  --ink:#1B0F1D; --body:#4B3B4D; --muted:#8A7789;
  --primary:#D11E72; --accent:#8B3DD6;
  --surface:#FDF4F8; --surface-strong:#FDEDF5;
  --hairline:#EFE0E9;
  --green:#0E8A3E;
}
*{margin:0;padding:0;box-sizing:border-box;}
@page{
  size:A4;
  margin:15mm 18mm 13mm;
  @bottom-left{
    content:var(--pgfoot-l);
    font-family:'Satoshi',sans-serif; font-size:8px; letter-spacing:.02em;
    color:var(--muted); padding-bottom:1mm;
  }
  @bottom-right{
    content:var(--pg-page) " " counter(page) " " var(--pg-of) " " counter(pages);
    font-family:'Satoshi',sans-serif; font-size:8px; letter-spacing:.04em;
    color:var(--muted); padding-bottom:1mm;
  }
}
html,body{background:var(--paper);color:var(--ink);
  font-family:'Satoshi',sans-serif;-webkit-font-smoothing:antialiased;
  font-feature-settings:"tnum" 1;font-variant-numeric:tabular-nums;}
.sheet{position:relative;}
/* Brand gradient hairline — flows at the top of page one. */
.topbar{height:2.5px;border-radius:2px;margin-bottom:7mm;
  background:linear-gradient(90deg,var(--primary) 0%,var(--accent) 100%);}

/* ---- header ---- */
.head{display:flex;justify-content:space-between;align-items:flex-start;}
.brand{display:flex;gap:13px;align-items:flex-start;}
.brand .logo{height:38px;width:auto;display:block;margin-top:1px;}
/* Name cap-top sits level with the visible mark's top, the SSM baseline level
   with its bottom (the PNG's lower ~30% is transparent padding). */
.brand .wm{font-weight:700;font-size:15px;line-height:1;letter-spacing:.08em;
  color:var(--ink);white-space:nowrap;margin-top:-.5px;}
/* Proportional figures on the SSM line only; prices stay tabular. */
.brand .reg{font-size:10px;line-height:1;letter-spacing:.01em;color:var(--body);
  font-variant-numeric:proportional-nums;white-space:nowrap;margin-top:4.25px;}
.doc{text-align:right;}
/* The first pair's top margin collapses into this one: 18pt from the title
   to the first pair, then ~14pt between pairs. 10px (not 9) because Chromium
   snaps these baselines to whole px — measured in the prod Alpine Chromium,
   NO./DATE/STATUS land at 100.5 / 114.75 / 128.25pt. */
.doc .kind-big{font-weight:700;font-size:28px;
  letter-spacing:-.01em;line-height:1;margin-bottom:10px;color:var(--ink);}
.doc .pair{display:flex;justify-content:flex-end;align-items:baseline;gap:8px;
  margin-top:5.67px;}
.doc .lab{font-size:8.5px;font-weight:500;letter-spacing:.18em;
  text-transform:uppercase;color:var(--muted);}
.doc .val{font-size:11.5px;color:var(--ink);margin-top:0;line-height:1;}

/* ---- rule with primary leading segment ---- */
.rule{position:relative;height:.5px;background:var(--hairline);margin:16px 0 0;}
.rule:before{content:"";position:absolute;top:0;left:0;width:11%;height:1.6px;
  background:var(--primary);}

/* ---- hero ---- */
.eyebrow{display:inline-block;font-size:9px;font-weight:500;letter-spacing:.2em;
  text-transform:uppercase;color:var(--primary);
  background:var(--surface-strong);border-radius:999px;padding:3px 10px;}
.hero{margin-top:22px;}
/* Client identity under the "Prepared for" eyebrow (detailed quotation). */
.hero-client{margin-top:12px;}
.hero .title{font-weight:700;font-size:25px;color:var(--ink);
  letter-spacing:-.015em;line-height:1.05;margin-top:11px;}
.hero .subtitle{margin-top:7px;font-size:13px;color:var(--muted);}
.hero .intro{margin-top:14px;font-size:11.5px;line-height:1.65;color:var(--body);
  max-width:150mm;}

/* ---- parties (standard) ---- */
.parties{display:flex;gap:40px;margin-top:18px;}
.party{flex:1;}
.plabel{font-size:8.5px;font-weight:500;letter-spacing:.18em;
  text-transform:uppercase;color:var(--muted);margin-bottom:8px;}
.pname{font-size:12.5px;font-weight:700;letter-spacing:-.005em;color:var(--ink);}
.pln{font-size:10.5px;color:var(--muted);line-height:1.7;margin-top:4px;}

/* ---- section header (primary square marker; .alt = accent) ---- */
.sec{margin-top:26px;}
.sec-h{display:flex;align-items:center;gap:11px;}
.sec-h .sq{width:8px;height:8px;border-radius:2px;background:var(--primary);flex:none;}
.sec-h.alt .sq{background:var(--accent);}
.sec-h .t{font-weight:700;font-size:14.5px;color:var(--ink);
  letter-spacing:-.01em;}

/* ---- tables ---- */
table{width:100%;border-collapse:collapse;margin-top:14px;}
thead th{font-size:8.5px;letter-spacing:.16em;
  text-transform:uppercase;color:var(--muted);font-weight:500;text-align:left;
  padding:0 0 9px;border-bottom:.5px solid var(--hairline);}
th.r,td.r{text-align:right;}
tbody td{padding:11px 0;border-bottom:.5px solid var(--hairline);vertical-align:top;}
tbody tr:last-child td{border-bottom:0;}
.c-item{font-size:11.5px;font-weight:500;color:var(--ink);padding-right:14px;
  white-space:nowrap;}
.c-detail{font-size:11px;color:var(--body);line-height:1.55;padding-right:18px;}
.c-price{font-size:11.5px;color:var(--ink);
  text-align:right;white-space:nowrap;}
.c-price .was{color:var(--muted);text-decoration:line-through;margin-right:7px;}
.price-free{color:var(--primary);}
.price-muted{color:var(--muted);}

/* ---- section total ---- */
.sec-total{display:flex;justify-content:space-between;align-items:baseline;
  border-top:1.4px solid var(--ink);padding-top:11px;margin-top:0;}
.sec-total .l{font-weight:700;font-size:11.5px;}
.sec-total .v{font-weight:500;font-size:13px;color:var(--primary);}
.sec-note{font-size:10.5px;color:var(--muted);margin-top:13px;line-height:1.5;}

/* ---- bullet lists (primary dots) ---- */
.bul{list-style:none;margin-top:14px;}
.bul.two{column-count:2;column-gap:34px;}
.bul li{position:relative;padding-left:17px;margin-bottom:9px;font-size:11px;
  color:var(--body);line-height:1.5;break-inside:avoid;}
.bul li:before{content:"";position:absolute;left:1px;top:5px;width:5px;height:5px;
  border-radius:50%;background:var(--primary);}
.bul-eyebrow{display:inline-block;font-size:9px;font-weight:500;letter-spacing:.2em;
  text-transform:uppercase;color:var(--primary);
  background:var(--surface-strong);border-radius:999px;padding:3px 10px;
  margin-bottom:7px;}

/* ---- option cards ---- */
.opts-h{display:flex;align-items:center;gap:12px;margin-top:26px;}
.opts-h .promo{font-size:8px;font-weight:500;letter-spacing:.14em;
  text-transform:uppercase;color:var(--primary);background:var(--surface-strong);
  border-radius:999px;padding:3px 9px;}
.opts{display:flex;gap:18px;margin-top:14px;}
.opt{flex:1;border:1px solid var(--hairline);border-radius:9px;
  background:var(--surface);padding:18px 19px 19px;}
.opt.accent{border-color:var(--primary);}
.opt .badge{font-size:8.5px;font-weight:500;letter-spacing:.16em;
  text-transform:uppercase;color:var(--muted);}
.opt.accent .badge{color:var(--primary);}
.opt .t{font-weight:700;font-size:13.5px;margin-top:11px;letter-spacing:-.01em;}
.opt .s{font-size:10.5px;color:var(--muted);margin-top:6px;line-height:1.45;}
.opt .price{display:flex;align-items:baseline;gap:10px;margin-top:22px;}
.opt .price .v{font-weight:500;font-size:20px;color:var(--ink);}
.opt.accent .price .v{color:var(--primary);}
.opt .price .was{font-size:12px;color:var(--muted);
  text-decoration:line-through;}
.opt .price .note{font-size:9.5px;color:var(--muted);}

/* ---- generic blocks ---- */
.para{font-size:11px;color:var(--body);line-height:1.6;margin-top:13px;max-width:155mm;}

/* ---- summary ---- */
.sum{margin-top:14px;}
.sum-row{display:flex;justify-content:space-between;align-items:baseline;
  padding:11px 0;border-bottom:.5px solid var(--hairline);font-size:11.5px;}
.sum-row .l{color:var(--ink);}
.sum-row .v{color:var(--ink);}
.sum-row.muted .l,.sum-row.muted .v{color:var(--muted);}
.sum-row.redv .v{color:var(--primary);}
.sum-row.greenv .v{color:var(--green);}
.sum-row.total{border-top:1.4px solid var(--ink);border-bottom:0;margin-top:1px;
  padding-top:13px;}
.sum-row.total .l{font-weight:700;font-size:13px;}
.sum-row.total .v{font-size:14px;font-weight:500;color:var(--primary);}

/* ---- panels ---- */
.panels{display:flex;flex-wrap:wrap;gap:18px;margin-top:18px;}
.panel{flex:1;border:1px solid var(--hairline);border-radius:9px;
  background:var(--surface);padding:17px 19px 18px;}
.panel.accent{border-color:var(--primary);}
.panel .val{font-size:22px;color:var(--ink);letter-spacing:-.01em;}
.panel .label{font-size:9px;font-weight:500;letter-spacing:.16em;
  text-transform:uppercase;color:var(--muted);margin-top:6px;}
.panel.accent .label{color:var(--primary);}
.panel.accent .val{color:var(--primary);}
.panel .note{font-size:10px;color:var(--muted);line-height:1.55;margin-top:10px;}
.panel .note b{color:var(--ink);font-weight:500;}
/* Accent panel with a "what this bill is for" half: a 2-col × 3-row grid so
   each right-hand line shares a baseline with its left-hand partner (amount /
   title, label / label, note / note). The 56px gap lands the right column
   where the old balance panel's text started. Always takes its own row. */
.panel.split{flex:1 1 100%;display:grid;grid-template-columns:1fr 1fr;
  grid-auto-flow:column;grid-template-rows:auto auto auto;align-items:baseline;
  column-gap:56px;}
.panel .bf-t{font-size:13px;font-weight:500;color:var(--ink);letter-spacing:-.005em;}
.panel.split .label.bf-l{color:var(--muted);}

/* ---- payment plan (quotations: its own titled section) ---- */
.payment-plan{margin-top:26px;}
.payment-plan .pp-title{font-weight:700;font-size:19px;color:var(--ink);
  letter-spacing:-.015em;line-height:1.1;margin-top:12px;}
.payment-plan .pp-line{margin-top:8px;font-size:11px;color:var(--body);line-height:1.6;}
.payment-plan .panels{margin-top:16px;}
.payment-plan .summary{margin-top:16px;}
.payment-plan .summary .sum-row .v .dt{color:var(--muted);font-size:10px;margin-left:7px;}
.payment-plan .caption{font-size:8.5px;font-weight:500;letter-spacing:.18em;
  text-transform:uppercase;color:var(--muted);margin-top:18px;}
.payment-plan .schedule table{margin-top:10px;}
.payment-plan .schedule td{padding:7px 0;}
.payment-plan .schedule .c-item{font-weight:400;color:var(--body);}
/* Pagination — an instalment / partner plan opens on a fresh page (modern
   break-* and legacy page-break-* spellings both set), its cards, rows and
   schedule never split, and a schedule that still overflows repeats its header
   row. A lump sum (.inline: heading + cards, no schedule) keeps flowing. */
.payment-plan{break-before:page;page-break-before:always;}
.payment-plan:not(.inline){margin-top:0;}
.payment-plan.inline{break-before:auto;page-break-before:auto;}
.payment-plan .summary,.payment-plan .schedule{break-inside:avoid;page-break-inside:avoid;}
.payment-plan .schedule thead{display:table-header-group;}
.payment-plan .schedule tr{break-inside:avoid;page-break-inside:avoid;}
.payment-plan .pp-title,.payment-plan .pp-line,.payment-plan .caption{
  break-after:avoid;page-break-after:avoid;}

/* ---- how to pay (invoice) ---- */
/* Kept whole across a page break — a half-split QR is unscannable. */
.howpay{break-inside:avoid;page-break-inside:avoid;}
.hp-body{display:flex;gap:26px;margin-top:14px;align-items:stretch;}
/* 36mm renders the 77-module DuitNow QR at ~0.42mm per module. That is close to
   the ~0.33mm floor phone cameras need on a QR this dense — sized to match the
   height of the three methods beside it, so don't shrink it further. */
.hp-qr{flex:0 0 36mm;width:36mm;}
.hp-qr img{width:36mm;height:auto;display:block;border:1px solid var(--hairline);
  border-radius:8px;}
.hp-methods{flex:1;display:flex;flex-direction:column;justify-content:space-between;}
.hp-m + .hp-m{margin-top:13px;padding-top:13px;border-top:.5px solid var(--hairline);}
.hp-mt{font-size:11.5px;font-weight:700;color:var(--ink);margin-bottom:6px;}
.hp-ml{font-size:10.5px;color:var(--muted);line-height:1.7;}
.hp-ml b{color:var(--ink);font-weight:500;}
.hp-ml .mono{color:var(--ink);}

/* ---- bottom notes ---- */
.notes{margin-top:16px;padding-top:13px;border-top:.5px solid var(--hairline);}
.notes .n{font-size:10.5px;color:var(--body);line-height:1.55;margin-bottom:5px;}
.notes .n b{color:var(--ink);font-weight:700;}

/* ---- standard totals ---- */
.foot{display:flex;justify-content:space-between;gap:44px;margin-top:18px;}
.terms{flex:1;max-width:100mm;}
.totals{width:78mm;}
.tot-row{display:flex;justify-content:space-between;font-size:11px;color:var(--muted);
  padding:8px 0;}
.tot-row .v{color:var(--ink);}
.tot-row.grand{border-top:1.4px solid var(--ink);margin-top:4px;padding-top:12px;
  font-size:13px;font-weight:700;color:var(--ink);}
.tot-row.grand .v{font-size:14px;font-weight:500;color:var(--primary);}
.deposit{margin-top:14px;border:1px solid var(--primary);border-radius:9px;
  background:var(--surface);padding:15px 18px;}
.deposit .val{font-size:22px;color:var(--primary);}
.deposit .label{font-size:9px;font-weight:500;letter-spacing:.16em;
  text-transform:uppercase;color:var(--primary);margin-top:6px;}
.deposit .bal{font-size:10px;color:var(--muted);margin-top:9px;}

/* ---- payment / acceptance (standard) ---- */
.lower{display:flex;gap:44px;margin-top:18px;padding-top:14px;border-top:.5px solid var(--hairline);}
.pay{flex:1;}
.pay .ln{font-size:10.5px;color:var(--muted);line-height:1.9;}
.pay .ln b{color:var(--ink);font-weight:500;}
.pay .mono{color:var(--ink);}
.accept{width:78mm;}
.sign{margin-top:22px;border-top:1px solid var(--ink);padding-top:7px;
  font-size:8.5px;font-weight:500;letter-spacing:.14em;
  text-transform:uppercase;color:var(--muted);}

/* ---- designed-by credit (end of body) ---- */
.credit{margin-top:30px;padding-top:14px;border-top:.5px solid var(--hairline);}
.credit .name{font-weight:700;font-size:12px;}
.credit .tag{font-size:10.5px;color:var(--muted);margin-top:3px;}
.credit .contact{font-size:10px;color:var(--body);
  margin-top:9px;letter-spacing:.01em;}

/* ---- pagination ---- */
/* Chromium reads break-*; the page-break-* spellings ride along for any
   engine that only knows the legacy names. Atomic units never split … */
tr,.sum-row,.tot-row,.sec-total,.panel,.opt,.hp-m,.deposit,.credit{
  break-inside:avoid;page-break-inside:avoid;}
/* … headings, eyebrows and labels never strand at a page foot … */
.sec-h,.bul-eyebrow,.eyebrow,.plabel,.opts-h{
  break-after:avoid;page-break-after:avoid;}
/* … sections themselves may flow across pages … */
.sec,.opts-block{break-inside:auto;}
/* … and a table that does flow repeats its header row on the next page, so a
   continuation never opens on bare ITEM / DETAIL / PRICE with no context. */
thead{display:table-header-group;}
`;

/* ---------------------------------------------------------------- partials */

function priceCell(r: DetailRow | SummaryRow, cur: string): string {
  const anyR = r as DetailRow & SummaryRow;
  if (anyR.priceText) {
    const cls = anyR.priceMuted ? "price-muted" : "price-free";
    return `<span class="${cls}">${esc(anyR.priceText)}</span>`;
  }
  const neg = (r as SummaryRow).negative ? "− " : "";
  const was = (r as DetailRow).priceWas != null
    ? `<span class="was">${money((r as DetailRow).priceWas!, cur)}</span>`
    : "";
  return `${was}${neg}${money(anyR.price ?? 0, cur)}`;
}

function tableHTML(
  rows: DetailRow[],
  cur: string,
  cols: [string, string, string],
): string {
  const body = rows
    .map(
      (r) => `<tr>
        <td class="c-item">${esc(r.title)}</td>
        <td class="c-detail">${esc(r.detail)}</td>
        <td class="c-price">${priceCell(r, cur)}</td>
      </tr>`,
    )
    .join("");
  return `<table>
    <thead><tr>
      <th>${esc(cols[0])}</th><th>${esc(cols[1])}</th><th class="r">${esc(cols[2])}</th>
    </tr></thead>
    <tbody>${body}</tbody>
  </table>`;
}

function bulletHTML(list: BulletList, cur?: string): string {
  void cur;
  const eyebrow = list.eyebrow
    ? `<div class="bul-eyebrow">${esc(list.eyebrow)}</div>`
    : "";
  const items = list.items.map((x) => `<li>${esc(x)}</li>`).join("");
  const note = list.note ? `<div class="sec-note">${esc(list.note)}</div>` : "";
  return `${eyebrow}<ul class="bul${list.columns === 2 ? " two" : ""}">${items}</ul>${note}`;
}

function sectionHeaderHTML(title: string, promo?: string, alt = false): string {
  const pill = promo ? `<span class="promo">${esc(promo)}</span>` : "";
  return `<div class="sec-h${alt ? " alt" : ""}"><span class="sq"></span><span class="t">${esc(title)}</span>${pill}</div>`;
}

/**
 * Bank details for the invoice "How to pay" block.
 *
 * Deliberately owned by the renderer rather than frozen into each payload:
 * every invoice, however old, must show the account that is *currently* being
 * paid into. A frozen copy would leave older invoices pointing at a dead
 * account the day this changes. `data.pay` still wins when a payload supplies
 * it, so custom builder payloads can override per-document.
 *
 * KEEP IN SYNC with `DocumentMapper::BANK` (backend), which words the same
 * details into the panel notes.
 */
const STUDIO_PAY: PaymentInfo = {
  bank: "OCBC Bank",
  holder: "Axel Nova Ventures",
  acct: "7051415701",
  online: "Card (credit & debit) and FPX online banking",
};

/**
 * Invoice "How to pay" block — DuitNow QR, card / online banking, bank transfer.
 *
 * Invoice-only: a receipt is already settled and a quotation isn't payable yet.
 * The QR is a *static* merchant QR, so it carries no amount — the caption tells
 * the payer to key in the total themselves.
 */
function payBlockHTML(data: DocumentData, L: LocaleStrings): string {
  const pay = { ...STUDIO_PAY, ...(data.pay ?? {}) };
  const transfer = [pay.bank, pay.holder].filter(Boolean).join(" · ");

  // Three methods, easiest first: scan the QR to the left, transfer manually,
  // or ask for a card link. The QR's own instructions live here rather than
  // under the image so all three read as one column.
  const methods = [
    `<div class="hp-m">
      <div class="hp-mt">${esc(L.howToPay.scanTitle)}</div>
      <div class="hp-ml">${esc(L.howToPay.scanText)}</div>
    </div>`,
    `<div class="hp-m">
      <div class="hp-mt">${esc(L.howToPay.transferTitle)}</div>
      ${transfer ? `<div class="hp-ml"><b>${esc(transfer)}</b></div>` : ""}
      ${pay.acct ? `<div class="hp-ml"><span class="mono">${esc(pay.acct)}</span></div>` : ""}
      ${data.number ? `<div class="hp-ml">${esc(L.howToPay.reference)} <span class="mono">${esc(data.number)}</span></div>` : ""}
    </div>`,
    `<div class="hp-m">
      <div class="hp-mt">${esc(L.howToPay.cardTitle)}</div>
      <div class="hp-ml">${fmt(esc(L.howToPay.cardText), { email: `<b>${esc(data.studio.email)}</b>` })}</div>
    </div>`,
  ].join("");

  return `<div class="sec howpay">
    ${sectionHeaderHTML(L.sections.howToPay)}
    <div class="hp-body">
      <div class="hp-qr">
        <img src="${DUITNOW_QR}" alt="DuitNow QR · ${esc(data.studio.name)}">
      </div>
      <div class="hp-methods">${methods}</div>
    </div>
  </div>`;
}

/**
 * The titled "Payment plan" section of a quotation — ONE container holding the
 * eyebrow + h2, a one-line summary, the deposit / monthly cards, the labelled
 * rows and the captioned, dated schedule. Everything arrives as data
 * (PaymentPlan::documentBlock + ::panels, ISO dates); every heading, label,
 * caption and date is worded here from the locale file. Instalment / partner
 * open on a fresh page (.payment-plan); a lump sum (.inline) keeps flowing —
 * heading + summary line + cards, no rows, no schedule.
 */
export function paymentPlanHTML(
  block: PaymentPlanBlock,
  cur: string,
  locale?: DocumentLocale | null,
  panels: Panel[] = [],
): string {
  const L = strings(locale);
  const partner = block.plan === "partner";
  const scheduled = block.plan !== "lump_sum" && block.months > 0;
  const day = L.dayOfMonth(block.billingDay);

  const title =
    block.plan === "instalment"
      ? fmt(L.plan.title.instalment, { months: block.months })
      : partner
        ? fmt(L.plan.title.partner, { months: block.months })
        : L.plan.title.lump_sum;

  // One summary line under the heading — only the parts that apply to the plan.
  const line: string[] = [];
  if (scheduled) {
    line.push(fmt(partner ? L.plan.line.setup : L.plan.line.deposit, { amount: rm(block.deposit, cur) }));
    line.push(fmt(L.plan.line.instalments, { months: block.months, amount: rm(block.monthly, cur) }));
    line.push(fmt(L.plan.line.billed, { day }));
  } else {
    line.push(fmt(L.plan.line.depositPct, { amount: rm(block.deposit, cur), pct: block.depositPctLabel }));
    if (block.balance > 0) line.push(fmt(L.plan.line.balance, { amount: rm(block.balance, cur) }));
  }
  line.push(fmt(L.plan.line.total, { amount: rm(block.total, cur) }));

  const cards = panels.length
    ? `<div class="panels">${panels.map((p) => panelHTML(p, cur, L)).join("")}</div>`
    : "";

  let summary = "";
  let schedule = "";
  if (scheduled) {
    const row = (label: string, value: string, cls = ""): string =>
      `<div class="sum-row${cls}"><span class="l">${esc(label)}</span><span class="v">${value}</span></div>`;
    const monthlyDetail =
      fmt(L.plan.rows.months, { months: block.months }) +
      (block.includesCarePlan ? `, ${L.plan.rows.includesCare}` : "");
    summary = `<div class="summary">${[
      row(partner ? L.plan.rows.setup : L.plan.rows.deposit, rm(block.deposit, cur)),
      row(
        partner ? L.plan.rows.monthlyFee : L.plan.rows.monthly,
        `${rm(block.monthly, cur)}<span class="dt">${esc(monthlyDetail)}</span>`,
      ),
      row(L.plan.rows.billingDay, esc(fmt(L.plan.rows.eachMonth, { day }))),
      block.firstDate
        ? row(partner ? L.plan.rows.firstPayment : L.plan.rows.first, esc(formatDate(block.firstDate, L)))
        : "",
      block.lastDate
        ? row(partner ? L.plan.rows.lastPayment : L.plan.rows.last, esc(formatDate(block.lastDate, L)))
        : "",
      row(
        partner ? fmt(L.plan.rows.totalMonths, { months: block.months }) : L.plan.rows.total,
        rm(block.total, cur),
        " total redv",
      ),
    ].join("")}</div>`;

    // priceText (not price): tableHTML formats `price` to whole ringgit.
    const schedRows: DetailRow[] = block.schedule.map((r) => ({
      title: fmt(partner ? L.plan.schedule.month : L.plan.schedule.instalment, { n: r.n }),
      detail: formatDate(r.date, L),
      priceText: rm(r.amount, cur),
      priceMuted: true,
    }));
    if (schedRows.length) {
      schedule = `<div class="caption">${esc(L.plan.schedule.caption)}</div>
    <div class="schedule">${tableHTML(schedRows, cur, [L.plan.schedule.payment, L.plan.schedule.date, L.plan.schedule.amount])}</div>`;
    }
  }

  return `<div class="payment-plan${scheduled ? "" : " inline"}">
    <div class="eyebrow">${esc(L.plan.eyebrow)}</div>
    <h2 class="pp-title">${esc(title)}</h2>
    <div class="pp-line">${esc(line.join(L.plan.line.separator))}</div>
    ${cards}${summary}${schedule}
  </div>`;
}

function sectionHTML(sec: Section, cur: string, L: LocaleStrings): string {
  const total =
    sec.totalLabel != null
      ? `<div class="sec-total"><span class="l">${esc(sec.totalLabel)}</span>` +
        `<span class="v">${sec.totalText ? esc(sec.totalText) : money(sec.total ?? 0, cur)}</span></div>`
      : "";
  const note = sec.note ? `<div class="sec-note">${esc(sec.note)}</div>` : "";
  return `<div class="sec">
    ${sectionHeaderHTML(sec.title)}
    ${tableHTML(sec.rows, cur, [L.columns.item, L.columns.detail, L.columns.price])}
    ${total}${note}
  </div>`;
}

function optionCardHTML(c: OptionCard, cur: string): string {
  const was =
    c.priceWas != null ? `<span class="was">${money(c.priceWas, cur)}</span>` : "";
  const note = c.priceNote ? `<span class="note">${esc(c.priceNote)}</span>` : "";
  const sub = c.sub ? `<div class="s">${esc(c.sub)}</div>` : "";
  return `<div class="opt${c.accent ? " accent" : ""}">
    <div class="badge">${esc(c.badge)}</div>
    <div class="t">${esc(c.title)}</div>${sub}
    <div class="price"><span class="v">${money(c.price, cur)}</span>${was}${note}</div>
  </div>`;
}

/**
 * A card's label + note. Quotation plan roles (PaymentPlan::panels) carry only
 * figures — worded here from the locale file; invoice / receipt panels keep
 * the label + note frozen in their payload.
 */
function panelCopy(p: Panel, L: LocaleStrings): { label: string; note: string } {
  const months = p.months ?? 0;
  const day = L.dayOfMonth(p.billingDay ?? 0);
  const billed =
    p.firstDate && p.lastDate
      ? fmt(L.panels.billed, { day, first: formatDate(p.firstDate, L), last: formatDate(p.lastDate, L) })
      : fmt(L.panels.billedNoSpan, { day });
  switch (p.role) {
    case "lump_deposit":
      return { label: fmt(L.panels.lump_deposit.label, { pct: p.pctLabel ?? "" }), note: L.panels.lump_deposit.note };
    case "lump_balance":
      return L.panels.lump_balance;
    case "inst_deposit":
      return L.panels.inst_deposit;
    case "inst_monthly":
      return { label: fmt(L.panels.inst_monthly.label, { months }), note: billed };
    case "partner_setup":
      return L.panels.partner_setup;
    case "partner_monthly":
      return { label: fmt(L.panels.partner_monthly.label, { months }), note: billed };
    default:
      return { label: p.label ?? "", note: p.note ?? "" };
  }
}

function panelHTML(p: Panel, cur: string, L: LocaleStrings, billingFor?: DocumentData["billingFor"]): string {
  const copy = panelCopy(p, L);
  const note = copy.note
    ? `<div class="note">${esc(copy.note).replace(/\n/g, "<br>")}</div>`
    : "";
  if (billingFor?.title) {
    // Grid auto-flows by column, so every one of the six cells must exist —
    // an empty <div> holds a slot when a note is missing.
    const bfNote = billingFor.text
      ? `<div class="note">${esc(billingFor.text).replace(/\n/g, "<br>")}</div>`
      : "<div></div>";
    return `<div class="panel split${p.accent ? " accent" : ""}">
    <div class="val">${money(p.value, cur, 2)}</div>
    <div class="label">${esc(copy.label)}</div>${note || "<div></div>"}
    <div class="bf-t">${esc(billingFor.title)}</div>
    <div class="label bf-l">${esc(billingFor.label || L.sections.scopeCovered)}</div>${bfNote}
  </div>`;
  }
  return `<div class="panel${p.accent ? " accent" : ""}">
    <div class="val">${money(p.value, cur, 2)}</div>
    <div class="label">${esc(copy.label)}</div>${note}
  </div>`;
}

/**
 * Letterhead identity for the header on every document kind.
 *
 * Owned by the renderer, not read from `data.studio`, for the same reason as
 * STUDIO_PAY: PDFs re-render from the frozen payload on every download, and
 * older payloads carry `reg: "Reg. …"`. Reading it from the payload would print
 * the stale string on every past document. Header chrome is house style; the
 * payload freezes data, not letterhead.
 *
 * KEEP IN SYNC with `DocumentMapper::STUDIO['reg']` (backend).
 */
const STUDIO_IDENTITY = {
  name: "AXEL NOVA VENTURES",
  reg: "SSM Registration: 202603119899 (CA0420977-U)",
} as const;

function headHTML(data: DocumentData, L: LocaleStrings): string {
  const kindWord =
    data.kind === "invoice" ? L.kind.invoice : data.kind === "receipt" ? L.kind.receipt : "";
  const logo = data.studio.logo || STUDIO_LOGO;

  const pairs: string[] = [];
  if (data.kind === "quotation") {
    pairs.push(pair(L.meta.quotation, data.number));
  } else {
    pairs.push(pair(L.meta.no, data.number));
  }
  // ISO dates (live quotations) are formatted per locale; a frozen payload's
  // pre-formatted "22 June 2026" prints as-is.
  pairs.push(pair(L.meta.date, formatDate(data.issued, L)));
  if (data.validUntil) {
    const lab =
      data.metaLabel2 ?? (data.kind === "quotation" ? L.meta.validUntil : L.meta.due);
    pairs.push(pair(lab, formatDate(data.validUntil, L)));
  }
  if (data.status) pairs.push(pair(L.meta.status, data.status));

  const big = kindWord ? `<div class="kind-big">${kindWord}</div>` : "";

  return `<div class="head">
    <div class="brand">
      <img class="logo" src="${esc(logo)}" alt="${esc(data.studio.name)}" />
      <div>
        <div class="wm">${esc(STUDIO_IDENTITY.name)}</div>
        <div class="reg">${esc(STUDIO_IDENTITY.reg)}</div>
      </div>
    </div>
    <div class="doc">${big}${pairs.join("")}</div>
  </div>`;
}

/** One right-aligned meta line: label then value on a shared baseline. */
function pair(label: string, value: string): string {
  return `<div class="pair"><span class="lab">${esc(label)}</span><span class="val">${esc(value)}</span></div>`;
}

function creditHTML(data: DocumentData): string {
  const by = data.studio.designedBy ?? data.studio.name;
  const contact = [data.studio.email, data.studio.site]
    .filter(Boolean)
    .map(esc)
    .join("&nbsp;&nbsp;·&nbsp;&nbsp;");
  const tag = data.studio.tagline
    ? `<div class="tag">${esc(data.studio.tagline)}</div>`
    : "";
  return `<div class="credit">
    <div class="name">${esc(by)}</div>${tag}
    ${contact ? `<div class="contact">${contact}</div>` : ""}
  </div>`;
}

/* ---------------------------------------------------------- standard layout */

function renderStandard(data: DocumentData): string {
  const cur = data.currency;
  const L = strings(data.locale);
  const t = computeTotals(data);
  const addr = esc(data.client.address).replace(/\n/g, "<br>");

  const rows = (data.items ?? [])
    .map(
      (it) => `<tr>
        <td class="c-item">${esc(it.title)}${it.desc ? `<div class="c-detail" style="font-weight:400;white-space:normal;margin-top:4px">${esc(it.desc)}</div>` : ""}</td>
        <td class="c-detail r" style="white-space:nowrap">${it.qty}${it.unit ? ` ${esc(it.unit)}` : ""}</td>
        <td class="c-price">${money(it.rate, cur)}</td>
        <td class="c-price">${money(it.qty * it.rate, cur)}</td>
      </tr>`,
    )
    .join("");

  const discountRow = t.discount
    ? `<div class="tot-row"><span>${esc(L.totals.discount)}</span><span class="v">− ${money(t.discount, cur)}</span></div>`
    : "";
  const taxRow = data.taxRate
    ? `<div class="tot-row"><span>${esc(data.taxLabel ?? L.totals.tax)}</span><span class="v">${money(t.tax, cur)}</span></div>`
    : "";

  const terms = (data.terms ?? []).map((x) => `<li>${esc(x)}</li>`).join("");
  // reg is not repeated here — the letterhead already carries the SSM line.
  const studioLn = esc(data.studio.email);

  // A scheduled plan replaces the deposit card with the Payment plan block
  // below; a lump sum keeps the card (fixed amount or pct, label derived).
  const hasDeposit =
    data.depositAmount != null ? data.depositAmount < t.total : (data.depositPct ?? 100) < 100;
  const depositCard =
    hasDeposit && !data.paymentPlan
      ? `<div class="deposit">
           <div class="val">${money(t.deposit, cur, 2)}</div>
           <div class="label">${esc(L.deposit.toCommence)} · ${esc(data.depositPctLabel ?? `${data.depositPct}%`)}</div>
           <div class="bal">${esc(L.deposit.balanceOnDelivery)}&nbsp;&nbsp;${money(t.balance, cur, 2)}</div>
         </div>`
      : "";
  const planBlock = data.paymentPlan ? paymentPlanHTML(data.paymentPlan, cur, data.locale) : "";

  return `
  ${headHTML(data, L)}
  <div class="rule"></div>
  <div class="parties">
    <div class="party">
      <div class="plabel">${esc(L.parties.from)}</div>
      <div class="pname">${esc(data.studio.name)}</div>
      <div class="pln">${studioLn}</div>
    </div>
    <div class="party">
      <div class="plabel">${esc(data.kind === "quotation" ? L.parties.preparedFor : L.parties.billedTo)}</div>
      <div class="pname">${esc(data.client.name)}</div>
      <div class="pln">${[esc(data.client.company), esc(data.client.attn), addr, esc(data.client.email), esc(data.client.phone)].filter(Boolean).join("<br>")}</div>
    </div>
  </div>

  <div class="hero" style="margin-top:18px">
    <div class="title" style="font-size:19px">${esc(data.project)}</div>
    ${data.intro ? `<div class="intro">${esc(data.intro)}</div>` : ""}
  </div>

  <table>
    <thead><tr>
      <th>${esc(L.columns.scope)}</th><th class="r">${esc(L.columns.qty)}</th><th class="r">${esc(L.columns.rate)}</th><th class="r">${esc(L.columns.amount)}</th>
    </tr></thead>
    <tbody>${rows}</tbody>
  </table>

  <div class="foot">
    <div class="terms">
      ${terms ? `<div class="plabel">${esc(L.standard.terms)}</div><ul class="bul">${terms}</ul>` : ""}
    </div>
    <div class="totals">
      <div class="tot-row"><span>${esc(L.totals.subtotal)}</span><span class="v">${money(t.subtotal, cur)}</span></div>
      ${discountRow}${taxRow}
      <div class="tot-row grand"><span>${esc(L.totals.total)}</span><span class="v">${money(t.total, cur)}</span></div>
      ${depositCard}
    </div>
  </div>
  ${planBlock}

  <div class="lower">
    <div class="pay">
      <div class="plabel">${esc(L.standard.payment)}</div>
      ${data.pay?.online ? `<div class="ln"><b>${esc(L.standard.online)}</b>&nbsp; ${esc(data.pay.online)}</div>` : ""}
      ${data.pay?.bank ? `<div class="ln"><b>${esc(L.standard.transfer)}</b>&nbsp; ${esc([data.pay.bank, data.pay.holder].filter(Boolean).join(" · "))}</div>` : ""}
      ${data.pay?.acct ? `<div class="ln"><b>${esc(L.standard.account)}</b>&nbsp; <span class="mono">${esc(data.pay.acct)}</span></div>` : ""}
    </div>
    <div class="accept">
      <div class="plabel">${esc(data.kind === "quotation" ? L.standard.acceptance : L.standard.notes)}</div>
      <div class="ln" style="font-size:10.5px;line-height:1.55">${
        esc(data.kind === "quotation" ? L.standard.acceptText : L.standard.thanksText)
      }</div>
      ${data.kind === "quotation" ? `<div class="sign">${esc(L.standard.signature).replace(/ · /g, "&nbsp;·&nbsp;")}</div>` : ""}
    </div>
  </div>

  ${creditHTML(data)}
  `;
}

/* ---------------------------------------------------------- detailed layout */

function renderDetailed(data: DocumentData): string {
  const cur = data.currency;
  const L = strings(data.locale);
  const parts: string[] = [headHTML(data, L), `<div class="rule"></div>`];

  // Hero — quotation opens with who it's for (name, company, email, phone —
  // any null line skipped) under the "Prepared for" eyebrow, then the project
  // title; invoice/receipt use a Bill-to / Project split.
  if (data.kind === "quotation") {
    const c = data.client ?? ({ name: "" } as Client);
    const contactLines = [c.company, c.email, c.phone]
      .filter(Boolean)
      .map((x) => esc(x))
      .join("<br>");
    const clientBlock = c.name || contactLines
      ? `<div class="hero-client">
          ${c.name ? `<div class="pname">${esc(c.name)}</div>` : ""}
          ${contactLines ? `<div class="pln">${contactLines}</div>` : ""}
        </div>`
      : "";
    parts.push(`<div class="hero">
      <div class="eyebrow">${esc(L.parties.preparedFor)}</div>
      ${clientBlock}
      <div class="title">${esc(data.project)}</div>
      ${data.subtitle ? `<div class="subtitle">${esc(data.subtitle)}</div>` : ""}
      ${data.intro ? `<div class="intro">${esc(data.intro)}</div>` : ""}
    </div>`);
  } else {
    const addr = esc(data.client.address).replace(/\n/g, "<br>");
    parts.push(`<div class="parties" style="margin-top:20px">
      <div class="party">
        <div class="plabel">${esc(L.parties.billTo)}</div>
        <div class="pname">${esc(data.client.name)}</div>
        <div class="pln">${[esc(data.client.attn), addr, esc(data.client.email), esc(data.client.company)].filter(Boolean).join("<br>")}</div>
      </div>
      <div class="party">
        <div class="plabel">${esc(L.parties.project)}</div>
        <div class="pname">${esc(data.project)}</div>
        ${data.subtitle ? `<div class="pln">${esc(data.subtitle)}</div>` : ""}
      </div>
    </div>`);
    if (data.intro) parts.push(`<div class="para">${esc(data.intro)}</div>`);
  }

  // Sections (packages)
  for (const sec of data.sections ?? []) parts.push(sectionHTML(sec, cur, L));

  // "What's included" bullet groups
  for (const inc of data.included ?? [])
    parts.push(`<div class="sec">${bulletHTML(inc, cur)}</div>`);

  // Package options
  if (data.options) {
    const cards = data.options.cards.map((c) => optionCardHTML(c, cur)).join("");
    parts.push(`<div class="opts-block">
      <div class="opts-h">${sectionHeaderHTML(data.options.title ?? L.sections.packageOptions, data.options.promo)}</div>
      <div class="opts">${cards}</div>
    </div>`);
  }

  // Care & hosting
  if (data.care) {
    const headers = data.care.headers ?? [L.columns.plan, L.columns.detail, L.columns.price];
    const rows: DetailRow[] = data.care.rows.map((r) => ({
      title: r.label,
      detail: r.detail,
      priceText: `${money(r.price, cur)}${r.period ? ` / ${r.period}` : ""}`,
      priceMuted: true,
    }));
    parts.push(`<div class="sec">
      ${sectionHeaderHTML(data.care.title, undefined, true)}
      ${data.care.intro ? `<div class="para" style="margin-top:11px">${esc(data.care.intro)}</div>` : ""}
      ${tableHTML(rows, cur, headers)}
      ${data.care.note ? `<div class="sec-note">${esc(data.care.note)}</div>` : ""}
    </div>`);
  }

  // What you provide
  if (data.provide)
    parts.push(`<div class="sec">${sectionHeaderHTML(data.provide.title ?? L.sections.provide)}${bulletHTML(data.provide)}</div>`);

  // Not included
  if (data.notIncluded)
    parts.push(`<div class="sec">${sectionHeaderHTML(data.notIncluded.title ?? L.sections.notIncluded)}${bulletHTML(data.notIncluded)}</div>`);

  // Timeline
  if (data.timeline)
    parts.push(`<div class="sec">${sectionHeaderHTML(data.timeline.title ?? L.sections.timeline)}<div class="para">${esc(data.timeline.text)}</div></div>`);

  // Payment terms
  if (data.paymentTerms)
    parts.push(`<div class="sec">${sectionHeaderHTML(data.paymentTerms.title ?? L.sections.paymentTerms)}${bulletHTML({ items: data.paymentTerms.items })}</div>`);

  // Display switches hide at RENDER time: the payload keeps every row and
  // panel (amount_total is derived from the summary rows), and hidden parts are
  // not emitted at all — no display:none, so hidden figures can't leak through
  // copy, search or a screen reader.
  const showSummary = data.display?.summary !== false;
  const showRemaining = data.display?.remaining !== false;

  // Summary (invoice)
  if (data.summary && showSummary) {
    const rows = data.summary.rows
      .filter((r) => showRemaining || r.role !== "remaining")
      .map((r) => {
        const cls = [
          r.total ? "total" : "",
          r.priceMuted ? "muted" : "",
          r.red ? "redv" : "",
          r.green ? "greenv" : "",
        ]
          .filter(Boolean)
          .join(" ");
        return `<div class="sum-row ${cls}"><span class="l">${esc(r.label)}</span><span class="v">${priceCell(r, cur)}</span></div>`;
      })
      .join("");
    parts.push(`<div class="sec">${sectionHeaderHTML(data.summary.title ?? L.sections.summary)}<div class="sum">${rows}</div></div>`);
  }

  // Deposit / balance panels. A quotation's cards live INSIDE its titled
  // Payment plan section (one container: heading, summary line, cards, rows,
  // schedule — instalment / partner on their own page, lump sum inline).
  // Invoices / receipts render the panels here, with billingFor filling the
  // right half of the accent (amount-due) panel.
  const panels = (data.panels ?? []).filter(
    (p) => showRemaining || p.role !== "balance",
  );
  if (data.paymentPlan) {
    parts.push(paymentPlanHTML(data.paymentPlan, cur, data.locale, panels));
  } else if (panels.length) {
    parts.push(`<div class="panels">${panels.map((p) => panelHTML(p, cur, L, p.accent ? data.billingFor : undefined)).join("")}</div>`);
  }

  // Scope covered — what this bill pays for, before the payment instructions.
  if (data.scope?.items?.length)
    parts.push(`<div class="sec">${sectionHeaderHTML(data.scope.title || L.sections.scopeCovered)}${bulletHTML(data.scope)}</div>`);

  // How to pay — invoices only, directly under the amount-due panel it refers to.
  if (data.kind === "invoice") parts.push(payBlockHTML(data, L));

  // Bottom notes. Frozen invoice/receipt payloads may carry the admin's
  // free-text notes as a plain string — normalize to NoteLine[] before mapping.
  const notes: NoteLine[] =
    typeof data.notes === "string"
      ? data.notes.trim()
        ? [{ label: "", text: data.notes }]
        : []
      : (data.notes ?? []);
  if (notes.length)
    parts.push(`<div class="notes">${notes.map((n) => `<div class="n">${n.label ? `<b>${esc(n.label)}</b> ` : ""}${esc(n.text)}</div>`).join("")}</div>`);

  parts.push(creditHTML(data));
  return parts.join("\n");
}

/* -------------------------------------------------------------- entrypoint */

/**
 * Render a full standalone HTML document (fonts embedded) for the given data.
 * `data.layout` selects the content format; both share one visual design.
 */
export function renderDocumentHTML(data: DocumentData): string {
  const body =
    data.layout === "detailed" ? renderDetailed(data) : renderStandard(data);

  // Running page-foot identity line (left side) and the localized "Page x of y"
  // words (right side; the counters come from @page).
  const L = strings(data.locale);
  const pgfootL = [data.studio.name, data.studio.tagline, data.number]
    .filter(Boolean)
    .join("  ·  ");
  const rootVar = `:root{--pgfoot-l:"${cssStr(pgfootL)}";--pg-page:"${cssStr(L.page.page)}";--pg-of:"${cssStr(L.page.of)}";}`;

  // Document <title> → becomes the PDF's /Title metadata (Chromium embeds the page
  // title when printing). Without it the page renders as about:blank and the PDF
  // viewer/tab shows "about:blank". e.g. "AXNQ-2026-0007 · One Malaysia Taxi".
  const docName = data.project || data.client?.name || data.studio.name;
  const docTitle = [data.number, docName].filter(Boolean).join(" · ") || data.studio.name;

  return `<!doctype html><html><head><meta charset="utf-8"><title>${esc(docTitle)}</title><style>
${FONT_FACES}
${CSS}
${rootVar}
</style></head><body>
<div class="topbar"></div>
<div class="sheet">
${body}
</div>
</body></html>`;
}
