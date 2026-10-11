// Snapshot + behaviour tests for the Payment plan section of the quotation PDF
// template, per plan × locale. The snapshots pin the markup the renderer emits
// for the same data in English and Bahasa Melayu — chrome from the locale file,
// founder content untouched, money as "RM 2,700.00", dates per locale.
import { describe, expect, it } from 'vitest'
import { paymentPlanHTML, renderDocumentHTML } from './template'
import type { DocumentData, DocumentLocale, Panel, PaymentPlanBlock } from './types'

const PLANS: DocumentLocale[] = ['en', 'bm']

/** AXNQ-2026-0017: RM 14,340 — fixed RM 2,700 deposit, then 12 × RM 970 from 20 Nov 2026 on the 20th. */
const instalment: PaymentPlanBlock = {
  plan: 'instalment',
  deposit: 2700,
  depositPctLabel: '18.8%',
  balance: 11640,
  monthly: 970,
  months: 12,
  billingDay: 20,
  firstDate: '2026-11-20',
  lastDate: '2027-10-20',
  includesCarePlan: true,
  total: 14340,
  schedule: Array.from({ length: 12 }, (_, i) => {
    const m = 11 + i // November 2026 … October 2027
    const year = 2026 + Math.floor((m - 1) / 12)
    const month = ((m - 1) % 12) + 1
    return { n: i + 1, date: `${year}-${String(month).padStart(2, '0')}-20`, amount: 970 }
  }),
}
const instalmentPanels: Panel[] = [
  { role: 'inst_deposit', value: 2700 },
  { role: 'inst_monthly', value: 970, accent: true, months: 12, billingDay: 20, firstDate: '2026-11-20', lastDate: '2027-10-20' },
]

const partner: PaymentPlanBlock = {
  plan: 'partner',
  deposit: 1000,
  depositPctLabel: '7.7%',
  balance: 12000,
  monthly: 500,
  months: 24,
  billingDay: 20,
  firstDate: '2027-01-20',
  lastDate: '2028-12-20',
  includesCarePlan: false,
  total: 13000,
  schedule: Array.from({ length: 24 }, (_, i) => {
    const year = 2027 + Math.floor(i / 12)
    const month = (i % 12) + 1
    return { n: i + 1, date: `${year}-${String(month).padStart(2, '0')}-20`, amount: 500 }
  }),
}
const partnerPanels: Panel[] = [
  { role: 'partner_setup', value: 1000 },
  { role: 'partner_monthly', value: 500, accent: true, months: 24, billingDay: 20, firstDate: '2027-01-20', lastDate: '2028-12-20' },
]

const lumpSum: PaymentPlanBlock = {
  plan: 'lump_sum',
  deposit: 5000,
  depositPctLabel: '50%',
  balance: 5000,
  monthly: 0,
  months: 0,
  billingDay: 20,
  firstDate: null,
  lastDate: null,
  includesCarePlan: false,
  total: 10000,
  schedule: [],
}
const lumpSumPanels: Panel[] = [
  { role: 'lump_deposit', value: 5000, pctLabel: '50%' },
  { role: 'lump_balance', value: 5000, accent: true },
]

describe('paymentPlanHTML snapshots (plan × locale)', () => {
  for (const locale of PLANS) {
    it(`instalment · ${locale}`, () => {
      expect(paymentPlanHTML(instalment, 'RM', locale, instalmentPanels)).toMatchSnapshot()
    })
    it(`partner · ${locale}`, () => {
      expect(paymentPlanHTML(partner, 'RM', locale, partnerPanels)).toMatchSnapshot()
    })
    it(`lump_sum · ${locale}`, () => {
      expect(paymentPlanHTML(lumpSum, 'RM', locale, lumpSumPanels)).toMatchSnapshot()
    })
  }
})

