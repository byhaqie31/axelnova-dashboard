import { renderDocumentHTML } from './template'
import type { DocumentData } from './types'

/**
 * Render document HTML to a PDF buffer.
 *
 * The frontend image is Alpine, so we use `playwright-core` against the system
 * Chromium installed via `apk add chromium` (Playwright's bundled Chromium is
 * built for glibc and won't run on musl). Path + flags are overridable via env.
 */
export async function renderDocumentPDF(data: DocumentData): Promise<Buffer> {
  const { chromium } = await import('playwright-core')
  const html = renderDocumentHTML(data)

  const browser = await chromium.launch({
    executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH || '/usr/bin/chromium-browser',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
    headless: true,
  })

  try {
    const page = await browser.newPage()
    await page.setContent(html, { waitUntil: 'networkidle' })
    // Honour the template's CSS `@page` rule (A4 size, margins, and the
    // running page-foot in @bottom-left/@bottom-right). Don't pass width/
    // height/margin here — those would override the CSS and drop the
    // page-number footer on multi-page documents.
    const pdf = await page.pdf({
      printBackground: true,
      preferCSSPageSize: true,
    })
    return Buffer.from(pdf)
  }
  finally {
    await browser.close()
  }
}
