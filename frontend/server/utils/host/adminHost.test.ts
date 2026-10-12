// admin.axelnovaventures.com is internal: public pages 301 to the public site,
// admin / API / portal routes stay, and every admin-host response is noindex.
import { describe, expect, it } from 'vitest'
import { adminHostDecision, isPublicPath, normalizeHost } from './adminHost'

const ADMIN = 'admin.axelnovaventures.com'
const SITE = 'https://axelnovaventures.com'

const on = (host: string, url: string, method = 'GET') =>
  adminHostDecision({ host, method, url, adminHost: ADMIN, siteUrl: SITE })

describe('admin host', () => {
  it('301s a public page to the same path on the public site, query kept', () => {
    expect(on(ADMIN, '/services')).toEqual({ noindex: true, redirectTo: `${SITE}/services` })
    expect(on(ADMIN, '/services/digital-marketing?ref=nav')).toEqual({
      noindex: true,
      redirectTo: `${SITE}/services/digital-marketing?ref=nav`,
    })
    expect(on(ADMIN, '/').redirectTo).toBe(`${SITE}/`)
    expect(on(ADMIN, '/quote').redirectTo).toBe(`${SITE}/quote`)
    expect(on(ADMIN, '/partners/refer').redirectTo).toBe(`${SITE}/partners/refer`)
    expect(on(ADMIN, '/sitemap.xml').redirectTo).toBe(`${SITE}/sitemap.xml`)
    expect(on(ADMIN, '/blog/why-ux-audits-matter', 'HEAD').redirectTo).toBe(`${SITE}/blog/why-ux-audits-matter`)
  })

  it('keeps /admin and /api on the admin host, noindexed', () => {
    expect(on(ADMIN, '/admin')).toEqual({ noindex: true, redirectTo: null })
    expect(on(ADMIN, '/admin/login')).toEqual({ noindex: true, redirectTo: null })
    expect(on(ADMIN, '/api/v1/quote-builder/config')).toEqual({ noindex: true, redirectTo: null })
  })

  it('keeps the documents, cache purge and signed-in workspaces on the admin host', () => {
    for (const url of ['/documents/invoice/12', '/_cache/purge', '/team/login', '/partners/home', '/partners/login', '/portal/abc123']) {
      expect(on(ADMIN, url).redirectTo, url).toBeNull()
    }
  })

  it('never redirects a form post', () => {
    expect(on(ADMIN, '/quote', 'POST').redirectTo).toBeNull()
  })

  it('leaves the public site alone', () => {
    expect(on('axelnovaventures.com', '/services')).toEqual({ noindex: false, redirectTo: null })
    expect(on('localhost:3003', '/services')).toEqual({ noindex: false, redirectTo: null })
  })

  it('matches the admin host case-insensitively, with a port or a forwarded list', () => {
    expect(on('Admin.AxelNovaVentures.com:443', '/about').redirectTo).toBe(`${SITE}/about`)
    expect(normalizeHost('admin.axelnovaventures.com, 10.0.0.1')).toBe(ADMIN)
  })
})

describe('isPublicPath', () => {
  it('matches page trees on segment boundaries only', () => {
    expect(isPublicPath('/services/')).toBe(true)
    expect(isPublicPath('/legal/privacy-policy')).toBe(true)
    expect(isPublicPath('/servicesx')).toBe(false)
    expect(isPublicPath('/partners/earnings')).toBe(false)
  })
})
