import { createTokenAuth } from '~/composables/useTokenAuth'

// Partner-portal auth (token key `axn_partner_token`, login `/partners/login`). A thin
// wrapper over the shared createTokenAuth engine, on its own token key so the three
// surfaces stay isolated: a partner token can't be replayed against /admin or /team,
// and the backend `referral` guard rejects it anywhere but /v1/partner/*. The
// 401 → login interceptor lives in the engine.
export function usePartnerAuth() {
  const auth = createTokenAuth({ tokenKey: 'axn_partner_token', loginPath: '/partners/login' })

  async function logout() {
    try {
      await auth.apiFetch('/api/v1/partner/logout', { method: 'POST' })
    }
    catch {
      // Token already gone / network down — clearing locally is enough.
    }
    auth.clearToken()
    await navigateTo('/partners/login')
  }

  return { ...auth, logout }
}
