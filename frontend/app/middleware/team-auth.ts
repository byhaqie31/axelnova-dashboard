export default defineNuxtRouteMiddleware((to) => {
  // Client-only — the token lives in localStorage.
  if (import.meta.server) return

  const token = localStorage.getItem('axn_team_token')
  if (!token) {
    // Remember where the user was headed so login can send them back.
    const query = to.fullPath !== '/team' ? { redirect: to.fullPath } : undefined
    return navigateTo({ path: '/team/login', query })
  }
})
