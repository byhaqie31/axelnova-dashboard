import { createTokenAuth } from '~/composables/useTokenAuth'

// Admin-cockpit auth (token key `axn_admin_token`, login `/admin/login`). A thin
// wrapper over the shared createTokenAuth engine — which owns token storage, the
// Bearer header, and the 401 → login interceptor — plus the admin-only extras below.
export function useAdminAuth() {
  const auth = createTokenAuth({ tokenKey: 'axn_admin_token', loginPath: '/admin/login' })

  async function logout() {
    auth.clearToken()
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
      const { token } = await auth.apiFetch<{ token: string }>('/api/v1/admin/team-session', { method: 'POST' })
      setTeamToken(token)
      target = '/team'
    }
    catch {
      // Fall through to the login page.
    }
    if (tab) tab.location.href = target
    else await navigateTo(target)
  }

  return { ...auth, logout, jumpToTeam }
}
