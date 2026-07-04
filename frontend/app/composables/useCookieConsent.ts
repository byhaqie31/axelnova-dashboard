// Cookie-consent state. The choice itself is stored in `axn_consent` — a
// strictly-necessary record of the visitor's decision, so it may be set before
// consent. Non-essential cookies (e.g. the functional `axn_ref` attribution
// cookie) are only written once `granted` is true.
export type ConsentChoice = 'granted' | 'declined'

export function useCookieConsent() {
  const consent = useCookie<ConsentChoice | null>('axn_consent', {
    default: () => null,
    maxAge: 60 * 60 * 24 * 365, // 1 year
    sameSite: 'lax',
    path: '/',
  })

  const decided = computed(() => consent.value === 'granted' || consent.value === 'declined')
  const granted = computed(() => consent.value === 'granted')

  function accept() {
    consent.value = 'granted'
  }

  function decline() {
    consent.value = 'declined'
  }

  return { consent, decided, granted, accept, decline }
}
