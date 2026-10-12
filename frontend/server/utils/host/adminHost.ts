// admin.axelnovaventures.com is internal only — admin panel, API, MCP. The same
// Nuxt app answers on it (one nginx vhost), so its public pages would otherwise
// compete with axelnovaventures.com in search. Pure decision logic for
// server/middleware/admin-host.ts (plain TS, unit-tested without Nitro).
//
// Only the public pages (pages/public/**) are sent away. Everything else stays
// on the admin host: /admin, /api (never reaches Nuxt — nginx routes it to
// Laravel), /documents (the invoice-PDF fetch), /_cache/purge, OG images, and
// the signed-in team / partner / client workspaces.

/** Public pages that are a single path. /partners/* (besides refer) is the partner portal. */
const PUBLIC_EXACT = new Set(['/', '/partners', '/partners/refer', '/sitemap.xml'])

/** Public page trees: the path itself and everything under it. */
const PUBLIC_PREFIXES = [
  '/about',
  '/blog',
  '/collaborations',
  '/company',
  '/contact',
  '/feedback',
  '/investor',
  '/legal',
  '/projects',
  '/proposals',
  '/quote',
  '/services',
  '/support',
]

/** True when `path` (no query string) is one of the public pages. */
export function isPublicPath(path: string): boolean {
  const p = path === '' ? '/' : path.length > 1 ? path.replace(/\/+$/, '') : path
  if (PUBLIC_EXACT.has(p)) return true
  return PUBLIC_PREFIXES.some(prefix => p === prefix || p.startsWith(`${prefix}/`))
}

/** "Admin.Example.com:443, proxy" → "admin.example.com" (first forwarded value, no port). */
export function normalizeHost(host: string | null | undefined): string {
  return (host ?? '').split(',')[0]!.trim().toLowerCase().replace(/:\d+$/, '')
}

export interface AdminHostDecision {
  /** Send `X-Robots-Tag: noindex, nofollow`. */
  noindex: boolean
  /** 301 target on the public site, or null to serve the request here. */
  redirectTo: string | null
}

export function adminHostDecision(req: {
  host: string | null | undefined
  method: string
  /** Path plus query string, as h3's event.path. */
  url: string
  adminHost: string
  siteUrl: string
}): AdminHostDecision {
  if (!req.adminHost || normalizeHost(req.host) !== normalizeHost(req.adminHost)) {
    return { noindex: false, redirectTo: null }
  }

  const path = req.url.split('?')[0] ?? '/'
  const navigable = req.method === 'GET' || req.method === 'HEAD'

  return {
    noindex: true,
    redirectTo: navigable && isPublicPath(path) ? `${req.siteUrl.replace(/\/+$/, '')}${req.url || '/'}` : null,
  }
}
