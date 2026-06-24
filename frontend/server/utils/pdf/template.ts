import { FONT_FACES } from "./fonts";
import { STUDIO_LOGO } from "./logo";
import type {
  DocumentData,
  ComputedTotals,
  DetailRow,
  Section,
  BulletList,
  OptionCard,
  SummaryRow,
  Panel,
} from "./types";

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

export function computeTotals(d: DocumentData): ComputedTotals {
  const subtotal = (d.items ?? []).reduce((s, i) => s + i.qty * i.rate, 0);
  const discount = d.discount ?? 0;
  const base = subtotal - discount;
  const tax = Math.round(base * (d.taxRate ?? 0) * 100) / 100;
  const total = base + tax;
  const deposit = Math.round(total * ((d.depositPct ?? 100) / 100) * 100) / 100;
  const balance = total - deposit;
  return { subtotal, discount, tax, total, deposit, balance };
}

/* -------------------------------------------------------------------- CSS */

/* One shared design system (Geist + the brass-free red/gradient palette).
   Sizes are tuned to the AXN-011 reference render — re-check one-page fit
   before reflowing vertical spacing. */
const CSS = `
:root{
  --paper:#FFFFFF; --ink:#1C1C1E; --body:#39393C; --muted:#6B6B70; --faint:#9A9AA0;
  --line:#ECEAE7; --line2:#F0EEEB; --strong:#1C1C1E;
  --red:#EE1C25; --red-deep:#C8141C;
}
*{margin:0;padding:0;box-sizing:border-box;}
@page{
  size:A4;
  margin:15mm 18mm 13mm;
  @bottom-left{
    content:var(--pgfoot-l);
    font-family:'Geist Mono',monospace; font-size:8px; letter-spacing:.02em;
    color:var(--faint); padding-bottom:1mm;
  }
  @bottom-right{
    content:"Page " counter(page) " of " counter(pages);
    font-family:'Geist Mono',monospace; font-size:8px; letter-spacing:.04em;
    color:var(--faint); padding-bottom:1mm;
  }
}
html,body{background:var(--paper);color:var(--ink);
  font-family:'Geist',sans-serif;-webkit-font-smoothing:antialiased;
  font-feature-settings:"tnum" 1;}
.sheet{position:relative;}
/* Brand gradient hairline — flows at the top of page one (matches the logo). */
.topbar{height:2.5px;border-radius:2px;margin-bottom:7mm;
  background:linear-gradient(90deg,#4E7DF4 0%,#7E76EE 48%,#BE76E6 100%);}

/* ---- header ---- */
.head{display:flex;justify-content:space-between;align-items:flex-start;}
.brand{display:flex;gap:13px;align-items:flex-start;}
.brand .logo{height:38px;width:auto;display:block;margin-top:1px;}
.brand .wm{font-family:'Geist',sans-serif;font-weight:600;font-size:16px;line-height:1.12;
  letter-spacing:-.005em;color:var(--ink);}
.brand .tag{margin-top:6px;font-size:10.5px;color:var(--muted);letter-spacing:.005em;}
.doc{text-align:right;}
.doc .kind-big{font-family:'Geist',sans-serif;font-weight:600;font-size:28px;
  letter-spacing:-.01em;line-height:1;margin-bottom:9px;}
.doc .pair{margin-top:9px;}
.doc .lab{font-family:'Geist Mono',monospace;font-size:8.5px;letter-spacing:.18em;
  text-transform:uppercase;color:var(--faint);}
.doc .val{font-family:'Geist Mono',monospace;font-size:11.5px;color:var(--ink);
  margin-top:3px;}

/* ---- rule with red leading segment ---- */
.rule{position:relative;height:1px;background:var(--line);margin:16px 0 0;}
.rule:before{content:"";position:absolute;top:0;left:0;width:11%;height:1.6px;
  background:var(--red);}

/* ---- hero ---- */
.eyebrow{font-family:'Geist Mono',monospace;font-size:9px;letter-spacing:.28em;
  text-transform:uppercase;color:var(--red);}
.hero{margin-top:22px;}
.hero .title{font-family:'Geist',sans-serif;font-weight:600;font-size:25px;
  letter-spacing:-.015em;line-height:1.05;margin-top:11px;}
.hero .subtitle{margin-top:7px;font-size:13px;color:var(--muted);}
.hero .intro{margin-top:14px;font-size:11.5px;line-height:1.65;color:var(--body);
  max-width:150mm;}

/* ---- parties (standard) ---- */
.parties{display:flex;gap:40px;margin-top:18px;}
.party{flex:1;}
.plabel{font-family:'Geist Mono',monospace;font-size:8.5px;letter-spacing:.18em;
  text-transform:uppercase;color:var(--faint);margin-bottom:8px;}
.pname{font-size:12.5px;font-weight:600;letter-spacing:-.005em;}
.pln{font-size:10.5px;color:var(--muted);line-height:1.7;margin-top:4px;}

/* ---- section header (red square bullet) ---- */
.sec{margin-top:26px;}
.sec-h{display:flex;align-items:center;gap:11px;}
.sec-h .sq{width:8px;height:8px;border-radius:2px;background:var(--red);flex:none;}
.sec-h .t{font-family:'Geist',sans-serif;font-weight:600;font-size:14.5px;
  letter-spacing:-.01em;}

/* ---- tables ---- */
table{width:100%;border-collapse:collapse;margin-top:14px;}
thead th{font-family:'Geist Mono',monospace;font-size:8.5px;letter-spacing:.16em;
  text-transform:uppercase;color:var(--muted);font-weight:500;text-align:left;
  padding:0 0 9px;border-bottom:1px solid var(--line);}
th.r,td.r{text-align:right;}
tbody td{padding:11px 0;border-bottom:1px solid var(--line);vertical-align:top;}
tbody tr:last-child td{border-bottom:0;}
.c-item{font-size:11.5px;font-weight:500;color:var(--ink);padding-right:14px;
  white-space:nowrap;}
.c-detail{font-size:11px;color:var(--body);line-height:1.55;padding-right:18px;}
.c-price{font-family:'Geist Mono',monospace;font-size:11.5px;color:var(--ink);
  text-align:right;white-space:nowrap;}
.c-price .was{color:var(--faint);text-decoration:line-through;margin-right:7px;}
.price-free{color:var(--red);}
.price-muted{color:var(--muted);}

/* ---- section total ---- */
.sec-total{display:flex;justify-content:space-between;align-items:baseline;
  border-top:1.4px solid var(--strong);padding-top:11px;margin-top:0;}
.sec-total .l{font-weight:600;font-size:11.5px;}
.sec-total .v{font-family:'Geist Mono',monospace;font-weight:500;font-size:13px;
  color:var(--red-deep);}
.sec-note{font-size:10.5px;color:var(--muted);margin-top:13px;line-height:1.5;}

/* ---- bullet lists (red dots) ---- */
.bul{list-style:none;margin-top:14px;}
.bul.two{column-count:2;column-gap:34px;}
.bul li{position:relative;padding-left:17px;margin-bottom:9px;font-size:11px;
  color:var(--body);line-height:1.5;break-inside:avoid;}
.bul li:before{content:"";position:absolute;left:1px;top:5px;width:5px;height:5px;
  border-radius:50%;background:var(--red);}
.bul-eyebrow{font-family:'Geist Mono',monospace;font-size:9px;letter-spacing:.22em;
  text-transform:uppercase;color:var(--red);margin-bottom:4px;}

/* ---- option cards ---- */
.opts-h{display:flex;align-items:center;gap:12px;margin-top:26px;}
.opts-h .promo{font-family:'Geist Mono',monospace;font-size:8px;letter-spacing:.14em;
  text-transform:uppercase;color:var(--red);border:1px solid var(--red);
  border-radius:999px;padding:3px 9px;}
.opts{display:flex;gap:18px;margin-top:14px;}
.opt{flex:1;border:1px solid var(--line);border-radius:9px;padding:18px 19px 19px;}
.opt.accent{border-color:var(--red);}
.opt .badge{font-family:'Geist Mono',monospace;font-size:8.5px;letter-spacing:.16em;
  text-transform:uppercase;color:var(--muted);}
.opt.accent .badge{color:var(--red);}
.opt .t{font-weight:600;font-size:13.5px;margin-top:11px;letter-spacing:-.01em;}
.opt .s{font-size:10.5px;color:var(--muted);margin-top:6px;line-height:1.45;}
.opt .price{display:flex;align-items:baseline;gap:10px;margin-top:22px;}
.opt .price .v{font-family:'Geist Mono',monospace;font-weight:500;font-size:20px;
  color:var(--ink);}
.opt.accent .price .v{color:var(--red-deep);}
.opt .price .was{font-family:'Geist Mono',monospace;font-size:12px;color:var(--faint);
  text-decoration:line-through;}
.opt .price .note{font-family:'Geist Mono',monospace;font-size:9.5px;color:var(--muted);}

/* ---- generic blocks ---- */
.para{font-size:11px;color:var(--body);line-height:1.6;margin-top:13px;max-width:155mm;}

/* ---- summary ---- */
.sum{margin-top:14px;}
.sum-row{display:flex;justify-content:space-between;align-items:baseline;
  padding:11px 0;border-bottom:1px solid var(--line);font-size:11.5px;}
.sum-row .l{color:var(--ink);}
.sum-row .v{font-family:'Geist Mono',monospace;color:var(--ink);}
.sum-row.muted .l,.sum-row.muted .v{color:var(--muted);}
.sum-row.redv .v{color:var(--red);}
.sum-row.total{border-top:1.4px solid var(--strong);border-bottom:0;margin-top:1px;
  padding-top:13px;}
.sum-row.total .l{font-weight:600;font-size:13px;}
.sum-row.total .v{font-size:14px;font-weight:500;color:var(--red-deep);}

/* ---- panels ---- */
.panels{display:flex;gap:18px;margin-top:18px;}
.panel{flex:1;border:1px solid var(--line);border-radius:9px;padding:17px 19px 18px;}
.panel.accent{border-color:var(--red);}
.panel .val{font-family:'Geist Mono',monospace;font-size:22px;
  color:var(--ink);letter-spacing:-.01em;}
.panel .label{font-family:'Geist Mono',monospace;font-size:9px;letter-spacing:.16em;
  text-transform:uppercase;color:var(--muted);margin-top:6px;}
.panel.accent .label{color:var(--red);}
.panel.accent .val{color:var(--red);}
.panel .note{font-size:10px;color:var(--muted);line-height:1.55;margin-top:10px;}
.panel .note b{color:var(--ink);font-weight:500;}

/* ---- bottom notes ---- */
.notes{margin-top:16px;padding-top:13px;border-top:1px solid var(--line);}
.notes .n{font-size:10.5px;color:var(--body);line-height:1.55;margin-bottom:5px;}
.notes .n b{color:var(--ink);font-weight:600;}

/* ---- standard totals ---- */
.foot{display:flex;justify-content:space-between;gap:44px;margin-top:18px;}
.terms{flex:1;max-width:100mm;}
.totals{width:78mm;}
.tot-row{display:flex;justify-content:space-between;font-size:11px;color:var(--muted);
  padding:8px 0;}
.tot-row .v{font-family:'Geist Mono',monospace;color:var(--ink);}
.tot-row.grand{border-top:1.4px solid var(--strong);margin-top:4px;padding-top:12px;
  font-size:13px;font-weight:600;color:var(--ink);}
.tot-row.grand .v{font-size:14px;font-weight:500;color:var(--red-deep);}
.deposit{margin-top:14px;border:1px solid var(--red);border-radius:9px;padding:15px 18px;}
.deposit .val{font-family:'Geist Mono',monospace;font-size:22px;color:var(--red);}
.deposit .label{font-family:'Geist Mono',monospace;font-size:9px;letter-spacing:.16em;
  text-transform:uppercase;color:var(--red);margin-top:6px;}
.deposit .bal{font-size:10px;color:var(--muted);margin-top:9px;}

/* ---- payment / acceptance (standard) ---- */
.lower{display:flex;gap:44px;margin-top:18px;padding-top:14px;border-top:1px solid var(--line);}
.pay{flex:1;}
.pay .ln{font-size:10.5px;color:var(--muted);line-height:1.9;}
.pay .ln b{color:var(--ink);font-weight:500;}
.pay .mono{font-family:'Geist Mono',monospace;color:var(--ink);}
.accept{width:78mm;}
.sign{margin-top:22px;border-top:1px solid var(--strong);padding-top:7px;
  font-family:'Geist Mono',monospace;font-size:8.5px;letter-spacing:.14em;
  text-transform:uppercase;color:var(--faint);}

/* ---- designed-by credit (end of body) ---- */
.credit{margin-top:30px;padding-top:14px;border-top:1px solid var(--line);}
.credit .name{font-weight:600;font-size:12px;}
.credit .tag{font-size:10.5px;color:var(--muted);margin-top:3px;}
.credit .contact{font-family:'Geist Mono',monospace;font-size:10px;color:var(--body);
  margin-top:9px;letter-spacing:.01em;}
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

function sectionHeaderHTML(title: string, promo?: string): string {
  const pill = promo ? `<span class="promo">${esc(promo)}</span>` : "";
  return `<div class="sec-h"><span class="sq"></span><span class="t">${esc(title)}</span>${pill}</div>`;
}

function sectionHTML(sec: Section, cur: string): string {
  const total =
    sec.totalLabel != null
      ? `<div class="sec-total"><span class="l">${esc(sec.totalLabel)}</span>` +
        `<span class="v">${sec.totalText ? esc(sec.totalText) : money(sec.total ?? 0, cur)}</span></div>`
      : "";
  const note = sec.note ? `<div class="sec-note">${esc(sec.note)}</div>` : "";
  return `<div class="sec">
    ${sectionHeaderHTML(sec.title)}
    ${tableHTML(sec.rows, cur, ["Item", "Detail", "Price"])}
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

