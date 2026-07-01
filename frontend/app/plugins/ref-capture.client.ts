// Referral attribution capture (client-only). On landing with `?ref=CODE`, hold the
// code for the session; and once consent is granted, persist it to the functional
// `axn_ref` cookie (90-day window ≈ the commission window). First-touch wins — an
// existing code is never overwritten by a later link.
export default defineNuxtPlugin(() => {
  const route = useRoute()
  const { granted } = useCookieConsent()
  const pending = useState<string | null>('axn_pending_ref', () => null)

  const refCookie = useCookie<string | null>('axn_ref', {
    default: () => null,
    maxAge: 60 * 60 * 24 * 90, // ~commission window
    sameSite: 'lax',
    path: '/',
  })

  const incoming = typeof route.query.ref === 'string' ? route.query.ref.trim() : ''
  if (incoming) {
    pending.value = incoming
  }

  function persistIfAllowed() {
    // First-touch: only write when consented AND no code is already stored.
    if (granted.value && pending.value && !refCookie.value) {
      refCookie.value = pending.value
    }
  }

  persistIfAllowed()
  // If consent is granted later in the session, persist the pending code then.
  watch(granted, persistIfAllowed)
})
