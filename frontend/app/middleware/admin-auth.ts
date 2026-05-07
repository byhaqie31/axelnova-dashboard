export default defineNuxtRouteMiddleware(() => {
  // Only runs client-side
  if (import.meta.server) return

  const token = localStorage.getItem('axn_admin_token')
  if (!token) {
    return navigateTo('/admin/login')
  }
})