function panelHTML(p: Panel, cur: string): string {
  const note = p.note
    ? `<div class="note">${esc(p.note).replace(/\n/g, "<br>")}</div>`
    : "";
  return `<div class="panel${p.accent ? " accent" : ""}">
    <div class="val">${money(p.value, cur, 2)}</div>
    <div class="label">${esc(p.label)}</div>${note}
  </div>`;
}

function headHTML(data: DocumentData): string {
  const kindWord =
    data.kind === "invoice" ? "Invoice" : data.kind === "receipt" ? "Receipt" : "";
  const logo = data.studio.logo || STUDIO_LOGO;
  const tag = data.studio.tagline
    ? `<div class="tag">${esc(data.studio.tagline)}</div>`
    : "";

  const pairs: string[] = [];
  if (data.kind === "quotation") {
    pairs.push(pair("Quotation", data.number));
  } else {
    pairs.push(pair("No.", data.number));
  }
  pairs.push(pair("Date", data.issued));
  if (data.validUntil) {
    const lab =
      data.metaLabel2 ?? (data.kind === "quotation" ? "Valid until" : "Due");
    pairs.push(pair(lab, data.validUntil));
  }
  if (data.status) pairs.push(pair("Status", data.status));

  const big = kindWord ? `<div class="kind-big">${kindWord}</div>` : "";

  return `<div class="head">
    <div class="brand">
      <img class="logo" src="${esc(logo)}" alt="${esc(data.studio.name)}" />
      <div>
        <div class="wm">${wordmark(data.studio.name)}</div>
        ${tag}
      </div>
    </div>
    <div class="doc">${big}${pairs.join("")}</div>
  </div>`;
}

