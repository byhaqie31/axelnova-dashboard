// POST /documents/render-pdf
//
// Renders a DocumentData object to the real PDF (headless Chromium) for an
// accurate, true-A4 preview while editing a quotation / invoice / receipt.
// Same renderer as the issued document, so the preview IS the output. Pure
// transform — no DB access, no token. Button-triggered (not per-keystroke) so
// Chromium isn't launched on every edit.

import { renderDocumentPDF } from '../../utils/pdf/pdf'
import type { DocumentData } from '../../utils/pdf/types'

export default defineEventHandler(async (event) => {
  const data = await readBody<DocumentData>(event)

  if (!data || typeof data !== 'object' || Array.isArray(data)) {
    throw createError({ statusCode: 400, statusMessage: 'Missing document data' })
  }

  const pdf = await renderDocumentPDF(data)

  setHeader(event, 'Content-Type', 'application/pdf')
  setHeader(event, 'Content-Disposition', `inline; filename="${(data.number || 'preview')}.pdf"`)
  setHeader(event, 'Cache-Control', 'no-store')
  return pdf
})
