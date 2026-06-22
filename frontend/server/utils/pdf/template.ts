import { FONT_FACES } from "./fonts";
import type { DocumentData, ComputedTotals } from "./types";

/* Validated CSS — identical to the proven reference render. Do not reflow the
   vertical spacing without re-checking that content fits one A4 page. */
const CSS = `
:root{
  --paper:#FCFCFB; --ink:#15171A; --muted:#7A7F87; --faint:#A6AAB0;
  --line:#E7E5E0; --line2:#EEEDE9; --brass:#9A7B4F; --panel:#16181B;
}
*{margin:0;padding:0;box-sizing:border-box;}
@page{size:A4;margin:0;}
html,body{background:var(--paper);color:var(--ink);
  font-family:'Inter',sans-serif;-webkit-font-smoothing:antialiased;
  font-feature-settings:"tnum" 1,"cv05" 1;}
.page{width:210mm;min-height:297mm;padding:14mm 20mm 0;position:relative;background:var(--paper);overflow:hidden;}
.topline{position:absolute;top:0;left:0;right:0;height:3px;background:var(--brass);}
.head{display:flex;justify-content:space-between;align-items:flex-start;}
.brand .mark{font-family:'Cormorant',serif;font-weight:500;font-size:30px;
  letter-spacing:.42em;text-transform:uppercase;line-height:1;padding-left:.42em;}
.brand .tag{margin-top:9px;font-size:9.5px;letter-spacing:.26em;text-transform:uppercase;color:var(--muted);}
.doc{text-align:right;}
.doc .kind{font-family:'Cormorant',serif;font-size:22px;letter-spacing:.34em;
  text-transform:uppercase;color:var(--ink);padding-left:.34em;}
.doc .meta{margin-top:12px;font-family:'Plex',monospace;font-size:9.5px;
  letter-spacing:.02em;color:var(--muted);line-height:1.85;}
.doc .meta b{color:var(--ink);font-weight:500;}
.rule{height:1px;background:var(--line);margin:16px 0 0;}
.parties{display:flex;gap:34px;margin-top:12px;}
.party{flex:1;}
.eyebrow{font-size:8.5px;letter-spacing:.24em;text-transform:uppercase;color:var(--faint);margin-bottom:9px;}
.party .name{font-size:12.5px;font-weight:600;letter-spacing:.005em;}
.party .ln{font-size:10.5px;color:var(--muted);line-height:1.7;margin-top:3px;}
.project{margin-top:12px;padding-top:10px;border-top:1px solid var(--line);}
.project .pt{font-family:'Cormorant',serif;font-weight:500;font-size:19px;letter-spacing:.005em;}
.project .pi{margin-top:7px;font-size:10.5px;line-height:1.75;color:var(--muted);max-width:155mm;}
table{width:100%;border-collapse:collapse;margin-top:18px;}
thead th{font-size:8.5px;letter-spacing:.2em;text-transform:uppercase;color:var(--faint);
  font-weight:500;text-align:left;padding:0 0 11px;border-bottom:1px solid var(--ink);}
th.c-qty,th.c-rate,th.c-amt{text-align:right;}
tbody td{padding:7px 0;border-bottom:1px solid var(--line2);vertical-align:top;}
.c-num{width:34px;font-family:'Plex',monospace;font-size:10px;color:var(--brass);padding-top:9px;}
.c-desc{padding-right:18px;}
.it-title{font-size:11.5px;font-weight:600;letter-spacing:.005em;}
.it-sub{font-size:9.8px;color:var(--muted);line-height:1.6;margin-top:4px;max-width:96mm;}
.c-qty,.c-rate,.c-amt{text-align:right;white-space:nowrap;font-size:11px;padding-top:9px;}
.c-qty{width:74px;color:var(--ink);}
.c-qty .unit{color:var(--faint);font-size:9px;}
.c-rate{width:96px;color:var(--muted);}
.c-amt{width:104px;font-weight:600;font-variant-numeric:tabular-nums;}
.foot{display:flex;justify-content:space-between;gap:40px;margin-top:14px;}
.terms{flex:1;max-width:96mm;}
.terms .eyebrow{margin-bottom:11px;}
.terms ul{list-style:none;}
.terms li{position:relative;font-size:9.6px;color:var(--muted);line-height:1.55;
  padding-left:15px;margin-bottom:7px;}
.terms li:before{content:"";position:absolute;left:0;top:6px;width:4px;height:4px;
  background:var(--brass);transform:rotate(45deg);}
.totals{width:74mm;}
.tot-row{display:flex;justify-content:space-between;font-size:10.5px;color:var(--muted);
  padding:7px 0;}
.tot-row span:last-child{color:var(--ink);font-variant-numeric:tabular-nums;}
.tot-row.grand{border-top:1px solid var(--ink);margin-top:5px;padding-top:12px;
  font-size:13px;font-weight:600;color:var(--ink);}
.tot-row.grand span{color:var(--ink);}
.deposit{margin-top:12px;background:var(--panel);color:#F4F2EE;border-radius:3px;
  padding:14px 17px;}
.deposit .lab{font-size:8.5px;letter-spacing:.22em;text-transform:uppercase;color:#B9A98E;}
.deposit .val{margin-top:5px;font-family:'Cormorant',serif;font-size:25px;font-weight:600;
  font-variant-numeric:tabular-nums;white-space:nowrap;line-height:1;}
.deposit .bal{margin-top:9px;font-size:9px;color:#9A9690;letter-spacing:.02em;}
.lower{display:flex;gap:40px;margin-top:12px;padding-top:10px;border-top:1px solid var(--line);}
.pay{flex:1;}
.pay .ln{font-size:10px;color:var(--muted);line-height:1.85;}
.pay .ln b{color:var(--ink);font-weight:500;}
.pay .mono{font-family:'Plex',monospace;letter-spacing:.02em;color:var(--ink);}
.accept{width:74mm;}
.sign{margin-top:20px;border-top:1px solid var(--ink);padding-top:7px;
  font-size:8.5px;letter-spacing:.18em;text-transform:uppercase;color:var(--faint);}
.pgfoot{position:absolute;left:20mm;right:20mm;bottom:7mm;display:flex;
  justify-content:space-between;font-size:8.5px;letter-spacing:.04em;color:var(--faint);
  border-top:1px solid var(--line);padding-top:9px;}
`;

