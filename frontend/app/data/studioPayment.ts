// The studio's payment account, shown on the public /support page.
//
// KEEP IN SYNC with `STUDIO_PAY` (frontend/server/utils/pdf/template.ts — the
// invoice "How to pay" block) and `DocumentMapper::BANK` (backend — the
// quotation / invoice / receipt notes). Three copies because the Nitro PDF
// renderer, the Laravel mapper and the app bundle can't share a module.
export const STUDIO_BANK = {
  bank: 'OCBC Bank',
  holder: 'Axel Nova Ventures',
  acct: '7051415701',
} as const

/** The static DuitNow merchant QR (no amount — the payer keys it in). */
export const DUITNOW_QR_SRC = '/axn-duitnow-qr.png'
