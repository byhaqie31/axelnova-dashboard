// Workspace (/team) auth. A sibling of useAdminAuth, deliberately kept on its own
// token key so the two surfaces stay isolated: a workspace session can't be
// replayed against /admin and vice-versa (a marketer only ever holds a team token).
export function useTeamAuth() {
  const runtimeConfig = useRuntimeConfig()

  function getToken(): string | null {
    if (import.meta.server) return null
    return localStorage.getItem('axn_team_token')
  }

  function setToken(token: string) {
    localStorage.setItem('axn_team_token', token)
  }

  function clearToken() {
    localStorage.removeItem('axn_team_token')
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
    // Best-effort server revoke, then always clear locally and bounce to login.
    try {
      await apiFetch('/api/v1/team/logout', { method: 'POST' })
    }
    catch {
      // Token already gone / network down — clearing locally is enough.
    }
    clearToken()
    await navigateTo('/team/login')
  }

  return { getToken, setToken, clearToken, authHeaders, apiFetch, logout }
}
