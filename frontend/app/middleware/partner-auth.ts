export default defineNuxtRouteMiddleware((to) => {
  // Client-only — the token lives in localStorage.
  if (import.meta.server) return

  const token = localStorage.getItem('axn_partner_token')
  if (!token) {
    // Remember where the partner was headed so login can send them back.
    const query = to.fullPath !== '/partners/portal' ? { redirect: to.fullPath } : undefined
    return navigateTo({ path: '/partners/login', query })
  }
})
