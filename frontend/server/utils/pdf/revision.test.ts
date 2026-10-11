// Quotation revisions on the PDF (docs/global/QUOTATION-REVISIONS.md §5,
// acceptance test 5): from R2 on the reference prints as "AXNQ-… · R2" in the
// header, page-foot and title, a reason line sits under the title in the
// document locale, the download is named "AXNQ-…-R2.pdf", and the running
// "Page x of y" footer is untouched. R1 prints exactly as before. Also the
// phased lump-sum Payment plan section (40/40/20 …) per locale.
import { describe, expect, it } from 'vitest'
import { documentFileName, docReference, paymentPlanHTML, renderDocumentHTML } from './template'
import type { DocumentData, DocumentLocale, PaymentPlanBlock } from './types'

const LOCALES: DocumentLocale[] = ['en', 'bm']

function quotation(locale: DocumentLocale, revision: DocumentData['revision'] = null, layout: DocumentData['layout'] = 'detailed'): DocumentData {
  return {
    layout,
    kind: 'quotation',
    locale,
    number: 'AXNQ-2026-0017',
    revision,
    issued: '2026-10-11',
    validUntil: '2026-11-10',
    currency: 'RM',
    studio: { name: 'Axel Nova Ventures', tagline: '', email: 'hello@axelnova.tech', site: 'axelnovaventures.com', reg: '', designedBy: 'Qie' },
    client: { name: 'M Automobile Service', email: 'm-auto@example.com' },
    project: 'Sistem Bengkel — Pakej B',
    items: [{ title: 'Sistem', qty: 1, rate: 10800 }],
    sections: [{ title: 'Skop', rows: [{ title: 'Semua', price: 10800 }], total: 10800 }],
  }
}

const R2: DocumentData['revision'] = {
  number: 2,
  replaces: 1,
  replacedEvent: 'accepted',
  replacedDate: '2026-10-11',
  reason: 'Tukar ke pelan 40/40/20',
}

describe('revision label + reason (both locales, both layouts)', () => {
  for (const layout of ['detailed', 'standard'] as const) {
    it(`en · ${layout}`, () => {
      const html = renderDocumentHTML(quotation('en', R2, layout))
      expect(html).toContain('<span class="val">AXNQ-2026-0017 · R2</span>')
      expect(html).toContain(
        '<div class="rev-note">Revision 2 — replaces Revision 1 (accepted 11 October 2026). Reason: Tukar ke pelan 40/40/20</div>',
      )
      expect(html).toContain('<title>AXNQ-2026-0017 · R2 · Sistem Bengkel — Pakej B</title>')
    })

    it(`bm · ${layout}`, () => {
      const html = renderDocumentHTML(quotation('bm', R2, layout))
      expect(html).toContain('<span class="val">AXNQ-2026-0017 · R2</span>')
      expect(html).toContain(
        '<div class="rev-note">Semakan 2 — menggantikan Semakan 1 (diterima 11 Oktober 2026). Sebab: Tukar ke pelan 40/40/20</div>',
      )
      expect(html).not.toContain('Revision 2')
    })
  }

  it('keeps the running "Page x of y" footer intact, with the revised reference on the left', () => {
    for (const locale of LOCALES) {
      const html = renderDocumentHTML(quotation(locale, R2))
      const words = locale === 'bm' ? ['Halaman', 'daripada'] : ['Page', 'of']
      expect(html).toContain(`--pg-page:"${words[0]}";--pg-of:"${words[1]}";`)
      expect(html).toMatch(/--pgfoot-l:"Axel Nova Ventures {2}· {2}SSM No : [^"]+ {2}· {2}AXNQ-2026-0017 · R2";/)
      // The counters still come from @page — the footer CSS is unchanged.
      expect(html).toContain('counter(page)')
      expect(html).toContain('counter(pages)')
    }
  })

  it('words a sent (not accepted) predecessor, and a reason-less revision', () => {
    const sent = renderDocumentHTML(quotation('en', { number: 3, replaces: 2, replacedEvent: 'sent', replacedDate: '2026-10-20', reason: null }))
    expect(sent).toContain('<div class="rev-note">Revision 3 — replaces Revision 2 (sent 20 October 2026).</div>')
    expect(renderDocumentHTML(quotation('bm', { number: 3, replaces: 2, replacedEvent: 'sent', replacedDate: '2026-10-20' })))
      .toContain('Semakan 3 — menggantikan Semakan 2 (dihantar 20 Oktober 2026).')
  })

  it('prints R1 (and anything without a revision) exactly as before', () => {
    for (const revision of [null, { number: 1 }]) {
      const html = renderDocumentHTML(quotation('en', revision))
      expect(html).toContain('<span class="val">AXNQ-2026-0017</span>')
      expect(html).not.toContain('class="rev-note"')
      expect(html).not.toContain('· R1')
    }
  })

  it('names the download per revision', () => {
    expect(documentFileName(quotation('en', R2))).toBe('AXNQ-2026-0017-R2.pdf')
    expect(documentFileName(quotation('en'))).toBe('AXNQ-2026-0017.pdf')
    expect(docReference(quotation('en', R2))).toBe('AXNQ-2026-0017 · R2')
    // Invoices never carry a revision suffix.
    expect(documentFileName({ ...quotation('en', R2), kind: 'invoice', number: 'AXNI-2026-0009' })).toBe('AXNI-2026-0009.pdf')
  })
})

