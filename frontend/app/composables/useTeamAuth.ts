import { createTokenAuth } from '~/composables/useTokenAuth'

// Workspace (/team) auth (token key `axn_team_token`, login `/team/login`). A thin
// wrapper over the shared createTokenAuth engine, deliberately on its own token key
// so a workspace session can't be replayed against /admin and vice-versa (a marketer
// only ever holds a team token). The 401 → login interceptor lives in the engine.
export function useTeamAuth() {
  const auth = createTokenAuth({ tokenKey: 'axn_team_token', loginPath: '/team/login' })

  async function logout() {
    // Best-effort server revoke, then always clear locally and bounce to login.
    try {
      await auth.apiFetch('/api/v1/team/logout', { method: 'POST' })
    }
    catch {
      // Token already gone / network down — clearing locally is enough.
    }
    auth.clearToken()
    await navigateTo('/team/login')
  }

  return { ...auth, logout }
}