describe('paymentPlanHTML behaviour', () => {
  it('titles the instalment section from the locale file and the month count', () => {
    const en = paymentPlanHTML(instalment, 'RM', 'en', instalmentPanels)
    expect(en).toContain('>Payment plan<')
    expect(en).toContain('<h2 class="pp-title">12-Month Instalment Plan</h2>')
    expect(en).toContain('Deposit RM 2,700.00 · 12 × RM 970.00 · billed on the 20th · total RM 14,340.00')
    expect(en).toContain('>Payment schedule<')
    expect(en).toContain('<th>Payment</th><th>Date</th><th class="r">Amount</th>')
    expect(en).toContain('Instalment 1')
    expect(en).toContain('Instalment 12')
    expect(en).toContain('20 November 2026')
    expect(en).toContain('20 October 2027')
    expect(en).toContain('Monthly instalment')
    expect(en).toContain('× 12 months, includes Care Plan')
    expect(en).toContain('20th of each month')
    expect(en).not.toContain('Ansuran')

    const bm = paymentPlanHTML(instalment, 'RM', 'bm', instalmentPanels)
    expect(bm).toContain('>Pelan pembayaran<')
    expect(bm).toContain('<h2 class="pp-title">Pelan Ansuran 12 Bulan</h2>')
    expect(bm).toContain('Deposit RM 2,700.00 · 12 × RM 970.00 · dibil pada 20 haribulan · jumlah RM 14,340.00')
    expect(bm).toContain('>Jadual bayaran<')
    expect(bm).toContain('<th>Bayaran</th><th>Tarikh</th><th class="r">Jumlah</th>')
    expect(bm).toContain('Ansuran 1')
    expect(bm).toContain('20 Disember 2026')
    expect(bm).toContain('20 Oktober 2027')
    expect(bm).not.toContain('Instalment')
  })

  it('titles partner and lump-sum plans per locale', () => {
    expect(paymentPlanHTML(partner, 'RM', 'en', partnerPanels)).toContain('Technology Partner · 24 months')
    expect(paymentPlanHTML(partner, 'RM', 'bm', partnerPanels)).toContain('Rakan Teknologi · 24 bulan')
    expect(paymentPlanHTML(partner, 'RM', 'en', partnerPanels)).toContain('Month 24')
    expect(paymentPlanHTML(partner, 'RM', 'bm', partnerPanels)).toContain('Bulan 24')
    expect(paymentPlanHTML(lumpSum, 'RM', 'en', lumpSumPanels)).toContain('One-Time Payment')
    expect(paymentPlanHTML(lumpSum, 'RM', 'bm', lumpSumPanels)).toContain('Bayaran Sekali Gus')
  })

  it('breaks scheduled plans onto their own page and keeps a lump sum inline', () => {
    expect(paymentPlanHTML(instalment, 'RM', 'en', instalmentPanels)).toMatch(/<div class="payment-plan">/)
    expect(paymentPlanHTML(partner, 'RM', 'en', partnerPanels)).toMatch(/<div class="payment-plan">/)
    const lump = paymentPlanHTML(lumpSum, 'RM', 'en', lumpSumPanels)
    expect(lump).toMatch(/<div class="payment-plan inline">/)
    expect(lump).not.toContain('class="schedule"')
    expect(lump).not.toContain('class="summary"')
    expect(lump).toContain('Deposit RM 5,000.00 (50%) · balance RM 5,000.00 on completion · total RM 10,000.00')
  })

  it('labels the deposit / monthly cards from the locale file by role', () => {
    const en = paymentPlanHTML(instalment, 'RM', 'en', instalmentPanels)
    expect(en).toContain('Deposit on acceptance')
    expect(en).toContain('Monthly instalment · 12 months')
    expect(en).toContain('Billed on the 20th of each month, 20 November 2026 to 20 October 2027.')
    const bm = paymentPlanHTML(instalment, 'RM', 'bm', instalmentPanels)
    expect(bm).toContain('Deposit semasa penerimaan')
    expect(bm).toContain('Ansuran bulanan · 12 bulan')
    expect(bm).toContain('Dibil pada 20 haribulan setiap bulan, 20 November 2026 hingga 20 Oktober 2027.')
    expect(paymentPlanHTML(lumpSum, 'RM', 'en', lumpSumPanels)).toContain('Deposit (50%)')
    expect(paymentPlanHTML(lumpSum, 'RM', 'bm', lumpSumPanels)).toContain('Baki selepas siap')
  })
})