/** R2 of AXNQ-2026-0017: 40/40/20 on RM 10,800, Care Plan RM 220 / month billed separately. */
const phased: PaymentPlanBlock = {
  plan: 'lump_sum',
  deposit: 4320,
  depositPctLabel: '40%',
  balance: 6480,
  monthly: 0,
  months: 0,
  billingDay: 20,
  firstDate: null,
  lastDate: null,
  includesCarePlan: false,
  total: 10800,
  schedule: [],
  phases: [
    { n: 1, label: 'Semasa penerimaan', pct: 40, amount: 4320 },
    { n: 2, label: 'Semasa demo', pct: 40, amount: 4320 },
    { n: 3, label: 'Go-live', pct: 20, amount: 2160 },
  ],
  carePlanMonthly: 220,
}

describe('phased lump sum (Payment by Phase)', () => {
  for (const locale of LOCALES) {
    it(`snapshot · ${locale}`, () => {
      expect(paymentPlanHTML(phased, 'RM', locale)).toMatchSnapshot()
    })
  }

  it('lists every phase with its share and amount, inline, with the separate Care Plan line', () => {
    const en = paymentPlanHTML(phased, 'RM', 'en')
    expect(en).toMatch(/<div class="payment-plan inline">/)
    expect(en).toContain('<h2 class="pp-title">Payment by Phase</h2>')
    expect(en).toContain('3 phases · total RM 10,800.00')
    expect(en).toContain('>Payment phases<')
    expect(en).toContain('<th>Phase</th><th>Share</th><th class="r">Amount</th>')
    expect(en).toContain('Phase 1')
    expect(en).toContain('Semasa penerimaan · 40%')
    expect(en).toContain('RM 4,320.00')
    expect(en).toContain('RM 2,160.00')
    expect(en).toContain('Care Plan: RM 220.00 per month, billed separately')

    const bm = paymentPlanHTML(phased, 'RM', 'bm')
    expect(bm).toContain('<h2 class="pp-title">Bayaran Berperingkat</h2>')
    expect(bm).toContain('3 peringkat · jumlah RM 10,800.00')
    expect(bm).toContain('Peringkat 3')
    expect(bm).toContain('Care Plan: RM 220.00 sebulan, dibil berasingan')
    expect(bm).not.toContain('Phase 1')
  })

  it('handles any split — 50/50 and 20×5', () => {
    const five = paymentPlanHTML({ ...phased, carePlanMonthly: null, phases: [1, 2, 3, 4, 5].map((n) => ({ n, label: '', pct: 20, amount: 2160 })) }, 'RM', 'en')
    expect(five).toContain('5 phases')
    expect(five).toContain('Phase 5')
    expect(five).not.toContain('Care Plan')
    expect(paymentPlanHTML({ ...phased, phases: [{ n: 1, label: 'Start', pct: 50, amount: 5400 }, { n: 2, label: 'End', pct: 50, amount: 5400 }] }, 'RM', 'en'))
      .toContain('2 phases')
  })
})
