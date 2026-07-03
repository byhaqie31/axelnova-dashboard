export function useAdminAuth() {
  const runtimeConfig = useRuntimeConfig()

  function getToken(): string | null {
    if (import.meta.server) return null
    return localStorage.getItem('axn_admin_token')
  }

  function setToken(token: string) {
    localStorage.setItem('axn_admin_token', token)
  }

  function clearToken() {
    localStorage.removeItem('axn_admin_token')
  }

  function authHeaders(): Record<string, string> {
    const token = getToken()
    return token ? { Authorization: `Bearer ${token}` } : {}
  }

  async function apiFetch<T>(path: string, opts: Parameters<typeof $fetch>[1] = {}): Promise<T> {
    // Cast around nitro's typed-route inference: a template-literal URL sends
    // vue-tsc into "excessive stack depth" comparing route unions.
    const fetcher = $fetch as (url: string, o?: Parameters<typeof $fetch>[1]) => Promise<unknown>
    return await fetcher(`${runtimeConfig.public.apiBase}${path}`, {
      ...opts,
      headers: { ...authHeaders(), ...(opts.headers as Record<string, string> ?? {}) },
    }) as T
  }

  async function logout() {
    clearToken()
    await navigateTo('/admin/login')
  }

  /**
   * Admin → Team direct sign-in. Exchanges the cockpit session for a fresh
   * workspace token (POST /v1/admin/team-session — the only sanctioned bridge
   * between the two surfaces), stores it under the team key, and opens /team.
   * The tab opens synchronously so popup blockers allow it; if the exchange
   * fails (expired admin session, network) it lands on /team/login instead.
   */
  const { setToken: setTeamToken } = useTeamAuth()
  async function jumpToTeam() {
    const tab = window.open('about:blank', '_blank')
    let target = '/team/login'
    try {
      const { token } = await apiFetch<{ token: string }>('/api/v1/admin/team-session', { method: 'POST' })
      setTeamToken(token)
      target = '/team'
    }
    catch {
      // Fall through to the login page.
    }
    if (tab) tab.location.href = target
    else await navigateTo(target)
  }

  return { getToken, setToken, clearToken, authHeaders, apiFetch, logout, jumpToTeam }
}
