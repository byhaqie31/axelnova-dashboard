// Keep admin.axelnovaventures.com internal: every response on it is noindex,
// and a public page requested there 301s to the same path on the public site.
// Runs before the swr page cache, so a cached public page is never served on
// the admin host. Static files (public/, /_nuxt) are served ahead of server
// middleware and are not covered. Decision logic: server/utils/host/adminHost.ts.
import { adminHostDecision } from '../utils/host/adminHost'

export default defineEventHandler((event) => {
  const config = useRuntimeConfig(event)
  const decision = adminHostDecision({
    host: getRequestHost(event, { xForwardedHost: true }),
    method: event.method,
    url: event.path,
    adminHost: config.adminHost,
    siteUrl: config.public.siteUrl,
  })

  if (decision.noindex) setResponseHeader(event, 'X-Robots-Tag', 'noindex, nofollow')
  if (decision.redirectTo) return sendRedirect(event, decision.redirectTo, 301)
})
