// POST /documents/render
//
// Turns a DocumentData object into the exact standalone HTML the PDF is built
// from (renderDocumentHTML), for live previews in the admin while editing a
// quotation / invoice / receipt. Pure transform — no DB access, no token — so it
// stays in sync with the PDF by construction (same renderer).
//
// Lives in server/routes (not /api) for the same reason as the PDF route: the
// VPS proxy sends /api/* to Laravel.

import { renderDocumentHTML } from '../../utils/pdf/template'
import type { DocumentData } from '../../utils/pdf/types'

export default defineEventHandler(async (event) => {
  const data = await readBody<DocumentData>(event)

  if (!data || typeof data !== 'object' || Array.isArray(data)) {
    throw createError({ statusCode: 400, statusMessage: 'Missing document data' })
  }

  const html = renderDocumentHTML(data)

  setHeader(event, 'Content-Type', 'text/html; charset=utf-8')
  setHeader(event, 'Cache-Control', 'no-store')
  return html
})