describe('renderDocumentHTML', () => {
  const base: DocumentData = {
    layout: 'detailed',
    kind: 'quotation',
    number: 'AXNQ-2026-0017',
    issued: '2026-10-10',
    validUntil: '2026-11-09',
    currency: 'RM',
    studio: { name: 'Axel Nova Ventures', tagline: 'simple, effortless, human.', email: 'hello@example.com', site: 'example.com' },
    client: { name: 'Pengurusan M Automobile Service', company: 'M Automobile Service Sdn. Bhd.' },
    project: 'Sistem Bengkel — Pakej B (Invois Penuh + Website) · Ansuran 12 bulan',
    intro: 'Deposit RM 2,700 semasa penerimaan, kemudian RM 970 sebulan × 12.',
    sections: [{ title: 'Sistem bengkel', rows: [{ title: 'Invois penuh', detail: 'Invois, resit, laporan', price: 9000 }], totalLabel: 'Sistem bengkel total', total: 9000 }],
    summary: { rows: [{ label: 'Project total', price: 14340, total: true, red: true }] },
    panels: instalmentPanels,
    paymentPlan: instalment,
  }

  it('prints the chrome in the document locale and the content as authored', () => {
    const en = renderDocumentHTML({ ...base, locale: 'en' })
    expect(en).toContain('>Prepared for<')
    expect(en).toContain('>Date<')
    expect(en).toContain('10 October 2026')
    expect(en).toContain('>Valid until<')
    expect(en).toContain('9 November 2026')
    expect(en).toContain('<th>Item</th><th>Detail</th><th class="r">Price</th>')
    expect(en).toContain('--pg-page:"Page";--pg-of:"of";')
    expect(en).toContain('--pgfoot-l:"Axel Nova Ventures  ·  SSM No : 202603119899 (CA0420977-U)  ·  AXNQ-2026-0017";')
    expect(en).not.toContain('--pgfoot-l:"Axel Nova Ventures  ·  simple, effortless, human.')
    expect(en).toContain('<div class="name">Designed by Qie,<br>Axel Nova Ventures</div>')
    expect(en).toContain('.payment-plan{break-before:page;page-break-before:always;}')
    expect(en).toContain('.payment-plan .schedule thead{display:table-header-group;}')
    expect(en).toContain('.payment-plan .schedule tr{break-inside:avoid;page-break-inside:avoid;}')

    const bm = renderDocumentHTML({ ...base, locale: 'bm' })
    expect(bm).toContain('>Disediakan untuk<')
    expect(bm).toContain('>Tarikh<')
    expect(bm).toContain('10 Oktober 2026')
    expect(bm).toContain('>Sah sehingga<')
    expect(bm).toContain('<th>Item</th><th>Butiran</th><th class="r">Harga</th>')
    expect(bm).toContain('--pg-page:"Halaman";--pg-of:"daripada";')
    // Founder content is identical in both renders.
    for (const html of [en, bm]) {
      expect(html).toContain('Sistem Bengkel — Pakej B (Invois Penuh + Website) · Ansuran 12 bulan')
      expect(html).toContain('Deposit RM 2,700 semasa penerimaan, kemudian RM 970 sebulan × 12.')
      expect(html).toContain('Sistem bengkel total')
      expect(html).toContain('Project total')
    }
  })

  it('defaults to en when the payload carries no locale (frozen invoices) and keeps pre-formatted dates', () => {
    const invoice = renderDocumentHTML({
      ...base, kind: 'invoice', issued: '22 June 2026', validUntil: '06 July 2026', locale: undefined,
      panels: [{ label: 'Amount due', value: 1300, accent: true, note: 'Payable by bank transfer.' }], paymentPlan: undefined,
    })
    expect(invoice).toContain('>Invoice<')
    expect(invoice).toContain('22 June 2026')
    expect(invoice).toContain('06 July 2026')
    expect(invoice).toContain('Amount due')
    expect(invoice).toContain('>How to pay<')
    // A frozen payload's old credit string is ignored — the renderer owns the signature.
    const frozen = renderDocumentHTML({ ...base, kind: 'invoice', locale: undefined, paymentPlan: undefined,
      studio: { ...base.studio, designedBy: 'Designed by Qie / Axel Nova Ventures' } })
    expect(frozen).toContain('Designed by Qie,<br>Axel Nova Ventures')
    expect(frozen).not.toContain('Designed by Qie / Axel Nova Ventures')
    expect(invoice).not.toContain('class="payment-plan')
  })
})
