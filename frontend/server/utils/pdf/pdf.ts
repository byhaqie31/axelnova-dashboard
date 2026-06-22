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
    const pdf = await page.pdf({
      width: '210mm',
      height: '297mm',
      printBackground: true,
      margin: { top: '0', bottom: '0', left: '0', right: '0' },
    })
    return Buffer.from(pdf)
  }
  finally {
    await browser.close()
  }
}
