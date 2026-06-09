export default defineNuxtRouteMiddleware((to) => {
  // Only runs client-side
  if (import.meta.server) return

  const token = localStorage.getItem('axn_admin_token')
  if (!token) {
    // Remember where the user was headed so login can send them back.
    const query = to.fullPath !== '/admin' ? { redirect: to.fullPath } : undefined
    return navigateTo({ path: '/admin/login', query })
  }
})
