// Partner-portal auth. A sibling of useTeamAuth / useAdminAuth on its own token key
// (`axn_partner_token`) so the three surfaces stay isolated: a partner token can't
// be replayed against /admin or /team, and the backend `referral` guard rejects it
// anywhere but /v1/partner/*.
export function usePartnerAuth() {
  const runtimeConfig = useRuntimeConfig()

  function getToken(): string | null {
    if (import.meta.server) return null
    return localStorage.getItem('axn_partner_token')
  }

  function setToken(token: string) {
    localStorage.setItem('axn_partner_token', token)
  }

  function clearToken() {
    localStorage.removeItem('axn_partner_token')
  }

  function authHeaders() {
    const token = getToken()
    return token ? { Authorization: `Bearer ${token}` } : {}
  }

  async function apiFetch<T>(path: string, opts: Parameters<typeof $fetch>[1] = {}): Promise<T> {
    return $fetch<T>(`${runtimeConfig.public.apiBase}${path}`, {
      ...opts,
      headers: { ...authHeaders(), ...(opts.headers as Record<string, string> ?? {}) },
    })
  }

  async function logout() {
    try {
      await apiFetch('/api/v1/partner/logout', { method: 'POST' })
    }
    catch {
      // Token already gone / network down — clearing locally is enough.
    }
    clearToken()
    await navigateTo('/partners/login')
  }

  return { getToken, setToken, clearToken, authHeaders, apiFetch, logout }
}