/** Minimal HTML escaping for user-supplied text fields. */
function esc(s: string | undefined): string {
  if (!s) return "";
  return s
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

function money(n: number, cur: string): string {
  return `${cur} ${n.toLocaleString("en-MY", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

export function computeTotals(d: DocumentData): ComputedTotals {
  const subtotal = d.items.reduce((s, i) => s + i.qty * i.rate, 0);
  const discount = d.discount ?? 0;
  const base = subtotal - discount;
  const tax = Math.round(base * (d.taxRate ?? 0) * 100) / 100;
  const total = base + tax;
  const deposit =
    Math.round(total * ((d.depositPct ?? 100) / 100) * 100) / 100;
  const balance = total - deposit;
  return { subtotal, discount, tax, total, deposit, balance };
}

/**
 * Render a full standalone HTML document (fonts embedded) for the given data.
 * Works for both quotations and invoices via `data.kind`.
 */
export function renderDocumentHTML(data: DocumentData): string {
  const cur = data.currency;
  const t = computeTotals(data);
  const isInvoice = data.kind === "invoice";

  const rows = data.items
    .map((it, i) => {
      const amt = it.qty * it.rate;
      return `
      <tr>
        <td class="c-num">${String(i + 1).padStart(2, "0")}</td>
        <td class="c-desc">
          <div class="it-title">${esc(it.title)}</div>
          ${it.desc ? `<div class="it-sub">${esc(it.desc)}</div>` : ""}
        </td>
        <td class="c-qty">${it.qty} <span class="unit">${esc(it.unit)}</span></td>
        <td class="c-rate">${money(it.rate, cur)}</td>
        <td class="c-amt">${money(amt, cur)}</td>
      </tr>`;
    })
    .join("");

  const discountRow = t.discount
    ? `<div class="tot-row"><span>Discount</span><span>− ${money(t.discount, cur)}</span></div>`
    : "";
  const taxRow = data.taxRate
    ? `<div class="tot-row"><span>${esc(data.taxLabel ?? "Tax")}</span><span>${money(t.tax, cur)}</span></div>`
    : "";

  const terms = (data.terms ?? [])
    .map((x) => `<li>${esc(x)}</li>`)
    .join("");
  const addr = esc(data.client.address).replace(/\n/g, "<br>");
  const studioLn = [data.studio.location, data.studio.email, data.studio.reg]
    .filter(Boolean)
    .map(esc)
    .join("<br>");

  // Right-column second meta line + the dark panel differ by document kind.
  const secondMetaLabel = isInvoice
    ? (data.dueLabel ?? "Due")
    : "Valid until";
  const panelLabel = isInvoice
    ? (data.amountDueLabel ?? "Amount due")
    : `Deposit to commence · ${data.depositPct ?? 50}%`;
  // Quote: deposit to collect first. Invoice: amount being billed
  // (equals the full total when depositPct is 100/unset).
  const panelValue = t.deposit;
  const panelSub = isInvoice
    ? (data.pay?.online ? `Pay online · ${esc(data.pay.online)}` : "")
    : `Balance on delivery&nbsp;&nbsp;${money(t.balance, cur)}`;

  const acceptBlock = isInvoice
    ? `<div class="ln" style="font-size:9.6px;color:var(--muted);line-height:1.6">
         Thank you. Payment is due by the date shown above.</div>`
    : `<div class="ln" style="font-size:9.6px;color:var(--muted);line-height:1.6">
         Approve this quotation to begin. A deposit invoice follows on acceptance.</div>
       <div class="sign">Signature &nbsp;·&nbsp; Date</div>`;

  return `<!doctype html><html><head><meta charset="utf-8"><style>
${FONT_FACES}
${CSS}
</style></head><body>
<div class="page">
  <div class="topline"></div>

  <div class="head">
    <div class="brand">
      <div class="mark">${esc(data.studio.name).split(" ").slice(0, 2).join(" ") || "Axel Nova"}</div>
      <div class="tag">${esc(data.studio.tagline ?? "Design & Engineering Studio")}</div>
    </div>
    <div class="doc">
      <div class="kind">${isInvoice ? "Invoice" : "Quotation"}</div>
      <div class="meta">
        <div><b>${esc(data.number)}</b></div>
        <div>Issued&nbsp;&nbsp;${esc(data.issued)}</div>
        ${data.validUntil ? `<div>${secondMetaLabel}&nbsp;&nbsp;${esc(data.validUntil)}</div>` : ""}
      </div>
    </div>
  </div>

  <div class="rule"></div>

  <div class="parties">
    <div class="party">
      <div class="eyebrow">From</div>
      <div class="name">${esc(data.studio.name)}</div>
      <div class="ln">${studioLn}</div>
    </div>
    <div class="party">
      <div class="eyebrow">${isInvoice ? "Billed to" : "Prepared for"}</div>
      <div class="name">${esc(data.client.name)}</div>
      <div class="ln">${[esc(data.client.attn), addr, esc(data.client.email)].filter(Boolean).join("<br>")}</div>
    </div>
  </div>

  <div class="project">
    <div class="pt">${esc(data.project)}</div>
    ${data.intro ? `<div class="pi">${esc(data.intro)}</div>` : ""}
  </div>

  <table>
    <thead><tr>
      <th class="c-num"></th><th>Scope of work</th>
      <th class="c-qty">Qty</th><th class="c-rate">Rate</th><th class="c-amt">Amount</th>
    </tr></thead>
    <tbody>${rows}</tbody>
  </table>

  <div class="foot">
    <div class="terms">
      <div class="eyebrow">Terms</div>
      <ul>${terms}</ul>
    </div>
    <div class="totals">
      <div class="tot-row"><span>Subtotal</span><span>${money(t.subtotal, cur)}</span></div>
      ${discountRow}${taxRow}
      <div class="tot-row grand"><span>Total</span><span>${money(t.total, cur)}</span></div>
      <div class="deposit">
        <div class="lab">${panelLabel}</div>
        <div class="val">${money(panelValue, cur)}</div>
        ${panelSub ? `<div class="bal">${panelSub}</div>` : ""}
      </div>
    </div>
  </div>

  <div class="lower">
    <div class="pay">
      <div class="eyebrow">Payment</div>
      ${data.pay?.online ? `<div class="ln"><b>Online&nbsp;</b> ${esc(data.pay.online)}</div>` : ""}
      ${data.pay?.bank ? `<div class="ln"><b>Transfer&nbsp;</b> ${esc(data.pay.bank)}</div>` : ""}
      ${data.pay?.acct ? `<div class="ln"><b>Account&nbsp;</b> <span class="mono">${esc(data.pay.acct)}</span></div>` : ""}
    </div>
    <div class="accept">
      <div class="eyebrow">${isInvoice ? "Notes" : "Acceptance"}</div>
      ${acceptBlock}
    </div>
  </div>

  <div class="pgfoot">
    <span>${esc(data.studio.name)}${data.studio.reg ? ` · ${esc(data.studio.reg)}` : ""}</span>
    <span>${esc(data.studio.site ?? "")}</span>
  </div>
</div>
</body></html>`;
}