function pair(label: string, value: string): string {
  return `<div class="pair"><div class="lab">${esc(label)}</div><div class="val">${esc(value)}</div></div>`;
}

/** Wordmark on (at most) two lines: first two words, then the rest. */
function wordmark(name: string): string {
  const w = name.trim().split(/\s+/);
  if (w.length <= 2) return esc(name);
  return `${esc(w.slice(0, 2).join(" "))}<br>${esc(w.slice(2).join(" "))}`;
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
    ? `<div class="tot-row"><span>Discount</span><span class="v">− ${money(t.discount, cur)}</span></div>`
    : "";
  const taxRow = data.taxRate
    ? `<div class="tot-row"><span>${esc(data.taxLabel ?? "Tax")}</span><span class="v">${money(t.tax, cur)}</span></div>`
    : "";

  const terms = (data.terms ?? []).map((x) => `<li>${esc(x)}</li>`).join("");
  const studioLn = [data.studio.email, data.studio.reg]
    .filter(Boolean)
    .map(esc)
    .join("<br>");

  const depositCard =
    (data.depositPct ?? 100) < 100
      ? `<div class="deposit">
           <div class="val">${money(t.deposit, cur, 2)}</div>
           <div class="label">Deposit to commence · ${data.depositPct}%</div>
           <div class="bal">Balance on delivery&nbsp;&nbsp;${money(t.balance, cur, 2)}</div>
         </div>`
      : "";

  return `
  ${headHTML(data)}
  <div class="rule"></div>
  <div class="parties">
    <div class="party">
      <div class="plabel">From</div>
      <div class="pname">${esc(data.studio.name)}</div>
      <div class="pln">${studioLn}</div>
    </div>
    <div class="party">
      <div class="plabel">${data.kind === "quotation" ? "Prepared for" : "Billed to"}</div>
      <div class="pname">${esc(data.client.name)}</div>
      <div class="pln">${[esc(data.client.attn), addr, esc(data.client.email)].filter(Boolean).join("<br>")}</div>
    </div>
  </div>

  <div class="hero" style="margin-top:18px">
    <div class="title" style="font-size:19px">${esc(data.project)}</div>
    ${data.intro ? `<div class="intro">${esc(data.intro)}</div>` : ""}
  </div>

  <table>
    <thead><tr>
      <th>Scope of work</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">Amount</th>
    </tr></thead>
    <tbody>${rows}</tbody>
  </table>

  <div class="foot">
    <div class="terms">
      ${terms ? `<div class="plabel">Terms</div><ul class="bul">${terms}</ul>` : ""}
    </div>
    <div class="totals">
      <div class="tot-row"><span>Subtotal</span><span class="v">${money(t.subtotal, cur)}</span></div>
      ${discountRow}${taxRow}
      <div class="tot-row grand"><span>Total</span><span class="v">${money(t.total, cur)}</span></div>
      ${depositCard}
    </div>
  </div>

  <div class="lower">
    <div class="pay">
      <div class="plabel">Payment</div>
      ${data.pay?.online ? `<div class="ln"><b>Online</b>&nbsp; ${esc(data.pay.online)}</div>` : ""}
      ${data.pay?.bank ? `<div class="ln"><b>Transfer</b>&nbsp; ${esc(data.pay.bank)}</div>` : ""}
      ${data.pay?.acct ? `<div class="ln"><b>Account</b>&nbsp; <span class="mono">${esc(data.pay.acct)}</span></div>` : ""}
    </div>
    <div class="accept">
      <div class="plabel">${data.kind === "quotation" ? "Acceptance" : "Notes"}</div>
      <div class="ln" style="font-size:10.5px;line-height:1.55">${
        data.kind === "quotation"
          ? "Approve this quotation to begin. A deposit invoice follows on acceptance."
          : "Thank you. Payment is due by the date shown above."
      }</div>
      ${data.kind === "quotation" ? `<div class="sign">Signature&nbsp;·&nbsp;Date</div>` : ""}
    </div>
  </div>

  ${creditHTML(data)}
  `;
}

