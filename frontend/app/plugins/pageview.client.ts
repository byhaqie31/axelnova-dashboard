/**
 * Public page-view beacon. Fires a fire-and-forget POST on every public route
 * change (initial load + SPA navigations). Admin and portal routes are private
 * and never tracked. The server hashes the IP and drops bots — see
 * backend TrackingController. Errors are swallowed; tracking must never affect UX.
 */
export default defineNuxtPlugin(() => {
  const router = useRouter()
  const config = useRuntimeConfig()
  const base = config.public.apiBase

  let lastPath = ''

  function track(path: string) {
    if (!path || path === lastPath) return
    if (path.startsWith('/admin') || path.startsWith('/portal')) return
    lastPath = path

    try {
      $fetch(`${base}/api/v1/track/page-view`, {
        method: 'POST',
        keepalive: true,
        body: { path, referrer: document.referrer || null },
      }).catch(() => {})
    }
    catch { /* never let tracking throw */ }
  }

  // Initial load (router is already resolved at plugin run on the client).
  track(router.currentRoute.value.path)
  // Subsequent SPA navigations.
  router.afterEach(to => track(to.path))
})
