// GET /api/documents/:token/pdf
//
// Public, token-gated quotation PDF. Fetches the document data from the Laravel
// backend (the token is the only credential — unguessable, shared via the
// quotation email), then renders it through headless Chromium and streams the PDF.

import { renderDocumentPDF } from '../../../utils/pdf/pdf'
import type { DocumentData } from '../../../utils/pdf/types'

export default defineEventHandler(async (event) => {
  const token = getRouterParam(event, 'token')
  if (!token) {
    throw createError({ statusCode: 400, statusMessage: 'Missing token' })
  }

  const config = useRuntimeConfig()

  let doc: DocumentData
  try {
    // Private (server-side) API base — the docker-network backend hostname.
    doc = await $fetch<DocumentData>(`${config.apiBase}/api/v1/documents/${token}`)
  }
  catch {
    throw createError({ statusCode: 404, statusMessage: 'Document not found' })
  }

  const pdf = await renderDocumentPDF(doc)

  setHeader(event, 'Content-Type', 'application/pdf')
  setHeader(event, 'Content-Disposition', `inline; filename="${doc.number}.pdf"`)
  setHeader(event, 'Cache-Control', 'private, no-store')
  return pdf
})
