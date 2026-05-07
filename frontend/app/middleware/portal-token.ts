export default defineNuxtRouteMiddleware(() => {
  if (import.meta.client) {
    console.warn('Portal token middleware is a no-op until Phase 4')
  }
})
