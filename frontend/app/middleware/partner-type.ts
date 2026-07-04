// Type gate for the type-aware partner portal (Task 9). Pages declare their
// account type via page meta — e.g.
//   definePageMeta({ middleware: ['partner-auth', 'partner-type'], partnerType: 'referrer' })
// — and a signed-in partner of the other type is bounced back to /partners.
// This is a UX convenience on top of the token-existence check (same pattern as
// team); the server's `partner.type:*` middleware is what actually 403s a
// wrong-type token on the API.
import type { PartnerType } from '~/data/partnersNav'

export default defineNuxtRouteMiddleware(async (to) => {
  // Client-only — the token lives in localStorage (partner-auth already ran).
  if (import.meta.server) return

  const required = to.meta.partnerType as PartnerType | undefined
  if (!required) return

  const { ensure } = usePartnerMe()
  const me = await ensure()

  // Unresolvable /me (network blip) → let the page's own API calls surface it.
  if (!me) return

  if (me.type !== required) {
    return navigateTo('/partners')
  }
})
