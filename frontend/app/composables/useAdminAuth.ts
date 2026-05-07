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
    clearToken()
    await navigateTo('/admin/login')
  }

  return { getToken, setToken, clearToken, authHeaders, apiFetch, logout }
}
