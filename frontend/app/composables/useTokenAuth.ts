// Shared bearer-token auth engine for the three isolated surfaces
// (admin / team / partner). Each surface keeps its OWN localStorage key and login
// route so their sessions can't be replayed against one another — the only thing
// they share, and the reason this factory exists, is the response interceptor that
// bounces the user to login the moment the API rejects a stale/expired/revoked
// token with 401. Without it, a dead token sails past the presence-only route
// guards (middleware only checks the key exists) and the page just sits there
// broken. useAdminAuth / useTeamAuth / usePartnerAuth are thin wrappers over this.

// Module-scoped so that a page firing several requests in parallel that ALL 401 at
// once triggers exactly one redirect, not a stampede. A user is only ever signed
// in to one surface at a time, so a single shared flag is safe across surfaces.
let redirecting = false

export interface TokenAuth {
  getToken: () => string | null
  setToken: (token: string) => void
  clearToken: () => void
  authHeaders: () => Record<string, string>
  apiFetch: <T>(path: string, opts?: Parameters<typeof $fetch>[1]) => Promise<T>
}

export function createTokenAuth(opts: { tokenKey: string, loginPath: string }): TokenAuth {
  const { tokenKey, loginPath } = opts
  const runtimeConfig = useRuntimeConfig()
  // Captured at setup so the interceptor can read the current path / navigate even
  // from inside an async fetch callback (where the Nuxt instance may be unavailable).
  const route = useRoute()
  const router = useRouter()

  function getToken(): string | null {
    if (import.meta.server) return null
    return localStorage.getItem(tokenKey)
  }

  function setToken(token: string) {
    localStorage.setItem(tokenKey, token)
  }

  function clearToken() {
    localStorage.removeItem(tokenKey)
  }

  function authHeaders(): Record<string, string> {
    const token = getToken()
    return token ? { Authorization: `Bearer ${token}` } : {}
  }

  // Involuntary logout: the API rejected our token (401 Unauthenticated, or 419 on
  // an expired stateful session). Clear it and bounce to this surface's login,
  // remembering where we were (?redirect=) so re-login returns there, and flagging
  // ?expired=1 so the login page can explain why the bounce happened.
  function handleAuthExpiry(status: number | undefined, request: unknown) {
    if (!import.meta.client) return
    if (status !== 401 && status !== 419) return
    // A 401 on a logout call just means the token was already dead — the caller
    // (voluntary logout) is navigating to login itself, so don't double-bounce with
    // a spurious "session expired" notice.
    if (typeof request === 'string' && request.endsWith('/logout')) return
    // No stored token means this was NOT an authenticated request — e.g. the login
    // POST itself on bad credentials. Leave those alone so they show their own error
    // instead of being mistaken for an expired session.
    if (!getToken()) return
    // Already on the login screen (a rejected login attempt) — don't self-redirect.
    if (route.path === loginPath) return
    if (redirecting) return
    redirecting = true
    clearToken()
    router
      .push({ path: loginPath, query: { redirect: route.fullPath, expired: '1' } })
      .finally(() => { redirecting = false })
  }

  async function apiFetch<T>(path: string, callOpts: Parameters<typeof $fetch>[1] = {}): Promise<T> {
    // Cast around nitro's typed-route inference: a template-literal URL sends
    // vue-tsc into "excessive stack depth" comparing route unions.
    const fetcher = $fetch as (url: string, o?: Parameters<typeof $fetch>[1]) => Promise<unknown>
    return await fetcher(`${runtimeConfig.public.apiBase}${path}`, {
      ...callOpts,
      headers: { ...authHeaders(), ...(callOpts.headers as Record<string, string> ?? {}) },
      onResponseError({ request, response }) {
        handleAuthExpiry(response?.status, request)
      },
    }) as T
  }

  return { getToken, setToken, clearToken, authHeaders, apiFetch }
}