/* ---------------------------------------------------------- detailed layout */

function renderDetailed(data: DocumentData): string {
  const cur = data.currency;
  const parts: string[] = [headHTML(data), `<div class="rule"></div>`];

  // Hero — quotation leads with the client as the title; invoice/receipt use a
  // Bill-to / Project split.
  if (data.kind === "quotation") {
    parts.push(`<div class="hero">
      <div class="eyebrow">Prepared for</div>
      <div class="title">${esc(data.project)}</div>
      ${data.subtitle ? `<div class="subtitle">${esc(data.subtitle)}</div>` : ""}
      ${data.intro ? `<div class="intro">${esc(data.intro)}</div>` : ""}
    </div>`);
  } else {
    const addr = esc(data.client.address).replace(/\n/g, "<br>");
    parts.push(`<div class="parties" style="margin-top:20px">
      <div class="party">
        <div class="plabel">Bill to</div>
        <div class="pname">${esc(data.client.name)}</div>
        <div class="pln">${[esc(data.client.attn), addr, esc(data.client.email)].filter(Boolean).join("<br>")}</div>
      </div>
      <div class="party">
        <div class="plabel">Project</div>
        <div class="pname">${esc(data.project)}</div>
        ${data.subtitle ? `<div class="pln">${esc(data.subtitle)}</div>` : ""}
      </div>
    </div>`);
    if (data.intro) parts.push(`<div class="para">${esc(data.intro)}</div>`);
  }

  // Sections (packages)
  for (const sec of data.sections ?? []) parts.push(sectionHTML(sec, cur));

  // "What's included" bullet groups
  for (const inc of data.included ?? [])
    parts.push(`<div class="sec">${bulletHTML(inc, cur)}</div>`);

  // Package options
  if (data.options) {
    const cards = data.options.cards.map((c) => optionCardHTML(c, cur)).join("");
    parts.push(`<div class="opts-block">
      <div class="opts-h">${sectionHeaderHTML(data.options.title ?? "Package options", data.options.promo)}</div>
      <div class="opts">${cards}</div>
    </div>`);
  }

  // Care & hosting
  if (data.care) {
    const headers = data.care.headers ?? ["Plan", "Detail", "Price"];
    const rows: DetailRow[] = data.care.rows.map((r) => ({
      title: r.label,
      detail: r.detail,
      priceText: `${money(r.price, cur)}${r.period ? ` / ${r.period}` : ""}`,
      priceMuted: true,
    }));
    parts.push(`<div class="sec">
      ${sectionHeaderHTML(data.care.title)}
      ${data.care.intro ? `<div class="para" style="margin-top:11px">${esc(data.care.intro)}</div>` : ""}
      ${tableHTML(rows, cur, headers)}
      ${data.care.note ? `<div class="sec-note">${esc(data.care.note)}</div>` : ""}
    </div>`);
  }

  // What you provide
  if (data.provide)
    parts.push(`<div class="sec">${sectionHeaderHTML(data.provide.title ?? "What you provide")}${bulletHTML(data.provide)}</div>`);

  // Not included
  if (data.notIncluded)
    parts.push(`<div class="sec">${sectionHeaderHTML(data.notIncluded.title ?? "Not included in this version")}${bulletHTML(data.notIncluded)}</div>`);

  // Timeline
  if (data.timeline)
    parts.push(`<div class="sec">${sectionHeaderHTML(data.timeline.title ?? "Timeline")}<div class="para">${esc(data.timeline.text)}</div></div>`);

  // Payment terms
  if (data.paymentTerms)
    parts.push(`<div class="sec">${sectionHeaderHTML(data.paymentTerms.title ?? "Payment terms")}${bulletHTML({ items: data.paymentTerms.items })}</div>`);

  // Summary (invoice)
  if (data.summary) {
    const rows = data.summary.rows
      .map((r) => {
        const cls = [
          r.total ? "total" : "",
          r.priceMuted ? "muted" : "",
          r.red ? "redv" : "",
        ]
          .filter(Boolean)
          .join(" ");
        return `<div class="sum-row ${cls}"><span class="l">${esc(r.label)}</span><span class="v">${priceCell(r, cur)}</span></div>`;
      })
      .join("");
    parts.push(`<div class="sec">${sectionHeaderHTML(data.summary.title ?? "Summary")}<div class="sum">${rows}</div></div>`);
  }

  // Deposit / balance panels
  if (data.panels?.length)
    parts.push(`<div class="panels">${data.panels.map((p) => panelHTML(p, cur)).join("")}</div>`);

  // Bottom notes
  if (data.notes?.length)
    parts.push(`<div class="notes">${data.notes.map((n) => `<div class="n"><b>${esc(n.label)}</b> ${esc(n.text)}</div>`).join("")}</div>`);

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

  // Running page-foot identity line (left side; page numbers come from @page).
  const pgfootL = [data.studio.name, data.studio.tagline, data.number]
    .filter(Boolean)
    .join("  ·  ");
  const rootVar = `:root{--pgfoot-l:"${cssStr(pgfootL)}";}`;

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
