// POST /_cache/purge
//
// Drops every cached public page AND its `_payload.json` (the `swr` route rules
// in nuxt.config.ts) so the next visit renders fresh. Called by the backend's
// SiteCache when a live blog post changes (BlogPostObserver) or the public
// catalogue does (service category / package that is or was Live, add-ons,
// scope fields, pricing — the catalogue observers). The pages stay fast between
// changes, yet a change shows on the very next visit instead of whenever swr
// gets round to it. Clears ALL cached pages, not just /blog or /services: the
// homepage lists the latest posts, every page's footer lists the services, and
// matching Nitro's hashed keys per path would be fragile. Anything else simply
// re-renders on its next visit.
//
// Guarded by a shared secret (NUXT_CACHE_PURGE_TOKEN ↔ backend
// SITE_CACHE_PURGE_TOKEN). Unset or wrong token → 404, so the route looks
// absent. Lives in server/routes (not /api) because the VPS proxy sends /api/*
// to Laravel; the backend calls it container-to-container anyway.

import { timingSafeEqual } from 'node:crypto'

// Nitro's cachedEventHandler stores route rules under base "/cache", group
// "nitro/routes" — normalised storage keys start with this prefix.
const ROUTE_CACHE_PREFIX = 'cache:nitro:routes'

function tokenMatches(sent: string, expected: string): boolean {
  const a = Buffer.from(sent)
  const b = Buffer.from(expected)
  return a.length === b.length && timingSafeEqual(a, b)
}

export default defineEventHandler(async (event) => {
  const expected = useRuntimeConfig(event).cachePurgeToken
  const sent = (getHeader(event, 'authorization') ?? '').replace(/^Bearer\s+/i, '')

  if (!expected || !tokenMatches(sent, expected)) {
    throw createError({ statusCode: 404, statusMessage: 'Not Found' })
  }

  const storage = useStorage()
  const keys = await storage.getKeys(ROUTE_CACHE_PREFIX)
  await Promise.all(keys.map(key => storage.removeItem(key)))

  setHeader(event, 'Cache-Control', 'no-store')
  return { purged: keys.length }
})
