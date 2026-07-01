// Reads the current referral code for attribution. The persisted `axn_ref` cookie
// (functional, set only after consent) is primary; the in-session `pending` code
// (captured from ?ref this visit) is the best-effort fallback when consent was
// declined, so a referrer still gets credit without a stored cookie.
//
// `withRef()` appends ?ref to a backend conversion URL. We pass the code explicitly
// rather than rely on the browser sending the cookie, because the API is a separate
// origin — the backend resolves either the axn_ref cookie OR the ?ref param.
export function useReferralAttribution() {
  const refCookie = useCookie<string | null>('axn_ref', { default: () => null, path: '/' })
  const pending = useState<string | null>('axn_pending_ref', () => null)

  const refCode = computed(() => refCookie.value || pending.value || null)

  function withRef(url: string): string {
    const code = refCode.value
    if (!code) return url
    return url + (url.includes('?') ? '&' : '?') + 'ref=' + encodeURIComponent(code)
  }

  return { refCode, withRef }
}
